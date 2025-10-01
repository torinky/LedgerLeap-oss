<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints(); // 外部キー制約を一時的に無効化

        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->unsignedInteger('creator_id')->index();
            $table->unsignedInteger('modifier_id')->index();

            $table->timestamps();
            $table->softDeletes();
            $table->nestedSet();
        });

        Schema::enableForeignKeyConstraints(); // 外部キー制約を有効化
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('folders');
    }
};
