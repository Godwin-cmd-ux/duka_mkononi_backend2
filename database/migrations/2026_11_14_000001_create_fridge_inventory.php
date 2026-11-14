<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of supabase/migrations/20261114000001_create_fridge_inventory.sql.
 *
 * The authoritative DDL lives in the Supabase migration. This mirror exists
 * only so the local SQLite test schema can be built; it is not deployed to
 * production and it does not install the PostgreSQL RPC functions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fridges')) {
            Schema::create('fridges', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable()->index();
                $table->text('name');
                $table->text('status')->default('active');
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fridge_stock')) {
            Schema::create('fridge_stock', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('fridge_id')->index();
                $table->uuid('product_id')->index();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable()->index();
                $table->decimal('quantity', 14, 3)->default(0);
                $table->decimal('min_stock', 14, 3)->default(0);
                $table->timestamps();
                $table->unique(['fridge_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('fridge_stock_movements')) {
            Schema::create('fridge_stock_movements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable()->index();
                $table->uuid('fridge_id')->nullable()->index();
                $table->uuid('product_id')->index();
                $table->text('movement_type');
                $table->decimal('quantity', 14, 3);
                $table->text('source_location');
                $table->text('destination_location');
                $table->text('reference_type')->nullable();
                $table->uuid('reference_id')->nullable();
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('sale_item_fridge_allocations')) {
            Schema::create('sale_item_fridge_allocations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('sale_id')->index();
                $table->uuid('sale_item_id')->nullable()->index();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable()->index();
                $table->uuid('product_id')->index();
                $table->uuid('fridge_id')->nullable()->index();
                $table->text('fridge_name')->nullable();
                $table->decimal('quantity', 14, 3);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('fridge_stock_alerts')) {
            Schema::create('fridge_stock_alerts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable()->index();
                $table->uuid('fridge_id')->nullable()->index();
                $table->uuid('product_id')->index();
                $table->text('alert_type');
                $table->decimal('threshold', 14, 3)->nullable();
                $table->decimal('quantity', 14, 3)->nullable();
                $table->text('status')->default('open');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'min_outside_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('min_outside_stock', 14, 3)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fridge_stock_alerts');
        Schema::dropIfExists('sale_item_fridge_allocations');
        Schema::dropIfExists('fridge_stock_movements');
        Schema::dropIfExists('fridge_stock');
        Schema::dropIfExists('fridges');
    }
};
