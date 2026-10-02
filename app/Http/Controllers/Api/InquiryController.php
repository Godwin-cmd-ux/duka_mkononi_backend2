<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerReview;
use App\Models\Inquiry;
use App\Services\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InquiryController extends BaseController
{
    /* ------------------------------------------------------------------ */
    /* Public: submit an inquiry from the Contact Us form                  */
    /* ------------------------------------------------------------------ */

    public function store(Request $request)
    {
        $email = trim((string) $request->input('email'));
        $phone = trim((string) $request->input('phone'));
        $subject = trim((string) $request->input('subject'));
        $message = trim((string) $request->input('message'));

        $errors = [];
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Barua pepe si sahihi.';
        }
        if (! PhoneNumber::isValid($phone)) {
            $errors['phone'] = 'Namba ya simu si sahihi.';
        }
        if ($subject === '' || mb_strlen($subject) < 3) {
            $errors['subject'] = 'Kichwa cha ujumbe kinahitajika.';
        } elseif (mb_strlen($subject) > 150) {
            $errors['subject'] = 'Kichwa cha ujumbe ni kirefu mno.';
        }
        if ($message === '' || mb_strlen($message) < 10) {
            $errors['message'] = 'Ujumbe unahitajika.';
        } elseif (mb_strlen($message) > 5000) {
            $errors['message'] = 'Ujumbe ni mrefu mno.';
        }

        if ($errors !== []) {
            return $this->json(['error' => 'Tafadhali rekebisha sehemu zilizo na hitilafu.', 'errors' => $errors], 422);
        }

        try {
            Inquiry::create([
                'id' => (string) Str::uuid(),
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message,
                'status' => 'new',
                'source' => $request->input('source', 'website'),
                'locale' => $request->input('locale', 'sw'),
                'ip_address' => $this->ip($request),
                'user_agent' => substr((string) $request->userAgent(), 0, 400),
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kutuma ujumbe. Tafadhali jaribu tena.'], 500);
        }

        $this->log(null, 'INQUIRY_CREATE', '/api/inquiries', [], $this->ip($request), 'success');

        return $this->json(['message' => 'Ujumbe wako umepokelewa. Asante!'], 201);
    }

    /* ------------------------------------------------------------------ */
    /* Super Admin: manage inquiries                                       */
    /* ------------------------------------------------------------------ */

    public function adminIndex(Request $request)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa ya kuona maswali.'], 403);
        }

        $status = $request->query('status');
        $search = $request->query('search');
        $publishedFilter = $request->query('published');

        try {
            $query = Inquiry::query();
            if (is_string($status) && in_array($status, ['new', 'reviewed', 'published', 'archived'], true)) {
                $query->where('status', $status);
            }
            $inquiries = $query->orderByDesc('created_at')->get()->toArray();
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Imeshindikana kupata maswali.'], 500);
        }

        $publishedIds = $this->publishedReviewMap(array_column($inquiries, 'id'));

        if (is_string($search) && $search !== '') {
            $needle = mb_strtolower(trim($search));
            $inquiries = array_values(array_filter($inquiries, function ($i) use ($needle) {
                $haystack = mb_strtolower(implode(' ', [
                    $i['email'] ?? '', $i['phone'] ?? '', $i['subject'] ?? '', $i['message'] ?? '',
                ]));

                return str_contains($haystack, $needle);
            }));
        }

        if ($publishedFilter === '1' || $publishedFilter === 'true') {
            $inquiries = array_values(array_filter($inquiries, fn ($i) => isset($publishedIds[$i['id']])));
        } elseif ($publishedFilter === '0' || $publishedFilter === 'false') {
            $inquiries = array_values(array_filter($inquiries, fn ($i) => ! isset($publishedIds[$i['id']])));
        }

        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));
        $total = count($inquiries);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $slice = array_slice($inquiries, ($page - 1) * $perPage, $perPage);

        $data = array_map(function ($i) use ($publishedIds) {
            return $this->adminInquiry($i, $publishedIds[$i['id']] ?? null);
        }, $slice);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => $total, 'page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage],
        ]);
    }

    public function adminShow(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa ya kuona swali hili.'], 403);
        }

        $inquiry = Inquiry::where('id', $id)->first();
        if (! $inquiry) {
            return $this->json(['error' => 'Swali haipatikani.'], 404);
        }

        $review = CustomerReview::where('inquiry_id', $id)->first();

        return $this->json([
            'inquiry' => $this->adminInquiry($inquiry->toArray(), $review ? $review->toArray() : null),
        ]);
    }

    public function markReviewed(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        $inquiry = Inquiry::where('id', $id)->first();
        if (! $inquiry) {
            return $this->json(['error' => 'Swali haipatikani.'], 404);
        }

        if ($inquiry->status === 'new') {
            Inquiry::where('id', $id)->update([
                'status' => 'reviewed',
                'reviewed_at' => now()->toISOString(),
                'reviewed_by' => $this->userId($request),
                'updated_at' => now()->toISOString(),
            ]);
        }

        return $this->json(['message' => 'Swali limewekwa kama lililohakikiwa.']);
    }

    public function publish(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa ya kuchapisha.'], 403);
        }

        $inquiry = Inquiry::where('id', $id)->first();
        if (! $inquiry) {
            return $this->json(['error' => 'Swali haipatikani.'], 404);
        }

        $displayName = trim((string) $request->input('display_name', ''));
        $rating = $request->input('rating');
        $rating = is_numeric($rating) ? (int) $rating : null;
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            $rating = null;
        }

        $now = now()->toISOString();

        // The public review carries only the approved public content. Email and
        // phone are never copied here.
        $payload = [
            'title' => $inquiry->subject,
            'body' => $inquiry->message,
            'display_name' => $displayName !== '' ? $displayName : null,
            'rating' => $rating,
            'is_published' => true,
            'published_at' => $now,
            'updated_at' => $now,
        ];

        $existing = CustomerReview::where('inquiry_id', $id)->first();
        if ($existing) {
            CustomerReview::where('inquiry_id', $id)->update($payload);
        } else {
            CustomerReview::create(array_merge($payload, [
                'id' => (string) Str::uuid(),
                'inquiry_id' => $id,
                'created_at' => $now,
            ]));
        }

        Inquiry::where('id', $id)->update([
            'status' => 'published',
            'published_at' => $now,
            'published_by' => $this->userId($request),
            'updated_at' => $now,
        ]);

        $this->log($this->userId($request), 'INQUIRY_PUBLISH', '/api/system-admin/inquiries/' . $id, [], $this->ip($request), 'success');

        return $this->json(['message' => 'Swali limechapishwa kama rejea ya mteja.']);
    }

    public function unpublish(Request $request, string $id)
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['error' => 'Huna ruhusa.'], 403);
        }

        $inquiry = Inquiry::where('id', $id)->first();
        if (! $inquiry) {
            return $this->json(['error' => 'Swali haipatikani.'], 404);
        }

        $now = now()->toISOString();
        CustomerReview::where('inquiry_id', $id)->update([
            'is_published' => false,
            'updated_at' => $now,
        ]);
        Inquiry::where('id', $id)->update([
            'status' => 'reviewed',
            'updated_at' => $now,
        ]);

        return $this->json(['message' => 'Rejea imeondolewa kwenye ukurasa wa mwanzo.']);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * The platform's Super Admin. This deployment stores platform admins under
     * the `admin` role (the mobile system_admin portal itself gates on
     * role === 'admin' and AdminController requires 'admin'), while
     * `system_admin` is the reserved platform role used elsewhere in the code.
     * Both are accepted so the module is actually reachable; a business
     * "admin" is the platform's existing trust boundary for cross-business
     * endpoints. See the delivery report for the recommended hardening
     * (introduce a dedicated `system_admin` account/role).
     */
    private function isSuperAdmin(Request $request): bool
    {
        return in_array($this->role($request), ['system_admin', 'admin'], true);
    }

    private function publishedReviewMap(array $inquiryIds): array
    {
        if ($inquiryIds === []) {
            return [];
        }

        try {
            $rows = CustomerReview::whereIn('inquiry_id', $inquiryIds)->get()->toArray();
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[$row['inquiry_id']] = $row;
        }

        return $map;
    }

    private function adminInquiry(array $inquiry, ?array $review): array
    {
        return [
            'id' => $inquiry['id'] ?? null,
            'email' => $inquiry['email'] ?? null,
            'phone' => $inquiry['phone'] ?? null,
            'subject' => $inquiry['subject'] ?? null,
            'message' => $inquiry['message'] ?? null,
            'status' => $inquiry['status'] ?? null,
            'source' => $inquiry['source'] ?? null,
            'locale' => $inquiry['locale'] ?? null,
            'created_at' => $inquiry['created_at'] ?? null,
            'reviewed_at' => $inquiry['reviewed_at'] ?? null,
            'published_at' => $inquiry['published_at'] ?? null,
            'is_published' => (bool) ($review['is_published'] ?? false),
            'review' => $review ? [
                'id' => $review['id'] ?? null,
                'display_name' => $review['display_name'] ?? null,
                'title' => $review['title'] ?? null,
                'body' => $review['body'] ?? null,
                'rating' => $review['rating'] ?? null,
                'is_published' => (bool) ($review['is_published'] ?? false),
                'published_at' => $review['published_at'] ?? null,
            ] : null,
        ];
    }
}
