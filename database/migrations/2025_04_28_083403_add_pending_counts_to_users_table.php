<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // login_landing_page の後あたりに追加
            $table->unsignedInteger('pending_inspection_count')->default(0)->after('login_landing_page')->index();
            $table->unsignedInteger('pending_approval_count')->default(0)->after('pending_inspection_count')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pending_approval_count')) {
                $table->dropIndex(['pending_approval_count']); // インデックスも削除
                $table->dropColumn('pending_approval_count');
            }
            if (Schema::hasColumn('users', 'pending_inspection_count')) {
                $table->dropIndex(['pending_inspection_count']); // インデックスも削除
                $table->dropColumn('pending_inspection_count');
            }
        });
    }
};