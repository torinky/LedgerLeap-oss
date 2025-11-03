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
        Schema::table('attached_files', function (Blueprint $table) {
            $table->longText('vlm_markdown')->nullable()->comment('VLM抽出Markdown結果（RAG用）')->after('original_file_path');
            $table->json('vlm_structured_data')->nullable()->comment('VLM構造化データ（エンティティ、テーブル等）')->after('vlm_markdown');
            $table->string('vlm_model', 100)->nullable()->comment('使用VLMモデル名')->after('vlm_structured_data');
            $table->decimal('vlm_confidence', 4, 3)->nullable()->comment('VLM処理信頼度（0.000-1.000）')->after('vlm_model');
            $table->unsignedInteger('vlm_processing_time_ms')->nullable()->comment('VLM処理時間（ミリ秒）')->after('vlm_confidence');
            $table->timestamp('vlm_processed_at')->nullable()->comment('VLM処理完了日時')->after('vlm_processing_time_ms');

            $table->index('vlm_model', 'idx_vlm_model');
            $table->index('vlm_processed_at', 'idx_vlm_processed_at');
            $table->index(['status', 'vlm_processed_at'], 'idx_status_vlm_processed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attached_files', function (Blueprint $table) {
            $table->dropIndex('idx_status_vlm_processed');
            $table->dropIndex('idx_vlm_processed_at');
            $table->dropIndex('idx_vlm_model');
            
            $table->dropColumn([
                'vlm_markdown',
                'vlm_structured_data',
                'vlm_model',
                'vlm_confidence',
                'vlm_processing_time_ms',
                'vlm_processed_at',
            ]);
        });
    }
};