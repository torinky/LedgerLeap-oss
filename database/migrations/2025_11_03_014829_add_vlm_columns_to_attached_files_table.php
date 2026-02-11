<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase4でVLMカラム追加済み前提
     * Phase5で並列処理用タイムスタンプを追加
     *
     * 【重要】このマイグレーションは冪等性を持つように実装されています。
     * - カラム追加前に存在確認を行い、既存環境でも安全に実行可能
     * - after() 句は動的に決定され、依存カラムが存在しない場合にも対応
     *
     * 詳細: docs/development/Testing-Best-Practices.md#-マイグレーション管理とトラブルシューティング
     * 履歴: docs/work/testing/2026-02-11_migration-deadlock-troubleshooting.md
     */
    public function up(): void
    {
        Schema::table('attached_files', function (Blueprint $table) {
            // Phase4: VLM関連カラム（既に存在している前提）
            if (! Schema::hasColumn('attached_files', 'vlm_markdown')) {
                $table->longText('vlm_markdown')->nullable()->comment('VLM抽出Markdown結果（RAG用）')->after('original_file_path');
                $table->json('vlm_structured_data')->nullable()->comment('VLM構造化データ（エンティティ、テーブル等）')->after('vlm_markdown');
                $table->string('vlm_model', 100)->nullable()->comment('使用VLMモデル名')->after('vlm_structured_data');
                $table->decimal('vlm_confidence', 4, 3)->nullable()->comment('VLM処理信頼度（0.000-1.000）')->after('vlm_model');
                $table->unsignedInteger('vlm_processing_time_ms')->nullable()->comment('VLM処理時間（ミリ秒）')->after('vlm_confidence');
                $table->timestamp('vlm_processed_at')->nullable()->comment('VLM処理完了日時')->after('vlm_processing_time_ms');

                $table->index('vlm_model', 'idx_vlm_model');
                $table->index('vlm_processed_at', 'idx_vlm_processed_at');
                $table->index(['status', 'vlm_processed_at'], 'idx_status_vlm_processed');
            }

            // Phase5: 並列処理統合用タイムスタンプ
            // vlm_processed_atの存在確認して適切な位置に追加
            $afterColumn = Schema::hasColumn('attached_files', 'vlm_processed_at') ? 'vlm_processed_at' : 'original_file_path';

            if (! Schema::hasColumn('attached_files', 'tika_processed_at')) {
                $table->timestamp('tika_processed_at')->nullable()->comment('Tika処理完了日時')->after($afterColumn);
            }
            if (! Schema::hasColumn('attached_files', 'vlm_failed_at')) {
                $table->timestamp('vlm_failed_at')->nullable()->comment('VLM処理失敗日時')->after('tika_processed_at');
            }
            if (! Schema::hasColumn('attached_files', 'ocr_processed_at')) {
                $table->timestamp('ocr_processed_at')->nullable()->comment('OCR処理完了日時')->after('vlm_failed_at');
            }
            if (! Schema::hasColumn('attached_files', 'ocr_failed_at')) {
                $table->timestamp('ocr_failed_at')->nullable()->comment('OCR処理失敗日時')->after('ocr_processed_at');
            }
            if (! Schema::hasColumn('attached_files', 'processing_finalized_at')) {
                $table->timestamp('processing_finalized_at')->nullable()->comment('最終化処理完了日時')->after('ocr_failed_at');
            }
            if (! Schema::hasColumn('attached_files', 'finalized_source')) {
                $table->string('finalized_source', 20)->nullable()->comment('最終化時の採用ソース（vlm/ocr/tika）')->after('processing_finalized_at');
            }

            // Phase5: 最終化検索用インデックス
            if (! Schema::hasIndex('attached_files', 'idx_processing_finalized_at')) {
                $table->index('processing_finalized_at', 'idx_processing_finalized_at');
            }
            if (! Schema::hasIndex('attached_files', 'idx_ready_for_finalization')) {
                $table->index(['tika_processed_at', 'processing_finalized_at'], 'idx_ready_for_finalization');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Phase5で追加したカラム・インデックスのみを削除
     * Phase4のVLMカラムは残す
     */
    public function down(): void
    {
        Schema::table('attached_files', function (Blueprint $table) {
            // Phase5で追加したインデックスを削除
            if (Schema::hasIndex('attached_files', 'idx_ready_for_finalization')) {
                $table->dropIndex('idx_ready_for_finalization');
            }
            if (Schema::hasIndex('attached_files', 'idx_processing_finalized_at')) {
                $table->dropIndex('idx_processing_finalized_at');
            }

            // Phase5で追加したカラムを削除
            $columnsToDelete = [];
            foreach (['tika_processed_at', 'vlm_failed_at', 'ocr_processed_at', 'ocr_failed_at', 'processing_finalized_at', 'finalized_source'] as $column) {
                if (Schema::hasColumn('attached_files', $column)) {
                    $columnsToDelete[] = $column;
                }
            }

            if (! empty($columnsToDelete)) {
                $table->dropColumn($columnsToDelete);
            }
        });
    }
};
