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
            // organization_idカラムが存在しない場合は追加
            if (!Schema::hasColumn('model_has_roles', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('role_id');
            }

            // 既存のユニークインデックスを削除（存在する場合のみ）
            if (Schema::hasIndex('model_has_roles', 'model_has_roles_role_id_model_id_model_type_unique')) {
                $table->dropUnique('model_has_roles_role_id_model_id_model_type_unique');
            }

            // 新しいユニークインデックスを追加（既に存在しない場合のみ）
            if (!Schema::hasIndex('model_has_roles', 'model_has_roles_unique')) {
                $table->unique(['organization_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_unique');
            }

            // 外部キー制約を追加（存在しない場合）
            if (!Schema::hasColumn('model_has_roles', 'organization_id')) {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        model_has_rolesはspatie/laravel-permissionで処理されるためここでは削除しない
    }
};
