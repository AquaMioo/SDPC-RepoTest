<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Closed = 'closed';
    case Archived = 'archived';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
        };
    }

    /**
     * Determine if the project is visible to students browsing the platform.
     *
     * Drafts and postings awaiting admin screening are client-only.
     */
    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::Open, self::InProgress, self::Completed], true);
    }

    /**
     * Determine if students may still apply to a project in this status.
     */
    public function acceptsApplications(): bool
    {
        return $this === self::Open;
    }

    /**
     * Determine if the client may still edit the posting's core details.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::PendingReview, self::Open], true);
    }

    /**
     * Determine if the posting is still work in hand rather than history.
     *
     * A team may only run one posting at a time, so everything from the draft
     * drawer through active work holds their slot. Completed, closed and
     * archived postings are finished business and give it back.
     */
    public function isUnfinished(): bool
    {
        return in_array($this, [self::Draft, self::PendingReview, self::Open, self::InProgress], true);
    }

    /**
     * Get the statuses that count as active work for dashboard totals.
     *
     * @return array<self>
     */
    public static function active(): array
    {
        return [self::Open, self::InProgress];
    }
}
