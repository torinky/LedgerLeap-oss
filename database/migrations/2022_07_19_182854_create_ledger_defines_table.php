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
            $table->text('create_description')->nullable();
            $table->text('list_description')->nullable();
            $table->text('detail_description')->nullable();

            $table->unsignedInteger('version')->default(1); // バージョン番号
            $table->boolean('workflow_enabled')->default(false); // ワークフロー有効/無効
            $table->unsignedBigInteger('recommended_inspector_id')->nullable()->index(); // 推奨点検者(User)
            $table->unsignedBigInteger('recommended_approver_id')->nullable()->index();  // 推奨承認者(User)
            $table->unsignedBigInteger('recommended_inspector_role_id')->nullable()->index(); // 推奨点検者(Role)
            $table->unsignedBigInteger('recommended_approver_role_id')->nullable()->index();  // 推奨承認者(Role)

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
