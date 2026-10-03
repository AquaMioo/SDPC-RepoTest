<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementStatus;
use App\Enums\AgreementTemplate;
use App\Enums\MilestoneStatus;
use App\Models\Agreement;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

/**
 * Turns an accepted application into a contract waiting to be filled in.
 *
 * Runs the moment a client accepts a student. It writes no money and no dates:
 * a draft states who, on what, under whose default terms, and leaves every
 * figure for the client to negotiate. Nothing here commits either side — that
 * is what signing is for.
 */
class DraftAgreement
{
    /**
     * Draft the agreement for an accepted application.
     *
     * Idempotent by way of the unique index on application_id: accepting a
     * student twice, or a retried job, returns the agreement that already
     * exists instead of writing a second one.
     */
    public function handle(Application $application): Agreement
    {
        return DB::transaction(function () use ($application): Agreement {
            $existing = $application->agreement;

            if ($existing !== null) {
                return $existing;
            }

            $project = $application->project;

            $agreement = Agreement::query()->create([
                'project_id' => $project->id,
                'application_id' => $application->id,
                'team_id' => $project->team_id,
                'student_id' => $application->user_id,
                'reference' => $this->nextReference(),
                'version' => 1,
                /* The SDPC Memorandum of Agreement. */
                'template' => AgreementTemplate::SdpcMemorandum,
                'status' => AgreementStatus::Draft,
                /* The brief is the honest starting point for the scope. */
                'scope_summary' => $project->description,
                'deliverables' => $this->deliverables($project->objectives),
                'intellectual_property_terms' => config('agreements.default_terms.intellectual_property'),
                'confidentiality_terms' => config('agreements.default_terms.confidentiality'),
                'academic_terms' => config('agreements.default_terms.academic'),
            ]);

            $this->seedMilestones($agreement);

            return $agreement->load('milestones');
        });
    }

    /**
     * Build the next human-quotable reference, e.g. SDPC-2026-014.
     *
     * Numbered within the year so the sequence stays short and reads like a
     * document number rather than a database id. Every version of a contract
     * shares its reference, so a revision never takes a number of its own.
     *
     * The next number is one past the HIGHEST used this year, never the count
     * of references: deleting an account deletes its agreements outright, and
     * a count then lands on a number still in use. With only SDPC-2026-002
     * left after 001 went, counting gave 002 again and every acceptance failed
     * on the unique (reference, version) index (2026-10-03).
     */
    protected function nextReference(): string
    {
        $prefix = (string) config('agreements.reference_prefix');
        $year = now()->year;
        $stem = "{$prefix}-{$year}-";

        $highest = Agreement::withTrashed()
            ->where('reference', 'like', "{$stem}%")
            ->pluck('reference')
            ->map(fn (string $reference): int => (int) substr($reference, strlen($stem)))
            ->max() ?? 0;

        return sprintf('%s%03d', $stem, $highest + 1);
    }

    /**
     * Split the posting's objectives into one deliverable per line.
     *
     * The posting form already asks for "objectives — one per line", so this
     * carries the client's own words across rather than inventing a scope.
     *
     * @return list<string>
     */
    protected function deliverables(?string $objectives): array
    {
        if ($objectives === null || trim($objectives) === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $objectives) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Give the agreement an empty milestone structure to fill in.
     */
    protected function seedMilestones(Agreement $agreement): void
    {
        /** @var list<string> $titles */
        $titles = config('agreements.default_milestones', []);

        foreach ($titles as $index => $title) {
            $agreement->milestones()->create([
                'position' => $index + 1,
                'title' => $title,
                'amount' => 0,
                'status' => MilestoneStatus::Pending,
            ]);
        }
    }
}
