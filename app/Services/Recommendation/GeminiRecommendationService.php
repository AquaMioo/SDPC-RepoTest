<?php

namespace App\Services\Recommendation;

use App\Models\Project;
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
class GeminiRecommendationService implements RecommendationService, ScoresFreeText
{
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
            ->with(['skills', 'course', 'school'])
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
     * Deliberately delegated. The model reads one brief against many students;
     * turning it round means one student against many briefs, which is a
     * different prompt, a different cache shape and a second set of failure
     * modes for a screen where the computed ranking is already good. When the
     * student board needs model judgement this is where it goes — not before.
     *
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    public function scoresForStudent(User $student): Collection
    {
        return $this->fallback->scoresForStudent($student);
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
     * Ask the model to rank the candidates, or hand back to the computed scorer.
     *
     * @param  Collection<int, StudentProfile>  $candidates
     * @param  callable(): Collection<int, array<string, mixed>>  $fallback
     * @return Collection<int, array{score: float, compatibility: int, reason: array<string, mixed>}>
     */
    protected function rank(string $brief, Collection $candidates, string $cacheKey, callable $fallback): Collection
    {
        if (! $this->isConfigured() || $candidates->isEmpty()) {
            return $fallback();
        }

        $judgements = Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('gemini.cache_minutes')),
            fn (): ?array => $this->ask($brief, $candidates),
        );

        if ($judgements === null) {
            /* Never cache a failure as an answer. */
            Cache::forget($cacheKey);

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
    protected function ask(string $brief, Collection $candidates): ?array
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
                ->post($this->endpoint(), $this->payload($brief, $candidates));
        } catch (ConnectionException $exception) {
            return $this->logFailure('unreachable', $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->logFailure('threw', $exception->getMessage());
        }

        if (! $response->successful()) {
            return $this->logFailure('rejected', 'HTTP '.$response->status());
        }

        return $this->parse($response->json(), $candidates);
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
            'generationConfig' => [
                /* A ranking should not wander between page loads. */
                'temperature' => 0.2,
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
                                    'matched_skills' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                                    'missing_skills' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                                ],
                                'required' => ['id', 'compatibility', 'insight'],
                            ],
                        ],
                    ],
                    'required' => ['matches'],
                ],
            ],
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
    protected function parse(?array $body, Collection $candidates): ?array
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

        $known = $candidates->pluck('user_id')->all();
        $scores = [];

        foreach ($matches as $match) {
            $id = data_get($match, 'id');

            /* An id we never sent is a hallucination, not a match. */
            if (! is_int($id) || ! in_array($id, $known, true)) {
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
