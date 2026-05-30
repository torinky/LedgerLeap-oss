<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 第5版で簡素化：
     * - scoring_configs テーブルを削除（config/ledgerleap.phpで管理）
     * - ledger_defines.activity_score を削除（リアルタイム集計）
     * - ledgers.is_pinned を削除（既存機能に存在しない）
     * - ledgers.priority_level を削除（既存機能に存在しない）
     */
    public function up(): void
    {
        // 1. ledgers テーブルへのスコア保存用カラム追加
        Schema::table('ledgers', function (Blueprint $table) {
            if (! Schema::hasColumn('ledgers', 'activity_score')) {
                $table->integer('activity_score')->default(0)->after('content_attached');
            }
            if (! Schema::hasColumn('ledgers', 'composite_score')) {
                $table->decimal('composite_score', 10, 4)->default(0)->after('activity_score');
            }
            $table->index('composite_score');
        });

        // 2. activity_log テーブルへのインデックス追加
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['tenant_id', 'subject_type', 'subject_id', 'created_at'], 'idx_activity_for_scoring');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropIndex(['composite_score']);
            $table->dropColumn(['activity_score', 'composite_score']);
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('idx_activity_for_scoring');
        });
    }
};
