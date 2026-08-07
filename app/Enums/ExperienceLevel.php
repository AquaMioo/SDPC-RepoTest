<?php

namespace App\Enums;

enum ExperienceLevel: string
{
    case Any = 'any';
    case Shipped = 'shipped';
    case Documented = 'documented';

    /**
     * Get the display label for the experience level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any — open to first projects',
            self::Shipped => 'Has shipped at least one system',
            self::Documented => 'Has documented client work',
        };
    }

    /**
     * Get the selectable experience levels for the posting form.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $level) => ['value' => $level->value, 'label' => $level->label()])
            ->values()
            ->toArray();
    }
}
