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
        Schema::create('attached_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ledger_id')->index();
            $table->unsignedBigInteger('ledger_define_id')->index();
            $table->unsignedInteger('column_id')->index();
            $table->string('filename', 500)->index();
            $table->string('hashedbasename', 500)->index();
            $table->string('status', 50)->index();
            $table->boolean('contain_content')->index();
            $table->boolean('optimized')->index();
            $table->string('mime', 500)->index();
            $table->text('path');
            $table->string('original_file_path')->nullable();
            $table->string('original_mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // Add this line
            $table->unsignedInteger('creator_id')->index();
            $table->unsignedInteger('modifier_id')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attached_files');
    }
};
