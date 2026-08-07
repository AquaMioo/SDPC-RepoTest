<?php

namespace App\Enums;

enum ProjectVisibility: string
{
    case AllStudents = 'all_students';
    case PreferredSchool = 'preferred_school';
    case InviteOnly = 'invite_only';

    /**
     * Get the display label for the visibility setting.
     */
    public function label(): string
    {
        return match ($this) {
            self::AllStudents => 'All verified students',
            self::PreferredSchool => 'Students from my preferred school only',
            self::InviteOnly => "Invite only — I'll pick from recommendations",
        };
    }

    /**
     * Get the selectable visibility options for the posting form.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $visibility) => ['value' => $visibility->value, 'label' => $visibility->label()])
            ->values()
            ->toArray();
    }
}
