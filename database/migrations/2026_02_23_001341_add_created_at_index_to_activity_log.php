<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * activity_log の ORDER BY created_at DESC + EXISTS サブクエリ組み合わせ時の
     * sort_buffer_size 超過エラー対策として created_at 単独インデックスを追加。
     * 2段階クエリ（ID のみでソート → IN で本データ取得）と併用することで
     * ソート対象の行サイズを最小化する。
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            if (! $this->indexExists('activity_log', 'idx_activity_log_created_at')) {
                $table->index('created_at', 'idx_activity_log_created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            if ($this->indexExists('activity_log', 'idx_activity_log_created_at')) {
                $table->dropIndex('idx_activity_log_created_at');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
