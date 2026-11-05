// 🔐 Shared session helpers — single source of truth for the cached login session.
//
// After a successful login, app/login.tsx caches `isLoggedIn = 'true'` plus the
// user's token and role in AsyncStorage. On every app launch the session gate
// (app/index.tsx) reads these to send the user straight to their dashboard, so
// they never have to sign in again unless they intentionally press Logout.

// All auth keys to wipe when a user logs out (or the session is invalid).
export const SESSION_KEYS = [
  'userToken',
  'userData',
  'userId',
  'userEmail',
  'userRole',
  'userStatus',
  'userName',
  'userLanguage',
  'userBusiness',
  'isLoggedIn',
] as const;

// Platform super admin check — 'super_admin' is the canonical role (promoted by
// the technician in the database) and 'system_admin' is the legacy spelling of
// the same role. Mirrors Roles::isSuperAdmin() on the backend and the guard used
// by the web system-admin views, so a promoted super admin is never denied.
export const isSystemAdminRole = (userRole: string | null | undefined): boolean =>
  userRole === 'super_admin' || userRole === 'system_admin';

// Cached role → the exact screen the user should land on.
//   admin    → msimamizi dashboard (index)
//   seller   → muuzaji profile
//   customer → mteja businesses
export const getDashboardRouteForRole = (userRole: string | null | undefined): string => {
  switch (userRole) {
    case 'admin':
      return '/msimamizi';
    // Platform super admin (promoted by the technician in the database;
    // 'system_admin' is the legacy spelling of the same role) lands on the
    // msimamizi dashboard, where the System Management button appears.
    case 'super_admin':
    case 'system_admin':
      return '/msimamizi';
    case 'seller':
      return '/muuzaji/profaili';
    case 'customer':
      return '/mteja/biashara';
    default:
      // Unknown / missing role — show the home + login screens
      return '/(tabs)';
  }
};
