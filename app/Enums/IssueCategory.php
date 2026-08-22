<?php

namespace App\Enums;

/**
 * What a report is about.
 *
 * Deliberately a short, closed list: a free-text reason produces a queue no
 * administrator can triage, and these five cover what the platform can
 * actually act on.
 */
enum IssueCategory: string
{
    case DuplicateAccount = 'duplicate_account';
    case DelayedDelivery = 'delayed_delivery';
    case Misrepresentation = 'misrepresentation';
    case Harassment = 'harassment';
    case Other = 'other';

    /**
     * Get the display label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::DuplicateAccount => 'Duplicate account report',
            self::DelayedDelivery => 'Delayed project delivery',
            self::Misrepresentation => 'Misrepresented skills or business',
            self::Harassment => 'Harassment or abusive conduct',
            self::Other => 'Other',
        };
    }

    /**
     * The categories as option rows for a select.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            self::cases(),
        );
    }
}
