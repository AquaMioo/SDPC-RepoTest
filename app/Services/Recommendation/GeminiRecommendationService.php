<?php

namespace App\Services\Recommendation;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\StudentEducation;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Matching\ScopeProfile;
use App\Services\Matching\SkillInference;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Matching read by a language model instead of by keyword overlap.
 *
 * The computed scorer works on words: it stems the brief, stems the skills,
 * and counts what lands in both. That is fast and explainable and it cannot
 * tell that "a system to track what we have in the stockroom" is the same
 * request as "inventory management" — the words never meet. Reading the two
 * descriptions and judging the fit is the thing a model is actually good at,
 * and it is the whole reason this driver exists.
 *
 * EVERY FAILURE FALLS BACK. No key, a timeout, a 429, a refusal, a reply that
 * is not the shape we asked for, a student the model forgot — each one ends at
 * the computed scorer rather than at an error page or an empty screen. Nobody
 * on this platform is ever blocked because Google is slow. Matching is on the
 * critical path of the entire product, which makes that non-negotiable, and it
 * is the same posture as SheerIdStudentVerifier and AnnounceMessage.
 *
 * @see .ai/rules/services-matching.md for the computed scorer this falls back to
 */
class GeminiRecommendationService implements RecommendationService, ScoresFreeText, ScoresProjectsForText
{
    /** Set while the model is known to be unwell; see isCoolingDown(). */
    protected const COOLDOWN_KEY = 'gemini.cooldown';

    public function __construct(
        protected ComputedRecommendationService $fallback,
        protected SkillInference $inference,
    ) {}

    /**
     * Score every student against a posting, keyed by student user id.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresFor(Project $project): Collection
    {
        $scope = ScopeProfile::fromProject($project, $this->inference);

        if (! $scope->isMeaningful()) {
            return collect();
        }

        $candidates = StudentProfile::query()
            ->with(['skills', 'course', 'educations.course', 'languages'])
            ->limit($this->maxCandidates())
            ->get();

        return $this->rank(
            brief: $this->briefFor($project),
            candidates: $candidates,
            cacheKey: 'gemini.project.'.$project->id.'.'.$project->updated_at?->timestamp,
            fallback: fn (): Collection => $this->fallback->scoresFor($project),
        );
    }

    /**
     * Score every open posting against a student, keyed by project id.
     *
     * The mirror of scoresFor(): one reader, many briefs. This is what puts a
     * real judgement behind the board's "Recommended" order and behind the
     * match panel at the top of it — both of which were already drawn, and
     * both of which were being fed by keyword overlap until now.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresForStudent(User $student): Collection
    {
        $profile = $student->studentProfile;

        if ($profile === null) {
            return collect();
        }

        $profile->loadMissing(['skills', 'course', 'educations.course', 'languages']);

        return $this->rankBriefs(
            reader: "STUDENT:\n".$this->encode($this->describe($profile)),
            briefs: $this->openBriefs(),
            /*
             * Keyed on the profile's own timestamp, so editing your skills
             * re-asks rather than serving an hour-old judgement of the person
             * you used to be.
             */
            cacheKey: 'gemini.student.'.$student->id.'.'.$profile->updated_at?->timestamp,
            fallback: fn (): Collection => $this->fallback->scoresForStudent($student),
        );
    }

    /**
     * Score open postings against the capstone a student describes.
     *
     * The advanced search: a student types what they are building this term
     * and gets the briefs that map onto it. There is no profile involved and
     * no skill list — only two sentences — which is precisely the case the
     * keyword scorer cannot answer.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function projectScoresForText(string $title, string $description, User $student): Collection
    {
        $capstone = trim($title."\n".$description);

        if ($capstone === '') {
            return collect();
        }

        return $this->rankBriefs(
            reader: "The student is building this capstone project this term:\n"
                .$this->encode(array_filter([
                    'capstone_title' => trim($title) ?: null,
                    'capstone_description' => trim($description) ?: null,
                ])),
            briefs: $this->openBriefs(),
            cacheKey: 'gemini.capstone.'.md5($capstone),
            /*
             * Without a model there is no sensible reading of two sentences,
             * so the board falls back to ranking against the saved profile —
             * which is what it did before this search existed.
             */
            fallback: fn (): Collection => $this->fallback->scoresForStudent($student),
        );
    }

    /**
     * Score students against free text, e.g. "POS to my website system".
     *
     * The case the model helps most with: there is no posting, no skill list
     * and no category — only a sentence a client typed.
     *
     * @param  Collection<int, StudentProfile>  $students
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresForSearch(string $query, Collection $students): Collection
    {
        if (trim($query) === '' || $students->isEmpty()) {
            return collect();
        }

        return $this->rank(
            brief: 'A client is looking for someone to build: '.$query,
            candidates: $students->take($this->maxCandidates()),
            cacheKey: 'gemini.search.'.md5($query.'|'.$students->pluck('user_id')->join(',')),
            fallback: fn (): Collection => $this->fallback->scoresForSearch($query, $students),
        );
    }

    /**
     * Scoring is always available, because the fallback always is.
     *
     * The recruit screen hides its match panel on false, and there is no state
     * of this driver in which the platform has nothing to show.
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Determine if the model itself is reachable, as opposed to the fallback.
     */
    public function isConfigured(): bool
    {
        return filled(config('gemini.api_key'));
    }

    /**
     * Whether a recent failure means we should not ask again yet.
     *
     * The fallback made every fault survivable, but not cheap: a failure was
     * forgotten the moment it was handled, so the next reader paid the whole
     * timeout to discover the same outage. With the API answering 503 that put
     * twenty seconds in front of the board on EVERY load, for every student,
     * for as long as the outage lasted — a page that is otherwise served in
     * about two hundred milliseconds.
     *
     * One reader now pays for the discovery and everybody else is served by
     * the computed scorer until the cooldown lapses.
     */
    protected function isCoolingDown(): bool
    {
        return Cache::has(self::COOLDOWN_KEY);
    }

    /**
     * Stop asking for a while. Any fault counts — a refusal is as good a
     * reason to leave it alone as a timeout is.
     */
    protected function beginCooldown(): void
    {
        $minutes = (int) config('gemini.cooldown_minutes');

        if ($minutes > 0) {
            Cache::put(self::COOLDOWN_KEY, true, now()->addMinutes($minutes));
        }
    }

    /**
     * Render a payload fragment as readable JSON for the prompt.
     *
     * Pretty-printed and with slashes left alone: the model reads this, and
     * escaped slashes in a URL or a course code are noise it has to undo.
     *
     * @param  array<mixed>  $value
     */
    protected function encode(array $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Temperature and the response schema, shared by both prompt shapes.
     *
     * responseSchema is what makes this usable at all. Without it the reply is
     * prose that happens to contain numbers, and parsing prose from a model is
     * how you get a feature that works until the day it does not.
     *
     * @return array<string, mixed>
     */
    protected function generationConfig(): array
    {
        return [
            /* A ranking should not wander between page loads. */
            'temperature' => 0.2,
            /*
             * Gemini 3 reasons by default and it costs seconds, not
             * milliseconds — enough to blow the request timeout on its own.
             * See config/gemini.php for the measurements.
             */
            'thinkingConfig' => ['thinkingLevel' => config('gemini.thinking_level')],
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'matches' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'id' => ['type' => 'INTEGER'],
                                'compatibility' => ['type' => 'INTEGER'],
                                'insight' => ['type' => 'STRING'],
                                'recommendation' => ['type' => 'STRING'],
                                /*
                                     * The per-factor bars the board and the
                                     * recruit screen already draw. Without
                                     * these the match panel renders its ring
                                     * over an empty space.
                                     */
                                'factors' => [
                                    'type' => 'ARRAY',
                                    'items' => [
                                        'type' => 'OBJECT',
                                        'properties' => [
                                            'label' => ['type' => 'STRING'],
                                            'value' => ['type' => 'INTEGER'],
                                        ],
                                        'required' => ['label', 'value'],
                                    ],
                                ],
                                'matched_skills' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                                'missing_skills' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                            ],
                            'required' => ['id', 'compatibility', 'insight'],
                        ],
                    ],
                ],
                'required' => ['matches'],
            ],
        ];
    }

    /**
     * The open postings a student could be matched against.
     *
     * @return Collection<int, Project>
     */
    protected function openBriefs(): Collection
    {
        return Project::query()
            ->where('status', ProjectStatus::Open)
            ->with('skills')
            ->limit($this->maxCandidates())
            ->get();
    }

    /**
     * Rank briefs for one reader — a student profile, or a typed capstone.
     *
     * The same shape as rank(), turned round: there the model reads one brief
     * against many people, here one person against many briefs. Kept separate
     * rather than generalised into one method with a flag, because the prompt,
     * the ids and the fallback all differ and a shared version would be three
     * conditionals wearing a trench coat.
     *
     * @param  Collection<int, Project>  $briefs
     * @param  callable(): Collection<int, array<string, mixed>>  $fallback
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    protected function rankBriefs(string $reader, Collection $briefs, string $cacheKey, callable $fallback): Collection
    {
        if (! $this->isConfigured() || $briefs->isEmpty() || $this->isCoolingDown()) {
            return $fallback();
        }

        $ids = $briefs->pluck('id')->all();

        $judgements = Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('gemini.cache_minutes')),
            fn (): ?array => $this->ask($this->briefPayload($reader, $briefs), $ids),
        );

        if ($judgements === null) {
            Cache::forget($cacheKey);
            $this->beginCooldown();

            return $fallback();
        }

        $computed = $fallback();

        /* A brief the model skipped keeps its computed score, never vanishes. */
        return $briefs->mapWithKeys(fn (Project $project): array => [
            $project->id => $judgements[$project->id]
                ?? $computed[$project->id]
                ?? $this->empty(),
        ]);
    }

    /**
     * Build the request body for ranking briefs against one reader.
     *
     * @param  Collection<int, Project>  $briefs
     * @return array<string, mixed>
     */
    protected function briefPayload(string $reader, Collection $briefs): array
    {
        return [
            'systemInstruction' => [
                'parts' => [[
                    'text' => implode(' ', [
                        'You match client project briefs to a student developer for a university platform.',
                        'For each brief, judge how well it fits what this student can build and wants to build, and return a compatibility score from 0 to 100.',
                        'Be strict: 90+ means an excellent fit they should apply to today, 50 means a stretch worth considering, under 30 means the wrong brief for them.',
                        'Recognise that a plain-English description and a technical term can mean the same work.',
                        'The insight must be one sentence addressed to the student, specific to this brief, and must never invent experience they have not claimed.',
                        'Give two to four factors: the named dimensions you judged on, each scored 0 to 100.',
                        'The recommendation is one sentence telling the student how to pitch themselves for this brief.',
                        'Return every brief id you were given, exactly once.',
                    ]),
                ]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $reader."\n\nOPEN BRIEFS:\n".$this->encode(
                        $briefs->map(fn (Project $project): array => array_filter([
                            'id' => $project->id,
                            'title' => $project->title,
                            'category' => $project->category,
                            'industry' => $project->industry,
                            'description' => $project->description,
                            'objectives' => $project->objectives,
                            'skills' => $project->skills->pluck('name')->all(),
                        ]))->values()->all(),
                    ),
                ]],
            ]],
            'generationConfig' => $this->generationConfig(),
        ];
    }

    /**
     * Ask the model to rank the candidates, or hand back to the computed scorer.
     *
     * @param  Collection<int, StudentProfile>  $candidates
     * @param  callable(): Collection<int, array<string, mixed>>  $fallback
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    protected function rank(string $brief, Collection $candidates, string $cacheKey, callable $fallback): Collection
    {
        if (! $this->isConfigured() || $candidates->isEmpty() || $this->isCoolingDown()) {
            return $fallback();
        }

        $judgements = Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('gemini.cache_minutes')),
            fn (): ?array => $this->ask(
                $this->payload($brief, $candidates),
                $candidates->pluck('user_id')->all(),
            ),
        );

        if ($judgements === null) {
            /* Never cache a failure as an answer. */
            Cache::forget($cacheKey);
            $this->beginCooldown();

            return $fallback();
        }

        $computed = $fallback();

        /*
         * The computed score is kept for anybody the model did not return.
         * A model that skips a candidate must not silently delete them from
         * the results — that reads as "this student does not exist" rather
         * than "the model had nothing to say".
         */
        return $candidates
            ->mapWithKeys(function (StudentProfile $profile) use ($judgements, $computed): array {
                $judgement = $judgements[$profile->user_id] ?? null;

                return [
                    $profile->user_id => $judgement === null
                        ? ($computed[$profile->user_id] ?? $this->empty())
                        : $judgement,
                ];
            });
    }

    /**
     * Put the question to the model and read the answer back.
     *
     * Returns null on every fault, which is the caller's signal to fall back.
     *
     * @param  Collection<int, StudentProfile>  $candidates
     * @return array<int, array{score: float, compatibility: int, reason: array<string, mixed>}>|null
     */
    protected function ask(array $payload, array $knownIds): ?array
    {
        try {
            $response = Http::asJson()
                ->timeout((int) config('gemini.timeout'))
                /*
                 * The key travels in a header, never in the query string. A
                 * URL is logged by proxies, kept in history and repeated in
                 * error reports; a header is not.
                 */
                ->withHeaders(['x-goog-api-key' => (string) config('gemini.api_key')])
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException $exception) {
            return $this->logFailure('unreachable', $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->logFailure('threw', $exception->getMessage());
        }

        if (! $response->successful()) {
            return $this->logFailure('rejected', 'HTTP '.$response->status());
        }

        return $this->parse($response->json(), $knownIds);
    }

    /**
     * Build the request body.
     *
     * responseSchema is what makes this usable. Without it the reply is prose
     * that happens to contain numbers, and parsing prose from a model is how
     * you get a feature that works until the day it does not.
     *
     * @param  Collection<int, StudentProfile>  $candidates
     * @return array<string, mixed>
     */
    protected function payload(string $brief, Collection $candidates): array
    {
        return [
            'systemInstruction' => [
                'parts' => [[
                    'text' => implode(' ', [
                        'You match student developers to client project briefs for a university platform.',
                        'For each candidate, judge how well their skills and experience fit the brief and return a compatibility score from 0 to 100.',
                        'Be strict: 90+ means they could start tomorrow, 50 means a plausible stretch, under 30 means the wrong person.',
                        'Recognise that a plain-English description and a technical term can mean the same work.',
                        'The insight must be one sentence, specific to this candidate and this brief, and must never invent experience the profile does not claim.',
                        /*
                         * The reader sees this sentence. They do not see the
                         * ids — those exist only so the reply can be matched
                         * back to a row — so "Candidate 23 has experience in"
                         * reads as a leak of something meaningless. Names are
                         * deliberately withheld, which leaves the model with
                         * nothing to call anybody: tell it not to try.
                         */
                        'Never name or number the candidate. Do not write "Candidate 12" or refer to an id. Write about the work: "Has shipped two stock systems in Laravel."',
                        'Give two to four factors: the named dimensions you actually judged on, each scored 0 to 100.',
                        'A factor label names a capability the brief calls for, such as "Laravel and MySQL" or "Multi-tenant data" — never a generic word like "Skills".',
                        'The recommendation is one sentence of advice to the reader on how to act on this match.',
                        'Return every candidate id you were given, exactly once.',
                    ]),
                ]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => "BRIEF:\n".$brief."\n\nCANDIDATES:\n".json_encode(
                        $candidates->map(fn (StudentProfile $p) => $this->describe($p))->values(),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                    ),
                ]],
            ]],
            'generationConfig' => $this->generationConfig(),
        ];
    }

    /**
     * Describe a posting for the model.
     *
     * Labelled rather than concatenated. ScopeProfile joins the same fields
     * into one blob because a keyword scorer only counts words, but a reader
     * does better when it knows which sentence is the industry and which is
     * the actual work — and the whole reason for this driver is that it reads.
     */
    protected function briefFor(Project $project): string
    {
        return collect([
            'Title' => $project->title,
            'Category' => $project->category,
            'Industry' => $project->industry,
            'Description' => $project->description,
            'Objectives' => $project->objectives,
        ])
            ->filter()
            ->map(fn (string $value, string $label): string => $label.': '.$value)
            ->join("\n");
    }

    /**
     * Describe one candidate for the model.
     *
     * NO NAME, NO EMAIL, NO PHOTO, NO BARANGAY. The model is asked to judge
     * whether somebody can build a thing, and none of those help it do that —
     * they only widen what leaves the platform. What goes out is an internal
     * id and the professional facts the student published themselves, and the
     * id is meaningless to anybody outside this database.
     *
     * @return array<string, mixed>
     */
    protected function describe(StudentProfile $profile): array
    {
        return array_filter([
            'id' => $profile->user_id,
            'headline' => $profile->headline,
            'about' => $profile->biography,
            'course' => $profile->course?->name,
            'year_level' => $profile->year_level,
            /*
             * Degree and area of study, but never the school's name. "BSIT,
             * 4th year, majoring in systems development" is the part that
             * bears on whether somebody can build a thing; the campus is not,
             * and in a cohort of forty at one school it narrows the field to a
             * person. See the privacy note on this method.
             */
            'studied' => $profile->relationLoaded('educations')
                ? $profile->educations
                    ->map(fn (StudentEducation $education): ?string => $education->displayQualification())
                    ->filter()
                    ->take(3)
                    ->values()
                    ->all()
                : [],
            /*
             * Spoken languages, asked for by the brief spec: a client writing
             * in Filipino is better served by somebody who reads it.
             */
            'languages' => $profile->relationLoaded('languages')
                ? $profile->languages
                    ->map(fn ($language): string => $language->name.' ('.$language->proficiency->value.')')
                    ->all()
                : [],
            'completed_projects' => $profile->completed_projects_count,
            'skills' => $profile->skills->pluck('name')->all(),
        ]);
    }

    /**
     * Turn the model's reply into the shape every screen already reads.
     *
     * @param  array<string, mixed>|null  $body
     * @param  Collection<int, StudentProfile>  $candidates
     * @return array<int, array{score: float, compatibility: int, reason: array<string, mixed>}>|null
     */
    protected function parse(?array $body, array $knownIds): ?array
    {
        $text = data_get($body, 'candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            return $this->logFailure('empty', 'no text part in the reply');
        }

        $decoded = json_decode($text, true);
        $matches = data_get($decoded, 'matches');

        if (! is_array($matches)) {
            return $this->logFailure('unparseable', 'reply was not the requested shape');
        }

        $scores = [];

        foreach ($matches as $match) {
            $id = data_get($match, 'id');

            /* An id we never sent is a hallucination, not a match. */
            if (! is_int($id) || ! in_array($id, $knownIds, true)) {
                continue;
            }

            $compatibility = (int) data_get($match, 'compatibility', 0);
            $compatibility = max(0, min(100, $compatibility));

            $scores[$id] = [
                /* Cast: 100/100 is an int in PHP, and the shape promises a float. */
                'score' => (float) ($compatibility / 100),
                'compatibility' => $compatibility,
                'reason' => [
                    'insight' => (string) data_get($match, 'insight', ''),
                    'recommendation' => $this->text($match, 'recommendation'),
                    'factors' => $this->factors($match),
                    'matchedSkills' => array_values(array_filter(
                        (array) data_get($match, 'matched_skills', []),
                        'is_string',
                    )),
                    'missingSkills' => array_values(array_filter(
                        (array) data_get($match, 'missing_skills', []),
                        'is_string',
                    )),
                    'source' => 'gemini',
                ],
            ];
        }

        return $scores === [] ? $this->logFailure('unusable', 'no recognisable candidate in the reply') : $scores;
    }

    /**
     * The per-factor bars, kept only where both halves are usable.
     *
     * ProjectBoardController::highlight() already skips anything that is not
     * a {label, value} pair rather than guessing, so a malformed factor is
     * survivable — but filtering here means the stored reason is clean rather
     * than merely tolerated downstream.
     *
     * @return list<array{label: string, value: int}>
     */
    protected function factors(mixed $match): array
    {
        return collect((array) data_get($match, 'factors', []))
            ->filter(fn ($factor): bool => is_array($factor)
                && is_string($factor['label'] ?? null)
                && ($factor['label'] ?? '') !== ''
                && is_numeric($factor['value'] ?? null))
            ->map(fn (array $factor): array => [
                'label' => (string) $factor['label'],
                'value' => max(0, min(100, (int) $factor['value'])),
            ])
            ->values()
            ->all();
    }

    /**
     * One optional string off the model's reply, or null.
     */
    protected function text(mixed $match, string $key): ?string
    {
        $value = data_get($match, $key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * What a student with nothing said about them scores.
     *
     * @return array{score: float, compatibility: int, reason: array<string, mixed>}
     */
    protected function empty(): array
    {
        return ['score' => 0.0, 'compatibility' => 0, 'reason' => []];
    }

    /**
     * The generateContent endpoint for the configured model.
     */
    protected function endpoint(): string
    {
        return rtrim((string) config('gemini.base_url'), '/')
            .'/models/'.config('gemini.model').':generateContent';
    }

    /**
     * How many candidates one request may carry.
     */
    protected function maxCandidates(): int
    {
        return max(1, (int) config('gemini.max_candidates'));
    }

    /**
     * Record why the model was not used, and hand back null.
     *
     * Warning rather than error: nobody's request failed. The page rendered
     * with computed scores and the person browsing has no idea anything
     * happened, which is the design.
     */
    protected function logFailure(string $kind, string $reason): null
    {
        Log::warning('Gemini matching fell back to the computed scorer.', [
            'kind' => $kind,
            'reason' => $reason,
            'model' => config('gemini.model'),
        ]);

        return null;
    }
}
