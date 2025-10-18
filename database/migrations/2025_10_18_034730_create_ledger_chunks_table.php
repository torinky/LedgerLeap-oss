<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

            $table->timestamps();

            $table->index(['ledger_id', 'chunk_index']);
            
            // Add full-text index for chunk_text for hybrid search in the future
            $table->fullText('chunk_text');
        });

        // Add embedding column with raw SQL since Laravel doesn't directly support MEDIUMBLOB
        // MEDIUMBLOB can hold up to 16MB, sufficient for embeddings up to 4M dimensions
        // Common dimensions: 384 (1,536 bytes), 768 (3,072 bytes), 1024 (4,096 bytes)
        DB::statement('ALTER TABLE ledger_chunks ADD COLUMN embedding MEDIUMBLOB NULL AFTER chunk_source');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_chunks');
    }
};