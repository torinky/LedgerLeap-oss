<?php

use App\Enums\WorkflowStatus;
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

        Schema::create('ledger_diffs', function (Blueprint $table) {
            $table->engine = 'Mroonga';
            $table->id();
            $table->foreignId('ledger_id');
            $table->unsignedInteger('version')->default(1)->index();
            $table->unsignedInteger('ledger_define_id')->index();
            $table->index(['ledger_id', 'ledger_define_id']);
            $table->unsignedInteger('creator_id')->index(); // このDiffを作成した人(編集者)
            $table->unsignedInteger('modifier_id')->index(); // このDiffが記録した変更の実行者 (通常 creator_id と同じか?) ※用途要確認
            $table->json('column_define'); // 変更時の台帳定義

            // --- ワークフロー関連カラム追加 ---
            $table->string('status')->default(WorkflowStatus::DRAFT->value)->index(); // ワークフロー状態 (Enum)
            $table->unsignedBigInteger('inspector_id')->nullable()->index();      // 点検担当者 (User ID)
            $table->unsignedBigInteger('approver_id')->nullable()->index();       // 承認担当者 (User ID)
            $table->timestamp('requested_at')->nullable(); // 点検/承認依頼日時
            $table->timestamp('inspected_at')->nullable(); // 点検完了日時
            $table->timestamp('approved_at')->nullable();  // 承認完了日時
            $table->timestamp('returned_at')->nullable();  // 作成中に戻された日時
            $table->text('comments')->nullable();          // 作成中に戻す理由などのコメント

            $table->json('completed_inspector_role_ids')->comment('このDiff作成時点で内容に対し点検完了した必須点検ロールIDの配列');
            $table->json('completed_approver_role_ids')->comment('このDiff作成時点で内容に対し承認完了した必須承認ロールIDの配列');

            $table->timestamps(); // created_at は Diff 作成日時, updated_at は Diff 更新日時
        });
        // JSONカラム content は後から追加
        DB::statement('ALTER TABLE ledger_diffs ADD COLUMN content longtext COMMENT \'flags "COLUMN_VECTOR"\'');
        DB::statement('ALTER TABLE ledger_diffs ADD FULLTEXT index (content) COMMENT \'tokenizer "TokenBigramSplitSymbolAlphaDigit", index_flags "WITH_SECTION|WITH_POSITION"\'');

        Schema::enableForeignKeyConstraints(); // 外部キー制約を有効化
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ledger_diffs');
    }
};
