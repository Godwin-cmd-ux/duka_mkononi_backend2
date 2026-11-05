import SystemAdminDashboard from './dashboard';

/** Platform logs page (mobile twin of /system_admin/logs). */
export default function SystemAdminLogsScreen() {
  return <SystemAdminDashboard initialTab="logs" />;
}
