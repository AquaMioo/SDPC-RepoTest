<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Student = 'student';
    case Admin = 'admin';

    /**
     * Get the role assigned to every self-registered account.
     *
     * Only a fallback for the column default: the sign up form makes people
     * choose, and App\Actions\Fortify\CreateNewUser assigns that choice
     * explicitly rather than relying on this.
     */
    public static function default(): self
    {
        return self::Student;
    }

    /**
     * Get every role value, e.g. for validation rules or database defaults.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the roles a user may choose for themselves at registration.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function selfAssignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role->isSelfRegisterable())
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Client => __('Client'),
            self::Student => __('Student'),
            self::Admin => __('Administrator'),
        };
    }

    /**
     * Determine if the role may authenticate through the admin portal.
     */
    public function canUseAdminPortal(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Determine if the role may authenticate through the public (student / client) portal.
     */
    public function canUsePublicPortal(): bool
    {
        return ! $this->canUseAdminPortal();
    }

    /**
     * Determine if an account with this role may be created by the user themselves.
     */
    public function isSelfRegisterable(): bool
    {
        return $this->canUsePublicPortal();
    }
}
