# デモ環境整備 - Step 1: SearchLedgersTool最小構成

**作成日:** 2025年10月4日  
**目的:** LLMとの対話デモができる最小限のデータセット構築  
**方針:** シンプル・確実・段階的

---

## 🎯 Step 1の目標

### やること
- ✅ 検索可能な台帳を5-10件作成
- ✅ 日本語項目名、長文コンテンツ
- ✅ LLMが対話できる内容
- ✅ SearchLedgersToolの動作確認

### やらないこと
- ❌ 複雑な権限設定（最小限のみ）
- ❌ 全InputTypeの網羅（必要最小限）
- ❌ ワークフローの複雑な状態
- ❌ 大量データ

---

## 📝 必要なデータ（超シンプル版）

### 1. ユーザー: 2名のみ

```yaml
田中太郎:
  email: demo@example.com
  password: demo1234
  role: 一般ユーザー
  用途: デモ・テスト用メインユーザー

山田花子:
  email: admin@example.com  
  password: demo1234
  role: 管理者
  用途: 管理機能確認用
```

### 2. フォルダ: 3個のみ

```yaml
/ (ルート)
└── デモ用フォルダ/
    ├── 日報/
    └── 議事録/
```

### 3. 台帳定義: 1種類（営業日報）

**重要な設計変更（2025-10-05）:**
- タグの本来の目的: フォルダと台帳定義を横断する検索用
- 状態管理: カラムとして定義し、全文検索で検索可能に

```yaml
タイトル: "[DEMO] 営業日報"
フォルダ: "デモ用フォルダ/日報"
ワークフロー: なし（シンプルに）

台帳定義に付与するタグ（横断検索用）:
  - "2025年度営業計画"  # プロジェクト横断
  - "新製品展開"        # 活動横断
  - "顧客管理"          # 機能横断

項目（8カラム）:
  0. 日付:
    type: YMD
    必須: true
  
  1. 顧客名:
    type: text
    必須: true
  
  2. 訪問目的:
    type: text
    必須: false
  
  3. 商談ステータス:  # ← 新規追加（状態管理）
    type: select
    必須: true
    選択肢:
      - 初回訪問
      - 提案中
      - フォローアップ
      - 価格交渉中
      - 契約直前
      - 契約済み
      - 見送り
      - 再提案予定
  
  4. 優先度:  # ← 新規追加（状態管理）
    type: select
    必須: true
    選択肢:
      - 高
      - 中
      - 低
  
  5. 商談内容:
    type: textarea
    必須: true
    ※ ここに長文を入れる
  
  6. 成果・所感:
    type: textarea
    必須: false
    ※ ここにも長文を入れる
  
  7. 次回アクション:
    type: textarea
    必須: false
```

### 4. 台帳データ: 7件（検索テスト用）

**重要:** タグは台帳レコードには付与しません（台帳定義に付与済み）。状態管理はカラム値として保存します。

#### 件1: 顧客A - 新規提案
```yaml
日付: 2025-10-01
顧客名: 株式会社A商事
訪問目的: 新製品の提案
商談ステータス: 提案中  # カラム値として管理
優先度: 高                # カラム値として管理
商談内容: |
  本日、株式会社A商事の購買部長である鈴木様と田中様にお会いし、
  当社の新製品「LedgerLeap」についてご紹介させていただきました。
  
  鈴木様からは、現在使用している台帳管理システムが古く、
  検索機能が弱いことが課題とのお話がありました。
  特に、過去の記録を探すのに時間がかかり、
  業務効率が落ちているとのことです。
  
  LedgerLeapの全文検索機能、特にMroongaを使った
  高速検索をデモでお見せしたところ、非常に興味を持っていただけました。
  また、ワークフロー機能による承認プロセスの自動化についても、
  「これはまさに求めていた機能だ」とおっしゃっていただけました。

成果・所感: |
  非常に好感触でした。特に検索機能とワークフローに
  強い関心を示されていました。
  価格面での調整が必要になりそうですが、導入の可能性は高いと感じています。
  
次回アクション: |
  来週、詳細な見積もりと導入スケジュールの提案書を持参します。
  また、実際のデータを使ったPOC環境の準備も進めます。
```

#### 件2: 顧客A - フォローアップ
```yaml
日付: 2025-10-02
顧客名: 株式会社A商事
訪問目的: 提案フォローアップ
商談ステータス: フォローアップ
優先度: 高
商談内容: |
  昨日の提案を受けて、鈴木部長から追加のご質問をいただきました。
  
  主な質問内容:
  1. 既存システムからのデータ移行方法と期間
  2. セキュリティ対策（特にアクセス制御）
  3. バックアップとディザスタリカバリの仕組み
  4. カスタマイズの可能性と費用
  
  特にデータ移行については、現在3万件以上の台帳データがあり、
  これを確実に移行できるかが最大の関心事とのことでした。
  当社の実績として、B社での5万件のデータ移行事例をご紹介し、
  安心していただけたようです。

成果・所感: |
  技術的な質問が多く出たことは、導入に向けて
  真剣に検討されている証拠だと感じました。
  来週の提案書では、移行計画を重点的に説明する必要があります。
  
次回アクション: |
  データ移行計画書を作成し、来週の訪問時に提示します。
  また、POC環境でA商事様の実データサンプルを使った
  デモができるよう準備します。

```

#### 件3: 顧客B - 定期訪問
```yaml
日付: 2025-09-28
顧客名: 株式会社Bシステムズ
訪問目的: 定期訪問・状況確認
商談ステータス: 契約済み
優先度: 中
商談内容: |
  既存顧客である株式会社Bシステムズへの定期訪問を実施しました。
  担当の佐藤様から、現在使用中の当社システムについて
  概ね満足しているとのフィードバックをいただきました。
  
  一方で、以下の要望もいただきました:
  - スマートフォンアプリの操作性向上
  - CSVエクスポート機能の拡充
  - より詳細な利用統計レポート
  
  特にスマートフォンアプリについては、現場作業員の方々が
  タブレットで日報を入力する際に、若干使いづらさを感じている
  とのことでした。

成果・所感: |
  長期的な信頼関係が構築できていることを実感しました。
  要望事項については、開発チームと相談の上、
  次回バージョンアップで対応できる見込みです。
  
次回アクション: |
  開発チームに要望を伝え、対応可否と時期を確認します。
  来月の定期訪問時に回答します。

```

#### 件4: 顧客C - 価格交渉
```yaml
日付: 2025-09-30
顧客名: C製造株式会社
訪問目的: 価格交渉
商談ステータス: 契約直前
優先度: 高
商談内容: |
  C製造株式会社の導入検討が最終段階に入りました。
  本日は経理部長の伊藤様も同席され、価格についての
  詳細な協議を行いました。
  
  先方からの要望:
  - 初期費用の分割払い対応
  - ユーザー数に応じた段階的な料金設定
  - 3年契約での割引適用
  
  当社としては、3年契約を条件に15%の割引を提示しました。
  また、初期費用については6ヶ月の分割払いに対応できることを
  お伝えしました。
  
  伊藤部長からは「予算内に収まる見込みが立った」との
  コメントをいただき、次回の役員会で最終承認を得る
  方向で進めていただけることになりました。

成果・所感: |
  価格交渉は難航するかと思いましたが、
  柔軟な支払い条件を提示できたことで、
  スムーズに合意に至りました。
  役員会の承認が得られれば、今月中の契約締結も可能です。
  
次回アクション: |
  正式な見積書と契約書ドラフトを作成し、
  来週初めに提出します。

```

#### 件5: 顧客D - 課題発生
```yaml
日付: 2025-10-03
顧客名: 株式会社Dコーポレーション
訪問目的: トラブル対応
商談ステータス: 契約済み
優先度: 高
商談内容: |
  昨日、D社の担当者から緊急の連絡があり、
  システムの動作が遅くなっているとのことで訪問しました。
  
  原因を調査したところ、データ量の急激な増加により
  データベースのインデックスが最適化されていない状態でした。
  現場でインデックスの再構築を実施したところ、
  パフォーマンスが大幅に改善されました。
  
  また、今後同様の問題が発生しないよう、
  定期的なメンテナンスについて提案を行いました:
  - 月次でのインデックス最適化
  - データアーカイブの実施（2年以前のデータ）
  - パフォーマンス監視の導入
  
  担当の加藤様からは、迅速な対応に感謝していただけました。

成果・所感: |
  トラブルは発生しましたが、迅速に対応できたことで
  信頼関係を維持できました。
  予防的なメンテナンス提案も好意的に受け止めていただき、
  追加契約の可能性も出てきました。
  
次回アクション: |
  メンテナンスサービスの提案書を作成し、
  来週提出します。

```

#### 件6: 顧客E - 見送り
```yaml
日付: 2025-09-25
顧客名: 株式会社E物産
訪問目的: 最終提案
商談ステータス: 見送り
優先度: 中
商談内容: |
  3ヶ月にわたり提案を続けてきた株式会社E物産ですが、
  本日、導入を見送る旨の連絡をいただきました。
  
  見送りの理由:
  1. 予算の都合（今期の設備投資予算が削減された）
  2. 既存システムの延命対応を優先
  3. 社内の業務プロセス見直しが先決
  
  担当の木村様からは、来期以降に改めて検討したいとの
  お話をいただきました。また、当社の提案内容自体は
  高く評価していただけているとのことです。

成果・所感: |
  残念な結果ではありますが、完全に見送りというわけではなく、
  時期の問題であることが確認できました。
  来期の予算編成時期（12月頃）に再度アプローチする価値はあります。
  
次回アクション: |
  半年後（2026年3月）に状況確認の連絡を入れます。
  それまで定期的な情報提供（メールマガジン等）で
  関係を維持します。

```

#### 件7: 顧客F - 初回訪問
```yaml
日付: 2025-10-04
顧客名: 株式会社Fソリューションズ
訪問目的: 初回訪問・ヒアリング
商談ステータス: 初回訪問
優先度: 高
商談内容: |
  新規案件として、株式会社Fソリューションズへの
  初回訪問を実施しました。
  
  F社は従業員300名規模のIT企業で、
  現在は紙とExcelで各種報告書を管理しているとのことです。
  情報システム部長の林様から、以下の課題をお聞きしました:
  
  主要課題:
  1. 情報が分散しており、過去の記録を探すのに時間がかかる
  2. 承認プロセスが煩雑で、承認待ちの書類が滞留する
  3. テレワーク環境で紙の書類を扱うのが困難
  4. 法令対応（電子帳簿保存法など）への不安
  
  林様は、LedgerLeapのワークフロー機能と
  全文検索機能に強い関心を示されました。
  特に、OCR機能による紙資料のデジタル化と検索については
  「まさに求めていた機能」とおっしゃっていました。

成果・所感: |
  非常に前向きな反応をいただけました。
  課題が明確で、当社のソリューションが
  フィットする可能性が高いです。
  競合他社の提案も受けているとのことですが、
  機能面で優位性があると感じています。
  
次回アクション: |
  来週、デモ環境をご用意して、
  実際の操作感を確認していただきます。
  特にワークフロー機能とOCR検索を中心に
  デモを実施する予定です。

```

---

## 📊 実装結果サマリー（2025-10-05更新）

### データ構造
```
=== 台帳定義情報 ===
台帳定義: [DEMO] 営業日報
カラム数: 8

=== カラム一覧 ===
0. 日付 (YMD)
1. 顧客名 (text)
2. 訪問目的 (text)
3. 商談ステータス (select) ← 状態管理
4. 優先度 (select) ← 状態管理
5. 商談内容 (textarea)
6. 成果・所感 (textarea)
7. 次回アクション (textarea)

=== タグ一覧（台帳定義に付与） ===
タグ数: 3
  - 2025年度営業計画  # プロジェクト横断検索用
  - 新製品展開        # プロジェクト横断検索用
  - 顧客管理          # プロジェクト横断検索用

=== 台帳レコード サンプル ===
件数: 7件
例) 株式会社A商事
  - 商談ステータス: 提案中（カラム値として管理）
  - 優先度: 高（カラム値として管理）
  - 全文検索対象: すべてのカラム値
```

### 重要な設計変更（2025-10-05）

#### タグの正しい設計
- **目的**: フォルダと台帳定義を横断する検索
- **付与先**: 台帳定義（LedgerDefine）
- **内容**: プロジェクト名など横断的なキーワード
- **効果**: 異なるフォルダ・異なる台帳定義を同じタグで検索可能

#### 状態管理の正しい設計
- **目的**: 台帳レコードごとの詳細分類
- **実装**: カラムとして定義（select型）
- **検索**: 全文検索でカラム値も検索対象
- **効果**: 「価格交渉中」「優先度 高」などで検索可能

### 検索シナリオ例

#### シナリオ1: タグによる横断検索
```
質問: 「2025年度営業計画に関連する台帳を見せて」
結果: 営業日報の全7件（将来的には他の台帳定義も横断可能）
```

#### シナリオ2: 状態による検索
```
質問: 「価格交渉中の案件を教えて」
結果: C製造株式会社（商談ステータス: 価格交渉中）
     + 商談内容に「価格交渉」を含むレコード
```

#### シナリオ3: 複合検索
```
質問: 「優先度が高い提案中の案件を見せて」
結果: 株式会社A商事（提案中/フォローアップ、優先度: 高）
```

---

## 🔧 MCP認証設定

### MCP_AUTH_TOKEN の設定

LLMからMCPツールにアクセスするためには、認証トークンが必要です。

```bash
# 1. デモトークンを生成
./vendor/bin/sail artisan demo:generate-mcp-token

# 2. 表示されたトークンを.envに設定（自動的に設定される場合もあります）
# MCP_AUTH_TOKEN="1|choSRerWOWp3FBHK256c1QEDrcjPbLdiHmcxaDCdcf626617"

# 3. トークンを環境変数として設定（MCPサーバー起動時に使用）
export MCP_AUTH_TOKEN="1|choSRerWOWp3FBHK256c1QEDrcjPbLdiHmcxaDCdcf626617"
```

### 認証エラー時の対応

認証エラーが発生した場合、エラーメッセージに原因と解決方法が表示されます:

- **トークン未設定**: `MCP_AUTH_TOKEN environment variable is not set`
  - 解決: .envファイルにMCP_AUTH_TOKENを設定

- **トークン無効**: `The provided token is invalid or has been revoked`
  - 解決: `php artisan demo:generate-mcp-token`で新しいトークンを生成

- **権限不足**: `The token does not have MCP access permissions`
  - 解決: mcp:*権限を持つトークンを生成

---

## 🔧 実装手順

### Step 1-1: 既存Seederの確認（15分）
```bash
# 既存のSeederを確認
cat database/seeders/DatabaseSeeder.php

# テスト環境でSeederを実行してみる
php artisan migrate:fresh --seed

# どんなデータが入るか確認
php artisan tinker
>>> User::count()
>>> Folder::count()
>>> LedgerDefine::count()
>>> Ledger::count()
```

### Step 1-2: 最小限のDemoSeeder作成（1-2時間）
```php
// database/seeders/DemoMinimalSeeder.php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Ledger;
use App\Models\Tag;
use App\Models\ColumnDefine;
use Illuminate\Database\Seeder;

class DemoMinimalSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating demo users...');
        $demoUser = $this->createDemoUser();
        $adminUser = $this->createAdminUser();
        
        $this->command->info('Creating demo folders...');
        $folder = $this->createDemoFolder($demoUser);
        
        $this->command->info('Creating ledger define...');
        $define = $this->createSalesDailyDefine($folder, $demoUser);
        
        $this->command->info('Creating tags...');
        $tags = $this->createTags();
        
        $this->command->info('Creating demo ledgers...');
        $this->createDemoLedgers($define, $demoUser, $tags);
        
        $this->command->info('Demo data created successfully!');
        $this->command->info('Login: demo@example.com / demo1234');
    }
    
    private function createDemoUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => '田中太郎',
                'password' => bcrypt('demo1234'),
            ]
        );
    }
    
    private function createAdminUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '山田花子',
                'password' => bcrypt('demo1234'),
            ]
        );
    }
    
    private function createDemoFolder(User $user): Folder
    {
        $root = Folder::firstOrCreate(
            ['title' => '/', 'parent_id' => null],
            ['creator_id' => $user->id, 'modifier_id' => $user->id]
        );
        
        $demoFolder = Folder::firstOrCreate(
            ['title' => 'デモ用フォルダ', 'parent_id' => $root->id],
            ['creator_id' => $user->id, 'modifier_id' => $user->id]
        );
        
        $dailyFolder = Folder::firstOrCreate(
            ['title' => '日報', 'parent_id' => $demoFolder->id],
            ['creator_id' => $user->id, 'modifier_id' => $user->id]
        );
        
        return $dailyFolder;
    }
    
    private function createSalesDailyDefine(Folder $folder, User $user): LedgerDefine
    {
        $define = LedgerDefine::firstOrCreate(
            ['title' => '[DEMO] 営業日報'],
            [
                'folder_id' => $folder->id,
                'workflow_enabled' => false,
                'creator_id' => $user->id,
                'modifier_id' => $user->id,
            ]
        );
        
        // カラム定義
        $columns = [
            new ColumnDefine(0, '日付', 'date', null, 'date', 0, false, [], true, false),
            new ColumnDefine(1, '顧客名', 'text', null, 'text', 1, false, [], true, false),
            new ColumnDefine(2, '訪問目的', 'text', null, 'text', 2, false, [], false, false),
            new ColumnDefine(3, '商談内容', 'textarea', null, 'textarea', 3, false, [], true, false),
            new ColumnDefine(4, '成果・所感', 'textarea', null, 'textarea', 4, false, [], false, false),
            new ColumnDefine(5, '次回アクション', 'textarea', null, 'textarea', 5, false, [], false, false),
        ];
        
        $define->column_define = $columns;
        $define->save();
        
        return $define;
    }
    
    private function createTags(): array
    {
        $tagNames = [
            '新規', '重要', '大型案件', 'フォローアップ', 'データ移行',
            '既存顧客', '定期訪問', '要望', '価格交渉', '契約直前',
            'トラブル対応', 'メンテナンス', '見送り', '再提案予定',
            '初回訪問', '有望'
        ];
        
        $tags = [];
        foreach ($tagNames as $name) {
            $tags[$name] = Tag::firstOrCreate(['name' => $name]);
        }
        
        return $tags;
    }
    
    private function createDemoLedgers(LedgerDefine $define, User $user, array $tags): void
    {
        // 件1: 顧客A - 新規提案
        $ledger1 = Ledger::create([
            'ledger_define_id' => $define->id,
            'creator_id' => $user->id,
            'status' => 'none',
            'content' => [
                0 => '2025-10-01',
                1 => '株式会社A商事',
                2 => '新製品の提案',
                3 => "本日、株式会社A商事の購買部長である鈴木様と田中様にお会いし、当社の新製品「LedgerLeap」についてご紹介させていただきました。\n\n鈴木様からは、現在使用している台帳管理システムが古く、検索機能が弱いことが課題とのお話がありました。特に、過去の記録を探すのに時間がかかり、業務効率が落ちているとのことです。\n\nLedgerLeapの全文検索機能、特にMroongaを使った高速検索をデモでお見せしたところ、非常に興味を持っていただけました。また、ワークフロー機能による承認プロセスの自動化についても、「これはまさに求めていた機能だ」とおっしゃっていただけました。",
                4 => "非常に好感触でした。特に検索機能とワークフローに強い関心を示されていました。価格面での調整が必要になりそうですが、導入の可能性は高いと感じています。",
                5 => "来週、詳細な見積もりと導入スケジュールの提案書を持参します。また、実際のデータを使ったPOC環境の準備も進めます。",
            ],
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        $ledger1->tags()->attach([$tags['新規']->id, $tags['重要']->id, $tags['大型案件']->id]);
        
        // 件2以降も同様に作成...
        // （実装時に全7件を作成）
    }
}
```

### Step 1-3: データ投入と確認（30分）
```bash
# デモデータを投入
php artisan db:seed --class=DemoMinimalSeeder

# データ確認
php artisan tinker
>>> User::where('email', 'demo@example.com')->first()
>>> LedgerDefine::where('title', 'like', '[DEMO]%')->first()
>>> Ledger::count()
>>> Ledger::first()->content

# 検索テスト（Mroonga）
>>> Ledger::where('content', 'like', '%検索機能%')->count()
```

### Step 1-4: SearchLedgersToolのテスト（30分）
```bash
# MCPサーバーを起動
php artisan mcp:serve

# 別ターミナルでテスト
# 1. 基本検索
curl -X POST http://localhost:8080/mcp \
  -H "Authorization: Bearer {token}" \
  -d '{"tool": "SearchLedgersTool", "params": {"q": "検索機能"}}'

# 2. タグ検索
curl -X POST http://localhost:8080/mcp \
  -H "Authorization: Bearer {token}" \
  -d '{"tool": "SearchLedgersTool", "params": {"tags": "重要"}}'

# 3. format=summary
curl -X POST http://localhost:8080/mcp \
  -H "Authorization: Bearer {token}" \
  -d '{"tool": "SearchLedgersTool", "params": {"q": "顧客A", "format": "summary"}}'
```

### Step 1-5: LLMとの対話テスト（30分）
```
Claude/ChatGPTとMCP接続して:

1. 「株式会社A商事に関する営業記録を見せて」
   → 件1と件2が返ってくるか

2. 「重要案件の一覧を表示」
   → タグ「重要」の台帳が返ってくるか

3. 「トラブルが発生した顧客を教えて」
   → 件5（顧客D）が返ってくるか

4. 「検索機能について説明している記録はある？」
   → 件1（顧客A）が返ってくるか

5. 「今週作成された日報をまとめて」
   → 件1, 件5, 件7が返ってくるか
```

---

## ✅ 完了チェックリスト

### 実装
- [x] DemoMinimalSeeder作成
- [x] ユーザー2名作成
- [x] フォルダ3個作成
- [x] 台帳定義1種作成（8カラム: 日付/顧客名/訪問目的/商談ステータス/優先度/商談内容/成果・所感/次回アクション）
- [x] 台帳7件作成（長文コンテンツ、状態管理カラム付き）
- [x] タグ3個作成（台帳定義に付与、横断検索用）

### 動作確認
- [x] Seeder実行成功
- [x] データが正しく投入されている
- [x] 基本的な検索が動作する（ledger_define_idフィルター）
- [x] content表示の修正完了
- [x] キーワード検索のタイムアウト問題を解決（2025-10-04）
- [ ] SearchLedgersToolが動作する（MCP経由）
- [ ] LLMとの対話が成立する

### 発見した課題と解決

- ✅ テナントID未設定問題を解決（2025-10-05）
  - 問題: DemoMinimalSeederでtenant_idがNULLになっていた
  - 原因: テナント作成・初期化を行っていなかった
  - 解決: Step 0でテナント作成と初期化を追加
    - テナントID 'demo-tenant' を作成
    - tenancy()->initialize() でテナントコンテキスト設定
  - 効果: 全モデルにtenant_id='demo-tenant'が設定される
  - コミット: dcb0d86 "fix(seeder): add tenant creation and initialization"
- ✅ API応答の`content`が空 → `LedgerResource.php`を修正して解決
  - `relationLoaded('column_define')`チェックを削除
  - `column_define`は属性なのでdefinがロードされていれば利用可能
- ✅ タグの設計が誤り → 正しい設計に修正（2025-10-05）
  - 誤り: 台帳レコードに状態を表すタグを付与
  - 正解: 台帳定義にプロジェクト横断タグ、状態はカラムで管理
  - 効果: 横断検索が可能に、状態管理が明確化
- ✅ キーワード検索（`?q=A商事`）のタイムアウト問題を解決（2025-10-04）
  - 原因: JSON形成時に存在しないenum()メソッドを呼び出していた
  - 具体的には `$ledger->status->value` の処理で、statusがEnum型でない場合にエラー
  - 修正: `is_object($ledger->status) ? $ledger->status->value : $ledger->status` に変更
  - コミット: 9753b2c "feat: enhance SearchLedgersTool with content preview and improved field mappings"
  - 効果: APIレスポンスが正常に返るようになり、タイムアウトが解消
- ✅ テナント作成・マイグレーション成功
- ✅ API認証成功
- ✅ `ledger_define_id`フィルターは正常動作（7件取得確認）
- ✅ Seeder実装完了・検証済み

### 次のStep準備
- [ ] content表示の修正
- [ ] MCPツール経由でのアクセステスト
- [ ] 問題点・改善点のリストアップ
- [ ] Step 2の計画（次に追加する機能）

---

## 🚀 次のStep（Step 1完了後に検討）

### Step 2候補: CreateLedgersTool
- 台帳作成機能のテスト
- LLMが台帳を作成できるか確認

### Step 3候補: ワークフロー
- workflow_enabled=true の台帳定義追加
- 承認フローのテスト

### Step 4候補: 統計ツール
- 期間別統計の確認

---

**方針:** まずStep 1を完璧に動かす。問題があれば調整。動いたら次へ。

**作成者**: AI Assistant  
**ステータス**: 実装準備完了
