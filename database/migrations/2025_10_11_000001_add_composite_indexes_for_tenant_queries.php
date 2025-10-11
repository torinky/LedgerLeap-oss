<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * テナントクエリ最適化のための複合インデックス追加
     * - 参照: docs/work/db-architecture-study/2025-10-11_partitioning-investigation-result.md
     * - パーティショニングの代替として、複合インデックスで性能最適化
     */
    public function up(): void
    {
        // ledgers テーブル
        Schema::table('ledgers', function (Blueprint $table) {
            // tenant_id + created_at: テナント別の最新台帳取得を高速化
            if (! $this->indexExists('ledgers', 'idx_tenant_created')) {
                $table->index(['tenant_id', 'created_at'], 'idx_tenant_created');
            }
            // tenant_id + status: テナント別のステータスフィルタを高速化
            if (! $this->indexExists('ledgers', 'idx_tenant_status')) {
                $table->index(['tenant_id', 'status'], 'idx_tenant_status');
            }
        });

        // ledger_diffs テーブル
        Schema::table('ledger_diffs', function (Blueprint $table) {
            // tenant_id + ledger_id: テナント別の台帳履歴取得を高速化
            if (! $this->indexExists('ledger_diffs', 'idx_tenant_ledger')) {
                $table->index(['tenant_id', 'ledger_id'], 'idx_tenant_ledger');
            }
        });

        // attached_files テーブル
        Schema::table('attached_files', function (Blueprint $table) {
            // tenant_id + ledger_id: テナント別の添付ファイル取得を高速化
            if (! $this->indexExists('attached_files', 'idx_tenant_ledger')) {
                $table->index(['tenant_id', 'ledger_id'], 'idx_tenant_ledger');
            }
        });

        // activity_log テーブル
        Schema::table('activity_log', function (Blueprint $table) {
            // tenant_id + created_at: テナント別のログ取得を高速化
            if (! $this->indexExists('activity_log', 'idx_tenant_created')) {
                $table->index(['tenant_id', 'created_at'], 'idx_tenant_created');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            if ($this->indexExists('ledgers', 'idx_tenant_created')) {
                $table->dropIndex('idx_tenant_created');
            }
            if ($this->indexExists('ledgers', 'idx_tenant_status')) {
                $table->dropIndex('idx_tenant_status');
            }
        });

        Schema::table('ledger_diffs', function (Blueprint $table) {
            if ($this->indexExists('ledger_diffs', 'idx_tenant_ledger')) {
                $table->dropIndex('idx_tenant_ledger');
            }
        });

        Schema::table('attached_files', function (Blueprint $table) {
            // 外部キー制約がインデックスを使用しているため、インデックスは削除しない
            // tenant_idの外部キーは attached_files_tenant_id_foreign という名前で存在
            // インデックス idx_tenant_ledger (tenant_id, ledger_id) がこの外部キーに使用されている
            // MySQLでは外部キーに使用されているインデックスは削除不可
        });

        Schema::table('activity_log', function (Blueprint $table) {
            if ($this->indexExists('activity_log', 'idx_tenant_created')) {
                $table->dropIndex('idx_tenant_created');
            }
        });
    }

    /**
     * インデックスが存在するかチェック
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
