<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of supabase/migrations/20261114000004_add_stock_receipts.sql.
 *
 * The authoritative table, constraints and receive_product_stock RPC live in
 * the SQL migration. This mirror keeps tests and local development aware of
 * the ledger without pretending SQLite enforces PostgreSQL role checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_receipts')) {
            return;
        }

        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('product_id');
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('source')->default('manual');
            $table->string('receiving_reference');
            $table->text('notes')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
            $table->index(['branch_id', 'created_at']);
            $table->unique(['business_id', 'receiving_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipts');
    }
};
