<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    /** Agreed but not started. */
    case Pending = 'pending';

    /** The student is building it. */
    case InProgress = 'in_progress';

    /** The student handed it over and the client has not answered yet. */
    case Submitted = 'submitted';

    /** The client accepted the work. */
    case Approved = 'approved';

    /** The client sent it back with comments. */
    case Returned = 'returned';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In progress',
            self::Submitted => 'In review',
            self::Approved => 'Approved',
            self::Returned => 'Returned',
        };
    }

    /**
     * Get how far through a milestone this status sits, as a percentage.
     *
     * The Project process screen averages these across an agreement's
     * milestones, which is the only honest progress figure the platform has —
     * every one of these transitions is recorded by a person, not inferred.
     */
    public function progress(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::InProgress => 40,
            self::Submitted, self::Returned => 80,
            self::Approved => 100,
        };
    }

    /**
     * Get the statuses a student may move a milestone into.
     *
     * @return array<self>
     */
    public static function studentAssignable(): array
    {
        return [self::InProgress, self::Submitted];
    }

    /**
     * Get the statuses a client may move a milestone into.
     *
     * @return array<self>
     */
    public static function clientAssignable(): array
    {
        return [self::Approved, self::Returned];
    }

    /**
     * Determine if the milestone is finished and can no longer move.
     */
    public function isFinal(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Get the tag variant the design uses to render this status.
     */
    public function tagVariant(): string
    {
        return match ($this) {
            self::Approved => 'accent',
            self::InProgress, self::Submitted => 'outline',
            self::Pending, self::Returned => 'neutral',
        };
    }
}
