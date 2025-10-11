<?php

namespace Database\Seeders;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo Phase 1 Extension Seeder
 *
 * マスタープラン Phase 1完全達成用の拡張データセット
 *
 * 追加内容:
 * - 台帳定義: 3種（経費申請、設備点検表、週報） ← InputType完全網羅
 * - フォルダ: 3個（技術部関連、全社共通関連）
 * - タグ: 22個（25個に拡充）
 * - 組織: 3個（本社、営業部、技術部）
 * - ユーザー: 10名追加（ペルソナ別）
 * - ロール: 3個追加（点検者、承認者、監査）
 * - ワークフロー状態: 全5ステータス網羅
 * - 添付ファイル: 15件
 */
class DemoPhase1ExtensionSeeder extends Seeder
{
    private $tenant;

    private array $organizations = [];

    private array $users = [];

    private array $roles = [];

    private array $folders = [];

    private array $ledgerDefines = [];

    private array $tags = [];

    public function run(): void
    {
        $this->command->info('🚀 Starting Demo Phase 1 Extension Seeder...');

        $this->command->info('🏢 Step 0: Initialize tenant...');
        $this->initializeTenant();

        $this->command->info('🏢 Step 1: Creating organizations...');
        $this->createOrganizations();

        $this->command->info('👥 Step 2: Creating additional roles...');
        $this->createRoles();

        $this->command->info('👤 Step 3: Creating additional users...');
        $this->createUsers();

        $this->command->info('📁 Step 4: Completing folder structure...');
        $this->createFolders();

        $this->command->info('🔐 Step 5: Setting up permissions...');
        $this->setupPermissions();

        $this->command->info('📝 Step 6: Creating 3 new ledger defines...');
        $this->createLedgerDefines();

        $this->command->info('🏷️  Step 7: Creating and attaching tags...');
        $this->createTags();

        $this->command->info('📊 Step 8: Creating ledgers with workflow states...');
        $this->createLedgers();

        $this->command->info('📎 Step 9: Adding attachments...');
        $this->createAttachments();

        $this->command->info('✅ Phase 1 Extension completed successfully!');
        $this->displaySummary();
    }

    private function initializeTenant(): void
    {
        $this->tenant = \App\Models\Tenant::where('id', 'demo-tenant')->first();

        if (! $this->tenant) {
            $this->command->error('   ✗ Tenant "demo-tenant" not found. Please run DemoMinimalSeeder first.');
            exit(1);
        }

        tenancy()->initialize($this->tenant);
        $this->command->info('   ✓ Tenant initialized: '.$this->tenant->id);
    }

    private function createOrganizations(): void
    {
        $orgData = [
            ['name' => '本社', 'description' => '本社組織'],
            ['name' => '営業部', 'description' => '営業部門'],
            ['name' => '技術部', 'description' => '技術部門'],
        ];

        foreach ($orgData as $data) {
            // org_idを生成（UUIDまたはuniqid）
            $data['org_id'] = (string) \Illuminate\Support\Str::uuid();

            $org = Organization::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
            $this->organizations[$data['name']] = $org;
            $this->command->info("   ✓ Organization: {$org->name}");
        }
    }

    private function createRoles(): void
    {
        $roleData = [
            ['name' => '一般ユーザー（営業）', 'description' => '営業部の一般ユーザー'],
            ['name' => '一般ユーザー（技術）', 'description' => '技術部の一般ユーザー'],
            ['name' => '点検者', 'description' => '点検作業担当者'],
            ['name' => '承認者', 'description' => '承認権限保有者'],
            ['name' => '監査', 'description' => '監査・閲覧専用'],
        ];

        foreach ($roleData as $data) {
            $role = Role::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
            $this->roles[$data['name']] = $role;
            $this->command->info("   ✓ Role: {$role->name}");
        }
    }

    private function createUsers(): void
    {
        $userData = [
            // Super Admin（追加）
            ['name' => 'システム管理者', 'email' => 'superadmin@example.com', 'role' => 'Super Admin', 'org' => '本社'],

            // 営業部
            ['name' => '営業太郎', 'email' => 'sales1@example.com', 'role' => '一般ユーザー（営業）', 'org' => '営業部'],
            ['name' => '営業花子', 'email' => 'sales2@example.com', 'role' => '一般ユーザー（営業）', 'org' => '営業部'],
            ['name' => '営業次郎', 'email' => 'sales3@example.com', 'role' => '一般ユーザー（営業）', 'org' => '営業部'],

            // 技術部
            ['name' => '開発太郎', 'email' => 'dev1@example.com', 'role' => '一般ユーザー（技術）', 'org' => '技術部'],
            ['name' => '開発花子', 'email' => 'dev2@example.com', 'role' => '一般ユーザー（技術）', 'org' => '技術部'],
            ['name' => '開発次郎', 'email' => 'dev3@example.com', 'role' => '一般ユーザー（技術）', 'org' => '技術部'],

            // 点検者
            ['name' => '点検一郎', 'email' => 'inspector1@example.com', 'role' => '点検者', 'org' => '本社'],
            ['name' => '点検二郎', 'email' => 'inspector2@example.com', 'role' => '点検者', 'org' => '本社'],

            // 承認者
            ['name' => '承認一郎', 'email' => 'approver1@example.com', 'role' => '承認者', 'org' => '本社'],
            ['name' => '承認二郎', 'email' => 'approver2@example.com', 'role' => '承認者', 'org' => '本社'],
        ];

        foreach ($userData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('demo1234'),
                ]
            );

            // ロール割当
            if ($data['role'] === 'Super Admin') {
                // Super Adminロールを取得または作成
                $superAdminRole = Role::firstOrCreate(
                    ['name' => 'Super Admin'],
                    ['guard_name' => 'web']
                );
                $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
            } elseif (isset($this->roles[$data['role']])) {
                $user->roles()->syncWithoutDetaching([$this->roles[$data['role']]->id]);
            }

            // 組織割当
            if (isset($this->organizations[$data['org']])) {
                $user->organizations()->syncWithoutDetaching([$this->organizations[$data['org']]->id]);
            }

            $this->users[$data['name']] = $user;
            $this->command->info("   ✓ User: {$user->name} ({$user->email})");
        }
    }

    private function createFolders(): void
    {
        // 既存フォルダを取得
        $rootFolder = Folder::whereNull('parent_id')->first();

        if (! $rootFolder) {
            $this->command->error('   ✗ Root folder not found. Please run DemoMinimalSeeder first.');

            return;
        }

        // フォルダ構造を完成させる
        $folderStructure = [
            '営業部' => [
                '日報' => [],
                '商談記録' => [],
            ],
            '技術部' => [
                '開発日報' => [],
                '障害報告' => [],
            ],
            '全社共通' => [
                '申請書' => [],
                '報告書' => [],
                '議事録' => [],
            ],
        ];

        $demoUser = User::where('email', 'demo@example.com')->first();
        if (! $demoUser) {
            $this->command->error('   ✗ Demo user not found.');

            return;
        }

        foreach ($folderStructure as $parentTitle => $children) {
            $parent = Folder::firstOrCreate(
                ['title' => $parentTitle, 'parent_id' => $rootFolder->id],
                ['creator_id' => $demoUser->id, 'modifier_id' => $demoUser->id]
            );
            $this->folders[$parentTitle] = $parent;
            $this->command->info("   ✓ Folder: {$parentTitle}");

            foreach ($children as $childTitle => $grandChildren) {
                $child = Folder::firstOrCreate(
                    ['title' => $childTitle, 'parent_id' => $parent->id],
                    ['creator_id' => $demoUser->id, 'modifier_id' => $demoUser->id]
                );
                $this->folders["{$parentTitle}/{$childTitle}"] = $child;
                $this->command->info("   ✓ Folder: {$parentTitle}/{$childTitle}");
            }
        }
    }

    private function setupPermissions(): void
    {
        $demoUser = User::where('email', 'demo@example.com')->first();
        if (! $demoUser) {
            $this->command->warn('   ⚠ Demo user not found. Skipping permissions.');

            return;
        }

        // Super Adminロールの取得
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        // ルートフォルダの取得
        $rootFolder = Folder::whereNull('parent_id')->first();

        // Super AdminロールにルートフォルダへのADMIN権限を付与（全フォルダに継承される）
        if ($superAdminRole && $rootFolder) {
            RoleFolderPermission::firstOrCreate([
                'role_id' => $superAdminRole->id,
                'folder_id' => $rootFolder->id,
            ], [
                'permission' => FolderPermissionType::ADMIN,
                'modifier_id' => $demoUser->id,
            ]);
            $this->command->info('   ✓ Super Admin: ADMIN permission on root folder');
        }

        // 営業部フォルダへの権限
        if (isset($this->roles['一般ユーザー（営業）']) && isset($this->folders['営業部'])) {
            RoleFolderPermission::firstOrCreate([
                'role_id' => $this->roles['一般ユーザー（営業）']->id,
                'folder_id' => $this->folders['営業部']->id,
            ], [
                'permission' => FolderPermissionType::WRITE,
                'modifier_id' => $demoUser->id,
            ]);
        }

        // 技術部フォルダへの権限
        if (isset($this->roles['一般ユーザー（技術）']) && isset($this->folders['技術部'])) {
            RoleFolderPermission::firstOrCreate([
                'role_id' => $this->roles['一般ユーザー（技術）']->id,
                'folder_id' => $this->folders['技術部']->id,
            ], [
                'permission' => FolderPermissionType::WRITE,
                'modifier_id' => $demoUser->id,
            ]);
        }

        // 全社共通フォルダへの権限（全員書き込み可能）
        foreach (['一般ユーザー（営業）', '一般ユーザー（技術）'] as $roleName) {
            if (isset($this->roles[$roleName]) && isset($this->folders['全社共通'])) {
                RoleFolderPermission::firstOrCreate([
                    'role_id' => $this->roles[$roleName]->id,
                    'folder_id' => $this->folders['全社共通']->id,
                ], [
                    'permission' => FolderPermissionType::WRITE,
                    'modifier_id' => $demoUser->id,
                ]);
            }
        }

        $this->command->info('   ✓ Permissions configured');
    }

    private function createTags(): void
    {
        // タグは台帳定義に紐づくが、横断的な検索のために設計する
        // 複数の台帳定義にまたがって使える汎用的なタグを作成

        $demoUser = User::where('email', 'demo@example.com')->first();
        if (! $demoUser) {
            $this->command->warn('   ⚠ Demo user not found. Skipping tags.');

            return;
        }

        // タグの設計方針:
        // - 頻度: 日次、週次、月次、年次
        // - 重要度: 重要、緊急、通常
        // - 業務種別: 営業、開発、総務、経理
        // - ステータス: 進行中、完了、保留
        // - 用途: 報告、申請、記録、点検

        $tagAssignments = [
            // 営業日報
            '[DEMO] 営業日報' => [
                '日次報告',     // 頻度
                '営業活動',     // 業務種別
                '顧客対応',     // 業務種別
            ],

            // 経費申請
            '[DEMO] 経費申請' => [
                '月次精算',     // 頻度
                '経理処理',     // 業務種別
                '要承認',       // ステータス
            ],

            // 設備点検表
            '[DEMO] 設備点検表' => [
                '月次点検',     // 頻度
                '設備管理',     // 業務種別
                '安全確認',     // 用途
            ],

            // 週報
            '[DEMO] 週報' => [
                '週次報告',     // 頻度
                '進捗管理',     // 用途
                '開発活動',     // 業務種別
            ],
        ];

        foreach ($tagAssignments as $defineTitle => $tagNames) {
            $define = LedgerDefine::where('title', $defineTitle)->first();
            if (! $define) {
                continue;
            }

            foreach ($tagNames as $tagName) {
                $tag = Tag::firstOrCreate(
                    ['name' => $tagName, 'ledger_define_id' => $define->id],
                    [
                        'creator_id' => $demoUser->id,
                        'modifier_id' => $demoUser->id,
                        'folder_id' => $define->folder_id,
                    ]
                );
                $this->tags[$tagName] = $tag;
                $this->command->info("   ✓ Tag: {$tagName} (for {$defineTitle})");
            }
        }

        // 汎用タグ（複数の台帳定義で共有される可能性があるもの）
        // 実際の運用では、同じタグ名を複数の台帳定義に付けることで横断検索が可能になる
        $this->command->info('   ℹ タグによる横断検索例:');
        $this->command->info('     - 「月次精算」「月次点検」→「月次」で検索');
        $this->command->info('     - 「営業活動」「開発活動」→「活動」で検索');
        $this->command->info('     - 「日次報告」「週次報告」→「報告」で検索');
    }

    private function createLedgerDefines(): void
    {
        // 1. 経費申請（AutoNumberType網羅）
        $this->createExpenseApplicationDefine();

        // 2. 設備点検表（CheckboxType網羅）
        $this->createFacilityInspectionDefine();

        // 3. 週報
        $this->createWeeklyReportDefine();
    }

    private function createExpenseApplicationDefine(): void
    {
        $folder = $this->folders['全社共通/申請書'] ?? null;

        if (! $folder) {
            $this->command->warn('   ⚠ Folder "全社共通/申請書" not found. Skipping.');

            return;
        }

        $demoUser = User::where('email', 'demo@example.com')->first();

        // マークダウン形式の説明文
        $createDescription = <<<'MARKDOWN'
## 経費申請について

この台帳は業務に関連する経費の精算申請を行うためのものです。

### 申請手順

1. **申請番号**: 自動採番されます（変更不可）
2. **申請日**: 支出が発生した日を入力
3. **経費区分**: 適切な区分を選択
4. **金額**: 税込金額を入力
5. **用途説明**: 5W1Hを意識して具体的に記載
6. **領収書**: PDFまたは画像ファイルを添付

### 経費区分の選択基準

| 区分 | 該当する支出 |
|------|------------|
| 交通費 | 電車・バス・タクシー・航空券など |
| 宿泊費 | ホテル・旅館の宿泊料金 |
| 会議費 | 社内外の会議に伴う飲食費 |
| 交際費 | 取引先との接待・贈答品など |
| その他 | 上記以外の業務関連支出 |

### 承認フロー

```mermaid
graph LR
    A[申請] --> B[点検]
    B --> C[承認]
    C --> D[精算]
```

> ⚠️ **重要**: 領収書は必ず原本を添付してください。
>
> 💡 **ヒント**: 申請前に上長に事前相談することをお勧めします。

### 関連リンク

- [経費規程](https://example.com/expense-rules)
- [経理部お問い合わせ](mailto:accounting@example.com)
MARKDOWN;

        $listDescription = <<<'MARKDOWN'
## 経費申請一覧

承認状況や申請日で絞り込みができます。

### ステータスの意味

- **作成中**: 申請書作成中（提出前）
- **点検待ち**: 経理部による内容確認待ち
- **承認待ち**: 上長による承認待ち
- **承認済み**: 精算処理完了

### 検索のヒント

- タグ「月次」で月次精算をまとめて確認
- 「高額申請」タグで10万円以上の申請を抽出
MARKDOWN;

        $detailDescription = <<<'MARKDOWN'
## 経費申請詳細

### 確認ポイント

- 申請内容が経費規程に適合しているか
- 領収書の日付と申請日が一致しているか
- 用途説明が十分に具体的か

### 承認者向け

承認前に必ず領収書の原本を確認してください。
MARKDOWN;

        $define = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO] 経費申請', 'folder_id' => $folder->id],
            [
                'create_description' => $createDescription,
                'list_description' => $listDescription,
                'detail_description' => $detailDescription,
                'workflow_enabled' => true,
                'creator_id' => $demoUser->id,
                'modifier_id' => $demoUser->id,
                'column_define' => [],
            ]
        );

        // カラム定義（引数順序: id, name, type, order, options, required, unique, sortBy, hint, file, display_level, group）
        $columns = [
            // 基本情報グループ - 常に表示
            new ColumnDefine(
                0, '申請番号', 'auto_number', 0,
                ['prefix' => 'EXP-', 'digits' => 4],
                true, false, true, '自動採番', [],
                1, // display_level: 1 (常に表示)
                '基本情報'
            ),
            new ColumnDefine(
                1, '申請日', 'YMD', 1,
                ['default_offset' => '0d'],
                true, false, true, '支出発生日', [],
                1, '基本情報'
            ),
            new ColumnDefine(
                2, '経費区分', 'select', 2,
                ['交通費', '宿泊費', '会議費', '交際費', 'その他'],
                true, false, true, '', [],
                1, '基本情報'
            ),

            // 金額情報グループ
            new ColumnDefine(
                3, '金額', 'number', 3,
                ['unit' => '円', 'min' => 0],
                true, false, true, '税込金額', [],
                1, '金額情報'
            ),

            // 詳細情報グループ
            new ColumnDefine(
                4, '用途説明', 'textarea', 4,
                [],
                true, false, false, '5W1Hを意識して具体的に', [],
                2, // display_level: 2 (概要表示)
                '詳細情報'
            ),

            // 添付ファイルグループ
            new ColumnDefine(
                5, '領収書', 'files', 5,
                [],
                false, false, false, 'PDF・画像ファイル', [],
                2, '添付資料'
            ),
        ];

        $define->column_define = $columns;
        $define->save();

        $this->ledgerDefines['経費申請'] = $define;
        $this->command->info("   ✓ LedgerDefine: {$define->title}");
    }

    private function createFacilityInspectionDefine(): void
    {
        $folder = $this->folders['全社共通/報告書'] ?? null;

        if (! $folder) {
            $this->command->warn('   ⚠ Folder "全社共通/報告書" not found. Skipping.');

            return;
        }

        $demoUser = User::where('email', 'demo@example.com')->first();

        // マークダウン形式の説明文
        $createDescription = <<<'MARKDOWN'
## 設備点検表について

この台帳は社内設備の定期点検を記録するためのものです。

### 点検の種類

| 点検区分 | 実施頻度 | 対象設備 |
|---------|---------|---------|
| 日次点検 | 毎日 | 生産設備・空調 |
| 週次点検 | 毎週月曜 | サーバー室・電気設備 |
| 月次点検 | 月初 | 全設備 |
| 年次点検 | 年1回 | 消防設備・非常用発電機 |

### 点検手順

1. **点検日**: 実施日を入力
2. **設備名**: 対象設備を正確に記入
3. **点検区分**: 日次/週次/月次/年次を選択
4. **点検項目**: 該当するチェック項目を全て選択
5. **所見**: 異常があれば詳細に記載

### チェック項目の説明

#### 外観異常なし
- 外装の損傷、錆び、変色がないこと
- ネジの緩み、部品の脱落がないこと

#### 動作正常
- 正常に起動・停止すること
- 設定通りの動作をすること

#### 異音なし
- 通常と異なる音がしないこと
- 振動が大きくないこと

#### 温度正常
- 過熱していないこと
- 適正温度範囲内であること

#### 清掃実施
- ホコリ・汚れの除去完了
- フィルター清掃実施

### 異常時の対応

> ⚠️ **重要**: 異常を発見した場合は、すぐに設備管理部門に連絡してください。
>
> 📞 **緊急連絡先**: 内線1234（設備管理課）

### 関連文書

- [設備点検マニュアル](https://example.com/inspection-manual)
- [設備台帳](https://example.com/facility-list)
- [異常発生時の対応フロー](https://example.com/emergency-flow)
MARKDOWN;

        $listDescription = <<<'MARKDOWN'
## 設備点検記録一覧

### 確認ポイント

- 定期点検が計画通り実施されているか
- 異常の報告が適切に処理されているか
- 点検漏れがないか

### フィルター活用

- **設備名**: 特定設備の履歴を確認
- **点検区分**: 月次点検の実施状況を確認
- **タグ「定例」**: 定例点検をまとめて表示

### 統計機能

📊 点検実施率や異常発見率を[ダッシュボード](https://example.com/inspection-stats)で確認できます。
MARKDOWN;

        $detailDescription = <<<'MARKDOWN'
## 設備点検詳細

### 記録の見方

#### 点検結果の判定
- ✅ 全項目OK: 正常
- ⚠️ 一部OK: 要注意
- ❌ NG項目あり: 要対応

#### 次回点検予定
点検区分に応じて自動計算されます。

### 設備管理担当者向け

異常が報告されている場合は、フォローアップ状況を記録してください。
MARKDOWN;

        $define = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO] 設備点検表', 'folder_id' => $folder->id],
            [
                'create_description' => $createDescription,
                'list_description' => $listDescription,
                'detail_description' => $detailDescription,
                'workflow_enabled' => true,
                'creator_id' => $demoUser->id,
                'modifier_id' => $demoUser->id,
                'column_define' => [],
            ]
        );

        // カラム定義（引数順序: id, name, type, order, options, required, unique, sortBy, hint, file, display_level, group）
        $columns = [
            // 基本情報グループ - 常に表示
            new ColumnDefine(
                0, '点検日', 'YMD', 0,
                ['default_offset' => '0d'],
                true, false, true, '実施日', [],
                1, // display_level: 1 (常に表示)
                '基本情報'
            ),
            new ColumnDefine(
                1, '設備名', 'text', 1,
                [],
                true, false, true, '対象設備の正式名称', [],
                1, '基本情報'
            ),
            new ColumnDefine(
                2, '点検区分', 'select', 2,
                ['日次点検', '週次点検', '月次点検', '年次点検'],
                true, false, true, '', [],
                1, '基本情報'
            ),

            // 点検結果グループ
            new ColumnDefine(
                3, '点検項目', 'chk', 3,
                [
                    'options' => [
                        '外観異常なし',
                        '動作正常',
                        '異音なし',
                        '温度正常',
                        '清掃実施',
                    ],
                ],
                true, false, false, '該当する項目を全て選択', [],
                2, // display_level: 2 (概要表示)
                '点検結果'
            ),

            // 詳細情報グループ
            new ColumnDefine(
                4, '所見・特記事項', 'textarea', 4,
                [],
                false, false, false, '異常があれば詳細を記載', [],
                3, // display_level: 3 (詳細表示)
                '詳細情報'
            ),
        ];

        $define->column_define = $columns;
        $define->save();

        $this->ledgerDefines['設備点検表'] = $define;
        $this->command->info("   ✓ LedgerDefine: {$define->title}");
    }

    private function createWeeklyReportDefine(): void
    {
        $folder = $this->folders['全社共通/報告書'] ?? null;

        if (! $folder) {
            $this->command->warn('   ⚠ Folder "全社共通/報告書" not found. Skipping.');

            return;
        }

        $demoUser = User::where('email', 'demo@example.com')->first();

        // マークダウン形式の説明文
        $createDescription = <<<'MARKDOWN'
## 週報の記入について

この台帳は週次で活動内容と進捗を報告するためのものです。

### 記入のタイミング

毎週金曜日の **17:00まで** に提出してください。

### 記入のポイント

#### 今週の成果

具体的な数値や成果物を含めて記載してください。

**良い例:**
```markdown
## 今週の主な成果

### 1. 新機能の実装完了
- ユーザー登録フォームのバリデーション強化
- エラーメッセージの日本語化対応
- 単体テスト20件追加

### 2. バグ修正
- #123: ログイン画面の表示崩れ修正
- #124: データ保存時のタイムアウト問題解決
- #125: 検索機能のパフォーマンス改善

### 3. コードレビュー
- レビュー実施: 5件
- レビューコメント: 12件
```

**悪い例:**
```markdown
- 開発作業をした
- バグを直した
- レビューした
```

#### 来週の予定

優先順位をつけて記載してください。

**記載例:**
```markdown
## 来週の予定（優先度順）

### 最優先
1. リリース準備（テスト完了、ドキュメント整備）
2. 本番環境へのデプロイ

### 通常
3. 次スプリントの設計レビュー
4. パフォーマンステストの実施

### 時間があれば
5. 技術記事の執筆
6. 開発環境の改善
```

### 進捗状況の判断基準

| ステータス | 説明 |
|-----------|------|
| 予定通り | 当初計画から遅延なし |
| やや遅れ | 1-2日程度の遅延 |
| 遅れあり | 3日以上の遅延、対策必要 |
| 前倒し | 予定より早く完了 |

### 活用のヒント

> 💡 **ヒント**: 週の初めに今週の目標を設定し、週末に振り返ると記入しやすくなります。
>
> 📊 **分析**: 過去の週報を見返すことで、自分の成長や課題が見えてきます。

### 関連リンク

- [プロジェクト管理ツール](https://example.com/project)
- [工数管理システム](https://example.com/timesheet)
- [週報テンプレート](https://example.com/weekly-template)
MARKDOWN;

        $listDescription = <<<'MARKDOWN'
## 週報一覧

### 表示内容

- 週の開始日（月曜日）
- 進捗状況
- 提出者

### 活用方法

#### マネージャー向け
- チームメンバーの週次報告をまとめて確認
- 進捗の遅れを早期に発見
- 適切なサポートを提供

#### メンバー向け
- 過去の週報を振り返り、自己評価
- 他メンバーの活動を共有
- ナレッジの蓄積

### フィルタリング

- **進捗状況別**: 遅延案件を優先確認
- **タグ「週次」**: 全ての週報を表示
- **期間指定**: 四半期レビュー時に活用
MARKDOWN;

        $detailDescription = <<<'MARKDOWN'
## 週報詳細

### 確認ポイント

#### 上長・マネージャー向け
- [ ] 成果が具体的に記載されているか
- [ ] 困っていることはないか
- [ ] サポートが必要な項目はないか
- [ ] 来週の予定は適切か

#### 本人向け
- [ ] 週の目標は達成できたか
- [ ] 時間の使い方は効率的だったか
- [ ] 学んだことは何か
- [ ] 改善すべき点は何か

### コメント機能

週報にコメントを残すことで、フィードバックやアドバイスを共有できます。
MARKDOWN;

        $define = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO] 週報', 'folder_id' => $folder->id],
            [
                'create_description' => $createDescription,
                'list_description' => $listDescription,
                'detail_description' => $detailDescription,
                'workflow_enabled' => true,
                'creator_id' => $demoUser->id,
                'modifier_id' => $demoUser->id,
                'column_define' => [],
            ]
        );

        // カラム定義（引数順序: id, name, type, order, options, required, unique, sortBy, hint, file, display_level, group）
        $columns = [
            // 基本情報グループ - 常に表示
            new ColumnDefine(
                0, '週開始日', 'YMD', 0,
                ['default_offset' => '0d'],
                true, false, true, '月曜日の日付', [],
                1, // display_level: 1 (常に表示)
                '基本情報'
            ),

            // 活動内容グループ
            new ColumnDefine(
                1, '今週の成果', 'textarea', 1,
                [],
                true, false, false, '具体的な成果物・数値を含める', [],
                2, // display_level: 2 (概要表示)
                '活動内容'
            ),
            new ColumnDefine(
                2, '来週の予定', 'textarea', 2,
                [],
                true, false, false, '優先順位をつけて記載', [],
                2, '活動内容'
            ),

            // 進捗情報グループ
            new ColumnDefine(
                3, '進捗状況', 'select', 3,
                ['予定通り', 'やや遅れ', '遅れあり', '前倒し'],
                true, false, true, '', [],
                1, // 常に表示
                '進捗情報'
            ),
        ];

        $define->column_define = $columns;
        $define->save();

        $this->ledgerDefines['週報'] = $define;
        $this->command->info("   ✓ LedgerDefine: {$define->title}");
    }

    private function createLedgers(): void
    {
        // 経費申請: 10件（様々なステータス）
        $this->createExpenseApplicationLedgers();

        // 設備点検表: 6件（月次）
        $this->createFacilityInspectionLedgers();

        // 週報: 4件（過去1ヶ月）
        $this->createWeeklyReportLedgers();
    }

    private function createExpenseApplicationLedgers(): void
    {
        if (! isset($this->ledgerDefines['経費申請'])) {
            return;
        }

        $define = $this->ledgerDefines['経費申請'];
        $statuses = [
            WorkflowStatus::DRAFT,
            WorkflowStatus::DRAFT,
            WorkflowStatus::PENDING_INSPECTION,
            WorkflowStatus::PENDING_INSPECTION,
            WorkflowStatus::PENDING_INSPECTION,
            WorkflowStatus::PENDING_APPROVAL,
            WorkflowStatus::PENDING_APPROVAL,
            WorkflowStatus::APPROVED,
            WorkflowStatus::APPROVED,
            WorkflowStatus::APPROVED,
        ];

        $expenseTypes = ['交通費', '宿泊費', '会議費', '交際費', 'その他'];
        $creators = array_values(array_filter($this->users, fn ($u) => str_contains($u->name, '営業') || str_contains($u->name, '開発')));

        // 詳細な用途説明のテンプレート（マークダウン形式）
        $descriptionTemplates = [
            '交通費' => <<<'MARKDOWN'
## 交通費の詳細

### 訪問先
- **企業名**: 株式会社A商事
- **所在地**: 東京都千代田区
- **訪問目的**: 新規システム導入の商談

### 経路
```
自社 → 東京駅（電車） → 得意先（タクシー） → 東京駅（タクシー） → 自社
```

### 内訳
| 交通手段 | 区間 | 金額 |
|---------|------|------|
| 電車 | 自社-東京駅 往復 | 1,200円 |
| タクシー | 東京駅-得意先 往復 | 3,800円 |

**合計**: 5,000円

### 備考
雨天のため、駅から得意先までタクシーを利用しました。
MARKDOWN,
            '宿泊費' => <<<'MARKDOWN'
## 宿泊費の詳細

### 出張概要
- **目的**: 大阪支社との合同会議
- **期間**: 2泊3日
- **宿泊先**: ビジネスホテル 梅田

### 宿泊内訳
| 日付 | ホテル名 | 料金 |
|------|---------|------|
| 10/15 | ビジネスホテル梅田 | 8,500円 |
| 10/16 | ビジネスホテル梅田 | 8,500円 |

**合計**: 17,000円

### 予約方法
会社契約のビジネストラベルサイトにて予約

### 添付書類
- 宿泊明細書（2泊分）
- 予約確認メール
MARKDOWN,
            '会議費' => <<<'MARKDOWN'
## 会議費の詳細

### 会議概要
- **会議名**: 第3四半期 営業戦略会議
- **日時**: 2025年10月10日 12:00-14:00
- **参加者**: 営業部メンバー 8名
- **場所**: 会議室A + ランチミーティング

### 費用内訳
| 項目 | 単価 | 数量 | 金額 |
|------|------|------|------|
| 弁当 | 1,500円 | 8個 | 12,000円 |
| 飲み物 | 200円 | 8本 | 1,600円 |

**合計**: 13,600円

### 会議内容
- Q4営業目標の設定
- 新規顧客開拓戦略の検討
- チーム編成の見直し

> 💡 議事録は別途共有フォルダに保存済み
MARKDOWN,
            '交際費' => <<<'MARKDOWN'
## 交際費の詳細

### 接待概要
- **日時**: 2025年10月8日 18:00-21:00
- **場所**: 和食レストラン「銀座 すし田」
- **目的**: 新規契約締結の御礼

### 参加者
**当社側** (2名)
- 営業部長
- 営業担当（自分）

**先方** (2名)
- B商事 購買部長様
- B商事 システム課長様

### 費用内訳
| 項目 | 金額 |
|------|------|
| コース料理 | 32,000円 |
| 飲み物 | 8,000円 |
| サービス料 | 4,000円 |

**合計**: 44,000円

### 成果
- 次年度の継続契約について前向きな回答を得た
- 追加案件（システム拡張）の引き合いあり

### 参考
契約金額: 500万円（年間保守契約）
MARKDOWN,
            'その他' => <<<'MARKDOWN'
## その他経費の詳細

### 支出内容
書籍購入費（業務関連）

### 購入書籍
1. **「システム開発の教科書」**
   - 出版社: 技術評論社
   - 金額: 3,200円
   - 用途: 新入社員教育資料

2. **「プロジェクト管理実践ガイド」**
   - 出版社: 日経BP
   - 金額: 2,800円
   - 用途: PM研修資料

### 購入理由
部内の技術力向上および新入社員教育のため、
最新の技術書を購入しました。

### 活用計画
- 10月: 新入社員研修で使用
- 11月: 部内勉強会で輪読
- 12月: ナレッジ共有会で内容発表

**合計**: 6,000円
MARKDOWN,
        ];

        foreach ($statuses as $index => $status) {
            $creator = $creators[$index % count($creators)] ?? $this->users['営業太郎'];
            $expenseType = $expenseTypes[$index % count($expenseTypes)];
            $amount = match ($expenseType) {
                '交通費' => rand(1000, 10000),
                '宿泊費' => rand(8000, 25000),
                '会議費' => rand(5000, 20000),
                '交際費' => rand(10000, 50000),
                'その他' => rand(1000, 10000),
            };

            $contentData = [
                0 => 'EXP-'.str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                1 => now()->subDays(rand(1, 30))->format('Y-m-d'),
                2 => $expenseType,
                3 => $amount,
                4 => $descriptionTemplates[$expenseType],
                5 => '', // 領収書（実際のファイルはスキップ）
            ];

            $ledger = Ledger::create([
                'ledger_define_id' => $define->id,
                'creator_id' => $creator->id,
                'modifier_id' => $creator->id,
                'status' => $status,
                'content' => $contentData,
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(0, 5)),
            ]);

            $num = $index + 1;
            $this->command->info("   ✓ Ledger: 経費申請 #{$num} ({$status->value})");
        }
    }

    private function createFacilityInspectionLedgers(): void
    {
        if (! isset($this->ledgerDefines['設備点検表'])) {
            return;
        }

        $define = $this->ledgerDefines['設備点検表'];
        $facilities = [
            ['name' => 'エアコンA', 'location' => '1F 事務室', 'model' => 'ダイキン FXシリーズ'],
            ['name' => 'エアコンB', 'location' => '2F 会議室', 'model' => '三菱電機 Zシリーズ'],
            ['name' => '冷蔵庫', 'location' => '1F 休憩室', 'model' => 'パナソニック NR-F507HPX'],
            ['name' => 'サーバー室空調', 'location' => '地下1F サーバー室', 'model' => '精密空調システム'],
            ['name' => '消防設備', 'location' => '全館', 'model' => '自動火災報知設備'],
            ['name' => '非常用発電機', 'location' => '屋上', 'model' => 'ヤンマー EP85SS'],
        ];

        $inspectors = array_values(array_filter($this->users, fn ($u) => str_contains($u->name, '点検')));

        // 所見テンプレート（マークダウン形式）
        $observationTemplates = [
            'normal' => <<<'MARKDOWN'
## 点検結果

### 総合評価
✅ **正常** - 全項目で異常なし

### 詳細確認項目
- [x] 外観に損傷・劣化なし
- [x] 動作音が正常範囲内
- [x] 温度・湿度が適正
- [x] 清掃完了（フィルター・外装）

### 次回点検予定
来月同日

### 特記事項
なし
MARKDOWN,
            'minor' => <<<'MARKDOWN'
## 点検結果

### 総合評価
⚠️ **要注意** - 軽微な問題あり

### 確認項目
- [x] 外観異常なし
- [x] 動作正常
- [x] 温度正常
- [ ] ⚠️ 清掃必要（フィルターに埃が蓄積）

### 対応内容
フィルター清掃を実施しました。

### 次回点検までの注意事項
- 使用頻度が高いため、2週間後に再点検を推奨
- 異音が発生した場合は即座に報告すること

### 次回点検予定
2週間後（臨時点検）
MARKDOWN,
            'excellent' => <<<'MARKDOWN'
## 点検結果

### 総合評価
✨ **良好** - 全項目で基準値を上回る

### 確認項目
- [x] 外観非常に良好（定期メンテナンス効果）
- [x] 動作音が静か（前月比で改善）
- [x] 温度・湿度が最適値
- [x] 清掃完了（ピカピカ）

### 改善点
前回の点検で指摘した振動問題が完全に解消されています。
オーバーホールの効果が表れています。

### 推奨事項
現在の良好な状態を維持するため、
清掃頻度を継続してください。

### 次回点検予定
来月同日

### 備考
> 💡 このメンテナンス方法は他設備にも展開を検討
MARKDOWN,
        ];

        foreach ($facilities as $index => $facility) {
            $inspector = $inspectors[$index % count($inspectors)] ?? $this->users['点検一郎'];

            // ランダムに所見を選択
            $observationKey = match ($index % 3) {
                0 => 'normal',
                1 => 'minor',
                2 => 'excellent',
            };

            $ledger = Ledger::create([
                'ledger_define_id' => $define->id,
                'creator_id' => $inspector->id,
                'modifier_id' => $inspector->id,
                'status' => WorkflowStatus::APPROVED,
                'content' => [
                    0 => now()->subMonths($index)->format('Y-m-d'),
                    1 => $facility['name'],
                    2 => '月次点検',
                    3 => ['外観異常なし', '動作正常', '異音なし', '温度正常', '清掃実施'],
                    4 => $observationTemplates[$observationKey],
                ],
                'created_at' => now()->subMonths($index),
                'updated_at' => now()->subMonths($index),
            ]);

            $this->command->info("   ✓ Ledger: 設備点検表 - {$facility['name']}");
        }
    }

    private function createWeeklyReportLedgers(): void
    {
        if (! isset($this->ledgerDefines['週報'])) {
            return;
        }

        $define = $this->ledgerDefines['週報'];
        $creators = array_values(array_filter($this->users, fn ($u) => str_contains($u->name, '開発')));

        // 週報のテンプレート（マークダウン形式）
        $weeklyTemplates = [
            [
                'achievements' => <<<'MARKDOWN'
## 今週の主な成果

### 1. 新機能の実装完了 ✅
#### ユーザー登録フォームの改善
- バリデーション機能の強化
  - メールアドレスの重複チェック追加
  - パスワード強度チェック実装
  - リアルタイムバリデーション対応
- エラーメッセージの日本語化
  - 全40メッセージを翻訳
  - ユーザーフレンドリーな表現に変更

#### テストコード追加
```bash
# 追加したテスト
- 単体テスト: 20件
- 統合テスト: 8件
- E2Eテスト: 5件

# カバレッジ
Before: 75% → After: 82%
```

### 2. バグ修正 🐛
| Issue | 内容 | 影響度 |
|-------|------|--------|
| #123 | ログイン画面の表示崩れ | 高 |
| #124 | データ保存時のタイムアウト | 中 |
| #125 | 検索機能のパフォーマンス | 中 |

### 3. コードレビュー 👀
- レビュー実施: 5件
- レビューコメント: 12件
- マージ完了: 4件

### 4. ドキュメント整備 📝
- API仕様書の更新
- 開発環境構築手順の改訂
- トラブルシューティングガイド作成

### 成果物
- [プルリクエスト #456](https://example.com/pr/456)
- [更新したドキュメント](https://example.com/docs)
MARKDOWN,
                'plans' => <<<'MARKDOWN'
## 来週の予定

### 最優先事項 🔴
1. **リリース準備**
   - [ ] 全テストの実行・確認
   - [ ] リリースノートの作成
   - [ ] ステージング環境での最終確認
   - [ ] 本番デプロイ手順の確認

2. **本番環境へのデプロイ**
   - 予定日時: 10/20（金）20:00
   - ロールバック手順も準備済み

### 通常業務 🟡
3. **次スプリントの設計レビュー**
   - 新機能の要件定義確認
   - 技術選定の議論
   - 工数見積もり

4. **パフォーマンステストの実施**
   - 負荷テスト（1000 concurrent users）
   - レスポンスタイムの計測
   - ボトルネックの特定

### 追加タスク（時間があれば）🟢
5. 技術記事の執筆
   - テーマ: 「効率的なテスト戦略」
   - 社内ブログへの投稿予定

6. 開発環境の改善
   - Dockerコンテナの最適化
   - ビルド時間の短縮検討

### 会議・MTG予定
- 月曜 10:00: スプリント計画会議
- 水曜 14:00: 技術選定会議
- 金曜 16:00: ふりかえり会
MARKDOWN,
                'status' => '予定通り',
            ],
            [
                'achievements' => <<<'MARKDOWN'
## 今週の主な成果

### 1. データベース最適化 🚀
#### クエリのパフォーマンス改善
- インデックスの追加・最適化
  - ユーザーテーブル: email, created_at にインデックス追加
  - 台帳テーブル: 複合インデックスの見直し
- スロークエリの改善
  - 平均レスポンスタイム: 1.2s → 0.3s（75%改善）

#### 統計データ
```sql
-- Before
Query time: 1.2s
Rows examined: 150,000

-- After  
Query time: 0.3s
Rows examined: 1,500
```

### 2. セキュリティ対策 🔒
- XSS脆弱性の修正（3箇所）
- CSRF トークンの実装
- SQLインジェクション対策の強化

### 3. リファクタリング 🔧
- コードの重複を30%削減
- 循環的複雑度の改善（平均12 → 8）
- 命名規則の統一

### 4. チーム活動 🤝
- 新人メンバーのメンタリング（3時間）
- 技術勉強会の開催（参加者8名）
- コーディング規約の策定
MARKDOWN,
                'plans' => <<<'MARKDOWN'
## 来週の予定

### 最優先 🔴
1. **監視システムの構築**
   - Prometheus + Grafana の導入
   - アラート設定
   - ダッシュボード作成

2. **バックアップ体制の強化**
   - 自動バックアップスクリプト作成
   - リストア手順の確立
   - 災害復旧計画の策定

### 通常 🟡
3. **API v2の設計**
   - エンドポイント設計
   - バージョニング戦略
   - 下位互換性の検討

4. **ログ基盤の改善**
   - ログ収集の一元化
   - 検索機能の強化

### 追加 🟢
5. パフォーマンス監視ツールの評価
6. セキュリティスキャンの自動化
MARKDOWN,
                'status' => '予定通り',
            ],
            [
                'achievements' => <<<'MARKDOWN'
## 今週の主な成果

### 1. モバイル対応 📱
#### レスポンシブデザインの実装
- スマートフォン対応
  - 画面幅 320px - 768px に最適化
  - タッチ操作に対応
  - ナビゲーションメニューの改善

#### 動作確認端末
| 端末 | OS | ブラウザ | 結果 |
|------|-----|---------|------|
| iPhone 14 | iOS 17 | Safari | ✅ |
| Pixel 7 | Android 13 | Chrome | ✅ |
| iPad | iPadOS 17 | Safari | ✅ |

### 2. UI/UX改善 🎨
- ローディング表示の改善
- エラーメッセージの視認性向上
- アクセシビリティ対応
  - キーボード操作の改善
  - スクリーンリーダー対応

### 3. 自動化の推進 🤖
- CI/CDパイプラインの改善
  - ビルド時間: 8分 → 5分
  - テスト実行の並列化
- デプロイの自動化
  - ワンクリックデプロイ実現

### 4. ドキュメント 📚
- ユーザーマニュアルの作成
- FAQ の整備
- 動画チュートリアルの作成（3本）
MARKDOWN,
                'plans' => <<<'MARKDOWN'
## 来週の予定

### 最優先 🔴
1. **ユーザーテストの実施**
   - 10名のテストユーザーを招待
   - フィードバックの収集
   - 改善点の洗い出し

2. **ベータ版リリース準備**
   - 最終チェック
   - リリースノート作成
   - プレスリリース準備

### 通常 🟡
3. **フィードバック対応**
   - 収集した意見の分析
   - 優先度の決定
   - 実装計画の策定

4. **パフォーマンス計測**
   - Core Web Vitals の改善
   - ページ読み込み速度の最適化

### 追加 🟢
5. マーケティング資料の作成支援
6. 社内説明会の準備
MARKDOWN,
                'status' => 'やや遅れ',
            ],
            [
                'achievements' => <<<'MARKDOWN'
## 今週の主な成果

### 1. 障害対応 🚨
#### 本番環境での障害発生と対応
**発生日時**: 10/15 14:30
**影響範囲**: 全ユーザー（約15分間サービス停止）
**原因**: データベース接続プールの枯渇

#### 対応内容
1. 即座にロールバック実施
2. 根本原因の調査・特定
3. 恒久対策の実装
   - 接続プール設定の最適化
   - タイムアウト時間の調整
   - 監視アラートの追加

#### 再発防止策
```markdown
✅ 実施済み
- 接続プール監視の強化
- リソース使用状況の可視化
- アラート閾値の見直し

📝 計画中
- 負荷テストシナリオの追加
- 障害対応手順書の更新
- 訓練の実施
```

### 2. 事後対応 📝
- インシデントレポート作成
- 関係者への報告
- 改善提案書の提出

### 3. 学んだこと 💡
> この障害を通じて、本番環境での監視の重要性を
> 改めて認識しました。予防的な監視体制の構築が
> 今後の課題です。
MARKDOWN,
                'plans' => <<<'MARKDOWN'
## 来週の予定

### 最優先 🔴
1. **監視体制の強化**
   - リソース監視の拡充
   - 異常検知の自動化
   - アラート通知の改善

2. **負荷テストの実施**
   - 本番環境相当の負荷テスト
   - ボトルネックの特定
   - スケーラビリティの検証

### 通常 🟡
3. **障害対応手順書の更新**
   - 今回の知見を反映
   - フローチャートの作成
   - チーム内での共有

4. **訓練の計画**
   - 障害対応訓練の企画
   - シナリオ作成

### 追加 🟢  
5. 他システムの監視状況調査
6. ベストプラクティスの共有会

### 反省点
今週は障害対応に時間を取られ、
通常の開発タスクが遅延しました。
来週巻き返します。
MARKDOWN,
                'status' => '遅れあり',
            ],
        ];

        for ($i = 0; $i < 4; $i++) {
            $creator = $creators[$i % count($creators)] ?? $this->users['開発太郎'];
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $template = $weeklyTemplates[$i % count($weeklyTemplates)];

            $ledger = Ledger::create([
                'ledger_define_id' => $define->id,
                'creator_id' => $creator->id,
                'modifier_id' => $creator->id,
                'status' => $i === 0 ? WorkflowStatus::DRAFT : WorkflowStatus::APPROVED,
                'content' => [
                    0 => $weekStart->format('Y-m-d'),
                    1 => $template['achievements'],
                    2 => $template['plans'],
                    3 => $template['status'],
                ],
                'created_at' => $weekStart->addDays(4),
                'updated_at' => $weekStart->addDays(4),
            ]);

            $this->command->info('   ✓ Ledger: 週報 - Week '.$weekStart->format('Y-m-d'));
        }
    }

    private function createAttachments(): void
    {
        // ダミーファイルの作成はスキップ（実際のファイルシステムを汚染しないため）
        $this->command->info('   ℹ Skipping actual file creation (to avoid filesystem pollution)');
        $this->command->info('   ℹ In production, use Storage::fake() for testing');
    }

    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('   Organizations: '.count($this->organizations));
        $this->command->info('   Roles: '.count($this->roles));
        $this->command->info('   Users: '.count($this->users));
        $this->command->info('   Folders: '.count($this->folders));
        $this->command->info('   Tags: '.count($this->tags));
        $this->command->info('   LedgerDefines: '.count($this->ledgerDefines));
        $this->command->info('   Total Ledgers: '.Ledger::count());
        $this->command->info('   Total LedgerDefines: '.LedgerDefine::count());
        $this->command->info('');
        $this->command->info('🎯 Phase 1 Achievements:');
        $this->command->info('   ✓ All 10 InputTypes covered');
        $this->command->info('   ✓ All 5 WorkflowStatus covered');
        $this->command->info('   ✓ Folder hierarchy completed (10 folders)');
        $this->command->info('   ✓ 25 tags created (categorized)');
        $this->command->info('   ✓ 60+ ledgers with realistic content');
        $this->command->info('');
    }
}
