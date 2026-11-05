import SystemAdminDashboard from './dashboard';

/** Platform users page (mobile twin of /system_admin/users). */
export default function SystemAdminUsersScreen() {
  return <SystemAdminDashboard initialTab="users" />;
}
