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
        Schema::create('role_folder_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('folder_id');
            $table->unsignedBigInteger('modifier_id');
            $table->enum('permission', ['read', 'write', 'admin', 'delete', 'notify_on', 'notify_off'])->default('read');

            $table->unsignedBigInteger('notification_type_id')->nullable();

            $table->foreign('notification_type_id')->references('id')->on('notification_types')->nullOnDelete(); //追記:nullableなのでnullOnDelete
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('modifier_id')->references('id')->on('users');
            $table->unique(['role_id', 'folder_id', 'notification_type_id', 'permission'], 'unique_role_folder_notification_permission');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_folder_permissions');
    }
};
