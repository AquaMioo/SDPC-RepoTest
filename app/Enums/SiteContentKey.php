<?php

namespace App\Enums;

/**
 * The blocks of copy an administrator maintains on the content screen.
 *
 * A fixed set rather than free-form keys: the screen edits exactly these
 * three, and anything else would have nowhere to be displayed.
 */
enum SiteContentKey: string
{
    case Announcements = 'announcements';
    case Rules = 'rules';
    case Policies = 'policies';

    /**
     * Get every key value, e.g. for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Announcements => __('Announcements'),
            self::Rules => __('Platform rules'),
            self::Policies => __('System policies'),
        };
    }
}
