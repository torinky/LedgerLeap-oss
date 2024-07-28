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
            // model_typeカラムの長さを変更
            $table->string('model_type', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            // 元の長さに戻す（デフォルトの長さは環境によって異なる可能性があるため、
            // 必要に応じてこの値を調整してください）
            $table->string('model_type', 191)->change();
        });
    }
};
