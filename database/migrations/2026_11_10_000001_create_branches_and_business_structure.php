<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local mirror of supabase/migrations/20261110000001_create_branches_and_business_structure.sql.
 *
 * Application data lives in Supabase, so the authoritative DDL is the SQL
 * migration in supabase/migrations (run it in the Supabase SQL Editor). This
 * mirror keeps a fresh local/CI database structurally identical.
 *
 * Foreign keys to `businesses` and `users` are intentionally not declared
 * here: those tables are owned by Supabase and may not exist on a bare local
 * database. The real constraints live in the SQL migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'business_structure')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->string('business_structure')->default('non_branch');
                $table->index('business_structure');
            });
        }

        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('business_id');
                $table->string('branch_name');
                $table->text('location')->nullable();
                $table->double('latitude')->nullable();
                $table->double('longitude')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();

                $table->index(['business_id', 'status']);
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'business_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('business_id')->nullable();
            });
        }

        $this->addBranchColumn('users');
        $this->addBranchColumn('products');
        $this->addBranchColumn('sales');
        $this->addBranchColumn('customers');
        $this->addBranchColumn('services');
        $this->addBranchColumn('service_sales');

        if (Schema::hasTable('office_expenses')) {
            Schema::table('office_expenses', function (Blueprint $table) {
                if (! Schema::hasColumn('office_expenses', 'business_id')) {
                    $table->uuid('business_id')->nullable();
                }
                if (! Schema::hasColumn('office_expenses', 'branch_id')) {
                    $table->uuid('branch_id')->nullable();
                }
                $table->index(['business_id', 'branch_id']);
            });
        }
    }

    private function addBranchColumn(string $tableName): void
    {
        if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'branch_id')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->uuid('branch_id')->nullable();
                $table->index('branch_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
