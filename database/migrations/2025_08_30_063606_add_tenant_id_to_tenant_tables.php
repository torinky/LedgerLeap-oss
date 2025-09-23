<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('ledgers', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->index('tenant_id');
            }
        });

        Schema::table('ledger_defines', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_defines', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            }
        });

        Schema::table('folders', function (Blueprint $table) {
            if (!Schema::hasColumn('folders', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            }
        });

        Schema::table('ledger_diffs', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_diffs', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->index('tenant_id');
            }
        });

        Schema::table('attached_files', function (Blueprint $table) {
            if (!Schema::hasColumn('attached_files', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            }
        });

        Schema::table('tags', function (Blueprint $table) {
            if (!Schema::hasColumn('tags', 'tenant_id')) {
                $table->string('tenant_id')->after('id')->nullable();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            }
        });

        Schema::table('activity_log', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_log', 'tenant_id')) {
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
