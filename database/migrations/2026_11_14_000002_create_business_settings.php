<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of supabase/migrations/20261114000002_create_business_settings.sql.
 *
 * Application data lives in Supabase, so the authoritative DDL is the SQL
 * migration (run it in the Supabase SQL Editor). This mirror keeps a fresh
 * local/CI database structurally identical.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->uuid('business_id')->primary();
                $table->boolean('fridge_enabled')->default(true);
                $table->boolean('scanner_enabled')->default(false);
                $table->boolean('services_enabled')->default(true);
                $table->boolean('product_management_enabled')->default(false);
                $table->boolean('notify_low_stock')->default(true);
                $table->boolean('notify_fridge_stock')->default(true);
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
