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
        Schema::create('technical_term_groups', function (Blueprint $table) {
            $table->engine = 'Mroonga';
            $table->id();
            $table->longText('synonyms'); // JSON形式の同義語リスト
            $table->timestamps();

            //            外部キー制約を使う場合はストレージエンジンを揃えないとsqlエラーになる
            //            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('creator_id')->index();
            $table->unsignedInteger('modifier_id')->index();
        });
        DB::statement('ALTER TABLE technical_term_groups ADD UNIQUE INDEX synonyms_index (synonyms(100))');
        DB::statement('ALTER TABLE technical_term_groups ADD FULLTEXT index (synonyms) COMMENT \'tokenizer "TokenBigramSplitSymbolAlphaDigit", index_flags "WITH_SECTION|WITH_POSITION"\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_term_groups');
    }
};
