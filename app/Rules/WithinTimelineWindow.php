<?php

namespace App\Rules;

use App\Support\TimelineWindow;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

/**
 * A timeline date: not in the past, and not more than one year from today.
 *
 * Used by every request that sets a date on the agreement or on Project
 * Management, so the bounds and their wording are the same everywhere. See
 * App\Support\TimelineWindow. The format itself is left to date_format.
 */
class WithinTimelineWindow implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return;
        }

        if ($date->lt(TimelineWindow::earliest())) {
            $fail(__('Dates cannot be in the past. Pick today or a later date.'));

            return;
        }

        if ($date->gt(TimelineWindow::latest())) {
            $fail(__('Dates cannot be more than one year from today. Pick :date or earlier.', [
                'date' => TimelineWindow::latest()->format('j M Y'),
            ]));
        }
    }
}
