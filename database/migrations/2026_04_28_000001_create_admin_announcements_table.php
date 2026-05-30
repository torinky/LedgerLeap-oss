<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('modifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('level')->default('info');
            $table->string('status')->default('draft');
            $table->json('scope')->nullable();
            $table->boolean('sticky')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('links')->nullable();
            $table->string('revision', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_announcements');
    }
};
