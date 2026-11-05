<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_blocks')) {
            Schema::create('business_blocks', function (Blueprint $table) {
                $table->string('business_id')->primary();
                $table->string('blocked_by')->nullable();
                $table->timestamp('blocked_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_blocks');
    }
};
