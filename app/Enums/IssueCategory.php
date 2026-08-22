<?php

namespace App\Enums;

/**
 * What a report is about.
 *
 * Deliberately a short, closed list: a free-text reason produces a queue no
 * administrator can triage, and these cover what the platform can actually act
 * on. The last two are about a posting rather than a person, and arrived with
 * job reporting — they are still things an administrator can decide, which is
 * the bar for being on this list at all.
 */
enum IssueCategory: string
{
    case DuplicateAccount = 'duplicate_account';
    case DelayedDelivery = 'delayed_delivery';
    case Misrepresentation = 'misrepresentation';
    case Harassment = 'harassment';
    case MisleadingPosting = 'misleading_posting';
    case Spam = 'spam';
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
            self::MisleadingPosting => 'Misleading or fake posting',
            self::Spam => 'Spam or repeated posting',
            self::Other => 'Other',
        };
    }

    /**
     * Determine if the category makes sense against a posting.
     *
     * Reporting a job is not the same complaint as reporting a person, and a
     * select offering "duplicate account" under a posting reads as a bug.
     */
    public function appliesToPosting(): bool
    {
        return match ($this) {
            self::DuplicateAccount, self::Harassment => false,
            default => true,
        };
    }

    /**
     * The categories as option rows for a select.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return self::rows(self::cases());
    }

    /**
     * The subset that can be levelled at a posting.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function postingOptions(): array
    {
        return self::rows(array_filter(
            self::cases(),
            fn (self $category): bool => $category->appliesToPosting(),
        ));
    }

    /**
     * Shape the given categories as option rows.
     *
     * @param  array<int, self>  $categories
     * @return array<array{value: string, label: string}>
     */
    private static function rows(array $categories): array
    {
        return array_values(array_map(
            fn (self $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            $categories,
        ));
    }
}
