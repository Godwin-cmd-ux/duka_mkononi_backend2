<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdvertisementController;
use App\Http\Controllers\Api\AiAdCopyController;
use App\Http\Controllers\Api\AiCertificationController;
use App\Http\Controllers\Api\AiCopilotController;
use App\Http\Controllers\Api\AiHealthController;
use App\Http\Controllers\Api\AiInquiryTriageController;
use App\Http\Controllers\Api\AiOnboardingController;
use App\Http\Controllers\Api\AiAdminAnalyticsController;
use App\Http\Controllers\Api\AiAnomalyController;
use App\Http\Controllers\Api\AiOrderQaController;
use App\Http\Controllers\Api\AiExpenseParseController;
use App\Http\Controllers\Api\AiImportController;
use App\Http\Controllers\Api\AiNlEntryController;
use App\Http\Controllers\Api\AiPricingController;
use App\Http\Controllers\Api\AiPublicConciergeController;
use App\Http\Controllers\Api\AiSearchController;
use App\Http\Controllers\Api\AiSpeechController;
use App\Http\Controllers\Api\AiRecommendationController;
use App\Http\Controllers\Api\AiReplyController;
use App\Http\Controllers\Api\AiReportController;
use App\Http\Controllers\Api\AiReviewController;
use App\Http\Controllers\Api\AiReportSummaryController;
use App\Http\Controllers\Api\AiRestockController;
use App\Http\Controllers\Api\AiShopAssistantController;
use App\Http\Controllers\Api\AiTeamActivityController;
use App\Http\Controllers\Api\AiTrustSummaryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\MiscController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProfitController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\ResetPasswordController;
use App\Http\Controllers\Api\RevenueController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\SystemAdminController;
use App\Http\Controllers\Api\UserActivityController;
use Illuminate\Support\Facades\Route;

Route::get('health', [MiscController::class, 'health']);
Route::get('test', [MiscController::class, 'test']);

// The /api/temp/* schema+dump endpoints were removed: they ran outside
// auth.jwt with the service_role key, so anyone could read the whole schema,
// row counts and table contents. Take a snapshot from the Supabase dashboard
// instead. (They are still in git history if a one-off run is ever needed.)

// Auth
Route::post('register', [AuthController::class, 'register']);
Route::post('register/initiate', [AuthController::class, 'registerInitiate']);
Route::post('register/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('register/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('login', [AuthController::class, 'login']);

// Password reset
Route::post('password-reset/request', [ResetPasswordController::class, 'request']);
Route::post('password-reset/verify-code', [ResetPasswordController::class, 'verifyCode']);
Route::post('password-reset/confirm', [ResetPasswordController::class, 'confirm']);
Route::post('password-reset/check-status', [ResetPasswordController::class, 'checkStatus']);
Route::post('forgot-password', [ResetPasswordController::class, 'forgotPassword']);
Route::post('reset-password', [ResetPasswordController::class, 'resetPassword']);
Route::get('check/password-reset-table', [ResetPasswordController::class, 'checkTable']);
Route::post('setup/password-reset-table', [ResetPasswordController::class, 'setupTable']);

Route::post('check-business', [BusinessController::class, 'checkBusiness']);
Route::get('check-business-name', [BusinessController::class, 'checkBusinessName']);
Route::get('businesses', [BusinessController::class, 'index']);
Route::get('businesses/{businessId}/products', [BusinessController::class, 'businessProducts']);
Route::get('test/businesses', [BusinessController::class, 'testBusinesses']);
Route::get('businesses/{id}', [BusinessController::class, 'show']);
Route::get('businesses/search/{query}', [BusinessController::class, 'search']);
Route::get('business/by-name/{business_name}', [BusinessController::class, 'byName']);

// Public catalogue (Shop, Businesses, Advertisements, Reviews)
Route::get('shop/businesses', [ShopController::class, 'businesses']);
Route::get('shop/businesses/certified', [ShopController::class, 'certifiedBusinesses']);
Route::get('shop/products', [ShopController::class, 'products']);
Route::get('shop/products/{id}', [ShopController::class, 'product']);
Route::get('shop/advertisements', [ShopController::class, 'advertisements']);
Route::get('shop/reviews', [ShopController::class, 'reviews']);

// Capability #11 - Conversational Shopping Assistant. PUBLIC (any shopper,
// signed in or not) and rate limited: it only reads the public catalogue.
// Signed-in shoppers get one remembered turn (cache, short TTL) for follow-ups.
Route::post('ai/shopping/ask', [AiShopAssistantController::class, 'ask'])->middleware('throttle:20,1');
Route::get('ai/shopping/suggestions', [AiShopAssistantController::class, 'suggestions'])->middleware('throttle:60,1');

// Capability #21 - Public AI Concierge. PUBLIC + throttled (and additionally
// capped per IP per hour inside the controller): a visitor who has never signed
// in can ask what the marketplace is, how to buy or sell, or where to download
// the app. It answers from the approved public knowledge base and the SAME
// public catalogue the shop endpoints serve, refuses questions about the
// platform's internals or anyone's private data, and can only ever return links
// this website already publishes.
Route::post('ai/concierge/ask', [AiPublicConciergeController::class, 'ask'])->middleware('throttle:20,1');
Route::get('ai/concierge/help', [AiPublicConciergeController::class, 'help'])->middleware('throttle:60,1');

// Capability #23 - Voice Input & Voice Answers. PUBLIC + throttled: this is the
// capability contract the voice controls read before showing a microphone
// button - which locales have VERIFIED speech coverage, the single-utterance
// limits, the privacy facts (no audio recorded, none sent to this server) and
// the localized UI strings. Speech itself runs on the visitor's own device, so
// no audio is ever uploaded and no speech API key exists anywhere.
Route::get('ai/speech/capabilities', [AiSpeechController::class, 'capabilities'])->middleware('throttle:60,1');

// Capability #24 - Semantic & Typo-Tolerant Search. PUBLIC + throttled: it
// ranks the SAME public catalogue rows the shop page serves (so public
// visibility, business scope and stock rules are unchanged) with typo
// tolerance, prefix, transliteration and configured synonyms. Embeddings are
// optional and off by default - the lexical path always works on its own.
Route::get('ai/search/products', [AiSearchController::class, 'products'])->middleware('throttle:60,1');

// Capability #12 - Business & Review Summarisation. PUBLIC + throttled: it
// reads only published reviews, public business rows and order aggregates.
// Cached per locale; publishing/unpublishing a review or (un)certifying a
// business bumps a cache version key so summaries refresh immediately.
Route::get('ai/trust/summary', [AiTrustSummaryController::class, 'summary'])->middleware('throttle:30,1');

// Capability #14 - Personalised Recommendations. PUBLIC + throttled read:
// identity is optional (a valid token personalises, a guest gets the same
// cold-start feed of popular/certified items); the dismiss and feedback
// WRITES live behind auth.jwt below because they are per-account state.
Route::get('ai/recommendations', [AiRecommendationController::class, 'index'])->middleware('throttle:30,1');

// Public inquiry submission (rate limited against spam)
Route::post('inquiries', [InquiryController::class, 'store'])->middleware('throttle:10,1');

// Public order placement + tracking (rate limited)
Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
Route::get('orders/track', [OrderController::class, 'track'])->middleware('throttle:20,1');

Route::get('matangazo', [AdvertisementController::class, 'publicIndex']);
Route::post('payments/pesapal-ipn', [PaymentController::class, 'ipn']);
Route::post('payments/pesapal-callback', [PaymentController::class, 'callback']);
Route::get('payments/pesapal/config-status', [PaymentController::class, 'configStatus']);
Route::post('payments/simulate', [PaymentController::class, 'simulate']);
// Node backend exposes this as GET /api/backup (mobile compatibility), so accept both verbs.
Route::match(['get', 'post'], 'backup', [BackupController::class, 'backup']);
Route::get('backup/status', [BackupController::class, 'status']);
Route::get('backup/download/{index}', [BackupController::class, 'download']);

Route::middleware('auth.jwt')->group(function () {
    // Debug/support helpers. These read the caller's OWN business (or, for
    // check-user, the whole user table) so they must never be reachable
    // anonymously: the mobile admin screen already sends its Bearer token.
    Route::get('debug/check-user/{email}', [DebugController::class, 'checkUser']);
    Route::get('debug/check-business', [DebugController::class, 'checkBusiness']);

    Route::get('user/profile', [ProfileController::class, 'show']);
    Route::put('user/profile', [ProfileController::class, 'update']);
    Route::put('user/password', [ProfileController::class, 'changePassword']);
    Route::put('onboarding/profile', [ProfileController::class, 'onboarding']);

    Route::post('setup/ai-import-migration', [AiImportController::class, 'migration']);
    // Capability #2 - Pricing and Margin Advisor (read-only, business scoped).
    Route::post('ai/price/suggest', [AiPricingController::class, 'suggest']);
    // Capability #3 - Restock and Stock-Out Prediction (read-only, business scoped).
    Route::post('ai/restock/list', [AiRestockController::class, 'list']);
    // Capability #4 - Natural-Language Business Reporting (read-only, business scoped).
    Route::post('ai/report/ask', [AiReportController::class, 'ask']);
    // Capability #5 - Business Health Score & Weekly Coaching (business scoped).
    Route::post('ai/reports/weekly', [AiHealthController::class, 'weekly']);
    Route::get('ai/reports/weekly', [AiHealthController::class, 'latest']);
    Route::get('ai/reports/weekly/history', [AiHealthController::class, 'history']);
    Route::post('ai/health/setup', [AiHealthController::class, 'setup']);

    // Capability #15 - AI Report Summaries (grounded explanations over the
    // EXISTING manager report queries; business-scoped to the caller).
    Route::post('ai/reports/summary', [AiReportSummaryController::class, 'summary']);

    // Capability #16 - Advertisement & Content Review Assistant (staff;
    // deterministic findings + advisory AI classification; the decision is
    // always a recorded human one).
    Route::post('ai/reviews/check', [AiReviewController::class, 'check']);
    Route::post('ai/reviews/decision', [AiReviewController::class, 'decision']);

    // Capability #17 - Team Activity Digest (manager-facing summary of the
    // business's OWN activity log rows; counts are computed in PHP, never by
    // the model, and the digest is never a performance assessment).
    Route::get('ai/activity/digest', [AiTeamActivityController::class, 'digest']);
    Route::get('ai/activity/history', [AiTeamActivityController::class, 'history']);

    // Capability #6 - Anomaly & Leak Detection (deterministic rules + AI narration).
    Route::get('ai/anomalies', [AiAnomalyController::class, 'index']);
    Route::post('ai/anomalies/dismiss', [AiAnomalyController::class, 'dismiss']);
    // Capability #13 - Order & Delivery Q&A (grounded in the caller's own orders).
    Route::post('ai/orders/ask', [AiOrderQaController::class, 'ask']);
    // Capability #7 - Smart Expense Categorisation (read-only preview; saves
    // go through the existing POST office-expenses workflow).
    Route::post('ai/expenses/parse', [AiExpenseParseController::class, 'parse']);
    // Capability #8 - Advertisement & Product-Copy Generator (read-only draft;
    // publishing goes through the existing POST matangazo workflow).
    Route::post('ai/ads/generate', [AiAdCopyController::class, 'generate']);
    // Capability #9 - Customer-Reply Drafting (read-only draft; sending goes
    // through the existing seller order status/notes workflow).
    Route::post('ai/replies/draft', [AiReplyController::class, 'draft']);
    // Capability #10 - Seller Copilot Q&A (multi-turn; read-only tools from
    // capabilities #3-#7; conversation history is business+user scoped).
    Route::post('ai/copilot/chat', [AiCopilotController::class, 'chat']);
    Route::get('ai/copilot/conversations', [AiCopilotController::class, 'conversations']);
    Route::delete('ai/copilot/conversations', [AiCopilotController::class, 'destroyAll']);
    Route::get('ai/copilot/conversations/{conversationId}', [AiCopilotController::class, 'show']);
    Route::delete('ai/copilot/conversations/{conversationId}', [AiCopilotController::class, 'destroy']);

    // Capability #14 - recommendation dismissals + relevance feedback
    // (per-account state with a TTL; never shared between customers).
    Route::post('ai/recommendations/dismiss', [AiRecommendationController::class, 'dismiss']);
    Route::post('ai/recommendations/feedback', [AiRecommendationController::class, 'feedback']);

    // Capability #25 - Schema-Free Natural-Language Data Entry. One sentence in,
    // one editable preview out; the commit step routes the CONFIRMED record
    // through the existing POST products / sales / office-expenses / customers
    // endpoints (its own controller owns no inventory, sales or expense logic),
    // requires `confirm: true`, a staff role and a stable idempotency key.
    Route::get('ai/entry/examples', [AiNlEntryController::class, 'examples']);
    Route::post('ai/entry/preview', [AiNlEntryController::class, 'preview']);
    Route::post('ai/entry/commit', [AiNlEntryController::class, 'commit']);

    Route::post('inventory/ai-import', [AiImportController::class, 'import']);
    Route::post('inventory/ai-import/verify', [AiImportController::class, 'verify']);
    Route::post('sales/ai-import', [AiImportController::class, 'salesImport']);
    Route::post('sales/ai-commit', [AiImportController::class, 'commitAiSale']);

    Route::get('products/my', [ProductController::class, 'my']);
    Route::get('products/dummy', [ProductController::class, 'dummy']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'destroy']);
    // Static segment first: Laravel prefers it over the {businessName} pattern
    // below. Lets the admin screens ask for their own catalogue without ever
    // putting a business name in the URL.
    Route::get('business/my/all-products', [ProductController::class, 'businessAllProducts']);
    Route::get('business/{businessName}/all-products', [ProductController::class, 'businessAllProducts']);

    Route::get('sales/my', [SaleController::class, 'my']);
    Route::post('sales', [SaleController::class, 'store']);
    Route::put('sales/{id}', [SaleController::class, 'update']);
    Route::get('customers/my', [CustomerController::class, 'my']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::put('customers/{id}', [CustomerController::class, 'update']);

    Route::get('matangazo/my', [AdvertisementController::class, 'my']);
    Route::post('matangazo', [AdvertisementController::class, 'store']);
    Route::delete('matangazo/{id}', [AdvertisementController::class, 'destroy']);

    Route::get('reactions/user/likes', [ReactionController::class, 'userLikes']);
    Route::post('reactions/like', [ReactionController::class, 'like']);
    Route::post('reactions/report', [ReactionController::class, 'report']);
    Route::get('reactions/matangazo/{matangazo_id}/counts', [ReactionController::class, 'counts']);

    Route::get('revenue/my', [RevenueController::class, 'my']);

    Route::get('user/activity', [UserActivityController::class, 'index']);

    Route::post('office-expenses', [ExpenseController::class, 'store']);
    Route::get('office-expenses/today', [ExpenseController::class, 'today']);
    Route::get('office-expenses/date/{date}', [ExpenseController::class, 'byDate']);
    Route::get('office-expenses/range', [ExpenseController::class, 'range']);
    Route::get('office-expenses/categories', [ExpenseController::class, 'categories']);
    Route::delete('office-expenses/{id}', [ExpenseController::class, 'destroy']);

    Route::get('profit/daily/{date?}', [ProfitController::class, 'daily']);
    Route::get('profit/monthly/{year}/{month}', [ProfitController::class, 'monthly']);
    Route::get('debug/expenses/{date}', [DebugController::class, 'expenses']);

    Route::get('payments/my', [PaymentController::class, 'my']);
    Route::get('payments/status/{order_tracking_id}', [PaymentController::class, 'statusByTracking']);
    Route::get('payments/{id}', [PaymentController::class, 'show']);
    Route::post('payments/record', [PaymentController::class, 'record']);
    Route::post('payments/pesapal/initiate', [PaymentController::class, 'initiate']);
    Route::get('payments/pesapal/status/{order_tracking_id}', [PaymentController::class, 'pesapalStatus']);

    // Seller order management (business scoped server-side)
    Route::get('seller/orders', [OrderController::class, 'sellerIndex']);
    Route::get('seller/orders/{id}', [OrderController::class, 'sellerShow']);
    Route::put('seller/orders/{id}/status', [OrderController::class, 'sellerUpdateStatus']);
    Route::post('seller/orders/{id}/notes', [OrderController::class, 'sellerAddNote']);

    // Super Admin: inquiries + review publishing + business certification
    Route::get('system-admin/inquiries', [InquiryController::class, 'adminIndex']);
    Route::get('system-admin/inquiries/{id}', [InquiryController::class, 'adminShow']);
    Route::put('system-admin/inquiries/{id}/reviewed', [InquiryController::class, 'markReviewed']);
    Route::post('system-admin/inquiries/{id}/publish', [InquiryController::class, 'publish']);
    Route::post('system-admin/inquiries/{id}/unpublish', [InquiryController::class, 'unpublish']);
    // Capability #18 - Inquiry Triage & Auto-Draft Replies (Super Admin only).
    // Classification, urgency and routing are computed in PHP; the draft is
    // composed from approved help content only, and a reply is sent ONLY after
    // an explicit human approval (or under the narrow opt-in auto-send policy).
    Route::get('ai/inquiries/queue', [AiInquiryTriageController::class, 'queue']);
    Route::post('ai/inquiries/{id}/triage', [AiInquiryTriageController::class, 'triage']);
    Route::post('ai/inquiries/{id}/reply', [AiInquiryTriageController::class, 'reply']);

    // Capability #19 - Certification & Onboarding Assistant.
    // The certification review is an EVIDENCE checklist for Super Admins: it
    // reads real business/member/product rows and never certifies anything (the
    // human decision stays on PUT /api/system-admin/businesses/{id}/certify).
    // The onboarding guide is for the signed-in user's OWN account and answers
    // only from the approved onboarding content.
    Route::get('ai/certification/queue', [AiCertificationController::class, 'queue']);
    Route::post('ai/certification/{id}/review', [AiCertificationController::class, 'review']);
    Route::get('ai/onboarding/guide', [AiOnboardingController::class, 'guide']);
    Route::post('ai/onboarding/ask', [AiOnboardingController::class, 'ask']);

    // Capability #20 - Natural-Language Admin Analytics (STRICT system admin).
    // The question selects one allow-listed read-only metric computed in PHP
    // from bounded, time-filtered reads over the platform's own tables; the
    // model may only pick from that allow-list and write one acknowledgement
    // sentence. No SQL is ever generated and every answer is platform-wide.
    Route::post('ai/platform-analytics/ask', [AiAdminAnalyticsController::class, 'ask']);
    Route::get('ai/platform-analytics/examples', [AiAdminAnalyticsController::class, 'examples']);

    Route::get('system-admin/businesses', [SystemAdminController::class, 'businesses']);
    Route::put('system-admin/businesses/{id}/certify', [SystemAdminController::class, 'certify']);

    Route::get('admin/users', [AdminController::class, 'users']);
    Route::get('admin/products', [AdminController::class, 'products']);
    Route::get('admin/sales', [AdminController::class, 'sales']);
    Route::get('admin/customers', [AdminController::class, 'customers']);
    Route::get('admin/stats', [AdminController::class, 'stats']);
    Route::get('admin/payments', [PaymentController::class, 'adminIndex']);
    Route::get('admin/logs', [AdminController::class, 'logs']);
    Route::get('admin/logs/search', [AdminController::class, 'logsSearch']);
    Route::delete('admin/logs/cleanup', [AdminController::class, 'logsCleanup']);
    Route::delete('admin/users/{id}', [AdminController::class, 'destroyUser']);
    Route::put('admin/users/{id}/status', [AdminController::class, 'updateUserStatus']);
    Route::get('admin/online-users', [AdminController::class, 'onlineUsers']);
    Route::get('admin/real-time-stats', [AdminController::class, 'realTimeStats']);
    Route::get('admin/user/{id}/presence-history', [AdminController::class, 'presenceHistory']);
    Route::put('admin/user/{id}/presence', [AdminController::class, 'updatePresence']);
    Route::get('admin/ws-status', [AdminController::class, 'wsStatus']);
    Route::post('admin/migrate/realtime', [AdminController::class, 'migrateRealtime']);
    Route::post('admin/migrate/password-reset', [AdminController::class, 'migratePasswordReset']);
    Route::get('admin/database-info', [AdminController::class, 'databaseInfo']);

    Route::post('admin/setup/notifications', [NotificationController::class, 'setup']);
    Route::post('admin/setup/notifications-read-state', [NotificationController::class, 'setupReadState']);
    Route::get('admin/notifications', [NotificationController::class, 'index']);
    Route::post('admin/notifications', [NotificationController::class, 'store']);
    Route::get('admin/notifications/stats', [NotificationController::class, 'stats']);
    Route::get('admin/notifications/check', [NotificationController::class, 'check']);
    Route::post('admin/notifications/test', [NotificationController::class, 'test']);
    Route::post('admin/notifications/read-state', [NotificationController::class, 'readState']);
    Route::post('admin/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
});
