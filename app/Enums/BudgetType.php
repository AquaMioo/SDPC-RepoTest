<?php

namespace App\Enums;

enum BudgetType: string
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';

    /**
     * Get the display label for the budget type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed price',
            self::Hourly => 'Hourly',
        };
    }

    /**
     * Get the selectable budget types for the posting form.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()])
            ->values()
            ->toArray();
    }
}
