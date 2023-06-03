<?php

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

            //            外部キー制約を使う場合はストレージエンジンを揃えないとsqlエラーになる
//            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('creator_id')->index();
            $table->unsignedInteger('modifier_id')->index();

//            $table->longText('content');
            $table->timestamps();
        });
//        DB::statement('ALTER TABLE ledgers COMMENT = \'engine "InnoDB"\'');
        DB::statement('ALTER TABLE ledgers ADD COLUMN content longtext COMMENT \'flags "COLUMN_VECTOR"\'');
        DB::statement('ALTER TABLE ledgers ADD FULLTEXT index (content) COMMENT \'tokenizer "TokenBigramSplitSymbolAlphaDigit", index_flags "WITH_SECTION|WITH_POSITION"\'');
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
