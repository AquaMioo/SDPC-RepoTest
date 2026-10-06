<?php

namespace App\Actions\Agreements;

use App\Enums\AgreementTemplate;
use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\AgreementRequirement;

/**
 * Puts the Section VII services into Project Management when the work starts.
 *
 * The SDPC memorandum's phases are Objective & Scope and Turnover. Section VII
 * never adds a phase: every service becomes one task in Objective & Scope, its
 * Objective the task's title and its Scope the task's description (owner,
 * 2026-10-07; they were two separate tasks in two phases before). Runs once,
 * when the second signature makes the agreement active; Section VII cannot
 * change after anybody signs, so there is nothing to keep in sync.
 *
 * The phase is found by position, like Turnover: the first one.
 */
class SeedServiceTasks
{
    /**
     * Seed the Objective & Scope tasks for a newly active agreement.
     */
    public function handle(Agreement $agreement): void
    {
        if ($agreement->template !== AgreementTemplate::SdpcMemorandum) {
            return;
        }

        $phase = $agreement->milestones()->first();

        if ($phase === null) {
            return;
        }

        $position = (int) $phase->tasks()->max('position');

        $agreement->services()->get()
            ->reject(fn (AgreementRequirement $service): bool => blank($service->title))
            ->each(function (AgreementRequirement $service) use ($phase, &$position): void {
                $phase->tasks()->create([
                    'position' => ++$position,
                    'title' => $service->title,
                    'description' => $service->body,
                    'status' => TaskStatus::Open,
                ]);
            });
    }
}
