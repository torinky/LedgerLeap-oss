<?php

use App\Enums\WorkflowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->engine = 'Mroonga';
            $table->id();
            $table->unsignedBigInteger('ledger_define_id')->index();
            $table->index(['id', 'ledger_define_id']);  // 複合インデックスを設定
            //            外部キー制約を使う場合はストレージエンジンを揃えないとsqlエラーになる
//            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('creator_id')->index();
            $table->unsignedInteger('modifier_id')->index();

            $table->string('status')->default(WorkflowStatus::DRAFT->value)->index(); // 最新のワークフロー状態
            $table->unsignedInteger('version')->default(1); // バージョン番号

            $table->timestamps();
        });
//        DB::statement('ALTER TABLE ledgers COMMENT = \'engine "InnoDB"\'');
        DB::statement('ALTER TABLE ledgers ADD COLUMN content longtext COMMENT \'flags "COLUMN_VECTOR"\'');
        DB::statement('ALTER TABLE ledgers ADD COLUMN content_attached longtext COMMENT \'flags "COLUMN_VECTOR"\'');
        DB::statement('ALTER TABLE ledgers ADD FULLTEXT index (content) COMMENT \'tokenizer "TokenBigramSplitSymbolAlphaDigit", index_flags "WITH_SECTION|WITH_POSITION"\'');
        DB::statement('ALTER TABLE ledgers ADD FULLTEXT index (content_attached) COMMENT \'tokenizer "TokenBigramSplitSymbolAlphaDigit", index_flags "WITH_SECTION|WITH_POSITION"\'');
        DB::statement('ALTER TABLE ledgers ADD FULLTEXT index (content, content_attached) COMMENT \'tokenizer "TokenBigramSplitSymbolAlphaDigit", index_flags "WITH_SECTION|WITH_POSITION"\'');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ledgers');
    }
};
