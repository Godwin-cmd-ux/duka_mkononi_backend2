<?php

namespace Tests\Unit;

use App\Services\Roles;
use Tests\TestCase;

/**
 * Role semantics for the platform super admin.
 *
 * What is pinned here:
 *  - super_admin is the canonical top role and the legacy system_admin
 *    spelling keeps working (pre-existing rows must not lose access);
 *  - admin-level gates accept super_admin (a promoted super admin keeps
 *    business-admin capabilities);
 *  - self-service signup can NEVER produce a platform role: super_admin is
 *    granted only by a technician updating the database directly.
 */
class RolesTest extends TestCase
{
    public function test_super_admin_is_canonical_and_system_admin_still_works(): void
    {
        $this->assertTrue(Roles::isSuperAdmin('super_admin'));
        $this->assertTrue(Roles::isSuperAdmin('system_admin'), 'Legacy spelling must keep working.');

        $this->assertFalse(Roles::isSuperAdmin('admin'));
        $this->assertFalse(Roles::isSuperAdmin('seller'));
        $this->assertFalse(Roles::isSuperAdmin('ADMIN'), 'Role matching is exact, never case-insensitive.');
        $this->assertFalse(Roles::isSuperAdmin(null));
    }

    public function test_admin_gates_accept_super_admin(): void
    {
        foreach (['admin', 'super_admin', 'system_admin'] as $role) {
            $this->assertTrue(Roles::isAdmin($role), "[{$role}] must pass admin-level gates.");
        }

        $this->assertFalse(Roles::isAdmin('seller'));
        $this->assertFalse(Roles::isAdmin('customer'));
        $this->assertFalse(Roles::isAdmin('msimamizi'), 'Frontend-only labels are not roles.');
        $this->assertFalse(Roles::isAdmin(null));
    }

    public function test_super_admin_can_log_in_through_the_msimamizi_portal(): void
    {
        // The msimamizi portal requests role 'admin'; a promoted super admin
        // must satisfy it (both spellings), with an exact match otherwise.
        $this->assertTrue(Roles::loginSatisfies('super_admin', 'admin'));
        $this->assertTrue(Roles::loginSatisfies('system_admin', 'admin'), 'Legacy spelling must keep signing in.');
        $this->assertTrue(Roles::loginSatisfies('admin', 'admin'));
        $this->assertTrue(Roles::loginSatisfies('seller', 'seller'));
        $this->assertTrue(Roles::loginSatisfies('customer', 'customer'));

        $this->assertFalse(Roles::loginSatisfies('super_admin', 'seller'), 'A super admin never satisfies a seller login.');
        $this->assertFalse(Roles::loginSatisfies('super_admin', 'customer'));
        $this->assertFalse(Roles::loginSatisfies('seller', 'admin'));
        $this->assertFalse(Roles::loginSatisfies('customer', 'admin'));
        $this->assertFalse(Roles::loginSatisfies(null, 'admin'));
    }

    public function test_super_admin_is_never_self_registerable(): void
    {
        foreach (Roles::REGISTERABLE as $role) {
            $this->assertTrue(Roles::isRegisterable($role), "[{$role}] is a signup role.");
        }

        $this->assertFalse(Roles::isRegisterable('super_admin'));
        $this->assertFalse(Roles::isRegisterable('system_admin'));
        $this->assertFalse(Roles::isRegisterable('root'));
        $this->assertFalse(Roles::isRegisterable(''));
        $this->assertFalse(Roles::isRegisterable(null));
    }

    /**
     * A business's owner keeps owning it after being promoted to a platform
     * admin. Every seller-facing scope asks for the owner/admin set, so it must
     * treat admin, super_admin and system_admin as the same owner - otherwise
     * changing X's role to super_admin orphans business Z and blanks the
     * seller dashboard for the team still in it.
     */
    public function test_business_owners_include_every_admin_spelling(): void
    {
        foreach (['admin', 'super_admin', 'system_admin'] as $role) {
            $this->assertTrue(Roles::isBusinessOwner($role), "[{$role}] must still own the business.");
            $this->assertContains($role, Roles::BUSINESS_OWNERS);
        }

        $this->assertFalse(Roles::isBusinessOwner('seller'));
        $this->assertFalse(Roles::isBusinessOwner('customer'));
        $this->assertFalse(Roles::isBusinessOwner(null));
    }

    /**
     * The member scope a seller's shared views use: owners + sellers. A promoted
     * owner (super_admin / system_admin) must remain a member so their products
     * and sales never vanish from the business the seller still works in.
     */
    public function test_business_members_keep_a_promoted_owner_in_scope(): void
    {
        foreach (['admin', 'super_admin', 'system_admin', 'seller'] as $role) {
            $this->assertContains($role, Roles::BUSINESS_MEMBERS, "[{$role}] must remain a business member.");
        }

        $this->assertNotContains('customer', Roles::BUSINESS_MEMBERS);
    }

    /**
     * Callers ask memberIds() for 'admin' to mean "this business's admins". The
     * expansion folds in the platform-admin spellings so a role change never
     * drops the owner's rows out of the shared scope. Non-admin requests are
     * left untouched (no widening for 'seller'), and duplicates collapse.
     */
    public function test_expand_member_roles_widens_admin_but_not_other_roles(): void
    {
        $expanded = Roles::expandMemberRoles(['admin', 'seller']);

        foreach (['admin', 'super_admin', 'system_admin', 'seller'] as $role) {
            $this->assertContains($role, $expanded, "[{$role}] must survive the expansion.");
        }

        $this->assertSame(['seller'], Roles::expandMemberRoles(['seller']), 'A seller-only scope must not be widened.');
        $this->assertSame([], Roles::expandMemberRoles([]), 'An empty scope stays empty.');
        $this->assertSame(
            ['admin', 'super_admin', 'system_admin'],
            Roles::expandMemberRoles(['admin']),
            'Expansion must be stable and free of duplicates.'
        );
    }
}
