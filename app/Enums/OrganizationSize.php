<?php

namespace App\Enums;

/**
 * How many people are in a client's organisation.
 *
 * Bands rather than a number, because the exact headcount is nobody's business
 * and changes weekly. What a student actually wants to know from this is who
 * they will be dealing with: "just me" means the owner reads every message,
 * "500+" means a procurement process.
 *
 * The bottom band matters most here. A great many clients on this platform are
 * one person with a shop, and a form that starts at "2–9" tells them they are
 * the wrong kind of business.
 */
enum OrganizationSize: string
{
    case JustMe = 'just_me';
    case Small = '2_9';
    case Medium = '10_99';
    case Large = '100_499';
    case Enterprise = '500_plus';

    /**
     * Get the display label for the band.
     */
    public function label(): string
    {
        return match ($this) {
            self::JustMe => __('Just me'),
            self::Small => '2 – 9',
            self::Medium => '10 – 99',
            self::Large => '100 – 499',
            self::Enterprise => '500+',
        };
    }

    /**
     * Get every band as a value/label pair, for the picker.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $size): array => [
                'value' => $size->value,
                'label' => $size->label(),
            ],
            self::cases(),
        );
    }
}
