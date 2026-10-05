<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdvertisementController;
use App\Http\Controllers\Api\AiHealthController;
use App\Http\Controllers\Api\AiImportController;
use App\Http\Controllers\Api\AiPricingController;
use App\Http\Controllers\Api\AiReportController;
use App\Http\Controllers\Api\AiRestockController;
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
use App\Http\Controllers\Api\TempDumpController;
use App\Http\Controllers\Api\UserActivityController;
use Illuminate\Support\Facades\Route;

Route::get('health', [MiscController::class, 'health']);
Route::get('test', [MiscController::class, 'test']);

// TEMPORARY: schema/data dump for SYSTEM_SCHEMA.sql + SYSTEM_SEED.sql. Delete after use.
Route::get('temp/schema', [TempDumpController::class, 'schema']);
Route::get('temp/tables', [TempDumpController::class, 'tables']);
Route::get('temp/dump/{table}', [TempDumpController::class, 'dump']);
Route::get('temp/count/{table}', [TempDumpController::class, 'count']);

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
Route::get('debug/check-user/{email}', [DebugController::class, 'checkUser']);
Route::get('debug/check-business', [DebugController::class, 'checkBusiness']);

Route::middleware('auth.jwt')->group(function () {
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
