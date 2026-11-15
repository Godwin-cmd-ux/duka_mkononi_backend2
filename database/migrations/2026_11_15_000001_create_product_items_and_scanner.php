<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of supabase/migrations/20261115000001_create_product_items_and_scanner.sql.
 *
 * The authoritative DDL lives in the Supabase migration. This mirror exists
 * only so the local SQLite test schema can be built; it is not deployed to
 * production and it does not install the PostgreSQL RPC functions (the tests
 * fake those over HTTP, exactly like the existing stock/fridge tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scanner_receiving_sessions')) {
            Schema::create('scanner_receiving_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable()->index();
                $table->uuid('product_id')->index();
                $table->text('status')->default('open');
                $table->integer('scanned_count')->default(0);
                $table->text('completed_reference')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_items')) {
            Schema::create('product_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable()->index();
                $table->uuid('product_id')->index();
                $table->text('qr_token');
                $table->text('status')->default('pending');
                $table->text('location')->nullable();
                $table->uuid('fridge_id')->nullable();
                $table->uuid('receiving_session_id')->nullable()->index();
                $table->text('receipt_reference')->nullable();
                $table->uuid('sale_id')->nullable()->index();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('sold_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
                // A physical item is identified by its token in the same way
                // the Postgres unique index on lower(btrim(qr_token)) does.
                $table->unique(['qr_token']);
            });
        }

        if (! Schema::hasTable('product_item_events')) {
            Schema::create('product_item_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable();
                $table->uuid('product_id')->index();
                $table->uuid('product_item_id')->index();
                $table->text('event_type');
                $table->text('from_status')->nullable();
                $table->text('to_status')->nullable();
                $table->text('from_location')->nullable();
                $table->text('to_location')->nullable();
                $table->uuid('fridge_id')->nullable();
                $table->text('reference_type')->nullable();
                $table->uuid('reference_id')->nullable();
                $table->uuid('actor_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('product_item_reservations')) {
            Schema::create('product_item_reservations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('product_item_id')->index();
                $table->uuid('business_id')->index();
                $table->uuid('branch_id')->nullable();
                $table->uuid('product_id')->index();
                $table->uuid('user_id')->nullable();
                $table->text('client_ref');
                $table->text('kind')->default('sale');
                $table->timestamp('expires_at');
                $table->timestamp('released_at')->nullable();
                $table->text('release_reason')->nullable();
                $table->uuid('sale_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_item_reservations');
        Schema::dropIfExists('product_item_events');
        Schema::dropIfExists('product_items');
        Schema::dropIfExists('scanner_receiving_sessions');
    }
};
