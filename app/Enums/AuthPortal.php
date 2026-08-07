<?php

namespace App\Enums;

use Illuminate\Http\Request;

enum AuthPortal: string
{
    case Admin = 'admin';
    case Public = 'public';

    /**
     * Resolve the portal the given request is authenticating through.
     *
     * Every admin route is registered under the "admin." route name prefix,
     * so the route name is the single source of truth for the portal.
     */
    public static function fromRequest(Request $request): self
    {
        return $request->routeIs('admin.*') ? self::Admin : self::Public;
    }

    /**
     * Determine if the given role may authenticate through this portal.
     */
    public function allows(UserRole $role): bool
    {
        return match ($this) {
            self::Admin => $role->canUseAdminPortal(),
            self::Public => $role->canUsePublicPortal(),
        };
    }

    /**
     * Get the message shown when a role may not authenticate through this portal.
     */
    public function rejectionMessage(): string
    {
        return match ($this) {
            self::Admin => __('This account is not authorized to use the Admin Portal.'),
            self::Public => __('Please login using the Admin Portal.'),
        };
    }

    /**
     * Get the name of the route displaying this portal's login screen.
     */
    public function loginRouteName(): string
    {
        return match ($this) {
            self::Admin => 'admin.login',
            self::Public => 'login',
        };
    }
}
