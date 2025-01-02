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
            $table->enum('permission', ['read', 'write', 'admin', 'delete'])->default('read');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('cascade');
            $table->foreign('modifier_id')->references('id')->on('users');
            $table->unique(['role_id', 'folder_id']); // 同じroleとfolderの組み合わせは一意にする
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
