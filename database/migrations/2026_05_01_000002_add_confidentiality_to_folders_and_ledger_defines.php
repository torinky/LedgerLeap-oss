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
        Schema::table('folders', function (Blueprint $table) {
            $table->string('confidentiality_level', 50)->nullable()->after('title');
            $table->json('confidentiality_scopes')->nullable()->after('confidentiality_level');
        });

        Schema::table('ledger_defines', function (Blueprint $table) {
            $table->string('confidentiality_level', 50)->nullable()->after('title');
            $table->json('confidentiality_scopes')->nullable()->after('confidentiality_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn(['confidentiality_level', 'confidentiality_scopes']);
        });

        Schema::table('ledger_defines', function (Blueprint $table) {
            $table->dropColumn(['confidentiality_level', 'confidentiality_scopes']);
        });
    }
};
