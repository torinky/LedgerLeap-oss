<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 多階層フォルダツリーのデモデータを生成するSeeder。
 *
 * 目的: Issue #73 (フォルダツリーのスクロール追従・深い階層対応) の検証用。
 *       実際の医療・製造現場を模した 5〜6 段階の深い階層を再現し、
 *       ブラウザ上での目視確認および自動テストのフィクスチャとして使用する。
 *
 * 生成されるツリー構造 (参考):
 *   病院全体 (depth=0)
 *   └── 内科部門 (depth=1)
 *       ├── 第一病棟 (depth=2)
 *       │   ├── Aチーム (depth=3)
 *       │   │   ├── 日勤班 (depth=4)
 *       │   │   │   └── 個人記録 (depth=5)
 *       │   │   └── 夜勤班 (depth=4)
 *       │   └── Bチーム (depth=3)
 *       └── 第二病棟 (depth=2)
 *           └── Cチーム (depth=3)
 *   └── 外科部門 (depth=1)
 *       └── 手術室 (depth=2)
 *           └── 術前チーム (depth=3)
 *   └── 管理部門 (depth=1)
 *       ├── 人事 (depth=2)
 *       ├── 総務 (depth=2)
 *       └── 情報システム (depth=2)
 *           └── セキュリティ (depth=3)
 *
 * 使用方法:
 *   ./vendor/bin/sail artisan tenants:seed --class=DeepHierarchyFolderSeeder
 *
 * @see Issue https://github.com/torinky/LedgerLeap/issues/73
 */
class DeepHierarchyFolderSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (! $user) {
            $user = User::factory()->create();
        }
        $uid = $user->id;

        // ルートフォルダ
        $root = Folder::create([
            'title' => '病院全体',
            'creator_id' => $uid,
            'modifier_id' => $uid,
        ]);

        // ── 内科部門 ───────────────────────────────────
        $naika = $root->children()->create(['title' => '内科部門', 'creator_id' => $uid, 'modifier_id' => $uid]);

        $ward1 = $naika->children()->create(['title' => '第一病棟', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $teamA = $ward1->children()->create(['title' => 'Aチーム', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $day = $teamA->children()->create(['title' => '日勤班', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $personal = $day->children()->create(['title' => '個人記録', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $teamA->children()->create(['title' => '夜勤班', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $ward1->children()->create(['title' => 'Bチーム', 'creator_id' => $uid, 'modifier_id' => $uid]);

        $ward2 = $naika->children()->create(['title' => '第二病棟', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $ward2->children()->create(['title' => 'Cチーム', 'creator_id' => $uid, 'modifier_id' => $uid]);

        // ── 外科部門 ───────────────────────────────────
        $geka = $root->children()->create(['title' => '外科部門', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $or = $geka->children()->create(['title' => '手術室', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $or->children()->create(['title' => '術前チーム', 'creator_id' => $uid, 'modifier_id' => $uid]);

        // ── 管理部門 ───────────────────────────────────
        $kanri = $root->children()->create(['title' => '管理部門', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $kanri->children()->create(['title' => '人事', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $kanri->children()->create(['title' => '総務', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $it = $kanri->children()->create(['title' => '情報システム', 'creator_id' => $uid, 'modifier_id' => $uid]);
        $it->children()->create(['title' => 'セキュリティ', 'creator_id' => $uid, 'modifier_id' => $uid]);

        // 台帳定義を各階層に配置（ツリーのバッジ表示確認用）
        $this->createLedgerDefine('日報', $ward1->id, $uid);
        $this->createLedgerDefine('申し送り', $teamA->id, $uid);
        $this->createLedgerDefine('個人業務記録', $personal->id, $uid);
        $this->createLedgerDefine('手術記録', $or->id, $uid);
        $this->createLedgerDefine('勤怠管理', $kanri->id, $uid);

        // NestedSetツリー構造を修復
        Folder::fixTree();

        $this->command?->info('多階層フォルダデモデータを作成しました（最大深さ: 6階層）');
    }

    private function createLedgerDefine(string $title, int $folderId, int $userId): void
    {
        LedgerDefine::create([
            'title' => $title,
            'folder_id' => $folderId,
            'creator_id' => $userId,
            'modifier_id' => $userId,
            'column_define' => [
                ['id' => 0, 'name' => '内容', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);
    }
}
