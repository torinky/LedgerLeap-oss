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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('org_id')->nullable()->unique()->comment('組織ID (ユーザー入力 / AD/LDAP識別子)');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('sort_order')->nullable();
            //            $table->unsignedBigInteger('parent_id')->nullable();
            //            $table->foreign('parent_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->timestamp('ad_last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->nestedSet();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');

    }
};
