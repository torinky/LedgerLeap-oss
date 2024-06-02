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
        Schema::create('ledger_defines', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('folder_id')->index();
            $table->index(['id', 'folder_id']);  // 複合インデックスを設定
            $table->string('title', 500)->index();
            $table->json('column_define');
            //            外部キー制約を使う場合はストレージエンジンを揃えないとsqlエラーになる
            //            $table->foreignId('user_id')->constrained('users');
            $table->unsignedInteger('creator_id')->index();
            $table->unsignedInteger('modifier_id')->index();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ledger_defines');
    }
};
