// Tells the root logout safety net where to send the user after signOut().
// Without this the safety net always falls back to the public home tabs, which
// is wrong for a super admin who should land on the admin login screen.
let pendingTarget: string | null = null;

export function setLogoutTarget(route: string): void {
  pendingTarget = route;
}

export function consumeLogoutTarget(): string | null {
  const target = pendingTarget;
  pendingTarget = null;
  return target;
}
