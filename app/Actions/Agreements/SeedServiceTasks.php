<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementTemplate;
use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\AgreementRequirement;

/**
 * Puts the Section VII services into Project Management when the work starts.
 *
 * The SDPC memorandum's phases are Objective, Scope and Turnover. Section VII
 * never adds a phase: every service's Objective (its title) becomes a task in
 * the Objective phase, and its Scope (its description) a task in the Scope
 * phase — two separate items, so each is delivered and verified on its own.
 * Runs once, when the second signature makes the agreement active; Section
 * VII cannot change after anybody signs, so there is nothing to keep in sync.
 *
 * The phases are found by position, like Turnover: the first is Objective,
 * the second Scope, the last Turnover.
 */
class SeedServiceTasks
{
    /**
     * Seed the Objective and Scope tasks for a newly active agreement.
     */
    public function handle(Agreement $agreement): void
    {
        if ($agreement->template !== AgreementTemplate::SdpcMemorandum) {
            return;
        }

        $phases = $agreement->milestones()->get();
        $work = $phases->slice(0, -1)->values();

        $objectivePhase = $work->get(0);
        $scopePhase = $work->get(1) ?? $objectivePhase;

        if ($objectivePhase === null) {
            return;
        }

        $services = $agreement->services()->get();

        $this->seed($objectivePhase, $services->map(fn (AgreementRequirement $service): ?string => $service->title)->all());
        $this->seed($scopePhase, $services->map(fn (AgreementRequirement $service): string => $service->body)->all());
    }

    /**
     * Append one open task per line, after any the phase already has.
     *
     * @param  list<string|null>  $lines
     */
    protected function seed(AgreementMilestone $phase, array $lines): void
    {
        $position = (int) $phase->tasks()->max('position');

        foreach ($lines as $line) {
            if ($line === null || trim($line) === '') {
                continue;
            }

            $phase->tasks()->create([
                'position' => ++$position,
                'title' => $line,
                'status' => TaskStatus::Open,
            ]);
        }
    }
}
