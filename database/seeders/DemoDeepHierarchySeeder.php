<?php

namespace Database\Seeders;

use App\Enums\FolderPermissionType;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 深い階層フォルダ検証用デモデータ Seeder
 *
 * Issue #73 (フォルダツリーのスクロール追従・深い階層対応) Sprint 6 の成果物。
 * 医療現場（総合病院）を模した 5〜6 段の深いフォルダ階層を再現し、以下を検証可能にする:
 *   - Sticky ツリーのスクロール追従動作
 *   - アコーディオン開閉 UI の操作感
 *   - descendants クエリ最適化 (Sprint 4) の効果測定
 *   - 選択ノード自動スクロール (Sprint 2) の動作確認
 *
 * 前提条件:
 *   DemoCompleteSeeder が実行済みで demo-tenant / demo@example.com が存在すること。
 *   本 Seeder 単体でも動作するが、その場合は Users/Role を自動作成する。
 *
 * 実行方法:
 *   ./vendor/bin/sail artisan db:seed --class=DemoDeepHierarchySeeder
 *
 * 削除・リセット方法:
 *   タイトルプレフィックス [DEMO-DEEP] のフォルダ・台帳定義を削除すれば他データに影響なし。
 *
 * @see https://github.com/torinky/LedgerLeap/issues/73
 * @see docs/work/ui-ux/ledger-list-redesign/2026-02-23_deep-hierarchy-demo-data-plan.md
 */
class DemoDeepHierarchySeeder extends Seeder
{
    private User $adminUser;

    private Role $adminRole;

    /** @var array<string, Folder> */
    private array $folders = [];

    /** @var array<string, LedgerDefine> */
    private array $defines = [];

    public function run(): void
    {
        $this->command->info('🏥 Starting DemoDeepHierarchySeeder (Issue #73 Sprint 6)...');
        $this->command->info('');

        $this->command->info('🏢 Step 1/5: Initializing tenant context...');
        $this->initializeTenant();

        $this->command->info('👤 Step 2/5: Resolving admin user...');
        $this->resolveAdminUser();

        $this->command->info('📁 Step 3/5: Building deep folder hierarchy (5-6 levels)...');
        $this->createFolders();

        $this->command->info('🔐 Step 4/5: Setting up permissions...');
        $this->setupPermissions();

        $this->command->info('📝 Step 5/5: Creating ledger defines and records...');
        $this->createLedgerDefinesAndRecords();

        $this->command->info('');
        $this->command->info('✅ DemoDeepHierarchySeeder completed successfully!');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('   Folders created : '.count($this->folders));
        $this->command->info('   LedgerDefines   : '.count($this->defines));
        $this->command->info('   Login           : demo@example.com / demo1234');
        $this->command->info('');
        $this->command->info('💡 To verify tree rendering, open the ledger list page in a browser.');
    }

    // -------------------------------------------------------------------------
    // Step 1: テナント初期化
    // -------------------------------------------------------------------------

    private function initializeTenant(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(
            ['id' => 'demo-tenant'],
            ['name' => 'Demo Tenant']
        );

        tenancy()->initialize($tenant);
        $this->command->info('   ✓ Tenant initialized: '.$tenant->id);
    }

    // -------------------------------------------------------------------------
    // Step 2: 管理者ユーザーの取得（なければ作成）
    // -------------------------------------------------------------------------

    private function resolveAdminUser(): void
    {
        $this->adminRole = Role::firstOrCreate(
            ['name' => Role::SUPER_ADMIN],
            ['guard_name' => 'web']
        );

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '山田花子',
                'password' => bcrypt('demo1234'),
            ]
        );

        if (! $this->adminUser->hasRole($this->adminRole)) {
            $this->adminUser->assignRole($this->adminRole);
        }

        $this->command->info('   ✓ Admin user: '.$this->adminUser->name.' ('.$this->adminUser->email.')');
    }

    // -------------------------------------------------------------------------
    // Step 3: フォルダ階層の構築
    //
    // [DEMO-DEEP] 総合病院（ルート）             [1段目]
    // ├── 内科部門                              [2段目]
    // │   ├── 外来診療科                         [3段目]
    // │   │   ├── 第一外来病棟                   [4段目]
    // │   │   │   ├── 朝番チーム ← 台帳定義あり   [5段目]
    // │   │   │   └── 夜番チーム ← 台帳定義あり   [5段目]
    // │   │   └── 第二外来病棟                   [4段目]
    // │   └── 入院診療科                         [3段目]
    // │       ├── 第一病棟                       [4段目]
    // │       └── 第二病棟                       [4段目]
    // ├── 外科部門                              [2段目]
    // │   ├── 一般外科                           [3段目]
    // │   │   └── 手術室 ← 台帳定義あり           [4段目]
    // │   └── 整形外科                           [3段目]
    // │       └── リハビリ病棟                   [4段目]
    // └── 管理部門                              [2段目]
    //     ├── 診療録管理                         [3段目]
    //     └── 医療安全管理                       [3段目]
    //         └── インシデント管理 ← 台帳定義あり  [4段目]
    // -------------------------------------------------------------------------

    private function createFolders(): void
    {
        $uid = $this->adminUser->id;

        // ルートを既存ルートの子として作成（DemoCompleteSeeder の '/' 配下に置く）
        $existingRoot = Folder::whereIsRoot()->first();

        if ($existingRoot) {
            $hospital = $existingRoot->children()->firstOrCreate(
                ['title' => '[DEMO-DEEP] 総合病院'],
                ['creator_id' => $uid, 'modifier_id' => $uid]
            );
        } else {
            // 単体実行時: ルートとして作成
            $hospital = Folder::firstOrCreate(
                ['title' => '[DEMO-DEEP] 総合病院', 'parent_id' => null],
                ['creator_id' => $uid, 'modifier_id' => $uid]
            );
        }
        $this->folders['hospital'] = $hospital;
        $this->command->info('   ✓ [1段目] [DEMO-DEEP] 総合病院');

        // 2段目: 部門
        $naika = $hospital->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 内科部門'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['naika'] = $naika;

        $geka = $hospital->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 外科部門'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['geka'] = $geka;

        $kanri = $hospital->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 管理部門'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['kanri'] = $kanri;
        $this->command->info('   ✓ [2段目] 内科部門 / 外科部門 / 管理部門');

        // 3段目: 診療科
        $gairaika = $naika->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 外来診療科'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['gairaika'] = $gairaika;

        $nyuinka = $naika->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 入院診療科'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['nyuinka'] = $nyuinka;

        $ippanGeka = $geka->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 一般外科'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['ippanGeka'] = $ippanGeka;

        $seikeigeka = $geka->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 整形外科'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['seikeigeka'] = $seikeigeka;

        $shinryoroku = $kanri->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 診療録管理'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['shinryoroku'] = $shinryoroku;

        $iryo_anzen = $kanri->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 医療安全管理'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['iryo_anzen'] = $iryo_anzen;
        $this->command->info('   ✓ [3段目] 外来診療科 / 入院診療科 / 一般外科 / 整形外科 / 診療録管理 / 医療安全管理');

        // 4段目: 病棟・部屋
        $dai1gairaiboto = $gairaika->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 第一外来病棟'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['dai1gairaiboto'] = $dai1gairaiboto;

        $dai2gairaiboto = $gairaika->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 第二外来病棟'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['dai2gairaiboto'] = $dai2gairaiboto;

        $dai1byoto = $nyuinka->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 第一病棟'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['dai1byoto'] = $dai1byoto;

        $dai2byoto = $nyuinka->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 第二病棟'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['dai2byoto'] = $dai2byoto;

        $shujutsuShitsu = $ippanGeka->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 手術室'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['shujutsuShitsu'] = $shujutsuShitsu;

        $rehabili = $seikeigeka->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] リハビリ病棟'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['rehabili'] = $rehabili;

        $incident = $iryo_anzen->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] インシデント管理'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['incident'] = $incident;
        $this->command->info('   ✓ [4段目] 第一外来病棟 / 第二外来病棟 / 第一病棟 / 第二病棟 / 手術室 / リハビリ病棟 / インシデント管理');

        // 5段目: チーム（最深部 - Sprint 1〜4 の検証目的）
        $asaban = $dai1gairaiboto->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 朝番チーム'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['asaban'] = $asaban;

        $yoruban = $dai1gairaiboto->children()->firstOrCreate(
            ['title' => '[DEMO-DEEP] 夜番チーム'],
            ['creator_id' => $uid, 'modifier_id' => $uid]
        );
        $this->folders['yoruban'] = $yoruban;
        $this->command->info('   ✓ [5段目] 朝番チーム / 夜番チーム');

        // NestedSet ツリー構造を修復（firstOrCreate 後は必須）
        Folder::fixTree();
        $this->command->info('   ✓ Folder::fixTree() completed');
    }

    // -------------------------------------------------------------------------
    // Step 4: 権限設定（adminRole → hospital ルートに ADMIN 権限）
    // -------------------------------------------------------------------------

    private function setupPermissions(): void
    {
        $hospital = $this->folders['hospital'];

        RoleFolderPermission::updateOrCreate(
            [
                'role_id' => $this->adminRole->id,
                'folder_id' => $hospital->id,
            ],
            [
                'permission' => FolderPermissionType::ADMIN,
                'modifier_id' => $this->adminUser->id,
            ]
        );

        $this->command->info('   ✓ ADMIN permission granted to '.Role::SUPER_ADMIN.' on [DEMO-DEEP] 総合病院');
    }

    // -------------------------------------------------------------------------
    // Step 5: 台帳定義・台帳レコードの作成
    //
    // 台帳定義:
    //   [DEMO-DEEP] 申し送り記録  → 朝番チーム / 夜番チーム
    //   [DEMO-DEEP] 手術記録      → 手術室
    //   [DEMO-DEEP] インシデント報告 → インシデント管理
    //
    // 台帳レコード:
    //   朝番チーム: 5件 / 夜番チーム: 3件 / 手術室: 4件 / インシデント管理: 2件
    // -------------------------------------------------------------------------

    private function createLedgerDefinesAndRecords(): void
    {
        $this->createOshiokuri();
        $this->createSurgery();
        $this->createIncident();
    }

    // ---- 申し送り記録 --------------------------------------------------------

    private function createOshiokuri(): void
    {
        $uid = $this->adminUser->id;

        $columns = [
            new ColumnDefine(0, '日付', 'YMD', 0, ['default_offset' => '0d'], true, false, null, '申し送り日', [], 1, '基本情報'),
            new ColumnDefine(1, '担当者名', 'text', 1, [], true, false, null, '', [], 1, '基本情報'),
            new ColumnDefine(2, '引継ぎ事項', 'textarea', 2, [], true, false, null, '重要な引継ぎ内容を記載してください', [], 1, '引継ぎ内容'),
            new ColumnDefine(3, '患者状態', 'select', 3, ['安定', '経過観察中', '要注意', '緊急対応中'], true, false, null, '', [], 1, '引継ぎ内容'),
            new ColumnDefine(4, '備考', 'textarea', 4, [], false, false, null, '', [], 2, '引継ぎ内容'),
        ];

        // 朝番チーム用
        $asabanDefine = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO-DEEP] 申し送り記録（朝番）'],
            [
                'folder_id' => $this->folders['asaban']->id,
                'workflow_enabled' => false,
                'creator_id' => $uid,
                'modifier_id' => $uid,
                'column_define' => $columns,
            ]
        );
        $this->defines['asaban'] = $asabanDefine;

        // 夜番チーム用
        $yorubanDefine = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO-DEEP] 申し送り記録（夜番）'],
            [
                'folder_id' => $this->folders['yoruban']->id,
                'workflow_enabled' => false,
                'creator_id' => $uid,
                'modifier_id' => $uid,
                'column_define' => $columns,
            ]
        );
        $this->defines['yoruban'] = $yorubanDefine;

        $this->command->info('   ✓ LedgerDefine: [DEMO-DEEP] 申し送り記録（朝番 / 夜番）');

        // 朝番チームのレコード 5件
        $asabanRecords = [
            ['2026-02-23', '鈴木 一郎', "3号室の田中様（78歳）の血圧が朝方140/90と高めでした。\n降圧剤を処方済みですが、日中も経過を観察してください。\n昼食後に再計測お願いします。", '経過観察中', '家族からの電話連絡あり。夕方に面会希望とのこと。'],
            ['2026-02-22', '高橋 花子', "5号室の佐藤様（65歳）の傷口の状態が改善しています。\n消毒は引き続き1日2回（朝・夕）実施してください。\n次回ドクター診察は明日10時予定。", '安定', ''],
            ['2026-02-21', '田中 健太', "本日は入院患者3名の転棟がありました。\n412号室→504号室、408号室→502号室、418号室→507号室\n各担当への引継ぎ書類は事務所に提出済み。", '安定', '転棟後の環境適応に注意が必要な患者様がいます。'],
            ['2026-02-20', '山本 美咲', "7号室の中村様（82歳）が夜中に転倒しかけました。\nベッドサイドにコールボタンを再配置しました。\n夜間の見回り頻度を上げるよう申し送ります。", '要注意', 'ドクターへの報告は完了済み。インシデントレポート提出不要との指示あり。'],
            ['2026-02-19', '伊藤 誠', "本日から新規入院患者2名（503号室・505号室）を受け入れました。\n503号室: 山田様（71歳）糖尿病管理\n505号室: 渡辺様（68歳）術後経過観察\nどちらも初日なので丁寧な対応をお願いします。", '経過観察中', ''],
        ];

        $this->bulkCreateLedgers($asabanDefine, $asabanRecords, '朝番チーム', 5);

        // 夜番チームのレコード 3件
        $yorubanRecords = [
            ['2026-02-23', '加藤 里奈', "深夜2時頃、6号室の橋本様（75歳）が腹痛を訴えました。\nドクターに連絡し、鎮痛剤を投与しました。\n朝の巡回時に状態確認をお願いします。", '要注意', 'バイタル記録は電子カルテに入力済み。'],
            ['2026-02-22', '小林 直樹', "夜間は全患者概ね安静でした。\n2号室の松本様の点滴交換を0時・4時に実施。\n特記事項なし。", '安定', ''],
            ['2026-02-21', '渡辺 由美', "10号室の新入院患者様（入院当日）が不安を訴えていました。\n20分程度話を聞き、落ち着いていただけました。\n朝の担当者に引き継いでください。", '経過観察中', '家族への連絡は不要とのご本人の意向あり。'],
        ];

        $this->bulkCreateLedgers($yorubanDefine, $yorubanRecords, '夜番チーム', 3);
    }

    // ---- 手術記録 ------------------------------------------------------------

    private function createSurgery(): void
    {
        $uid = $this->adminUser->id;

        $columns = [
            new ColumnDefine(0, '手術日', 'YMD', 0, ['default_offset' => '0d'], true, false, null, '', [], 1, '基本情報'),
            new ColumnDefine(1, '患者ID', 'text', 1, [], true, false, null, '例: PT-2026-001', [], 1, '基本情報'),
            new ColumnDefine(2, '術式名', 'text', 2, [], true, false, null, '', [], 1, '手術情報'),
            new ColumnDefine(3, '執刀医', 'text', 3, [], true, false, null, '', [], 1, '手術情報'),
            new ColumnDefine(4, '手術時間（分）', 'text', 4, [], true, false, null, '', [], 1, '手術情報'),
            new ColumnDefine(5, '特記事項', 'textarea', 5, [], false, false, null, '合併症・術中の特記事項など', [], 2, '詳細'),
        ];

        $surgeryDefine = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO-DEEP] 手術記録'],
            [
                'folder_id' => $this->folders['shujutsuShitsu']->id,
                'workflow_enabled' => false,
                'creator_id' => $uid,
                'modifier_id' => $uid,
                'column_define' => $columns,
            ]
        );
        $this->defines['surgery'] = $surgeryDefine;
        $this->command->info('   ✓ LedgerDefine: [DEMO-DEEP] 手術記録');

        // 手術室のレコード 4件
        $surgeryRecords = [
            ['2026-02-23', 'PT-2026-041', '腹腔鏡下胆嚢摘出術', '佐々木 外科部長', '95', '術中出血量: 少量。術後経過良好。翌日退院予定。'],
            ['2026-02-22', 'PT-2026-038', '虫垂切除術（腹腔鏡）', '田村 医師', '75', '緊急手術。術前検査完了後、速やかに施術。術後ICU入室。'],
            ['2026-02-21', 'PT-2026-035', '鼠径ヘルニア修復術', '佐々木 外科部長', '110', '左側。メッシュ法を採用。特記事項なし。'],
            ['2026-02-20', 'PT-2026-031', '胃内視鏡的ポリープ切除術', '森 消化器外科医', '45', '2個切除。病理検査に検体提出済み。'],
        ];

        foreach ($surgeryRecords as $i => $rec) {
            if (! Ledger::where('ledger_define_id', $surgeryDefine->id)->where('creator_id', $uid)->whereJsonContains('content->1', $rec[1])->exists()) {
                Ledger::create([
                    'ledger_define_id' => $surgeryDefine->id,
                    'creator_id' => $uid,
                    'modifier_id' => $uid,
                    'status' => 'none',
                    'content' => [0 => $rec[0], 1 => $rec[1], 2 => $rec[2], 3 => $rec[3], 4 => $rec[4], 5 => $rec[5]],
                    'created_at' => now()->subDays(3 - $i),
                    'updated_at' => now()->subDays(3 - $i),
                ]);
            }
        }
        $this->command->info('   ✓ Ledger records: 手術室 4件');
    }

    // ---- インシデント報告 -----------------------------------------------------

    private function createIncident(): void
    {
        $uid = $this->adminUser->id;

        $columns = [
            new ColumnDefine(0, '発生日', 'YMD', 0, ['default_offset' => '0d'], true, false, null, '', [], 1, '基本情報'),
            new ColumnDefine(1, '発生場所', 'text', 1, [], true, false, null, '例: 3階 東病棟 302号室', [], 1, '基本情報'),
            new ColumnDefine(2, 'インシデント種別', 'select', 2, ['転倒・転落', '投薬ミス', '誤嚥', '医療器具関連', 'その他'], true, false, null, '', [], 1, '発生内容'),
            new ColumnDefine(3, '重篤度', 'select', 3, ['レベル0（ヒヤリハット）', 'レベル1（患者への影響なし）', 'レベル2（要観察）', 'レベル3（処置必要）', 'レベル4（重篤）'], true, false, null, '', [], 1, '発生内容'),
            new ColumnDefine(4, '経緯・詳細', 'textarea', 4, [], true, false, null, '発生状況を具体的に記載してください', [], 1, '発生内容'),
            new ColumnDefine(5, '再発防止策', 'textarea', 5, [], false, false, null, '', [], 2, '対応・改善'),
        ];

        $incidentDefine = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO-DEEP] インシデント報告'],
            [
                'folder_id' => $this->folders['incident']->id,
                'workflow_enabled' => false,
                'creator_id' => $uid,
                'modifier_id' => $uid,
                'column_define' => $columns,
            ]
        );
        $this->defines['incident'] = $incidentDefine;
        $this->command->info('   ✓ LedgerDefine: [DEMO-DEEP] インシデント報告');

        // インシデント管理のレコード 2件
        $incidentRecords = [
            [
                '2026-02-22',
                '2階 西病棟 205号室',
                '転倒・転落',
                'レベル1（患者への影響なし）',
                "夜間巡回中、205号室の患者様（78歳）がベッドから降りようとしているところを発見しました。\n転倒には至らなかったが、コールボタンを押さずに動こうとした行動が確認されました。\n患者様に転倒リスクを再説明し、コールボタンの使用を改めてお願いしました。",
                "ベッド柵の設置状況を再確認し、転倒リスクの高い患者様へのラベル表示を強化する。\nナースステーションのモニター巡回頻度を夜間帯で増やす。",
            ],
            [
                '2026-02-18',
                '薬剤部受け取りカウンター',
                '投薬ミス',
                'レベル0（ヒヤリハット）',
                "薬剤師が患者様の薬袋を確認した際、隣の患者様のものと混在している可能性に気づきました。\n実際には混在はなく、確認の段階で発見された「ヒヤリハット」事例です。\n繁忙時間帯に受け取りカウンターの秩序が乱れやすい状況が背景にあります。",
                "薬袋の受け取りカウンターに区分けバーを設置する。\n繁忙時間帯の薬剤師補助スタッフを増員するよう検討する。\nWチェックの徹底を全スタッフに周知する。",
            ],
        ];

        foreach ($incidentRecords as $i => $rec) {
            if (! Ledger::where('ledger_define_id', $incidentDefine->id)->where('creator_id', $uid)->whereJsonContains('content->0', $rec[0])->exists()) {
                Ledger::create([
                    'ledger_define_id' => $incidentDefine->id,
                    'creator_id' => $uid,
                    'modifier_id' => $uid,
                    'status' => 'none',
                    'content' => [0 => $rec[0], 1 => $rec[1], 2 => $rec[2], 3 => $rec[3], 4 => $rec[4], 5 => $rec[5]],
                    'created_at' => now()->subDays(5 - $i * 4),
                    'updated_at' => now()->subDays(5 - $i * 4),
                ]);
            }
        }
        $this->command->info('   ✓ Ledger records: インシデント管理 2件');
    }

    // -------------------------------------------------------------------------
    // ヘルパー: Ledger を一括作成
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array<int, string>>  $records
     */
    private function bulkCreateLedgers(LedgerDefine $define, array $records, string $label, int $expectedCount): void
    {
        $uid = $this->adminUser->id;
        $created = 0;

        foreach ($records as $i => $rec) {
            // 重複作成を防ぐため、日付＋担当者名でチェック
            if (! Ledger::where('ledger_define_id', $define->id)->whereJsonContains('content->0', $rec[0])->whereJsonContains('content->1', $rec[1])->exists()) {
                Ledger::create([
                    'ledger_define_id' => $define->id,
                    'creator_id' => $uid,
                    'modifier_id' => $uid,
                    'status' => 'none',
                    'content' => array_combine(range(0, count($rec) - 1), $rec),
                    'created_at' => now()->subDays($expectedCount - $i),
                    'updated_at' => now()->subDays($expectedCount - $i),
                ]);
                $created++;
            }
        }

        $this->command->info("   ✓ Ledger records: {$label} {$created}/{$expectedCount}件");
    }
}
