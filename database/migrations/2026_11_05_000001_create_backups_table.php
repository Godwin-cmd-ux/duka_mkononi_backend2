<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The log of database backups: one row per backup.
 *
 * A backup itself is two files in Cloudinary (schema + seed). This table is
 * the record the Super Admin's Backups page reads, and the row a delete
 * removes after Cloudinary has confirmed both files are gone.
 *
 * `stamp` is the identity: a plain date for the nightly backup, date plus time
 * for an induced one — which is what lets several backups exist for one day
 * without a re-run overwriting the night's snapshot.
 *
 * Non-destructive, like every migration here: an existing table (for example
 * one built from SYSTEM_SCHEMA.sql on Supabase) is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backups')) {
            return;
        }

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->date('backup_date');
            $table->string('kind')->default('automatic');
            $table->string('stamp')->unique();
            $table->text('folder');
            $table->text('schema_url')->nullable();
            $table->bigInteger('schema_bytes')->nullable();
            $table->text('seed_url')->nullable();
            $table->bigInteger('seed_bytes')->nullable();
            $table->integer('tables_count')->default(0);
            $table->integer('rows_count')->default(0);
            $table->decimal('seconds', 8, 2)->nullable();
            $table->string('status')->default('complete');
            $table->timestamps();

            $table->index('backup_date');
        });
    }

    public function down(): void
    {
        // Non-destructive: never drop tables (project convention).
    }
};
