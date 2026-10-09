<?php

namespace App\Actions\Agreements;

use App\Enums\AddendumStatus;
use App\Enums\AgreementStatus;
use App\Models\Addendum;
use App\Models\Agreement;
use App\Models\User;
use App\Notifications\Agreements\ProjectExtensionRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The client's "Project extension" button: open a draft addendum.
 *
 * Only once the build is at least Addendum::EXTENSION_THRESHOLD% done
 * (Section III's academic protection threshold, measured the way every
 * progress figure is, Agreement::progress), and only one extension at a time.
 * The memorandum is untouched: the addendum is a second document beside it.
 * The student side hears about it at once (ProjectExtensionRequested).
 */
class RequestProjectExtension
{
    /**
     * Draft a new addendum on the agreement.
     *
     * @throws ValidationException when the build may not be extended yet
     */
    public function handle(Agreement $agreement, User $client): Addendum
    {
        $addendum = DB::transaction(function () use ($agreement, $client): Addendum {
            /* Locked, so two clicks cannot open two extensions. */
            $locked = Agreement::query()->lockForUpdate()->findOrFail($agreement->id);

            if ($locked->status !== AgreementStatus::Active) {
                throw ValidationException::withMessages([
                    'extension' => __('Only a project in progress can be extended.'),
                ]);
            }

            $progress = $agreement->progress();

            if ($progress < Addendum::EXTENSION_THRESHOLD) {
                throw ValidationException::withMessages([
                    'extension' => __('A project can be extended once it is at least :threshold% complete. This one is at :progress%.', [
                        'threshold' => Addendum::EXTENSION_THRESHOLD,
                        'progress' => $progress,
                    ]),
                ]);
            }

            if (Addendum::query()->where('agreement_id', $agreement->id)->open()->exists()) {
                throw ValidationException::withMessages([
                    'extension' => __('This project already has an extension in progress.'),
                ]);
            }

            /* One past the highest, never a count (see .ai/rules/agreements.md). */
            $sequence = (int) Addendum::query()->where('agreement_id', $agreement->id)->max('sequence') + 1;

            return Addendum::query()->create([
                'agreement_id' => $agreement->id,
                'sequence' => $sequence,
                'reference' => $agreement->reference.'-A'.$sequence,
                'status' => AddendumStatus::Draft,
                'requested_by' => $client->id,
            ]);
        });

        Notification::send($agreement->studentSide(), new ProjectExtensionRequested($addendum));

        return $addendum;
    }
}
