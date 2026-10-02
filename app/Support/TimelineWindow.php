<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The span of days a project timeline may use, and Turnover's minimum length.
 *
 * Every date somebody sets on the agreement or on Project Management (a phase,
 * a task deadline, an asked-for deadline) falls between today and one year
 * from today. "Today" is Singapore Time, the application's clock (config
 * app.timezone). The screens draw the same bounds on their date boxes
 * (resources/js/lib/calendar-days.ts).
 *
 * Turnover, the last phase, has to run at least one calendar month. The other
 * phases (the Section VII objectives) may overlap each other freely.
 */
final class TimelineWindow
{
    /**
     * The earliest date a timeline date may be set to: today.
     */
    public static function earliest(): CarbonImmutable
    {
        return CarbonImmutable::today();
    }

    /**
     * The latest date a timeline date may be set to: one year from today.
     */
    public static function latest(): CarbonImmutable
    {
        return CarbonImmutable::today()->addYear();
    }

    /**
     * Whether the given day falls inside the window.
     */
    public static function contains(CarbonInterface $date): bool
    {
        $day = CarbonImmutable::parse($date->toDateString());

        return $day->betweenIncluded(self::earliest(), self::latest());
    }

    /**
     * The earliest day a Turnover starting on the given day may end.
     *
     * One calendar month later, without spilling into the month after: a
     * Turnover starting on 31 January may end on 28 February.
     */
    public static function earliestTurnoverEnd(CarbonInterface $startsOn): CarbonImmutable
    {
        return CarbonImmutable::parse($startsOn->toDateString())->addMonthNoOverflow();
    }

    /**
     * The latest day a Turnover ending on the given day may start.
     */
    public static function latestTurnoverStart(CarbonInterface $endsOn): CarbonImmutable
    {
        return CarbonImmutable::parse($endsOn->toDateString())->subMonthNoOverflow();
    }

    /**
     * Whether a Turnover between these two days runs at least one month.
     */
    public static function isLongEnoughForTurnover(CarbonInterface $startsOn, CarbonInterface $endsOn): bool
    {
        return CarbonImmutable::parse($endsOn->toDateString())
            ->greaterThanOrEqualTo(self::earliestTurnoverEnd($startsOn));
    }

    /**
     * Why a Turnover is too short, naming the first day it could end.
     */
    public static function turnoverTooShortMessage(CarbonInterface $startsOn): string
    {
        return __('Turnover has to run at least one month. End it on or after :date.', [
            'date' => self::earliestTurnoverEnd($startsOn)->format('j M Y'),
        ]);
    }
}
