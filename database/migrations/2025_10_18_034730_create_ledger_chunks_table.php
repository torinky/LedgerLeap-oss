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
        Schema::create('ledger_chunks', function (Blueprint $table) {
            $table->engine = 'Mroonga';
            $table->id();
            $table->unsignedBigInteger('ledger_id')->index();
            $table->unsignedBigInteger('ledger_define_id')->index();
            $table->unsignedBigInteger('folder_id')->index();

            $table->unsignedInteger('chunk_index');
            $table->text('chunk_text');
            $table->enum('chunk_source', ['content', 'content_attached']);

            // Get embedding dimension from config
            $activeModel = config('rag.model.active', 'all-minilm-l6-v2');
            $dimension = config('rag.model.available_models.' . $activeModel . '.dimension', 384);
            $embeddingColumnSize = $dimension * 4; // float32 is 4 bytes

            $table->binary('embedding', $embeddingColumnSize)->nullable();

            $table->timestamps();

            $table->index(['ledger_id', 'chunk_index']);
            
            // Add full-text index for chunk_text for hybrid search in the future
            $table->fullText('chunk_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_chunks');
    }
};