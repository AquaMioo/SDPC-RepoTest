<?php

namespace App\Enums;

/**
 * What an administrator did about a report.
 *
 * The four things the platform can actually carry out. Each maps to a state
 * that already exists elsewhere — Monitor and RemoveAccess set the same
 * UserStatus the Users screen sets, ClosePosting sets the same ProjectStatus
 * the posting queue sets — so a decision taken here means the same thing as
 * the same decision taken anywhere else.
 */
enum IssueResolution: string
{
    case Warn = 'warn';
    case Monitor = 'monitor';
    case RemoveAccess = 'remove_access';
    case ClosePosting = 'close_posting';

    /**
     * Get every action value, e.g. for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The label the button wears.
     */
    public function label(): string
    {
        return match ($this) {
            self::Warn => __('Warn'),
            self::Monitor => __('Place under monitoring'),
            self::RemoveAccess => __('Remove access'),
            self::ClosePosting => __('Close posting'),
        };
    }

    /**
     * What gets written on the closed report.
     */
    public function resolution(): string
    {
        return match ($this) {
            self::Warn => 'Warned',
            self::Monitor => 'Placed under monitoring',
            self::RemoveAccess => 'Access removed',
            self::ClosePosting => 'Posting closed',
        };
    }

    /**
     * Determine if the action only makes sense when a posting is attached.
     */
    public function needsPosting(): bool
    {
        return $this === self::ClosePosting;
    }

    /**
     * The account status this action puts the reported account into, if any.
     */
    public function accountStatus(): ?UserStatus
    {
        return match ($this) {
            self::Monitor => UserStatus::Monitored,
            self::RemoveAccess => UserStatus::Deactivated,
            default => null,
        };
    }

    /**
     * The actions available for a report, as option rows for the screen.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function optionsFor(bool $hasPosting): array
    {
        return array_values(array_map(
            fn (self $action): array => [
                'value' => $action->value,
                'label' => $action->label(),
            ],
            array_filter(
                self::cases(),
                fn (self $action): bool => $hasPosting || ! $action->needsPosting(),
            ),
        ));
    }
}
