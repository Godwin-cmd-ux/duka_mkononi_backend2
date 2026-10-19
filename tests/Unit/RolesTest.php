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
}
