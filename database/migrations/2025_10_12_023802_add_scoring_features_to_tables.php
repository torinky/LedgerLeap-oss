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
        // 1. scoring_configs テーブルの新規作成
        Schema::create('scoring_configs', function (Blueprint $table) {
            $table->id();
            $table->string('profile_name', 50);
            $table->decimal('activity_weight', 3, 2)->default(0.33);
            $table->decimal('freshness_weight', 3, 2)->default(0.33);
            $table->decimal('importance_weight', 3, 2)->default(0.34);
            $table->decimal('relevance_weight', 3, 2)->default(0.00);
            $table->decimal('popularity_weight', 3, 2)->default(0.00);
            $table->boolean('is_system_default')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('tenant_id');
            $table->timestamps();

            $table->unique(['profile_name', 'tenant_id', 'user_id']);
            $table->index(['tenant_id', 'profile_name']);
        });

        // 2. ledgers テーブルへのカラム追加
        Schema::table('ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('ledgers', 'activity_score')) {
                $table->integer('activity_score')->default(0)->after('content_attached');
            }
            if (!Schema::hasColumn('ledgers', 'composite_score')) {
                $table->decimal('composite_score', 10, 4)->default(0)->after('activity_score');
            }
            if (!Schema::hasColumn('ledgers', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('composite_score');
            }
            if (!Schema::hasColumn('ledgers', 'priority_level')) {
                $table->tinyInteger('priority_level')->default(0)->after('is_pinned');
            }
            $table->index('composite_score');
        });

        // 3. ledger_defines テーブルへのカラム追加
        Schema::table('ledger_defines', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_defines', 'activity_score')) {
                // ★★★ ここを修正 ★★★
                $table->integer('activity_score')->default(0)->after('title');
            }
        });

        // 4. activity_log テーブルへのインデックス追加
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['tenant_id', 'subject_type', 'subject_id', 'created_at'], 'idx_activity_for_scoring');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_configs');

        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropIndex(['composite_score']);
            $table->dropColumn(['activity_score', 'composite_score', 'is_pinned', 'priority_level']);
        });

        Schema::table('ledger_defines', function (Blueprint $table) {
            $table->dropColumn('activity_score');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('idx_activity_for_scoring');
        });
    }
};
