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
 * - 台帳定義: 1種（営業日報、8カラム: 日付/顧客名/訪問目的/商談ステータス/優先度/商談内容/成果・所感/次回アクション）
 * - 台帳: 7件（長文コンテンツ、日本語項目名、状態管理カラム付き）
 * - タグ: 3個（台帳定義に付与、横断検索用）
 */
class DemoMinimalSeeder extends Seeder
{
    private $tenant;

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

        $this->command->info('🏢 Step 0/7: Creating and initializing tenant...');
        $this->createAndInitializeTenant();

        $this->command->info('📋 Step 1/7: Creating users and roles...');
        $this->createUsersAndRoles();

        $this->command->info('📁 Step 2/7: Creating folder structure...');
        $this->createFolders();

        $this->command->info('🔐 Step 3/7: Setting up permissions...');
        $this->setupPermissions();

        $this->command->info('📝 Step 4/7: Creating ledger define...');
        $this->createSalesDailyDefine();

        $this->command->info('🏷️  Step 5/7: Creating tags...');
        $this->createTags();

        $this->command->info('📊 Step 6/7: Creating demo ledgers...');
        $this->createDemoLedgers();

        $this->command->info('✅ Demo data created successfully!');
        $this->command->info('');
        $this->command->info('🔑 Login credentials:');
        $this->command->info('   Demo User:  demo@example.com  / demo1234');
        $this->command->info('   Admin User: admin@example.com / demo1234');
        $this->command->info('');
        $this->command->info('🏢 Tenant Info:');
        $this->command->info('   Tenant ID: '.$this->tenant->id);
        $this->command->info('');
    }

    private function createAndInitializeTenant(): void
    {
        // テナントを作成または取得
        $this->tenant = \App\Models\Tenant::firstOrCreate(
            ['id' => 'demo-tenant'],
            ['name' => 'Demo Tenant']
        );

        $this->command->info('   ✓ Tenant created or found: '.$this->tenant->id);

        // テナントを初期化（これ以降のモデル操作はこのテナントコンテキストで実行される）
        tenancy()->initialize($this->tenant);

        $this->command->info('   ✓ Tenant initialized: '.$this->tenant->id);

        // テナントデータベースをマイグレーション（まだマイグレーションされていない場合）
        $connection = \DB::connection('mysql');
        $tablesExist = $connection->getSchemaBuilder()->hasTable('folders');

        if (! $tablesExist) {
            $this->command->info('   ⚙️  Running tenant migrations...');
            \Artisan::call('tenants:migrate', [
                '--tenants' => [$this->tenant->id],
            ]);
            $this->command->info('   ✓ Tenant migrations completed');
        } else {
            $this->command->info('   ✓ Tenant database already migrated');
        }
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
        // カラム定義（日本語項目名 + 状態管理カラム + 表示レベル + グループ）
        // 引数順序: id, name, typeIdentifier, order, options, required, unique, sortBy, hint, file, display_level, group
        $columns = [
            // 基本情報グループ - 常に表示（display_level: 1）
            new ColumnDefine(
                0, '日付', 'YMD', 0,
                ['default_offset' => '0d'], // 今日をデフォルト
                true, false, false, '訪問日', [],
                1, // display_level: 1 (常に表示)
                '基本情報' // group
            ),
            new ColumnDefine(
                1, '顧客名', 'text', 1, [],
                true, false, false, '', [],
                1, '基本情報'
            ),
            new ColumnDefine(
                2, '訪問目的', 'text', 2, [],
                false, false, false, '', [],
                2, // display_level: 2 (概要表示)
                '基本情報'
            ),

            // 商談情報グループ
            new ColumnDefine(
                3, '商談ステータス', 'select', 3, [
                    '初回訪問',
                    '提案中',
                    'フォローアップ',
                    '価格交渉中',
                    '契約直前',
                    '契約済み',
                    '見送り',
                    '再提案予定',
                ],
                true, false, false, '', [],
                1, // 常に表示
                '商談情報'
            ),

            new ColumnDefine(
                4, '優先度', 'select', 4, [
                    '高', '中', '低',
                ],
                true, false, false, '', [],
                1, // 常に表示
                '商談情報'
            ),

            // 詳細情報グループ
            new ColumnDefine(
                5, '商談内容', 'textarea', 5, [],
                true, false, false, '', [],
                2, // 概要表示
                '詳細情報'
            ),
            new ColumnDefine(
                6, '成果・所感', 'textarea', 6, [],
                false, false, false, '', [],
                3, // 詳細表示
                '詳細情報'
            ),
            new ColumnDefine(
                7, '次回アクション', 'textarea', 7, [],
                false, false, false, '', [],
                2, // 概要表示
                '詳細情報'
            ),
        ];

        // マークダウン形式の説明文
        $createDescription = <<<'MARKDOWN'
## 営業日報の入力について

この台帳は日々の営業活動を記録するためのものです。以下の点に注意して入力してください。

### 入力時の注意事項

- **日付**: 訪問日を入力してください（デフォルトは今日の日付）
- **顧客名**: 正式な法人名・部署名を入力してください
- **商談ステータス**: 現在の商談フェーズを正確に選択してください

### 関連リンク

- [営業活動ガイドライン](https://example.com/sales-guideline)
- [顧客管理システム](https://example.com/crm)
- [商談報告テンプレート](https://example.com/template)

### 重要事項

> 💡 **ヒント**: 商談内容は具体的に記載すると、後から振り返る際に役立ちます。
>
> ⚠️ **注意**: 機密情報の取り扱いには十分注意してください。

MARKDOWN;

        $listDescription = <<<'MARKDOWN'
## 営業日報一覧

### 表示項目について

| 項目 | 説明 |
|------|------|
| 日付 | 訪問日 |
| 顧客名 | 訪問先企業・部署 |
| ステータス | 商談の進捗状況 |
| 優先度 | 案件の重要度（高・中・低） |

### フィルタリング機能

- **ステータス別**: 特定の商談フェーズで絞り込み
- **優先度別**: 重要案件を優先表示
- **タグ検索**: プロジェクト横断で検索可能

### 活用のヒント

契約直前や価格交渉中の案件を定期的にチェックし、適切なフォローアップを心がけましょう。

📊 [営業ダッシュボード](https://example.com/dashboard) で全体の進捗を確認できます。
MARKDOWN;

        $detailDescription = <<<'MARKDOWN'
## 営業日報詳細

この画面では、営業活動の詳細情報を確認できます。

### 情報の構成

#### 基本情報
- 訪問日時
- 訪問先企業情報
- 訪問目的

#### 商談情報
- 現在の商談ステータス
- 案件の優先度
- 関連タグ

#### 詳細情報
- 商談内容の詳細
- 成果・所感
- 次回アクションプラン

### 履歴管理

> 📝 この台帳の変更履歴は自動的に記録されます。
> 過去の編集内容を確認したい場合は、「履歴」タブをご覧ください。

### 関連機能

- **PDF出力**: 報告書として出力可能
- **共有**: チームメンバーと情報共有
- **リマインダー**: 次回アクションの通知設定

---

詳細は [営業活動マニュアル](https://example.com/manual) をご参照ください。
MARKDOWN;

        $this->salesDailyDefine = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO] 営業日報'],
            [
                'folder_id' => $this->dailyFolder->id,
                'workflow_enabled' => false, // シンプルにワークフローなし
                'column_define' => $columns,
                'create_description' => $createDescription,
                'list_description' => $listDescription,
                'detail_description' => $detailDescription,
                'creator_id' => $this->adminUser->id,
                'modifier_id' => $this->adminUser->id,
            ]
        );

        $this->command->info('   ✓ Ledger define created: [DEMO] 営業日報 with 8 columns (including status and priority)');
    }

    private function createTags(): void
    {
        // タグは台帳定義に付与（フォルダ・台帳定義を横断する検索用）
        // プロジェクト横断的なキーワードを使用
        $definesTags = [
            '2025年度営業計画',
            '新製品展開',
            '顧客管理',
        ];

        foreach ($definesTags as $name) {
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

        $this->command->info('   ✓ Tags attached to ledger define: '.implode(', ', $definesTags));
    }

    private function createDemoLedgers(): void
    {
        $ledgers = [
            // 件1: 顧客A - 新規提案
            [
                'content' => [
                    0 => '2025-10-01',                    // 日付
                    1 => '株式会社A商事',                 // 顧客名
                    2 => '新製品の提案',                   // 訪問目的
                    3 => '提案中',                         // 商談ステータス
                    4 => '高',                             // 優先度
                    5 => <<<'MARKDOWN'
## 面談概要

本日、**株式会社A商事** の購買部長である **鈴木様** と **田中様** にお会いし、当社の新製品「**LedgerLeap**」についてご紹介させていただきました。

### 先方の課題

鈴木様からは、現在使用している台帳管理システムについて以下の課題があるとのお話がありました：

- 🔍 **検索機能が弱い**
  - 過去の記録を探すのに時間がかかる
  - 業務効率が低下している
- 📊 データ量の増加に伴うパフォーマンス低下
- 👥 承認プロセスの手動管理による負担

### デモ内容

LedgerLeapの主要機能をデモンストレーション：

1. **全文検索機能**（Mroonga使用）
   - 高速な日本語全文検索
   - 添付ファイル内の検索も対応
2. **ワークフロー機能**
   - 承認プロセスの自動化
   - 担当者の自動推薦

### 先方の反応

> 💬 「これはまさに求めていた機能だ」
> — 鈴木部長

特に **検索機能** と **ワークフロー** に強い関心を示されていました。

### 技術スペック

| 機能 | 説明 | 先方の評価 |
|------|------|-----------|
| 全文検索 | Mroonga使用の高速検索 | ⭐⭐⭐⭐⭐ |
| ワークフロー | 承認プロセス自動化 | ⭐⭐⭐⭐⭐ |
| 権限管理 | 細かいアクセス制御 | ⭐⭐⭐⭐ |

### 参考リンク

- [LedgerLeap製品ページ](https://example.com/ledgerleap)
- [導入事例集](https://example.com/cases)
MARKDOWN,
                    6 => <<<'MARKDOWN'
### 評価 ⭐⭐⭐⭐⭐

非常に好感触でした。特に **検索機能** と **ワークフロー** に強い関心を示されていました。

#### プラス要因
- ✅ 現行システムの課題を明確に理解
- ✅ 予算確保の意向あり
- ✅ 決裁権限者との面談実現

#### 懸念事項
- ⚠️ 価格面での調整が必要になりそう
- ⚠️ 競合他社との比較検討中

#### 総合判断
導入の可能性は **高い** と判断しています（確度: 75%）
MARKDOWN,
                    7 => <<<'MARKDOWN'
### 次回アクションプラン

#### 📅 来週までに準備

1. **見積書の作成**
   - 詳細な価格表
   - 導入スケジュール案
   - ROI試算書

2. **POC環境の準備**
   - 実データを使ったデモ環境構築
   - A商事様の業務フローに合わせたカスタマイズ案

#### 📞 フォローアップ

- 10月8日（火）再訪問予定
- 担当: 鈴木部長、田中様、情報システム部長（追加）

#### 📌 期限

**10月7日（月）**: 提案書提出
MARKDOWN,
                ],
                'created_at' => now()->subDays(3),
            ],

            // 件2: 顧客A - フォローアップ
            [
                'content' => [
                    0 => '2025-10-02',
                    1 => '株式会社A商事',
                    2 => '提案フォローアップ',
                    3 => 'フォローアップ',
                    4 => '高',
                    5 => <<<'MARKDOWN'
## フォローアップミーティング

昨日の提案を受けて、鈴木部長から追加のご質問をいただきました。

### 主な質問事項

#### 1. データ移行について
- 📦 **現状**: 3万件以上の台帳データ
- 🔄 **移行方法**: どのように安全に移行できるか？
- ⏱️ **所要期間**: システム停止時間はどの程度か？

#### 2. セキュリティ対策
- 🔐 アクセス制御の仕組み
- 👤 ユーザー権限管理
- 📝 監査ログの取得

#### 3. バックアップ・DR
- 💾 バックアップ頻度と保存期間
- 🚨 災害時の復旧手順
- ☁️ クラウドバックアップの可否

#### 4. カスタマイズ
- 🎨 UI/UXのカスタマイズ範囲
- 🔌 既存システムとの連携
- 💰 追加費用の見込み

### 当社からの回答

特に **データ移行** については重点的に説明しました。

> 📊 **実績紹介**
>
> B社での **5万件のデータ移行事例** を紹介
> - 移行期間: 2週間
> - システム停止時間: 6時間
> - エラー率: 0.01%未満

この説明により、先方の懸念を払拭できたようです。

### 技術的な議論

```
データ移行プロセス:
1. データ抽出（CSVエクスポート）
2. データクレンジング
3. テスト環境での検証
4. 本番環境への移行
5. 検証・確認
```

### 関連ドキュメント

- [データ移行ガイドライン](https://example.com/migration-guide)
- [セキュリティホワイトペーパー](https://example.com/security)
MARKDOWN,
                    6 => <<<'MARKDOWN'
### 分析と所感

#### ポジティブな兆候 ✨

技術的な質問が多く出たことは、**導入に向けて真剣に検討** されている証拠だと感じました。

| 観点 | 評価 | 備考 |
|------|------|------|
| 興味度 | ⭐⭐⭐⭐⭐ | 具体的な質問多数 |
| 予算 | ⭐⭐⭐⭐ | 承認見込みあり |
| 時期 | ⭐⭐⭐⭐ | 今期中の導入希望 |

#### 重点事項

来週の提案書では、以下を重点的に説明する必要があります：

1. 📋 **詳細な移行計画**
2. 🔒 **セキュリティ対策の具体策**
3. 💰 **ROI試算**

#### 懸念事項

- ⚠️ 競合他社（X社）も提案中
- ⚠️ 予算承認プロセスに時間がかかる可能性
MARKDOWN,
                    7 => <<<'MARKDOWN'
### アクションアイテム

#### 📝 来週までに作成

- [ ] データ移行計画書（詳細版）
  - 移行スケジュール
  - リスク分析
  - ロールバック手順
- [ ] セキュリティ診断レポート
- [ ] POC環境の構築
  - A商事様の実データサンプル使用
  - 業務フローに沿ったシナリオ

#### 🎯 目標

**10月9日（水）**: POC環境デモ実施

#### 🤝 協力依頼

- 開発チーム: POC環境構築（優先度: 高）
- セキュリティチーム: 診断レポート作成

> 💡 **重要**: 競合との差別化ポイントを明確に！
MARKDOWN,
                ],
                'created_at' => now()->subDays(2),
            ],

            // 件3: 顧客B - 定期訪問
            [
                'content' => [
                    0 => '2025-09-28',
                    1 => '株式会社Bシステムズ',
                    2 => '定期訪問・状況確認',
                    3 => '契約済み',
                    4 => '中',
                    5 => "既存顧客である株式会社Bシステムズへの定期訪問を実施しました。担当の佐藤様から、現在使用中の当社システムについて概ね満足しているとのフィードバックをいただきました。\n\n一方で、以下の要望もいただきました:\n- スマートフォンアプリの操作性向上\n- CSVエクスポート機能の拡充\n- より詳細な利用統計レポート\n\n特にスマートフォンアプリについては、現場作業員の方々がタブレットで日報を入力する際に、若干使いづらさを感じているとのことでした。",
                    6 => '長期的な信頼関係が構築できていることを実感しました。要望事項については、開発チームと相談の上、次回バージョンアップで対応できる見込みです。',
                    7 => '開発チームに要望を伝え、対応可否と時期を確認します。来月の定期訪問時に回答します。',
                ],
                'created_at' => now()->subDays(6),
            ],

            // 件4: 顧客C - 価格交渉
            [
                'content' => [
                    0 => '2025-09-30',
                    1 => 'C製造株式会社',
                    2 => '価格交渉',
                    3 => '契約直前',
                    4 => '高',
                    5 => "C製造株式会社の導入検討が最終段階に入りました。本日は経理部長の伊藤様も同席され、価格についての詳細な協議を行いました。\n\n先方からの要望:\n- 初期費用の分割払い対応\n- ユーザー数に応じた段階的な料金設定\n- 3年契約での割引適用\n\n当社としては、3年契約を条件に15%の割引を提示しました。また、初期費用については6ヶ月の分割払いに対応できることをお伝えしました。\n\n伊藤部長からは「予算内に収まる見込みが立った」とのコメントをいただき、次回の役員会で最終承認を得る方向で進めていただけることになりました。",
                    6 => '価格交渉は難航するかと思いましたが、柔軟な支払い条件を提示できたことで、スムーズに合意に至りました。役員会の承認が得られれば、今月中の契約締結も可能です。',
                    7 => '正式な見積書と契約書ドラフトを作成し、来週初めに提出します。',
                ],
                'created_at' => now()->subDays(4),
            ],

            // 件5: 顧客D - トラブル対応
            [
                'content' => [
                    0 => '2025-10-03',
                    1 => '株式会社Dコーポレーション',
                    2 => 'トラブル対応',
                    3 => '契約済み',
                    4 => '高',
                    5 => "昨日、D社の担当者から緊急の連絡があり、システムの動作が遅くなっているとのことで訪問しました。\n\n原因を調査したところ、データ量の急激な増加によりデータベースのインデックスが最適化されていない状態でした。現場でインデックスの再構築を実施したところ、パフォーマンスが大幅に改善されました。\n\nまた、今後同様の問題が発生しないよう、定期的なメンテナンスについて提案を行いました:\n- 月次でのインデックス最適化\n- データアーカイブの実施（2年以前のデータ）\n- パフォーマンス監視の導入\n\n担当の加藤様からは、迅速な対応に感謝していただけました。",
                    6 => 'トラブルは発生しましたが、迅速に対応できたことで信頼関係を維持できました。予防的なメンテナンス提案も好意的に受け止めていただき、追加契約の可能性も出てきました。',
                    7 => 'メンテナンスサービスの提案書を作成し、来週提出します。',
                ],
                'created_at' => now()->subDays(1),
            ],

            // 件6: 顧客E - 見送り
            [
                'content' => [
                    0 => '2025-09-25',
                    1 => '株式会社E物産',
                    2 => '最終提案',
                    3 => '見送り',
                    4 => '中',
                    5 => "3ヶ月にわたり提案を続けてきた株式会社E物産ですが、本日、導入を見送る旨の連絡をいただきました。\n\n見送りの理由:\n1. 予算の都合（今期の設備投資予算が削減された）\n2. 既存システムの延命対応を優先\n3. 社内の業務プロセス見直しが先決\n\n担当の木村様からは、来期以降に改めて検討したいとのお話をいただきました。また、当社の提案内容自体は高く評価していただけているとのことです。",
                    6 => '残念な結果ではありますが、完全に見送りというわけではなく、時期の問題であることが確認できました。来期の予算編成時期（12月頃）に再度アプローチする価値はあります。',
                    7 => '半年後（2026年3月）に状況確認の連絡を入れます。それまで定期的な情報提供（メールマガジン等）で関係を維持します。',
                ],
                'created_at' => now()->subDays(9),
            ],

            // 件7: 顧客F - 初回訪問
            [
                'content' => [
                    0 => '2025-10-04',
                    1 => '株式会社Fソリューションズ',
                    2 => '初回訪問・ヒアリング',
                    3 => '初回訪問',
                    4 => '高',
                    5 => "新規案件として、株式会社Fソリューションズへの初回訪問を実施しました。\n\nF社は従業員300名規模のIT企業で、現在は紙とExcelで各種報告書を管理しているとのことです。情報システム部長の林様から、以下の課題をお聞きしました:\n\n主要課題:\n1. 情報が分散しており、過去の記録を探すのに時間がかかる\n2. 承認プロセスが煩雑で、承認待ちの書類が滞留する\n3. テレワーク環境で紙の書類を扱うのが困難\n4. 法令対応（電子帳簿保存法など）への不安\n\n林様は、LedgerLeapのワークフロー機能と全文検索機能に強い関心を示されました。特に、OCR機能による紙資料のデジタル化と検索については「まさに求めていた機能」とおっしゃっていました。",
                    6 => '非常に前向きな反応をいただけました。課題が明確で、当社のソリューションがフィットする可能性が高いです。競合他社の提案も受けているとのことですが、機能面で優位性があると感じています。',
                    7 => '来週、デモ環境をご用意して、実際の操作感を確認していただきます。特にワークフロー機能とOCR検索を中心にデモを実施する予定です。',
                ],
                'created_at' => now(),
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

            $ledgerNumber = $index + 1;
            $customerName = $ledgerData['content'][1];
            $status = $ledgerData['content'][3];
            $priority = $ledgerData['content'][4];
            $this->command->info("   ✓ Ledger {$ledgerNumber}/7 created: {$customerName} (ステータス: {$status}, 優先度: {$priority})");
        }

        $this->command->info('   ✓ All 7 demo ledgers created with status and priority fields');
    }
}
