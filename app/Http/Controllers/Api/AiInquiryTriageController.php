<?php

namespace App\Http\Controllers\Api;

use App\Models\Inquiry;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\InquiryTriage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Capability #18 - Inquiry Triage & Auto-Draft Replies.
 *
 * GET  /api/ai/inquiries/queue        - the triage queue: how many inquiries
 *                                       sit in each topic / urgency / team.
 * POST /api/ai/inquiries/{id}/triage  - classify one inquiry, decide urgency
 *                                       and routing, flag human support and
 *                                       COMPOSE a reply draft.
 * POST /api/ai/inquiries/{id}/reply   - send an APPROVED reply through the
 *                                       same mail path the notification
 *                                       system uses.
 *
 * Guarantees:
 *  - Super Admin only (the same `system_admin`/`admin` trust boundary the
 *    existing inquiry endpoints use);
 *  - classification, urgency and routing are computed in InquiryTriage from
 *    the inquiry text alone - the model never picks a route or a severity;
 *  - the draft is COMPOSED from the approved help content plus a fixed frame,
 *    so no internal record and no other customer's data can reach a customer.
 *    The optional model step may at most choose allow-listed topics and write
 *    one gated acknowledgement sentence;
 *  - sending REQUIRES an explicit `approved: true` from the human and a body.
 *    Only when an operator explicitly enables `ai.inquiry_triage.auto_send` may
 *    a general, low-urgency, human-free inquiry be answered automatically (and
 *    the response says that is what happened);
 *  - classification, routing, approval and delivery are recorded in the audit
 *    trail (`AI_INQUIRY_TRIAGE`, `AI_INQUIRY_REPLY_SENT`/`_FAILED`) and, when
 *    the additive migration is applied, on the inquiry row itself;
 *  - a generated reply is NEVER presented as a resolved issue.
 */
class AiInquiryTriageController extends BaseController
{
    private const NARRATION_CACHE_PREFIX = 'ai.inquiry_triage.narration.';

    /* ------------------------------------------------------------------ */
    /* Queue                                                               */
    /* ------------------------------------------------------------------ */

    public function queue(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.inquiries', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $limit = max(1, min(500, (int) config('ai.inquiry_triage.queue_limit', 100)));

        try {
            $open = ['new', 'reviewed'];
            $rows = Inquiry::whereIn('status', $open)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->toArray();

            $items = [];
            $counts = [
                'category' => array_fill_keys(InquiryTriage::CATEGORIES, 0),
                'urgency' => array_fill_keys(InquiryTriage::URGENCIES, 0),
                'route' => array_fill_keys(InquiryTriage::ROUTES, 0),
                'needs_human' => 0,
                'has_approved_help' => 0,
                'total' => 0,
            ];

            foreach ($rows as $row) {
                $triage = InquiryTriage::triage([
                    'locale' => $locale,
                    'subject' => (string) ($row['subject'] ?? ''),
                    'message' => (string) ($row['message'] ?? ''),
                    'account_found' => null,
                ]);

                $counts['total']++;
                $counts['category'][$triage['category']]++;
                $counts['urgency'][$triage['urgency']]++;
                $counts['route'][$triage['route']]++;
                if ($triage['needs_human']) {
                    $counts['needs_human']++;
                }
                if ($triage['topics'] !== []) {
                    $counts['has_approved_help']++;
                }

                $items[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'subject' => (string) ($row['subject'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'locale' => (string) ($row['locale'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'category' => $triage['category'],
                    'category_label' => $triage['category_label'],
                    'urgency' => $triage['urgency'],
                    'urgency_label' => $triage['urgency_label'],
                    'route' => $triage['route'],
                    'route_label' => $triage['route_label'],
                    'escalate' => $triage['escalate'],
                    'needs_human' => $triage['needs_human'],
                    'has_approved_help' => $triage['topics'] !== [],
                ];
            }

            /* Priority order: most severe urgency first, then newest. */
            $rank = array_flip(InquiryTriage::URGENCIES);
            usort($items, static function (array $a, array $b) use ($rank): int {
                $byUrgency = ($rank[$a['urgency']] ?? 99) <=> ($rank[$b['urgency']] ?? 99);

                return $byUrgency !== 0 ? $byUrgency : strcmp($b['created_at'], $a['created_at']);
            });

            $this->log($userId, 'AI_INQUIRY_QUEUE', '/api/ai/inquiries/queue', [
                'locale' => $locale,
                'total' => $counts['total'],
                'needs_human' => $counts['needs_human'],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json([
                'success' => true,
                'capability' => InquiryTriage::CAPABILITY,
                'locale' => $locale,
                'counts' => $counts,
                'items' => $items,
                'auto_send_policy' => (bool) config('ai.inquiry_triage.auto_send', false),
                'reviewed_note' => InquiryTriage::msg('lim_no_resolution', $locale),
                'generated_at' => now()->toISOString(true),
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_INQUIRY_QUEUE_ERROR', '/api/ai/inquiries/queue', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Triage + draft                                                      */
    /* ------------------------------------------------------------------ */

    public function triage(Request $request, string $id): JsonResponse
    {
        $startedAt = microtime(true);
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.inquiries', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $maxChars = max(200, (int) config('ai.inquiry_triage.max_message_chars', 4000));

        $inquiry = $this->findInquiry($id);
        if (! $inquiry) {
            return $this->json([
                'error' => InquiryTriage::msg('err_inquiry_missing', $locale),
                'code' => 'INQUIRY_NOT_FOUND',
            ], 404);
        }

        try {
            $row = $inquiry;
            $subject = mb_substr(trim((string) ($row['subject'] ?? '')), 0, 300);
            $message = mb_substr(trim((string) ($row['message'] ?? '')), 0, $maxChars);

            /* The customer's own locale drives the DRAFT language; the request
             * locale only drives the admin-facing labels. */
            $draftLocale = $this->supportedLocale((string) ($row['locale'] ?? '')) ?? $locale;

            /* Internal triage signal only - never placed in the draft text. An
             * account question with no matching account always needs a human. */
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $accountFound = null;
            if ($email !== '') {
                try {
                    $accountFound = User::whereRaw('LOWER(email) = ?', [$email])->exists();
                } catch (\Throwable $e) {
                    $accountFound = null;
                }
            }

            $triage = InquiryTriage::triage([
                'locale' => $locale,
                'subject' => $subject,
                'message' => $message,
                'account_found' => $accountFound,
            ]);

            /* Optional, gated model step: an acknowledgement line + an
             * allow-listed topic selection. It can never introduce a claim,
             * figure, link, address or an unapproved answer. */
            $narration = $this->narrate($id, $triage, $draftLocale, $ip, (bool) config('ai.inquiry_triage.ai_narrate', true));

            $topics = $narration['topics'] !== []
                ? array_values(array_filter($triage['topics'], static fn (array $t): bool => in_array($t['id'], $narration['topics'], true)))
                : $triage['topics'];
            if ($topics !== []) {
                $triage['topics'] = $topics;
            }

            $draft = InquiryTriage::draft(
                $triage,
                ['id' => $id, 'subject' => $subject, 'message' => $message],
                $draftLocale,
                $narration['ack'],
                $this->helpTable(),
            );

            $autoSendAllowed = InquiryTriage::mayAutoSend($triage, (bool) config('ai.inquiry_triage.auto_send', false));

            $payload = [
                'success' => true,
                'capability' => InquiryTriage::CAPABILITY,
                'locale' => $locale,
                'draft_locale' => $draftLocale,
                'inquiry' => [
                    'id' => $id,
                    'subject' => $subject,
                    'email' => (string) ($row['email'] ?? ''),
                    'phone' => (string) ($row['phone'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'customer_locale' => (string) ($row['locale'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ],
                'triage' => [
                    'category' => $triage['category'],
                    'category_label' => $triage['category_label'],
                    'category_scores' => $triage['category_scores'],
                    'category_evidence' => $triage['category_evidence'],
                    'urgency' => $triage['urgency'],
                    'urgency_label' => $triage['urgency_label'],
                    'urgency_reasons' => $triage['urgency_reasons'],
                    'urgency_evidence' => $triage['urgency_evidence'],
                    'route' => $triage['route'],
                    'route_label' => $triage['route_label'],
                    'escalate' => $triage['escalate'],
                    'route_reasons' => $triage['route_reasons'],
                    'needs_human' => $triage['needs_human'],
                    'needs_human_reasons' => $triage['needs_human_reasons'],
                    'matched_topics' => array_map(static fn (array $t): array => [
                        'id' => $t['id'],
                        'category' => $t['category'],
                        'evidence' => $t['evidence'],
                    ], $triage['topics']),
                    // Admin-only context: whether this email has an account.
                    'account_found' => $triage['account_found'],
                ],
                'draft' => [
                    'subject' => $draft['subject'],
                    'body' => $draft['body'],
                    'topic_ids' => $draft['topic_ids'],
                    'asked_details' => $draft['asked_details'],
                    'uses_approved_help' => $draft['uses_approved_help'],
                ],
                'approval' => [
                    'required' => ! $autoSendAllowed,
                    'auto_send_policy' => (bool) config('ai.inquiry_triage.auto_send', false),
                    'may_auto_send' => $autoSendAllowed,
                ],
                'sources' => $draft['sources'],
                'limitations' => [
                    InquiryTriage::msg('lim_help_only', $locale),
                    InquiryTriage::msg('lim_no_resolution', $locale),
                    InquiryTriage::msg('lim_narration', $locale),
                    InquiryTriage::msg('lim_no_send_in_triage', $locale),
                ],
                'ai' => [
                    'available' => $narration['available'],
                    'degraded' => $narration['degraded'],
                    'code' => $narration['code'],
                    'reason' => $narration['reason'],
                    'meta' => $narration['meta'],
                ],
                'generated_at' => now()->toISOString(true),
            ];

            /* Durable record: audit always, columns when the migration is applied. */
            $payload['persisted'] = $this->persistTriage($id, $triage, $draft, $userId);

            $this->log($userId, 'AI_INQUIRY_TRIAGE', '/api/ai/inquiries/'.$id.'/triage', [
                'category' => $triage['category'],
                'urgency' => $triage['urgency'],
                'route' => $triage['route'],
                'needs_human' => $triage['needs_human'],
                'topics' => $draft['topic_ids'],
                'uses_approved_help' => $draft['uses_approved_help'],
                'account_found' => $accountFound,
                'ai_status' => $narration['degraded'] ? 'degraded' : 'ok',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            /* The narrow opt-in policy may answer ONLY a general, low-urgency,
             * human-free inquiry, and the response says so explicitly. */
            if ($autoSendAllowed) {
                $delivery = $this->deliver($row, $draft['subject'], $draft['body'], $userId, $ip, true);
                $payload['auto_sent'] = $delivery['delivered'];
                $payload['delivery'] = $delivery;
                $payload['notice'] = $delivery['delivered']
                    ? InquiryTriage::msg('notice_auto_sent', $locale)
                    : InquiryTriage::msg('err_send_failed', $locale);
                $payload['persisted'] = $payload['persisted'] || $delivery['persisted'];
            } elseif ($triage['needs_human']) {
                $payload['notice'] = InquiryTriage::msg('notice_human', $locale);
            } else {
                $payload['notice'] = InquiryTriage::msg('why_manual', $locale);
            }

            return $this->json($payload);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_INQUIRY_TRIAGE_ERROR', '/api/ai/inquiries/'.$id.'/triage', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Approved reply                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Send the reply. The server re-derives the triage itself (so the client
     * cannot claim a harmless category to dodge the human requirement) and
     * refuses to send without an explicit approval flag and a real body.
     */
    public function reply(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId($request);
        $ip = $this->ip($request);

        if (! config('ai.features.inquiries', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Ruhusa haitoshi.', 'code' => 'FORBIDDEN_ROLE'], 403);
        }

        $locale = $this->resolveLocale($request);
        $approved = filter_var($request->input('approved', false), FILTER_VALIDATE_BOOL);
        $subject = trim((string) $request->input('subject', ''));
        $body = trim((string) $request->input('body', ''));
        $maxChars = max(200, (int) config('ai.inquiry_triage.max_message_chars', 4000));

        $inquiry = $this->findInquiry($id);
        if (! $inquiry) {
            return $this->json([
                'error' => InquiryTriage::msg('err_inquiry_missing', $locale),
                'code' => 'INQUIRY_NOT_FOUND',
            ], 404);
        }

        if (! $approved) {
            return $this->json([
                'error' => InquiryTriage::msg('err_approval_required', $locale),
                'code' => 'REPLY_APPROVAL_REQUIRED',
            ], 422);
        }
        if ($body === '' || mb_strlen($body) < 10) {
            return $this->json([
                'error' => InquiryTriage::msg('err_reply_empty', $locale),
                'code' => 'REPLY_BODY_REQUIRED',
            ], 422);
        }
        $body = mb_substr($body, 0, $maxChars * 2);

        try {
            $row = $inquiry;

            /* Re-derived server-side: the human decision is recorded against the
             * REAL triage, never against what the client claims. */
            $triage = InquiryTriage::triage([
                'locale' => $locale,
                'subject' => (string) ($row['subject'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
                'account_found' => null,
            ]);

            if ($subject === '') {
                $subject = mb_substr(InquiryTriage::msg('draft_subject', $locale, [
                    'original' => (string) ($row['subject'] ?? ''),
                ]), 0, 160);
            }

            /* Record the APPROVAL itself, before the delivery is attempted, so a
             * refused or failed send still leaves the human decision - and who
             * made it - on the record. */
            $approvedPersisted = $this->markApproved($id, $userId);

            $delivery = $this->deliver($row, $subject, $body, $userId, $ip, false);

            if (! $delivery['delivered']) {
                return $this->json([
                    'error' => InquiryTriage::msg('err_send_failed', $locale),
                    'code' => 'REPLY_SEND_FAILED',
                    'reason' => InquiryTriage::msg('err_send_failed_hint', $locale),
                    'detail' => $delivery['error'],
                    'queued_for_retry' => $delivery['queued'],
                ], 502);
            }

            /* Integrate with the existing workflow: a replied-to inquiry is no
             * longer "new". Sending is NOT a resolution - the audit records the
             * reply, and the follow-up stays the team's job. */
            $now = now()->toISOString();
            if ((string) ($row['status'] ?? '') === 'new') {
                try {
                    Inquiry::where('id', $id)->update([
                        'status' => 'reviewed',
                        'reviewed_at' => $now,
                        'reviewed_by' => $userId,
                        'updated_at' => $now,
                    ]);
                } catch (\Throwable $e) {
                    // The reply itself already succeeded; the status bump is best effort.
                }
            }

            return $this->json([
                'success' => true,
                'capability' => InquiryTriage::CAPABILITY,
                'locale' => $locale,
                'inquiry_id' => $id,
                'delivered' => true,
                'reply_status' => 'sent',
                'automated' => false,
                'needs_human' => $triage['needs_human'],
                'category' => $triage['category'],
                'route' => $triage['route'],
                'persisted' => $delivery['persisted'] || $approvedPersisted,
                'approved_by' => $userId,
                'notice' => InquiryTriage::msg('notice_approved', $locale),
                'resolution_note' => InquiryTriage::msg('lim_no_resolution', $locale),
                'sent_at' => $now,
            ]);
        } catch (\Throwable $error) {
            $this->log($userId, 'AI_INQUIRY_REPLY_FAILED', '/api/ai/inquiries/'.$id.'/reply', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->dbError($error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Load one inquiry by id. A malformed id - this backend rejects any value
     * that is not a UUID with a 400 - is treated as "not found" so a bad URL
     * never becomes an unhandled 500 carrying a raw database message.
     */
    /**
     * Load one inquiry by id, as an array. A malformed id (this backend rejects a
     * non-UUID) is a "not found", never an unhandled 500 with a raw database
     * message.
     *
     * NOTE: the row MUST be converted to an array here. This data layer returns
     * `App\Services\SupabaseRow`, NOT the model class, so a `: ?Inquiry` return
     * type would raise a TypeError that the catch block below would swallow into
     * a "not found" - i.e. every triage of a REAL inquiry would 404.
     *
     * @return array<string, mixed>|null
     */
    private function findInquiry(string $id): ?array
    {
        try {
            $inquiry = Inquiry::where('id', $id)->first();

            return $inquiry ? $inquiry->toArray() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Deliver a reply through the SAME path the notification system uses:
     * Mail::html with the message id captured from the MessageSent event, and a
     * pending_emails row as the fallback when the mailer fails - so a reply is
     * never silently lost, and the outcome is always recorded.
     *
     * @param  array<string, mixed>  $row
     * @return array{delivered: bool, queued: bool, error: ?string, persisted: bool}
     */
    private function deliver(array $row, string $subject, string $body, ?string $userId, ?string $ip, bool $automated): array
    {
        $id = (string) ($row['id'] ?? '');
        $to = trim((string) ($row['email'] ?? ''));

        $delivered = false;
        $queued = false;
        $error = null;

        if ($to === '') {
            $error = 'INQUIRY_HAS_NO_EMAIL';
        } else {
            try {
                $messageId = null;
                Event::listen(MessageSent::class, function (MessageSent $event) use (&$messageId) {
                    $messageId = $event->sent->getMessageId();
                });

                Mail::html(nl2br(e($body)), [], function ($m) use ($to, $subject) {
                    $m->to($to)->subject($subject);
                });

                $delivered = true;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                try {
                    \App\Services\Supabase::table('pending_emails')->insert([
                        'to' => $to,
                        'subject' => $subject,
                        'content' => $body,
                        'status' => 'pending',
                        'error' => $error,
                        'created_at' => $this->isoNow(),
                    ]);
                    $queued = true;
                } catch (\Throwable $dbError) {
                    // Nothing else to do: the audit row below records the failure.
                }
            }
        }

        $persisted = $this->persistReply($id, $subject, $body, $delivered, $error, $automated, $userId);

        $this->log($userId, $delivered ? 'AI_INQUIRY_REPLY_SENT' : 'AI_INQUIRY_REPLY_FAILED',
            '/api/ai/inquiries/'.$id.'/reply',
            array_filter([
                'automated' => $automated,
                // "approval" is explicit on every delivery record: either a human
                // approved it, or the narrow auto-send policy did.
                'approval' => $automated ? 'auto_policy' : 'human',
                'approved_by' => $automated ? null : $userId,
                'queued_for_retry' => $queued,
                'body_chars' => mb_strlen($body),
                'error' => $error,
            ], static fn ($v) => $v !== null && $v !== false),
            $ip, $delivered ? 'success' : 'failed');

        return ['delivered' => $delivered, 'queued' => $queued, 'error' => $error, 'persisted' => $persisted];
    }

    /**
     * Write the triage decision onto the inquiry. Returns false when the
     * additive migration has not been applied yet - the caller reports that
     * honestly instead of pretending the record exists.
     */
    private function persistTriage(string $id, array $triage, array $draft, ?string $userId): bool
    {
        try {
            Inquiry::where('id', $id)->update([
                'triage_category' => $triage['category'],
                'triage_urgency' => $triage['urgency'],
                'triage_route' => $triage['route'],
                'triage_status' => 'drafted',
                'triage_topics' => json_encode($draft['topic_ids']),
                'triage_meta' => json_encode([
                    'needs_human' => $triage['needs_human'],
                    'escalate' => $triage['escalate'],
                    'uses_approved_help' => $draft['uses_approved_help'],
                    'account_found' => $triage['account_found'],
                ]),
                'triage_at' => now()->toISOString(),
                'triage_by' => $userId,
                'updated_at' => now()->toISOString(),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Record the human approval (state `approved` of the documented lifecycle)
     * on the inquiry itself. Best effort, like the other column writes - the
     * audit row records the decision regardless.
     */
    private function markApproved(string $id, ?string $userId): bool
    {
        try {
            Inquiry::where('id', $id)->update([
                'triage_status' => 'approved',
                'approved_at' => now()->toISOString(),
                'approved_by' => $userId,
                'updated_at' => now()->toISOString(),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Write the delivery outcome onto the inquiry (best effort, same reason as
     * above). The audit row is written regardless.
     */
    private function persistReply(string $id, string $subject, string $body, bool $delivered, ?string $error, bool $automated, ?string $userId): bool
    {
        try {
            Inquiry::where('id', $id)->update([
                'reply_subject' => mb_substr($subject, 0, 200),
                'reply_body' => $body,
                'reply_status' => $delivered ? 'sent' : 'failed',
                'reply_error' => $error,
                'reply_automated' => $automated,
                'replied_at' => now()->toISOString(),
                'replied_by' => $userId,
                'triage_status' => $delivered ? 'sent' : 'failed',
                'updated_at' => now()->toISOString(),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The approved help table (config override, otherwise the built-in one). */
    private function helpTable(): ?array
    {
        $help = config('ai.inquiry_triage.help');

        return is_array($help) && $help !== [] ? $help : null;
    }

    /**
     * The gated model step. It may only return an acknowledgement sentence and
     * allow-listed topic ids; anything else is dropped and the deterministic
     * draft is used. The result is cached per inquiry + locale so a refresh does
     * not pay for the same sentence twice.
     */
    private function narrate(string $id, array $triage, string $locale, ?string $ip, bool $enabled): array
    {
        $fallback = [
            'available' => false,
            'degraded' => true,
            'code' => null,
            'reason' => null,
            'meta' => null,
            'ack' => null,
            'topics' => [],
        ];

        if (! $enabled || $triage['topics'] === []) {
            return $fallback;
        }

        $cacheKey = self::NARRATION_CACHE_PREFIX.md5($id.'|'.$locale.'|'.implode(',', array_column($triage['topics'], 'id')));

        try {
            $cached = cache()->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // A cache failure only costs one extra model call.
        }

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            $fallback['code'] = 'AI_DISABLED';
            $fallback['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }

        $instruction = implode("\n", [
            'You are the first-line support assistant of the Duka Mkononi marketplace.',
            'Return STRICT JSON only: {"ack": string, "topics": [string]}.',
            'The "topics" list may contain ONLY ids from this allow-list: '.implode(', ', array_column($triage['topics'], 'id')).'.',
            'The "ack" is ONE short sentence (no digits, no links, no email addresses, no phone numbers) that acknowledges the customer request and says the team is looking into it.',
            'You must NOT answer the question, promise anything, offer refunds, discounts or guarantees, or mention any account, order or other customer.',
            'Write the sentence in the language with locale code "'.$locale.'" (sw=Kiswahili, en=English, fr=French, es=Spanish, zh=Chinese, hi=Hindi, ur=Urdu, de=German).',
            'DATA: '.json_encode([
                'locale' => $locale,
                'category' => $triage['category'],
                'urgency' => $triage['urgency'],
                'matched_topics' => array_column($triage['topics'], 'id'),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $result = $client->generateJson(
                [['text' => 'Return the JSON acknowledgement for this inquiry.']],
                $instruction,
                [
                    'capability' => InquiryTriage::CAPABILITY.'_ack',
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 300,
                ],
            );

            $narration = InquiryTriage::sanitizeNarration($result->data);
            if ($narration === null || ($narration['ack'] === null && $narration['topics'] === [])) {
                return [
                    'available' => true,
                    'degraded' => true,
                    'code' => 'AI_UNSAFE_OUTPUT',
                    'reason' => AiException::reasonForCode('AI_UNSAFE_OUTPUT', $locale),
                    'meta' => $result->meta(),
                    'ack' => null,
                    'topics' => [],
                ];
            }

            $out = [
                'available' => true,
                'degraded' => false,
                'code' => null,
                'reason' => null,
                'meta' => $result->meta(),
                'ack' => $narration['ack'],
                'topics' => $narration['topics'],
            ];

            try {
                cache()->put($cacheKey, $out, max(1, (int) config('ai.inquiry_triage.cache_minutes', 10)) * 60);
            } catch (\Throwable $e) {
                // Cache is only a cost optimisation.
            }

            return $out;
        } catch (AiException $error) {
            $fallback['code'] = $error->errorCode;
            $fallback['reason'] = AiException::reasonForCode($error->errorCode, $locale);

            return $fallback;
        }
    }

    /**
     * The platform's Super Admin, exactly as the existing inquiry endpoints
     * define it (the deployment stores platform admins under `admin`, while
     * `system_admin` is the reserved platform role).
     */
    private function isSuperAdmin(Request $request): bool
    {
        return in_array($this->role($request), ['super_admin', 'system_admin', 'admin'], true);
    }

    private function supportedLocale(string $candidate): ?string
    {
        $candidate = strtolower(trim($candidate));
        $supported = (array) config('ai.locales', ['sw']);

        return in_array($candidate, $supported, true) ? $candidate : null;
    }
}
