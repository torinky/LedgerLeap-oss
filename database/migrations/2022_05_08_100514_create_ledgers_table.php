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

            $table->string('status')->default(WorkflowStatus::NONE->value)->index(); // 最新のワークフロー状態
            $table->unsignedBigInteger('latest_diff_id')->nullable();
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
        Schema::table('ledgers', function (Blueprint $table) {
            // 外部キー制約を削除する前に存在を確認
            if (Schema::hasColumn('ledgers', 'latest_diff_id')) {
                $foreignKeys = DB::select("SELECT CONSTRAINT_NAME 
                                       FROM information_schema.KEY_COLUMN_USAGE 
                                       WHERE TABLE_NAME = 'ledgers' 
                                       AND COLUMN_NAME = 'latest_diff_id' 
                                       AND TABLE_SCHEMA = DATABASE()");
                if (!empty($foreignKeys)) {
                    $table->dropForeign(['latest_diff_id']);
                }
            }
        });
        Schema::dropIfExists('ledgers');
    }
};
