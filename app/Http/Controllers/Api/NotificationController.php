<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use App\Models\User;
use App\Services\Supabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class NotificationController extends BaseController
{
    private function jsIso(): string
    {
        return gmdate('Y-m-d\TH:i:s.v\Z');
    }

    private function todayUtc(): string
    {
        return gmdate('Y-m-d');
    }

    private function requestIp(Request $request): ?string
    {
        $ip = $this->ip($request);
        if ($ip) {
            return $ip;
        }
        return $request->header('x-forwarded-for') ?: $request->ip();
    }

    private function sendMail(string $to, string $subject, string $html): array
    {
        try {
            $messageId = null;
            Event::listen(MessageSent::class, function (MessageSent $event) use (&$messageId) {
                $messageId = $event->sent->getMessageId();
            });

            Mail::html($html, [], function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });

            return ['success' => true, 'messageId' => $messageId ?: 'unknown'];
        } catch (\Throwable $e) {
            try {
                Supabase::table('pending_emails')->insert([
                    'to' => $to,
                    'subject' => $subject,
                    'content' => $html,
                    'status' => 'pending',
                    'error' => $e->getMessage(),
                    'created_at' => $this->isoNow(),
                ]);
            } catch (\Throwable $dbErr) {
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function setup(Request $request)
    {
        try {
            $ip = $this->requestIp($request);

            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS notifications (
                    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    notification_type VARCHAR(20) NOT NULL CHECK (notification_type IN ('all', 'admins', 'sellers', 'clients', 'specific')),
                    recipients JSONB NOT NULL,
                    sender_id UUID REFERENCES users(id),
                    sender_name VARCHAR(255),
                    sent_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
                    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
                    delivery_method VARCHAR(50) DEFAULT 'email' CHECK (delivery_method IN ('email', 'push', 'both')),
                    email_sent BOOLEAN DEFAULT false,
                    push_sent BOOLEAN DEFAULT false,
                    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
                    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
                );

                CREATE INDEX IF NOT EXISTS idx_notifications_sender_id ON notifications(sender_id);
                CREATE INDEX IF NOT EXISTS idx_notifications_sent_at ON notifications(sent_at);
                CREATE INDEX IF NOT EXISTS idx_notifications_status ON notifications(status);
                CREATE INDEX IF NOT EXISTS idx_notifications_notification_type ON notifications(notification_type);
            ";

            try {
                Supabase::table('notifications')->select('id')->limit(1)->get();
            } catch (\Throwable $e) {
                return $this->json([
                    'error' => 'Notifications table does not exist in Supabase',
                    'manual_sql' => $createTableSQL,
                    'instructions' => 'Please run the SQL above in Supabase SQL Editor',
                ], 500);
            }

            $this->log(null, 'SETUP_NOTIFICATIONS', '/api/admin/setup/notifications', [
                'success' => true,
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'message' => 'Notifications system setup completed!',
                'table' => 'notifications',
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Failed to setup notifications system',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'GET_NOTIFICATIONS_UNAUTHORIZED', '/api/admin/notifications', ['reason' => 'Non-admin access attempt'], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $notifications = Notification::query()->orderByDesc('sent_at')->limit(100)->get()->toArray();

            $this->log($adminId, 'GET_NOTIFICATIONS', '/api/admin/notifications', [
                'count' => count($notifications),
            ], $this->ip($request), 'success');

            return $this->json(array_values($notifications));
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'GET_NOTIFICATIONS_ERROR', '/api/admin/notifications', [
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Hitilafu ya kupata taarifa',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $adminId = $this->userId($request);
            $translations = $request->input('translations');
            $notificationType = $request->input('notification_type');
            $recipientIds = $request->input('recipient_ids', []) ?: [];
            $deliveryMethod = $request->input('delivery_method', 'email');

            if ($this->role($request) !== 'admin') {
                $this->log($adminId, 'SEND_NOTIFICATION_UNAUTHORIZED', '/api/admin/notifications', [
                    'reason' => 'Non-admin access attempt',
                ], $this->ip($request), 'failed');
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $langCount = is_array($translations) ? count($translations) : 0;

            if (!is_array($translations) || $langCount < 6) {
                $this->log($adminId, 'SEND_NOTIFICATION_FAILED', '/api/admin/notifications', [
                    'reason' => 'Missing required fields',
                ], $this->ip($request), 'failed');
                return $this->json([
                    'error' => 'Title, message na aina ya taarifa zinahitajika',
                ], 400);
            }

            $title = null;
            $message = null;
            if (isset($translations['sw']) && is_array($translations['sw'])) {
                $title = $translations['sw']['title'] ?? null;
                $message = $translations['sw']['message'] ?? null;
            }
            if ($title === null) {
                foreach ($translations as $lang) {
                    if (is_array($lang) && isset($lang['title'])) {
                        $title = $lang['title'];
                        $message = $lang['message'] ?? '';
                        break;
                    }
                }
            }

            $sender = User::where('id', $adminId)->select('full_name', 'email')->first();

            if (!$sender) {
                throw new \Exception('Sender not found');
            }

            $recipients = [];
            $userRecipients = [];

            switch ($notificationType) {
                case 'all':
                    $allUsers = User::where('status', 'approved')->select('id', 'email', 'full_name', 'role')->get();
                    foreach ($allUsers as $u) {
                        $userRecipients[] = $u;
                        $recipients[] = ['id' => $u->id, 'email' => $u->email, 'name' => $u->full_name ?: $u->email];
                    }
                    break;

                case 'admins':
                    $admins = User::where('role', 'admin')->where('status', 'approved')->select('id', 'email', 'full_name', 'role')->get();
                    foreach ($admins as $u) {
                        $userRecipients[] = $u;
                        $recipients[] = ['id' => $u->id, 'email' => $u->email, 'name' => $u->full_name ?: $u->email];
                    }
                    break;

                case 'sellers':
                    $sellers = User::where('role', 'seller')->where('status', 'approved')->select('id', 'email', 'full_name', 'role')->get();
                    foreach ($sellers as $u) {
                        $userRecipients[] = $u;
                        $recipients[] = ['id' => $u->id, 'email' => $u->email, 'name' => $u->full_name ?: $u->email];
                    }
                    break;

                case 'clients':
                    $clients = User::where('role', 'client')->where('status', 'approved')->select('id', 'email', 'full_name', 'role')->get();
                    foreach ($clients as $u) {
                        $userRecipients[] = $u;
                        $recipients[] = ['id' => $u->id, 'email' => $u->email, 'name' => $u->full_name ?: $u->email];
                    }
                    break;

                case 'specific':
                    if (count($recipientIds) === 0) {
                        $this->log($adminId, 'SEND_NOTIFICATION_FAILED', '/api/admin/notifications', [
                            'reason' => 'No recipients specified for specific notification',
                        ], $this->ip($request), 'failed');
                        return $this->json(['error' => 'Recipient IDs required for specific notification'], 400);
                    }

                    $specificUsers = User::whereIn('id', $recipientIds)->where('status', 'approved')->select('id', 'email', 'full_name', 'role')->get();
                    foreach ($specificUsers as $u) {
                        $userRecipients[] = $u;
                        $recipients[] = ['id' => $u->id, 'email' => $u->email, 'name' => $u->full_name ?: $u->email];
                    }
                    break;
            }

            if (count($recipients) === 0) {
                $this->log($adminId, 'SEND_NOTIFICATION_FAILED', '/api/admin/notifications', [
                    'reason' => 'No recipients found',
                    'notification_type' => $notificationType,
                    'recipient_count' => 0,
                ], $this->ip($request), 'failed');
                return $this->json(['error' => 'Hakuna wapokeaji waliopatikana'], 404);
            }

            $notificationData = [
                'id' => Str::uuid()->toString(),
                'title' => $title,
                'message' => $message,
                'notification_type' => $notificationType,
                'recipients' => json_encode(array_map(fn($r) => ['id' => $r['id'], 'email' => $r['email'], 'name' => $r['name']], $recipients)),
                'sender_id' => $adminId,
                'sender_name' => $sender->full_name ?: $sender->email ?: 'System Admin',
                'delivery_method' => $deliveryMethod,
                'status' => 'pending',
                'created_at' => $this->isoNow(),
                'updated_at' => $this->isoNow(),
            ];

            $savedNotification = Notification::create($notificationData);

            $emailResults = ['success' => 0, 'failed' => 0];
            $pushResults = ['success' => 0, 'failed' => 0];

            if ($deliveryMethod === 'email' || $deliveryMethod === 'both') {
                foreach ($recipients as $recipient) {
                    try {
                        $emailSubject = $title;
                        $emailHtml = '
                            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                                <div style="background-color: #3498db; color: white; padding: 20px; border-radius: 10px 10px 0 0;">
                                    <h1 style="margin: 0;">' . $title . '</h1>
                                </div>
                                <div style="padding: 30px; background-color: #f8f9fa; border-radius: 0 0 10px 10px;">
                                    <p style="color: #2c3e50; font-size: 16px; line-height: 1.6;">
                                        ' . str_replace("\n", '<br>', $message) . '
                                    </p>
                                    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;">
                                    <p style="color: #7f8c8d; font-size: 14px;">
                                        <strong>Imetumwa na:</strong> ' . ($sender->full_name ?: 'System Admin') . '<br>
                                        <strong>Muda:</strong> ' . gmdate('d/m/Y, H:i:s') . '<br>
                                        <strong>Mfumo:</strong> DukaMkononi
                                    </p>
                                    <div style="margin-top: 20px; padding: 15px; background-color: #e8f4fc; border-radius: 8px; border-left: 4px solid #3498db;">
                                        <p style="margin: 0; color: #2c3e50; font-size: 14px;">
                                            <strong>ðŸ”” Kumbuka:</strong> Hii ni taarifa rasmi kutoka kwa msimamizi wa mfumo wa DukaMkononi.
                                            Ikiwa hukutarajia taarifa hii, tafadhali wasiliana na msimamizi.
                                        </p>
                                    </div>
                                </div>
                                <div style="text-align: center; padding: 20px; color: #95a5a6; font-size: 12px; border-top: 1px solid #e0e0e0;">
                                    <p>Â© ' . gmdate('Y') . ' DukaMkononi. Haki zote zimehifadhiwa.</p>
                                    <p>Hii ni barua pepe ya kiotomatiki. Tafadhali usijibu.</p>
                                </div>
                            </div>
                        ';

                        $emailResult = $this->sendMail($recipient['email'], $emailSubject, $emailHtml);

                        if ($emailResult['success']) {
                            $emailResults['success']++;
                        } else {
                            $emailResults['failed']++;
                        }
                    } catch (\Throwable $emailError) {
                        $emailResults['failed']++;
                    }
                }

                Notification::where('id', $savedNotification->id)->update([
                    'email_sent' => $emailResults['success'] > 0,
                    'updated_at' => $this->isoNow(),
                ]);
            }

            $finalStatus = $emailResults['failed'] === count($recipients) ? 'failed' : 'sent';

            Notification::where('id', $savedNotification->id)->update([
                'status' => $finalStatus,
                'updated_at' => $this->isoNow(),
            ]);

            $this->log($adminId, 'NOTIFICATION_SENT', '/api/admin/notifications', [
                'notification_id' => $savedNotification->id,
                'title' => $title,
                'notification_type' => $notificationType,
                'recipient_count' => count($recipients),
                'email_success' => $emailResults['success'],
                'email_failed' => $emailResults['failed'],
                'final_status' => $finalStatus,
            ], $this->ip($request), 'success');

            return $this->json([
                'success' => true,
                'message' => 'Taarifa imetumwa kikamilifu kwa watumiaji ' . count($recipients) . '!',
                'notification' => [
                    'id' => $savedNotification->id,
                    'title' => $title,
                    'message' => $message,
                    'notification_type' => $notificationType,
                    'recipients' => count($recipients),
                    'sent_at' => $this->jsIso(),
                    'email_results' => $emailResults,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'NOTIFICATION_ERROR', '/api/admin/notifications', [
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Hitilafu katika kutuma taarifa',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function stats(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $today = $this->todayUtc();

            $total = Notification::query()->count();

            $todayNotifications = Notification::where('sent_at', '>=', $today)->get()->toArray();

            $allNotifications = Notification::query()->select('notification_type', 'status', 'recipients')->get()->toArray();

            $sentCount = count(array_filter($allNotifications, fn($n) => ($n['status'] ?? null) === 'sent'));
            $allCount = count($allNotifications);

            $totalRecipients = 0;
            foreach ($allNotifications as $n) {
                $recipients = json_decode($n['recipients'] ?? '', true);
                $totalRecipients += is_array($recipients) ? count($recipients) : 0;
            }

            $stats = [
                'total_sent' => $total ?: 0,
                'sent_today' => count($todayNotifications) ?: 0,
                'by_type' => [
                    'all' => count(array_filter($allNotifications, fn($n) => ($n['notification_type'] ?? null) === 'all')),
                    'admins' => count(array_filter($allNotifications, fn($n) => ($n['notification_type'] ?? null) === 'admins')),
                    'sellers' => count(array_filter($allNotifications, fn($n) => ($n['notification_type'] ?? null) === 'sellers')),
                    'clients' => count(array_filter($allNotifications, fn($n) => ($n['notification_type'] ?? null) === 'clients')),
                    'specific' => count(array_filter($allNotifications, fn($n) => ($n['notification_type'] ?? null) === 'specific')),
                ],
                'success_rate' => $allCount ? (int)round(($sentCount / $allCount) * 100) : 100,
                'total_recipients' => $totalRecipients ?: 0,
                'timestamp' => $this->jsIso(),
            ];

            $this->log($adminId, 'NOTIFICATION_STATS', '/api/admin/notifications/stats', $stats, $this->ip($request), 'success');

            return $this->json($stats);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'NOTIFICATION_STATS_ERROR', '/api/admin/notifications/stats', [
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Hitilafu ya kupata takwimu za taarifa',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Per-admin read-state stored in localStorage on the frontend; the
     * backend keeps a single authoritative "last read timestamp" per admin
     * so "Zimesomwa" (mark all read) survives reloads and other devices.
     */
    public function readState(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $lastRead = null;
            try {
                $row = Supabase::table('admin_notification_read_state')
                    ->where('admin_id', $adminId)
                    ->select('last_read_at')
                    ->first();
                $lastRead = $row ? $row->last_read_at : null;
            } catch (\Throwable $e) {
                // Table missing — read-state simply stays client-side only.
                $lastRead = null;
            }

            return $this->json(['success' => true, 'last_read_at' => $lastRead]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * "Zimesomwa": stamp the current time as this admin's last-read so every
     * notification sent before now counts as read.
     */
    public function markAllRead(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $now = $this->isoNow();

            try {
                $existing = Supabase::table('admin_notification_read_state')
                    ->where('admin_id', $adminId)
                    ->select('admin_id')
                    ->first();

                if ($existing) {
                    Supabase::table('admin_notification_read_state')
                        ->where('admin_id', $adminId)
                        ->update(['last_read_at' => $now]);
                } else {
                    Supabase::table('admin_notification_read_state')->insert([
                        'admin_id' => $adminId,
                        'last_read_at' => $now,
                        'created_at' => $now,
                    ]);
                }
            } catch (\Throwable $e) {
                // Table doesn't exist yet — respond so the UI can still clear
                // its badges; a one-time setup run creates the table.
                return $this->json([
                    'success' => false,
                    'persisted' => false,
                    'error' => 'Read-state table missing. Run /api/admin/setup/notifications-read-state once to create it.',
                    'details' => $e->getMessage(),
                ], 200);
            }

            $this->log($adminId, 'NOTIFICATIONS_MARK_ALL_READ', '/api/admin/notifications/mark-all-read', [], $this->ip($request), 'success');

            return $this->json(['success' => true, 'persisted' => true, 'last_read_at' => $now]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * One-time helper that creates the admin_notification_read_state table
     * (mirrors setup(): probe first, return manual SQL when missing).
     */
    public function setupReadState(Request $request)
    {
        try {
            Supabase::table('admin_notification_read_state')->select('admin_id')->limit(1)->get();

            return $this->json(['success' => true, 'message' => 'admin_notification_read_state table is ready']);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'admin_notification_read_state table does not exist in Supabase',
                'manual_sql' => "CREATE TABLE IF NOT EXISTS admin_notification_read_state (\n                    admin_id UUID PRIMARY KEY REFERENCES users(id),\n                    last_read_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),\n                    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()\n                );",
                'instructions' => 'Run the SQL above in the Supabase SQL Editor (or re-run this endpoint after creating it).',
            ], 500);
        }
    }

    public function check(Request $request)
    {
        try {
            $ip = $this->requestIp($request);

            try {
                Supabase::table('notifications')->select('id')->limit(1)->get();
            } catch (\Throwable $e) {
                return $this->json([
                    'exists' => false,
                    'message' => 'Notifications table does not exist',
                    'error' => $e->getMessage(),
                ], 200);
            }

            $this->log(null, 'CHECK_NOTIFICATIONS', '/api/admin/notifications/check', [
                'exists' => true,
                'ready' => true,
            ], $ip, 'success');

            return $this->json([
                'exists' => true,
                'ready' => true,
                'message' => 'Notifications system is ready',
            ]);
        } catch (\Throwable $e) {
            $this->log(null, 'CHECK_NOTIFICATIONS_ERROR', '/api/admin/notifications/check', [
                'error' => $e->getMessage(),
            ], $this->requestIp($request), 'failed');

            return $this->json([
                'error' => 'Failed to check notifications system',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function test(Request $request)
    {
        try {
            $adminId = $this->userId($request);

            if ($this->role($request) !== 'admin') {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            $email = $request->input('email');

            if (!$email) {
                return $this->json(['error' => 'Email is required for test'], 400);
            }

            $currentUser = $this->user($request);

            $testSubject = 'âœ… Test Notification - DukaMkononi System';
            $testMessage = '
                Hii ni taarifa ya majaribio kutoka kwenye mfumo wa DukaMkononi.

                Ikiwa unapokea barua pepe hii, inamaanisha mfumo wa kutuma taarifa unafanya kazi kikamilifu!

                Mambo yanayojaribiwa:
                1. âœ… Usafiri wa barua pepe
                2. âœ… Ujumbe wa HTML
                3. âœ… Uunganisho na mfumo
                4. âœ… Kasi ya kutuma

                Tarehe: ' . gmdate('d/m/Y, H:i:s') . '
                Mfumo: DukaMkononi Backend v2.0
                Admin: ' . ($currentUser->email ?? '') . '
            ';

            $emailHtml = '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px 15px 0 0; text-align: center;">
                        <h1 style="margin: 0; font-size: 28px;">âœ… TEST SUCCESSFUL</h1>
                        <p style="margin: 10px 0 0; opacity: 0.9;">DukaMkononi Notification System</p>
                    </div>
                    <div style="padding: 40px; background-color: #f8f9fa; border-radius: 0 0 15px 15px;">
                        <div style="background-color: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                            <p style="color: #2c3e50; font-size: 16px; line-height: 1.6;">
                                ' . str_replace("\n", '<br>', $testMessage) . '
                            </p>
                        </div>

                        <div style="margin-top: 30px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div style="background-color: #e8f6ef; padding: 15px; border-radius: 8px; border-left: 4px solid #27ae60;">
                                <div style="font-weight: bold; color: #27ae60; margin-bottom: 5px;">âœ“ Email Delivery</div>
                                <div style="font-size: 12px; color: #666;">Working perfectly</div>
                            </div>
                            <div style="background-color: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196f3;">
                                <div style="font-weight: bold; color: #2196f3; margin-bottom: 5px;">âœ“ System Integration</div>
                                <div style="font-size: 12px; color: #666;">Backend connected</div>
                            </div>
                        </div>

                        <div style="margin-top: 30px; padding: 20px; background-color: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                            <p style="margin: 0; color: #856404; font-size: 14px;">
                                <strong>ðŸ’¡ Kumbuka:</strong> Hii ni barua pepe ya majaribio tu.
                                Ikiwa umepokea hii, mfumo wako wa taarifa unafanya kazi vizuri!
                            </p>
                        </div>
                    </div>
                    <div style="text-align: center; padding: 25px; color: #95a5a6; font-size: 12px; border-top: 1px solid #e0e0e0; background-color: white;">
                        <p>Â© ' . gmdate('Y') . ' DukaMkononi - Test Email System</p>
                        <p style="font-size: 11px; margin-top: 5px;">Timestamp: ' . $this->jsIso() . '</p>
                    </div>
                </div>
            ';

            $emailResult = $this->sendMail($email, $testSubject, $emailHtml);

            if ($emailResult['success']) {
                $this->log($adminId, 'TEST_NOTIFICATION_SENT', '/api/admin/notifications/test', [
                    'recipient' => $email,
                    'success' => true,
                    'message_id' => $emailResult['messageId'],
                ], $this->ip($request), 'success');

                return $this->json([
                    'success' => true,
                    'message' => 'Test email sent successfully!',
                    'email' => $email,
                    'messageId' => $emailResult['messageId'],
                    'timestamp' => $this->jsIso(),
                ]);
            }

            $this->log($adminId, 'TEST_NOTIFICATION_FAILED', '/api/admin/notifications/test', [
                'recipient' => $email,
                'success' => false,
                'error' => $emailResult['error'],
            ], $this->ip($request), 'failed');

            return $this->json([
                'success' => false,
                'error' => 'Failed to send test email',
                'details' => $emailResult['error'],
                'email' => $email,
            ], 500);
        } catch (\Throwable $e) {
            $this->log($this->userId($request), 'TEST_NOTIFICATION_ERROR', '/api/admin/notifications/test', [
                'error' => $e->getMessage(),
            ], $this->ip($request), 'failed');

            return $this->json([
                'error' => 'Hitilafu katika kutuma barua pepe ya majaribio',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}