<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('email')->nullable();
                $table->text('password')->nullable();
                $table->text('role')->default('client');
                $table->text('status')->default('pending');
                $table->text('full_name')->nullable();
                $table->text('phone')->nullable();
                $table->text('business_name')->nullable();
                $table->text('business_location')->nullable();
                $table->text('business_logo_url')->nullable();
                $table->float('business_latitude')->nullable();
                $table->float('business_longitude')->nullable();
                $table->text('business_type')->nullable();
                $table->text('business_description')->nullable();
                $table->text('previous_status')->nullable();
                $table->boolean('is_online')->default(false);
                $table->text('last_seen')->nullable();
                $table->text('session_id')->nullable();
                $table->integer('connection_count')->default(0);
                $table->integer('total_online_time')->default(0);
                $table->text('language')->default('sw');
                $table->timestamp('last_login')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('email');
                $table->index('role');
                $table->index('status');
                $table->index('business_name');
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('seller_id')->index();
                $table->text('name');
                $table->text('description')->nullable();
                $table->text('category')->nullable();
                $table->float('price')->nullable();
                $table->float('cost_price')->nullable();
                $table->integer('stock')->nullable();
                $table->integer('min_stock_level')->default(0);
                $table->text('sku')->nullable();
                $table->text('barcode')->nullable();
                $table->text('unit')->nullable();
                $table->float('weight')->nullable();
                $table->text('dimensions')->nullable();
                $table->text('image_url')->nullable();
                $table->text('gallery_urls')->nullable();
                $table->boolean('is_active')->default(true);
                $table->float('expected_selling_price')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('category');
            });
        }

        if (!Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('invoice_number')->nullable();
                $table->text('seller_id')->index();
                $table->text('customer_id')->nullable();
                $table->date('sale_date')->nullable();
                $table->time('sale_time')->nullable();
                $table->float('total_amount')->nullable();
                $table->float('discount')->default(0);
                $table->float('tax_amount')->default(0);
                $table->text('payment_method')->nullable();
                $table->text('payment_status')->nullable();
                $table->text('notes')->nullable();
                $table->text('sale_type')->nullable();
                $table->text('shipping_address')->nullable();
                $table->float('shipping_cost')->default(0);
                $table->text('delivery_status')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('sale_date');
            });
        }

        if (!Schema::hasTable('sale_items')) {
            Schema::create('sale_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('sale_id')->index();
                $table->text('product_id')->nullable();
                $table->integer('quantity')->nullable();
                $table->float('unit_price')->nullable();
                $table->float('total_price')->nullable();
                $table->float('item_discount')->default(0);
                $table->text('discount_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('seller_id')->index();
                $table->text('name')->nullable();
                $table->text('phone')->nullable();
                $table->text('email')->nullable();
                $table->text('address')->nullable();
                $table->text('customer_type')->nullable();
                $table->float('total_purchases')->default(0);
                $table->integer('purchases_count')->default(0);
                $table->timestamp('last_purchase_date')->nullable();
                $table->float('avg_purchase_amount')->default(0);
                $table->integer('loyalty_points')->default(0);
                $table->float('credit_limit')->default(0);
                $table->float('outstanding_balance')->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->text('tax_number')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('user_logs')) {
            Schema::create('user_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index()->nullable();
                $table->text('action');
                $table->text('endpoint')->nullable();
                $table->text('details')->nullable();
                $table->text('ip_address')->nullable();
                $table->text('status')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('password_reset_codes')) {
            Schema::create('password_reset_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index()->nullable();
                $table->text('email');
                $table->text('reset_code');
                $table->boolean('is_used')->default(false);
                $table->timestamp('expires_at');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('used_at')->nullable();
            });
        }

        if (!Schema::hasTable('pending_emails')) {
            Schema::create('pending_emails', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('to');
                $table->text('subject');
                $table->text('content');
                $table->text('status')->default('pending');
                $table->text('error')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('matangazo')) {
            Schema::create('matangazo', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index();
                $table->text('title')->nullable();
                $table->text('description')->nullable();
                $table->text('media_url')->nullable();
                $table->text('media_type')->default('image');
                $table->text('thumbnail_url')->nullable();
                $table->boolean('is_free')->default(true);
                $table->float('price_amount')->nullable();
                $table->text('payment_status')->default('pending');
                $table->text('order_tracking_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('category')->nullable();
                $table->text('tags')->nullable();
                $table->integer('views_count')->default(0);
                $table->integer('clicks_count')->default(0);
                $table->integer('shares_count')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('payment_status');
            });
        }

        if (!Schema::hasTable('reactions')) {
            Schema::create('reactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index();
                $table->text('matangazo_id')->index();
                $table->text('reaction_type')->nullable();
                $table->text('report_reason')->nullable();
                $table->text('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('title')->nullable();
                $table->text('message')->nullable();
                $table->text('notification_type')->nullable();
                $table->text('recipients')->nullable();
                $table->text('sender_id')->nullable();
                $table->text('sender_name')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->text('status')->nullable();
                $table->text('delivery_method')->nullable();
                $table->boolean('email_sent')->default(false);
                $table->boolean('push_sent')->default(false);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index();
                $table->text('business_name')->nullable();
                $table->text('order_tracking_id')->index()->nullable();
                $table->text('merchant_reference')->nullable();
                $table->text('matangazo_id')->nullable();
                $table->text('description')->nullable();
                $table->float('amount')->nullable();
                $table->text('amount_currency')->default('TZS');
                $table->text('status')->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('user_presence_history')) {
            Schema::create('user_presence_history', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index();
                $table->text('session_id')->nullable();
                $table->text('status')->nullable();
                $table->text('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->text('device_info')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('active_sessions')) {
            Schema::create('active_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index();
                $table->text('session_id')->nullable();
                $table->text('device_info')->nullable();
                $table->text('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('disconnected_at')->nullable();
                $table->integer('duration_seconds')->default(0);
            });
        }

        if (!Schema::hasTable('ai_import_verifications')) {
            Schema::create('ai_import_verifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index();
                $table->text('business_name')->nullable();
                $table->integer('total_products')->default(0);
                $table->integer('duplicates_skipped')->default(0);
                $table->integer('new_products')->default(0);
                $table->text('source_name')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('office_expenses')) {
            Schema::create('office_expenses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->text('user_id')->index();
                $table->text('business_name')->nullable();
                $table->text('expense_date')->nullable();
                $table->float('amount')->nullable();
                $table->text('description')->nullable();
                $table->text('category')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->text('user_id')->nullable()->index();
                $table->text('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: never drop existing tables.
    }
};