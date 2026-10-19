<?php

namespace App\Services;

/**
 * Single source of truth for role semantics.
 *
 * The platform's top account used to be recognised by a hardcoded email
 * address in several frontends. The canonical model is now a database role:
 *
 *   super_admin  - promoted manually by the technician in Supabase; never
 *                  selectable at signup and never granted by the API
 *   system_admin - legacy spelling of the same role, still honoured so any
 *                  pre-existing row keeps working
 *   admin        - business admin (msimamizi)
 *   seller       - self-service signup
 *   customer     - self-service signup ('client' is the older spelling)
 */
final class Roles
{
    /** The only roles a client may ever self-register with. */
    public const REGISTERABLE = ['customer', 'client', 'seller', 'admin'];

    /** Roles that hold the platform's top authority. */
    private const SUPER = ['super_admin', 'system_admin'];

    /** Roles that may use business-admin and platform staff endpoints. */
    private const STAFF = ['admin', 'super_admin', 'system_admin'];

    public static function isSuperAdmin(?string $role): bool
    {
        return in_array((string) $role, self::SUPER, true);
    }

    public static function isAdmin(?string $role): bool
    {
        return in_array((string) $role, self::STAFF, true);
    }

    /** Self-service signup allow-list (super_admin is never registerable). */
    public static function isRegisterable(?string $role): bool
    {
        return in_array((string) $role, self::REGISTERABLE, true);
    }

    /**
     * Does an account with $actualRole satisfy a login request for
     * $requestedRole?
     *
     * The msimamizi portal logs in as 'admin', but a promoted platform super
     * admin (super_admin, or the legacy system_admin spelling) owns that
     * portal and must keep signing in through it; every other role continues
     * to require an exact match.
     */
    public static function loginSatisfies(?string $actualRole, string $requestedRole): bool
    {
        if ((string) $actualRole === $requestedRole) {
            return true;
        }

        return $requestedRole === 'admin' && self::isSuperAdmin($actualRole);
    }
}
