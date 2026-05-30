<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_user', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete(); // 修正前
            $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete(); // UUID 型
            //            $table->uuid('notification_id'); // UUID型に変更
            //            $table->foreign('notification_id')->references('id')->on('notifications')->onDelete('cascade'); // 外部キー制約
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'user_id']); // 同じユーザーが同じ通知を複数回既読にできないようにする

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_user');
    }
};
