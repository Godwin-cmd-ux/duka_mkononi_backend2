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

// Cached role → the exact screen the user should land on.
//   admin    → msimamizi dashboard (index)
//   seller   → muuzaji profile
//   customer → mteja businesses
export const getDashboardRouteForRole = (userRole: string | null | undefined): string => {
  switch (userRole) {
    case 'admin':
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
