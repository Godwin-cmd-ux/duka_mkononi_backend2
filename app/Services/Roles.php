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

    /**
     * Roles that own/administer a business (its msimamizi). A platform admin
     * (super_admin, or the legacy system_admin spelling) who was promoted from
     * a business admin still belongs to that business, so these count as the
     * business's owners. Keeping them here means changing a user's role never
     * makes their business look owner-less to the sellers still in it.
     */
    public const BUSINESS_OWNERS = ['admin', 'super_admin', 'system_admin'];

    /**
     * Every role that can belong to a business and hold its data (products,
     * sales, expenses, ...): the owners plus the sellers. Business data is
     * scoped by membership (business_id / business_name), never by role, so a
     * promoted owner's rows stay visible to their fellow members.
     */
    public const BUSINESS_MEMBERS = ['admin', 'super_admin', 'system_admin', 'seller'];

    public static function isSuperAdmin(?string $role): bool
    {
        return in_array((string) $role, self::SUPER, true);
    }

    public static function isAdmin(?string $role): bool
    {
        return in_array((string) $role, self::STAFF, true);
    }

    /** Does this role own/administer a business? */
    public static function isBusinessOwner(?string $role): bool
    {
        return in_array((string) $role, self::BUSINESS_OWNERS, true);
    }

    /**
     * Expand a caller's requested member-roles so a request for 'admin' also
     * pulls in the platform-admin spellings. Callers ask for 'admin' to mean
     * "this business's admins"; a promoted owner (super_admin / system_admin)
     * must stay in that set, so a role change never drops their rows out of the
     * business's scope.
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    public static function expandMemberRoles(array $roles): array
    {
        $expanded = [];

        foreach ($roles as $role) {
            $expanded[] = $role;

            if ($role === 'admin') {
                foreach (self::BUSINESS_OWNERS as $owner) {
                    $expanded[] = $owner;
                }
            }
        }

        return array_values(array_unique($expanded));
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
