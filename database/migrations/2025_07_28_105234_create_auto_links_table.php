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
        Schema::create('auto_links', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('pattern');
            $table->string('url_template');
            $table->text('description')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('open_in_new_tab')->default(true);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('modifier_id')->nullable();
            $table->timestamps();

            $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('modifier_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_links', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
            $table->dropForeign(['modifier_id']);
            $table->dropColumn(['creator_id', 'modifier_id']);
        });

        Schema::dropIfExists('auto_links');
    }
};