<?php

namespace Database\Seeders;

use App\Enums\FolderPermissionType;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo Minimal Seeder
 * 
 * LLMとの対話デモ用の最小限のデータセット
 * - ユーザー: 2名
 * - フォルダ: 3個（ルート、デモ用フォルダ、日報）
 * - 台帳定義: 1種（営業日報）
 * - 台帳: 7件（長文コンテンツ、日本語項目名）
 * - タグ: 16個
 */
class DemoMinimalSeeder extends Seeder
{
    private User $demoUser;
    private User $adminUser;
    private Role $demoRole;
    private Role $adminRole;
    private Folder $rootFolder;
    private Folder $demoFolder;
    private Folder $dailyFolder;
    private LedgerDefine $salesDailyDefine;
    private array $tags = [];

    public function run(): void
    {
        $this->command->info('🚀 Starting Demo Minimal Seeder...');

        $this->command->info('📋 Step 1/6: Creating users and roles...');
        $this->createUsersAndRoles();

        $this->command->info('📁 Step 2/6: Creating folder structure...');
        $this->createFolders();

        $this->command->info('🔐 Step 3/6: Setting up permissions...');
        $this->setupPermissions();

        $this->command->info('📝 Step 4/6: Creating ledger define...');
        $this->createSalesDailyDefine();

        $this->command->info('🏷️  Step 5/6: Creating tags...');
        $this->createTags();

        $this->command->info('📊 Step 6/6: Creating demo ledgers...');
        $this->createDemoLedgers();

        $this->command->info('✅ Demo data created successfully!');
        $this->command->info('');
        $this->command->info('🔑 Login credentials:');
        $this->command->info('   Demo User:  demo@example.com  / demo1234');
        $this->command->info('   Admin User: admin@example.com / demo1234');
        $this->command->info('');
    }

    private function createUsersAndRoles(): void
    {
        // ロールの取得または作成（RolesAndPermissionsSeederで作成済みの想定）
        $this->adminRole = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            ['guard_name' => 'web']
        );

        $this->demoRole = Role::firstOrCreate(
            ['name' => 'Demo User'],
            ['guard_name' => 'web']
        );

        // デモユーザー作成
        $this->demoUser = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => '田中太郎',
                'password' => bcrypt('demo1234'),
            ]
        );
        $this->demoUser->assignRole($this->demoRole);

        // 管理者ユーザー作成
        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '山田花子',
                'password' => bcrypt('demo1234'),
            ]
        );
        $this->adminUser->assignRole($this->adminRole);

        $this->command->info("   ✓ Users created: {$this->demoUser->name}, {$this->adminUser->name}");
    }

    private function createFolders(): void
    {
        // ルートフォルダ
        $this->rootFolder = Folder::firstOrCreate(
            ['title' => '/', 'parent_id' => null],
            [
                'creator_id' => $this->adminUser->id,
                'modifier_id' => $this->adminUser->id,
            ]
        );

        // デモ用フォルダ
        $this->demoFolder = Folder::firstOrCreate(
            ['title' => 'デモ用フォルダ', 'parent_id' => $this->rootFolder->id],
            [
                'creator_id' => $this->adminUser->id,
                'modifier_id' => $this->adminUser->id,
            ]
        );

        // 日報フォルダ
        $this->dailyFolder = Folder::firstOrCreate(
            ['title' => '日報', 'parent_id' => $this->demoFolder->id],
            [
                'creator_id' => $this->adminUser->id,
                'modifier_id' => $this->adminUser->id,
            ]
        );

        $this->command->info('   ✓ Folders created: /, デモ用フォルダ, 日報');
    }

    private function setupPermissions(): void
    {
        // デモユーザーロールに日報フォルダへの書き込み権限を付与
        RoleFolderPermission::updateOrCreate(
            [
                'role_id' => $this->demoRole->id,
                'folder_id' => $this->dailyFolder->id,
            ],
            [
                'permission' => FolderPermissionType::WRITE,
                'modifier_id' => $this->adminUser->id,
            ]
        );

        // 管理者ロールにルートフォルダへの管理権限を付与
        RoleFolderPermission::updateOrCreate(
            [
                'role_id' => $this->adminRole->id,
                'folder_id' => $this->rootFolder->id,
            ],
            [
                'permission' => FolderPermissionType::ADMIN,
                'modifier_id' => $this->adminUser->id,
            ]
        );

        $this->command->info('   ✓ Permissions set: WRITE for demo user, ADMIN for admin user');
    }

    private function createSalesDailyDefine(): void
    {
        // カラム定義（日本語項目名）
        // 引数順序: id, name, typeIdentifier, order, options, required, unique, sortBy, hint, file
        $columns = [
            new ColumnDefine(0, '日付', 'YMD', 0, [], true, false, false, '', []),
            new ColumnDefine(1, '顧客名', 'text', 1, [], true, false, false, '', []),
            new ColumnDefine(2, '訪問目的', 'text', 2, [], false, false, false, '', []),
            new ColumnDefine(3, '商談内容', 'textarea', 3, [], true, false, false, '', []),
            new ColumnDefine(4, '成果・所感', 'textarea', 4, [], false, false, false, '', []),
            new ColumnDefine(5, '次回アクション', 'textarea', 5, [], false, false, false, '', []),
        ];

        $this->salesDailyDefine = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO] 営業日報'],
            [
                'folder_id' => $this->dailyFolder->id,
                'workflow_enabled' => false, // シンプルにワークフローなし
                'column_define' => $columns,
                'creator_id' => $this->adminUser->id,
                'modifier_id' => $this->adminUser->id,
            ]
        );

        $this->command->info('   ✓ Ledger define created: [DEMO] 営業日報 with 6 columns');
    }

    private function createTags(): void
    {
        $tagNames = [
            '新規', '重要', '大型案件', 'フォローアップ', 'データ移行',
            '既存顧客', '定期訪問', '要望', '価格交渉', '契約直前',
            'トラブル対応', 'メンテナンス', '見送り', '再提案予定',
            '初回訪問', '有望',
        ];

        foreach ($tagNames as $name) {
            $this->tags[$name] = Tag::firstOrCreate(
                [
                    'name' => $name,
                    'ledger_define_id' => $this->salesDailyDefine->id,
                ],
                [
                    'folder_id' => $this->dailyFolder->id,
                    'creator_id' => $this->adminUser->id,
                    'modifier_id' => $this->adminUser->id,
                ]
            );
        }

        $this->command->info("   ✓ Tags created: " . count($this->tags) . " tags");
    }

    private function createDemoLedgers(): void
    {
        $ledgers = [
            // 件1: 顧客A - 新規提案
            [
                'content' => [
                    0 => '2025-10-01',
                    1 => '株式会社A商事',
                    2 => '新製品の提案',
                    3 => "本日、株式会社A商事の購買部長である鈴木様と田中様にお会いし、当社の新製品「LedgerLeap」についてご紹介させていただきました。\n\n鈴木様からは、現在使用している台帳管理システムが古く、検索機能が弱いことが課題とのお話がありました。特に、過去の記録を探すのに時間がかかり、業務効率が落ちているとのことです。\n\nLedgerLeapの全文検索機能、特にMroongaを使った高速検索をデモでお見せしたところ、非常に興味を持っていただけました。また、ワークフロー機能による承認プロセスの自動化についても、「これはまさに求めていた機能だ」とおっしゃっていただけました。",
                    4 => "非常に好感触でした。特に検索機能とワークフローに強い関心を示されていました。価格面での調整が必要になりそうですが、導入の可能性は高いと感じています。",
                    5 => "来週、詳細な見積もりと導入スケジュールの提案書を持参します。また、実際のデータを使ったPOC環境の準備も進めます。",
                ],
                'created_at' => now()->subDays(3),
                'tags' => ['新規', '重要', '大型案件'],
            ],

            // 件2: 顧客A - フォローアップ
            [
                'content' => [
                    0 => '2025-10-02',
                    1 => '株式会社A商事',
                    2 => '提案フォローアップ',
                    3 => "昨日の提案を受けて、鈴木部長から追加のご質問をいただきました。\n\n主な質問内容:\n1. 既存システムからのデータ移行方法と期間\n2. セキュリティ対策（特にアクセス制御）\n3. バックアップとディザスタリカバリの仕組み\n4. カスタマイズの可能性と費用\n\n特にデータ移行については、現在3万件以上の台帳データがあり、これを確実に移行できるかが最大の関心事とのことでした。当社の実績として、B社での5万件のデータ移行事例をご紹介し、安心していただけたようです。",
                    4 => "技術的な質問が多く出たことは、導入に向けて真剣に検討されている証拠だと感じました。来週の提案書では、移行計画を重点的に説明する必要があります。",
                    5 => "データ移行計画書を作成し、来週の訪問時に提示します。また、POC環境でA商事様の実データサンプルを使ったデモができるよう準備します。",
                ],
                'created_at' => now()->subDays(2),
                'tags' => ['フォローアップ', '重要', 'データ移行'],
            ],

            // 件3: 顧客B - 定期訪問
            [
                'content' => [
                    0 => '2025-09-28',
                    1 => '株式会社Bシステムズ',
                    2 => '定期訪問・状況確認',
                    3 => "既存顧客である株式会社Bシステムズへの定期訪問を実施しました。担当の佐藤様から、現在使用中の当社システムについて概ね満足しているとのフィードバックをいただきました。\n\n一方で、以下の要望もいただきました:\n- スマートフォンアプリの操作性向上\n- CSVエクスポート機能の拡充\n- より詳細な利用統計レポート\n\n特にスマートフォンアプリについては、現場作業員の方々がタブレットで日報を入力する際に、若干使いづらさを感じているとのことでした。",
                    4 => "長期的な信頼関係が構築できていることを実感しました。要望事項については、開発チームと相談の上、次回バージョンアップで対応できる見込みです。",
                    5 => "開発チームに要望を伝え、対応可否と時期を確認します。来月の定期訪問時に回答します。",
                ],
                'created_at' => now()->subDays(6),
                'tags' => ['既存顧客', '定期訪問', '要望'],
            ],

            // 件4: 顧客C - 価格交渉
            [
                'content' => [
                    0 => '2025-09-30',
                    1 => 'C製造株式会社',
                    2 => '価格交渉',
                    3 => "C製造株式会社の導入検討が最終段階に入りました。本日は経理部長の伊藤様も同席され、価格についての詳細な協議を行いました。\n\n先方からの要望:\n- 初期費用の分割払い対応\n- ユーザー数に応じた段階的な料金設定\n- 3年契約での割引適用\n\n当社としては、3年契約を条件に15%の割引を提示しました。また、初期費用については6ヶ月の分割払いに対応できることをお伝えしました。\n\n伊藤部長からは「予算内に収まる見込みが立った」とのコメントをいただき、次回の役員会で最終承認を得る方向で進めていただけることになりました。",
                    4 => "価格交渉は難航するかと思いましたが、柔軟な支払い条件を提示できたことで、スムーズに合意に至りました。役員会の承認が得られれば、今月中の契約締結も可能です。",
                    5 => "正式な見積書と契約書ドラフトを作成し、来週初めに提出します。",
                ],
                'created_at' => now()->subDays(4),
                'tags' => ['価格交渉', '契約直前', '重要'],
            ],

            // 件5: 顧客D - トラブル対応
            [
                'content' => [
                    0 => '2025-10-03',
                    1 => '株式会社Dコーポレーション',
                    2 => 'トラブル対応',
                    3 => "昨日、D社の担当者から緊急の連絡があり、システムの動作が遅くなっているとのことで訪問しました。\n\n原因を調査したところ、データ量の急激な増加によりデータベースのインデックスが最適化されていない状態でした。現場でインデックスの再構築を実施したところ、パフォーマンスが大幅に改善されました。\n\nまた、今後同様の問題が発生しないよう、定期的なメンテナンスについて提案を行いました:\n- 月次でのインデックス最適化\n- データアーカイブの実施（2年以前のデータ）\n- パフォーマンス監視の導入\n\n担当の加藤様からは、迅速な対応に感謝していただけました。",
                    4 => "トラブルは発生しましたが、迅速に対応できたことで信頼関係を維持できました。予防的なメンテナンス提案も好意的に受け止めていただき、追加契約の可能性も出てきました。",
                    5 => "メンテナンスサービスの提案書を作成し、来週提出します。",
                ],
                'created_at' => now()->subDays(1),
                'tags' => ['トラブル対応', '既存顧客', 'メンテナンス'],
            ],

            // 件6: 顧客E - 見送り
            [
                'content' => [
                    0 => '2025-09-25',
                    1 => '株式会社E物産',
                    2 => '最終提案',
                    3 => "3ヶ月にわたり提案を続けてきた株式会社E物産ですが、本日、導入を見送る旨の連絡をいただきました。\n\n見送りの理由:\n1. 予算の都合（今期の設備投資予算が削減された）\n2. 既存システムの延命対応を優先\n3. 社内の業務プロセス見直しが先決\n\n担当の木村様からは、来期以降に改めて検討したいとのお話をいただきました。また、当社の提案内容自体は高く評価していただけているとのことです。",
                    4 => "残念な結果ではありますが、完全に見送りというわけではなく、時期の問題であることが確認できました。来期の予算編成時期（12月頃）に再度アプローチする価値はあります。",
                    5 => "半年後（2026年3月）に状況確認の連絡を入れます。それまで定期的な情報提供（メールマガジン等）で関係を維持します。",
                ],
                'created_at' => now()->subDays(9),
                'tags' => ['見送り', '再提案予定'],
            ],

            // 件7: 顧客F - 初回訪問
            [
                'content' => [
                    0 => '2025-10-04',
                    1 => '株式会社Fソリューションズ',
                    2 => '初回訪問・ヒアリング',
                    3 => "新規案件として、株式会社Fソリューションズへの初回訪問を実施しました。\n\nF社は従業員300名規模のIT企業で、現在は紙とExcelで各種報告書を管理しているとのことです。情報システム部長の林様から、以下の課題をお聞きしました:\n\n主要課題:\n1. 情報が分散しており、過去の記録を探すのに時間がかかる\n2. 承認プロセスが煩雑で、承認待ちの書類が滞留する\n3. テレワーク環境で紙の書類を扱うのが困難\n4. 法令対応（電子帳簿保存法など）への不安\n\n林様は、LedgerLeapのワークフロー機能と全文検索機能に強い関心を示されました。特に、OCR機能による紙資料のデジタル化と検索については「まさに求めていた機能」とおっしゃっていました。",
                    4 => "非常に前向きな反応をいただけました。課題が明確で、当社のソリューションがフィットする可能性が高いです。競合他社の提案も受けているとのことですが、機能面で優位性があると感じています。",
                    5 => "来週、デモ環境をご用意して、実際の操作感を確認していただきます。特にワークフロー機能とOCR検索を中心にデモを実施する予定です。",
                ],
                'created_at' => now(),
                'tags' => ['新規', '初回訪問', '有望'],
            ],
        ];

        foreach ($ledgers as $index => $ledgerData) {
            $ledger = Ledger::create([
                'ledger_define_id' => $this->salesDailyDefine->id,
                'creator_id' => $this->demoUser->id,
                'modifier_id' => $this->demoUser->id,
                'status' => 'none', // ワークフローなしなので'none'
                'content' => $ledgerData['content'],
                'created_at' => $ledgerData['created_at'],
                'updated_at' => $ledgerData['created_at'],
            ]);

            // タグ情報はデバッグ用にコメントとして残すが、実際には使用しない
            // タグはLedgerDefineに紐づくため、Ledgerに個別にタグを付与することはできない
            // コメント: このLedgerのタグ = " . implode(', ', $ledgerData['tags'])

            $ledgerNumber = $index + 1;
            $customerName = $ledgerData['content'][1];
            $this->command->info("   ✓ Ledger {$ledgerNumber}/7 created: {$customerName}");
        }

        $this->command->info('   ✓ All 7 demo ledgers created successfully');
    }
}
