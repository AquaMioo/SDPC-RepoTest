<?php

namespace App\Notifications\Agreements;

use App\Models\Addendum;

/**
 * The fields every addendum notification stores, so PresentNotification can
 * word the line and link it to the addendum without a query.
 */
class AddendumPayload
{
    /**
     * Build the stored payload for one addendum notification.
     *
     * @return array<string, mixed>
     */
    public static function for(Addendum $addendum, string $type): array
    {
        return [
            'type' => $type,
            'addendum_id' => $addendum->id,
            'addendum_reference' => $addendum->reference,
            'agreement_id' => $addendum->agreement_id,
            'project_id' => $addendum->agreement->project_id,
            'project_title' => $addendum->agreement->project->title,
        ];
    }
}
