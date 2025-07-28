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
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_links');
    }
};