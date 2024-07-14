<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            // プライマリーキーを削除
            $table->dropPrimary();

            // organization_idをNULL許容に変更
            $table->unsignedBigInteger('organization_id')->nullable()->change();

            // 新しいプライマリーキーを設定（organization_idを除外）
            $table->primary(['role_id', 'model_id', 'model_type']);

            // organization_idを含むユニークインデックスを作成
            $table->unique(['organization_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            // ユニークインデックスを削除
            $table->dropUnique('model_has_roles_unique');

            // プライマリーキーを削除
            $table->dropPrimary();

            // organization_idをNOT NULLに戻す
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();

            // 元のプライマリーキーを再設定
            $table->primary(['organization_id', 'role_id', 'model_id', 'model_type']);
        });
    }
};
