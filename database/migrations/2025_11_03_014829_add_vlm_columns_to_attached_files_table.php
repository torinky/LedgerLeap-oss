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
            $table->timestamp('tika_processed_at')->nullable()->comment('Tika処理完了日時')->after('vlm_processed_at');
            $table->timestamp('vlm_failed_at')->nullable()->comment('VLM処理失敗日時')->after('tika_processed_at');
            $table->timestamp('ocr_processed_at')->nullable()->comment('OCR処理完了日時')->after('vlm_failed_at');
            $table->timestamp('ocr_failed_at')->nullable()->comment('OCR処理失敗日時')->after('ocr_processed_at');
            $table->timestamp('processing_finalized_at')->nullable()->comment('最終化処理完了日時')->after('ocr_failed_at');
            $table->string('finalized_source', 20)->nullable()->comment('最終化時の採用ソース（vlm/ocr/tika）')->after('processing_finalized_at');

            // Phase5: 最終化検索用インデックス
            $table->index('processing_finalized_at', 'idx_processing_finalized_at');
            $table->index(['tika_processed_at', 'processing_finalized_at'], 'idx_ready_for_finalization');
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
            $table->dropIndex('idx_ready_for_finalization');
            $table->dropIndex('idx_processing_finalized_at');

            // Phase5で追加したカラムを削除
            $table->dropColumn([
                'tika_processed_at',
                'vlm_failed_at',
                'ocr_processed_at',
                'ocr_failed_at',
                'processing_finalized_at',
                'finalized_source',
            ]);
        });
    }
};
