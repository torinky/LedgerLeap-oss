<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * テナントIDカラムとインデックスの追加
     *
     * 複合インデックスは別マイグレーションで追加:
     * - 2025_10_11_000001_add_composite_indexes_for_tenant_queries.php
     *
     * 関連ドキュメント:
     * - docs/work/db-architecture-study/2025-10-09_physical-db-separation-architecture-study.md
     * - docs/work/db-architecture-study/2025-10-11_partitioning-investigation-result.md
     */
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            if (! Schema::hasColumn('ledgers', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->index('tenant_id');
            }
        });

        Schema::table('ledger_defines', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_defines', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            }
        });

        Schema::table('folders', function (Blueprint $table) {
            if (! Schema::hasColumn('folders', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            }
        });

        Schema::table('ledger_diffs', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_diffs', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->index('tenant_id');
            }
        });

        Schema::table('attached_files', function (Blueprint $table) {
            if (! Schema::hasColumn('attached_files', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            }
        });

        Schema::table('tags', function (Blueprint $table) {
            if (! Schema::hasColumn('tags', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            }
        });

        Schema::table('activity_log', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_log', 'tenant_id')) {
                $table->string('tenant_id')->after('log_name')->nullable();
                $table->index('tenant_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('ledgers', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('ledger_defines', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_defines', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('folders', function (Blueprint $table) {
            if (Schema::hasColumn('folders', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('ledger_diffs', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_diffs', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('attached_files', function (Blueprint $table) {
            if (Schema::hasColumn('attached_files', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('tags', function (Blueprint $table) {
            if (Schema::hasColumn('tags', 'tenant_id')) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });

        Schema::table('activity_log', function (Blueprint $table) {
            if (Schema::hasColumn('activity_log', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
