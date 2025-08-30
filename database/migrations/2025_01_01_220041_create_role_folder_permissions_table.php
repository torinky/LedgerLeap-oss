<?php

use App\Enums\FolderPermissionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints(); // 外部キー制約を一時的に無効化

        Schema::create('role_folder_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->foreignId('modifier_id')->constrained('users');

            // permission カラム: アクセス権限のみを Enum で定義
            $permissionValues = FolderPermissionType::allPermissionValues();
            $table->enum('permission', $permissionValues);

            $table->unsignedBigInteger('notification_type_id')->nullable();
            $table->foreign('notification_type_id')->references('id')->on('notification_types')->nullOnDelete(); //追記:nullableなのでnullOnDelete

            $table->unique(['role_id', 'folder_id', 'notification_type_id', 'permission'], 'unique_role_folder_notification_permission');

            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints(); // 外部キー制約を有効化
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_folder_permissions');
    }
};
