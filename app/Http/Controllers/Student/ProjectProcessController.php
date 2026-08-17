<?php

namespace App\Http\Controllers\Student;

use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\AgreementMilestone;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Project process" — Project Progress Tracking, from the student's side.
 *
 * Every figure here is a milestone somebody moved by hand: the student says
 * what they have started and handed over, the client says what they accept.
 * Nothing is inferred from elapsed time, which is why the screen is empty
 * rather than optimistic before an agreement is signed.
 */
class ProjectProcessController extends Controller
{
    /**
     * Show the student the state of their current build.
     */
    public function __invoke(Request $request, Team $currentTeam): Response
    {
        $agreement = Agreement::query()
            ->where('student_id', $request->user()->id)
            ->active()
            ->with(['milestones', 'project.team.clientProfile'])
            ->latest('version')
            ->first();

        return Inertia::render('student/process', [
            'agreement' => $agreement === null ? null : [
                'id' => $agreement->id,
                'reference' => $agreement->reference,
                'progress' => $agreement->progress(),
                'projectTitle' => $agreement->project->title,
                'client' => $agreement->project->team->clientProfile->business_name
                    ?? $agreement->project->team->name,
                'startsOn' => $agreement->starts_on?->format('j M Y'),
                'endsOn' => $agreement->ends_on?->format('j M Y'),
                'milestones' => $agreement->milestones
                    ->map(fn (AgreementMilestone $milestone): array => [
                        'id' => $milestone->id,
                        'position' => $milestone->position,
                        'title' => $milestone->title,
                        'description' => $milestone->description,
                        'amount' => $milestone->amount,
                        'startsOn' => $milestone->starts_on?->format('j M'),
                        'endsOn' => $milestone->ends_on?->format('j M'),
                        'status' => $milestone->status->value,
                        'statusLabel' => $milestone->status->label(),
                        'statusVariant' => $milestone->status->tagVariant(),
                        'progress' => $milestone->status->progress(),
                        'reviewNote' => $milestone->review_note,
                        'isFinal' => $milestone->status->isFinal(),
                    ])
                    ->values()
                    ->all(),
            ],
            /*
             * The moves this side is allowed to make. A student starts work
             * and hands it over; approving their own milestone is the client's
             * to do, and UpdateMilestoneRequest refuses it either way.
             */
            'assignableStatuses' => collect(MilestoneStatus::studentAssignable())
                ->map(fn (MilestoneStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->all(),
        ]);
    }
}
