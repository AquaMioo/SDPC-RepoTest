<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Shortlisted = 'shortlisted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Determine if the client can still act on an application in this status.
     *
     * Withdrawn applications are student-owned decisions and are terminal for
     * the client; accepted and rejected are already resolved.
     */
    public function isActionable(): bool
    {
        return in_array($this, [self::Pending, self::Shortlisted], true);
    }

    /**
     * Get the statuses a client may transition an application into.
     *
     * @return array<self>
     */
    public static function clientAssignable(): array
    {
        return [self::Shortlisted, self::Accepted, self::Rejected];
    }
}
