<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_keywords', function (Blueprint $table) {
            $table->engine = 'Mroonga';
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('keyword');
            $table->string('lemma')->nullable();
            $table->string('reading')->nullable();
            $table->string('pos', 64)->nullable();
            $table->string('pos_sub', 64)->nullable();
            $table->boolean('is_proper_noun')->default(false);
            $table->unsignedInteger('search_count')->default(1);
            $table->unsignedInteger('user_count')->default(1);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'keyword'], 'idx_tenant_keyword');
            $table->index(['tenant_id', 'pos', 'pos_sub'], 'idx_tenant_pos');
            $table->index(['tenant_id', 'is_proper_noun', 'search_count'], 'idx_tenant_proper_noun');
            $table->index(['tenant_id', 'search_count'], 'idx_tenant_count');
        });

        DB::statement('ALTER TABLE search_keywords ADD FULLTEXT index ft_keyword (keyword) COMMENT \'tokenizer "TokenBigramSplitSymbolAlphaDigit", index_flags "WITH_SECTION|WITH_POSITION"\'');

        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('query_text', 512);
            $table->unsignedInteger('search_count')->default(1);
            $table->unsignedInteger('user_count')->default(1);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'query_text'], 'idx_tenant_query');
            $table->index(['tenant_id', 'search_count'], 'idx_tenant_count');
        });

        Schema::create('search_query_words', function (Blueprint $table) {
            $table->engine = 'Mroonga';
            $table->id();
            $table->unsignedBigInteger('query_id');
            $table->string('word');

            $table->index(['query_id'], 'idx_sqw_query');
            $table->index(['word', 'query_id'], 'idx_word_query');
        });

        DB::statement(
            "ALTER TABLE search_query_words ADD FULLTEXT INDEX ft_word (word) COMMENT 'tokenizer \"TokenBigramSplitSymbolAlphaDigit\", index_flags \"WITH_SECTION|WITH_POSITION\"'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('search_query_words');
        Schema::dropIfExists('search_queries');
        Schema::dropIfExists('search_keywords');
    }
};
