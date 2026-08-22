<?php

namespace App\Enums;

/**
 * Where a report has got to.
 *
 * A report arrives Pending. An administrator who has picked it up but not
 * finished with it marks it InReview. Resolved is terminal and records that a
 * decision was taken, whatever that decision was.
 */
enum IssueStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Resolved = 'resolved';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InReview => 'In review',
            self::Resolved => 'Resolved',
        };
    }

    /**
     * Whether the report still needs an administrator.
     */
    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }
}
