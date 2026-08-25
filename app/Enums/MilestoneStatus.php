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

    /*
     * There is deliberately no progress() here any more.
     *
     * It used to map a status to a percentage — in progress was 40%, submitted
     * was 80% — and those numbers were then averaged into the ring every
     * dashboard draws. Nobody ever supplied them. "In progress" is a state a
     * person recorded; 40% is a measurement nobody took, and printing it next
     * to a real figure lends it the same authority.
     *
     * What the platform genuinely knows is how many milestones the client has
     * approved, which is countable. Agreement::progress() reports that share,
     * and a milestone shows its status rather than a bar.
     */

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
