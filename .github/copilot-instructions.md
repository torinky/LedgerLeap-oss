# GitHub Copilot CLI - LedgerLeap開発設定
**作成日:** 2025年9月28日  
**最終更新:** 2026年1月3日（Phase 1-5添付ファイル機能統合完了）  
**対象:** GitHub Copilot CLI (バージョン 0.0.328)  
**プロジェクト:** LedgerLeap - Webベース台帳管理システム

## プロジェクト概要

### 基本情報
- **システム:** Webベース台帳管理システム（全文検索・権限管理・ワークフロー機能）
- **現在ブランチ:** `feature/LLM-integration` (LLM統合機能開発中)
- **技術スタック:** Laravel 12.0 + PHP 8.4 + MySQL/Mroonga + Livewire + Alpine.js
- **開発環境:** Laravel Sail (Docker)
- **添付ファイル機能:** Phase 1-5実装完了（2025年12月-2026年1月）

### 重要な制約
- **Mroonga:** 全文検索に必須。複合インデックスは機能しない
- **テスト:** 全文検索機能は`DatabaseMigrations`トレイト必須
- **Livewire:** パブリックプロパティはシンプルな連想配列のみ
- **テナント:** 全てのFeatureテストで`tenancy()->initialize()`が必須（Phase 6で確認）
- **AsColumnArrayJson:** `data_get()`は使用不可。直接配列アクセス必須（Phase 6で確認）
- **VLM/OCR処理:** ファイルタイプにより処理フローが異なる（Phase 1-5で実装）

## 開発コマンド

### 基本操作
```bash
# 開発環境
./vendor/bin/sail up -d
./vendor/bin/sail stop

# テスト
./vendor/bin/sail test
./vendor/bin/sail pest

# コード整形（コミット前必須）
./vendor/bin/sail pint

# Artisan
./vendor/bin/sail artisan [command]

# 初回セットアップ（開発環境）
./bin/setup.sh        # または ./dev.sh

# 本番環境セットアップ
./bin/setup.sh -p     # または ./prod.sh

# GPU環境の場合
# .env で PADDLEOCR_DEVICE=gpu に設定してから
./bin/setup.sh
```

### URL
- アプリ: http://localhost
- メール: http://localhost:8025

## アーキテクチャ

### 主要コンポーネント
```
Webサーバー (Nginx) → Laravel (PHP-FPM) → MySQL/Mroonga
                    ↓
                 Redis (キュー/キャッシュ)
                    ↓
            キューワーカー → VLM/OCR/Tika
                         (PaddleOCR-VL/OcrMyPDF/Apache Tika)
```

### ディレクトリ構造
```
/app/
├── Models/           # Eloquent
├── Services/         # ビジネスロジック
├── Livewire/        # インタラクティブUI
├── Filament/        # 管理画面
└── Mcp/             # LLM統合API

/docs/               # 公式ドキュメント
/docs/work/          # 計画、作業ドキュメント
/resources/views/    # Bladeテンプレート
/tests/              # テストコード
```

## データベース設計

### 核心テーブル

**台帳関連:**
- `ledgers`: 台帳データ本体（JSON形式content、スコアリング対応）
- `ledger_defines`: 台帳テンプレート（JSON形式column_define、ワークフロー設定）
- `ledger_diffs`: 台帳変更履歴・ワークフロースナップショット

**フォルダ・権限:**
- `folders`: 階層フォルダ（Nested Set、権限制御基盤）
- `role_folder_permissions`: ロール×フォルダの詳細権限管理
- `roles`, `permissions`: Spatie権限管理システム

**ユーザー・組織:**
- `users`: ユーザー情報・認証
- `organizations`: 組織階層（部署・チーム）
- `user_organizations`: ユーザー×組織の所属関係

**ファイル・通知:**
- `attached_files`: 添付ファイルメタデータ・VLM/OCR/Tika処理状態（Phase 1-5で拡張）
- `notifications`: Laravel通知システム
- `notification_types`: 通知種類定義

**その他:**
- `tags`, `taggables`: タグ管理（ポリモーフィック）
- `activity_log`: Spatie活動ログ（監査証跡）
- `auto_links`: 自動リンク設定

### Mroonga全文検索
```sql
-- ○ 動作する（単一インデックス）
SELECT * FROM ledgers WHERE MATCH(content) AGAINST('キーワード');

-- × 動作しない（複合インデックス）  
SELECT * FROM ledgers WHERE MATCH(content, content_attached) AGAINST('キーワード');

-- ○ 正解（OR結合）
SELECT * FROM ledgers WHERE 
  MATCH(content) AGAINST('キーワード') OR 
  MATCH(content_attached) AGAINST('キーワード');
```

## コーディング規約

### 命名規則
```php
// 変数: スネークケース
$ledger_item, $user_list

// メソッド: キャメルケース  
getUserProfile(), calculateTotalAmount()

// クラス: パスカルケース
LedgerController, UserService

// ルート: ケバブケース（LedgerLeap推奨）
Route::get('ledger-items/{id}', ...)->name('ledger-items.show');

// Blade: ケバブケース
user-profile.blade.php
```

### Git規約
```
ブランチ: feature/<issue-id>-<feature-name>
コミット: feat(scope): 日本語での説明

例: feat(auth): ユーザー登録APIエンドポイント実装
```

## Livewire開発パターン

### 状態管理（重要）
```php
// ○ 良い例（単一配列）
public array $columns = [
    ['type' => 'text', 'name' => 'title'],
    ['type' => 'number', 'name' => 'amount']
];

// × 悪い例（複数プロパティ分離）
public array $columnTypes = ['text', 'number'];
public array $columnNames = ['title', 'amount'];
```

### フックメソッド活用
```php
public function updatedColumns($value, $key)
{
    // $key = "0.type" のような形式で変更箇所特定
    if (str_contains($key, '.type')) {
        $index = explode('.', $key)[0];
        $this->columns[$index]['useOptions'] = 
            $this->columns[$index]['type'] === 'select';
    }
}
```

### Alpine.js連携
```php
// Livewireイベント発行
$this->dispatch('open-modal', ['data' => $data]);

// Alpine.jsで受信
@open-modal.window="modalOpen = true; modalData = $event.detail.data"
```

## テスト方針

### Feature Test
```php
use Illuminate\Foundation\Testing\DatabaseMigrations; // 全文検索用

class LedgerSearchTest extends TestCase
{
    use DatabaseMigrations; // RefreshDatabase不可
    
    public function test_mroonga_search()
    {
        // テストデータ作成
        $ledger = Ledger::factory()->create([
            'content' => ['title' => 'テスト台帳']
        ]);
        
        // わずかな待機（インデックス更新）
        sleep(1);
        
        // 検索実行
        $results = Ledger::scopeSearch('テスト')->get();
        $this->assertCount(1, $results);
    }
}
```

### Livewire Test
```php
public function test_toast_notification()
{
    Livewire::test(MyComponent::class)
        ->call('saveData')
        ->assertDispatched('mary-toast', [
            'type' => 'success',
            'title' => '保存完了'
        ]);
}
```

## LLM統合機能（進行中）

### 実装済みAPI
```
✅ POST /api/v1/ledgers        # 台帳作成
✅ GET /api/v1/search          # 高度検索（RAG対応）
✅ GET /api/v1/ledger-defines  # 台帳定義一覧
🔄 OpenAPIドキュメント生成     # 計画中
```

### 認証
```php
// Sanctum APIトークン
Authorization: Bearer {token}

// 管理画面でトークン発行・管理可能
// Filament UserResource → TokensRelationManager
```

### 検索API活用例
```bash
curl -H "Authorization: Bearer {token}" \
     "http://localhost/api/v1/search?q=日報&limit=5"
```

## サービス設計パターン

### ビジネスロジック分離
```php
// Controller: 薄く保つ
class LedgerController
{
    public function store(StoreLedgerRequest $request)
    {
        $ledger = $this->ledgerService->createLedger(
            $request->validated()
        );
        return new LedgerResource($ledger);
    }
}

// Service: ロジック集約
class LedgerService  
{
    public function createLedger(array $data): Ledger
    {
        // 複雑な処理をカプセル化
        DB::transaction(function () use ($data) {
            // 台帳作成 + タグ関連付け + 権限チェック等
        });
    }
}
```

### 権限管理
```php
// 包含関係チェック
FolderPermissionType::ADMIN->includes(FolderPermissionType::WRITE); // true

// ポリシー認可
$this->authorize('create', [Ledger::class, $folder]);

// サービス内権限確認
if (!$this->permissionService->canUserAccess($user, $folder, 'WRITE')) {
    throw new UnauthorizedException();
}
```

## VLM/OCR/Tika処理フロー（Phase 1-5実装完了）

### ファイルタイプ別処理一覧

| ファイルタイプ | MIME | VLM | OCR | ファイル名変更 | 最終source | 備考 |
|--------------|------|-----|-----|---------------|-----------|------|
| **画像（JPG/PNG）** | image/* | ✅ | ✅ | ✅ image.jpg→image.pdf | vlm > ocr > tika | OCRでPDF化 |
| **テキスト付きPDF** | application/pdf | ✅ | ✅ (skip-text) | ❌ doc.pdf→doc.pdf | vlm > tika | OCRは最適化のみ |
| **画像のみPDF** | application/pdf | ✅ | ✅ | ❌ scan.pdf→scan.pdf | vlm > ocr > tika | OCRでテキスト抽出 |
| **Office文書** | application/vnd.* | ❌ | ❌ | ❌ | tika | Tikaのみ |
| **テキスト** | text/* | ❌ | ❌ | ❌ | tika | 即座に完了 |

### 処理の優先順位

**エンジン選択:** VLM（最優先） > OCR（次点） > Tika（フォールバック）

**並列処理:**
- VLMとOCRを並列実行（処理時間を30-40%短縮）
- VLMは即座実行、OCRは2秒ディレイ
- Tikaは初期処理として単独実行

**ユーザー待機時間:** Tika完了後（約5秒）に画面復帰可能

### キー命名規則

```php
// 元のキー
content_attached[$columnId][$hashedbasename]

// OCR後のキー（画像ファイルのみ）
content_attached[$columnId][$hashedbasename_without_ext . '.pdf']

// 例:
// image.jpg → content_attached[1]['image.pdf']（新キー作成）
// document.pdf → content_attached[2]['document.pdf']（元キー上書き）
```

### 重要なロジック

```php
// OCR結果の判定（FinalizeAttachedFileProcessing.php）
if ($file->ocr_processed_at) {
    $isImageFile = str_starts_with($file->original_mime_type ?? '', 'image/');
    
    if ($isImageFile) {
        // 画像ファイル: .pdf キーをチェック
        $pdfKey = pathinfo($file->hashedbasename, PATHINFO_FILENAME) . '.pdf';
        $text = $ledger->content_attached[$columnId][$pdfKey]['meta']['content'] ?? null;
    } else {
        // PDFファイル: 元のキーをチェック
        $text = $ledger->content_attached[$columnId][$file->hashedbasename]['meta']['content'] ?? null;
    }
}
```

---

## 重要な実装教訓

### Livewire
1. **Single Source of Truth**: 状態を単一配列に集約
2. **wire:key**: DOM追跡を確実に
3. **イベント制御**: Alpine.jsとの競合回避
4. **Toast通知**: `dispatch('mary-toast')` でテスト対応

### 全文検索
1. **Mroonga制約**: 複合インデックス使用不可
2. **テスト制約**: `RefreshDatabase`不可、`DatabaseMigrations`必須
3. **検索実装**: `Ledger::scopeSearch()`メソッド活用

### ワークフロー
1. **状態管理**: `ledgers.status`と`ledger_diffs.status`の使い分け
2. **スナップショット**: 変更時に`ledger_diffs`で履歴保存
3. **通知**: 個別通知（即時）と集約通知（定期実行）の併用
4. **権限**: 点検者・承認者の推薦ロジック

### 権限管理
1. **継承**: フォルダ階層での権限継承
2. **Enum活用**: `FolderPermissionType`で型安全性確保
3. **ポリシー**: Laravelポリシーで一元管理
4. **可視化**: アクセス権限の詳細表示機能

### スコアリングシステム
1. **複合スコア**: 活動・新鮮度・重要度を統合
2. **非同期計算**: バッチ処理で負荷分散
3. **インデックス**: `idx_ledgers_composite_score`で高速ソート

### テスト環境
1. **OCR処理**: 外部プロセス連携を考慮
2. **モック**: Pest=`mock()`, PHPUnit=`$this->mock()`
3. **ファクトリ**: `ColumnDefine`は直接コンストラクタ使用
4. **テナント初期化**: 全Featureテストで`tenancy()->initialize()`必須

### AsColumnArrayJsonキャストの制約
1. **data_get()不可**: `AsColumnArrayJson`のシリアライゼーションにより`data_get()`が動作しない
2. **直接配列アクセス必須**: `$ledger->content[$id]`, `$ledger->content_attached[$id][$file]` の形式で
3. **0始まり連番必須**: テストデータは`[0 => '', 1 => 'value']`のように0から始める
4. **Null-safe演算子**: `??`を使って安全にアクセス

```php
// ❌ 動作しない
$text = data_get($ledger->content_attached, '1.file.meta.content');

// ✅ 正しい
$text = $ledger->content_attached[1]['file']['meta']['content'] ?? null;
```

## 開発時必須チェック

### コミット前
- [ ] `./vendor/bin/sail pint` 実行
- [ ] `./vendor/bin/sail test` 通過確認
- [ ] 関連ドキュメント更新
- [ ] 権限・セキュリティ影響確認

### 実装時
- [ ] 既存テストのリグレッション確認
- [ ] Livewire状態管理パターン適用
- [ ] サービスクラスへのロジック分離
- [ ] 適切な認証・認可実装

## 公式ドキュメント構成

### `/docs/` - 公式ドキュメント（実装済み機能の仕様）

**architecture/** - アーキテクチャと技術選定
- `overview.md` - システムアーキテクチャ概要
- `vlm-ocr-technology-selection.md` - VLM/OCR/Tika技術選定理由と実測ベンチマーク
- `QueueProcessing.md` - 非同期処理フローとジョブ設計
- `file-processing-flow.md` - 添付ファイル処理フロー詳細

**function/** - 機能仕様（ユーザー視点）
- `Attachment.md` - 添付ファイル機能の概要とUI操作
- `Workflow.md` - ワークフロー機能（承認フロー）
- `Search.md` - 全文検索機能
- `Authority.md` - 権限管理機能
- `Notification.md` - 通知機能

**features/** - 横断的機能
- `scoring-system.md` - スコアリングシステム

**models/** - データモデル仕様
- `Ledger.md` - 台帳モデル
- `LedgerDefine.md` - 台帳定義モデル
- `Folder.md` - フォルダモデル
- `AttachedFile.md` - 添付ファイルモデル

**services/** - サービスクラス仕様
- `LedgerService.md` - 台帳サービス
- `WorkflowService.md` - ワークフローサービス
- `Ledger/ColumnHtmlService.md` - カラム値のHTML表示サービス

**database/** - データベース設計
- `schema.md` - 主要テーブルのスキーマとMroonga制約

**development/** - 開発ガイド
- `coding_standards.md` - コーディング規約
- `Testing-Best-Practices.md` - テストのベストプラクティス
- `vlm-ocr.md` - VLM/OCR開発者ガイド

**operations/** - 運用ガイド
- `fileinspector-performance-monitoring.md` - パフォーマンス監視設定
- `database-performance-monitoring.md` - データベース監視

**api/** - API仕様
- `README.md` - API概要とエンドポイント一覧

### `/docs/work/` - 作業ファイル（計画・設計・実装記録）

**llm-integration/** - LLM連携機能の計画・実装記録
- 開発ロードマップ、API仕様書、MCP実装計画

**ui-ux/attachment/** - 添付ファイル機能の実装計画と記録（Phase 1-5）
- 詳細な実装計画、設計書、完了レポート
- 意思決定プロセスと検討過程の記録

**core-features/** - コア機能の設計書
- 台帳レコード複製機能、ワークフロー設計等

### ドキュメント管理方針

1. **公式ドキュメントの記載範囲を明確化**
   - 各ドキュメントの冒頭に「記載範囲」と「記載しない内容」を明記
   - 関連ドキュメントへのリンクを整備

2. **情報の重複を排除**
   - 同じ内容は1箇所にのみ記載
   - 詳細は専門ドキュメントに委ね、概要のみを記載

3. **Phase番号の明記**
   - 実装時期を明確化（例: Phase 1-5（添付ファイル機能統合、2025年12月-2026年1月））
   - 他機能のPhaseと混同しないよう注意

4. **コード実装を優先**
   - ドキュメントとコードが乖離している場合、コードの内容を優先
   - 実装コードを確認してからドキュメントを更新

5. **docs/workとの役割分担**
   - 公式ドキュメントから削除する情報は必ずdocs/workに保持
   - docs全体で情報量を落とさない

---

**このファイルはGitHub Copilot CLIでの開発支援のための設定情報です。**  
**LedgerLeapプロジェクトの特性と制約を理解した効率的な開発を支援します。**