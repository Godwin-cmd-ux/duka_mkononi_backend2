<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of supabase/migrations/20261105000001_create_services_and_service_sales.sql.
 *
 * Application data lives in Supabase, so the authoritative DDL is the SQL
 * migration in supabase/migrations (run it in the SQL Editor). This mirror
 * keeps a fresh local/CI database structurally identical, exactly like the
 * preferred_theme migration does.
 *
 * Foreign keys to `products`, `users` and `businesses` are intentionally not
 * declared here: those tables are owned by Supabase and may not exist on a
 * bare local database. The real constraints live in the SQL migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'name']);
                $table->index(['business_id', 'status']);
            });
        }

        if (! Schema::hasTable('service_consumables')) {
            Schema::create('service_consumables', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('service_id');
                $table->uuid('product_id');
                $table->decimal('required_quantity', 14, 3)->default(1);
                $table->timestamps();

                $table->unique(['service_id', 'product_id']);
                $table->index('product_id');
            });
        }

        if (! Schema::hasTable('service_sales')) {
            Schema::create('service_sales', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('invoice_number')->unique();
                $table->uuid('business_id');
                $table->uuid('branch_id')->nullable();
                $table->uuid('seller_id');
                $table->uuid('service_id')->nullable();
                $table->string('service_name');
                $table->decimal('service_quantity', 14, 3)->default(1);
                $table->decimal('selling_price', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->decimal('profit', 14, 2)->default(0);
                $table->uuid('customer_id')->nullable();
                $table->date('sale_date');
                $table->string('payment_method')->default('cash');
                $table->string('status')->default('completed');
                $table->text('notes')->nullable();
                $table->string('client_key')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'sale_date']);
                $table->index(['seller_id', 'sale_date']);
                $table->index('service_id');
                $table->unique(['seller_id', 'client_key']);
            });
        }

        if (! Schema::hasTable('service_sale_items')) {
            Schema::create('service_sale_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('service_sale_id');
                $table->uuid('product_id')->nullable();
                $table->string('product_name');
                $table->decimal('quantity', 14, 3);
                $table->decimal('buying_price', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->timestamp('created_at')->nullable();

                $table->index('service_sale_id');
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_sale_items');
        Schema::dropIfExists('service_sales');
        Schema::dropIfExists('service_consumables');
        Schema::dropIfExists('services');
    }
};
