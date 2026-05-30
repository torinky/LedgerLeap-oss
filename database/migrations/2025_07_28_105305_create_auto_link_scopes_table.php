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
        Schema::create('auto_link_scopes', function (Blueprint $table) {
            $table->foreignId('auto_link_id')->constrained()->onDelete('cascade');
            $table->morphs('scopeable');
            $table->primary(['auto_link_id', 'scopeable_id', 'scopeable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_link_scopes');
    }
};
