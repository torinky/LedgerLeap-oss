# GitHub Copilot CLI - LedgerLeap開発設定
**作成日:** 2025年9月28日  
**対象:** GitHub Copilot CLI (バージョン 0.0.328)  
**プロジェクト:** LedgerLeap - Webベース台帳管理システム

## プロジェクト概要

### 基本情報
- **システム:** Webベース台帳管理システム（全文検索・権限管理・ワークフロー機能）
- **現在ブランチ:** `feature/LLM-integration` (LLM統合機能開発中)
- **技術スタック:** Laravel 12.0 + PHP 8.4 + MySQL/Mroonga + Livewire + Alpine.js
- **開発環境:** Laravel Sail (Docker)

### 重要な制約
- **Mroonga:** 全文検索に必須。複合インデックスは機能しない
- **テスト:** 全文検索機能は`DatabaseMigrations`トレイト必須
- **Livewire:** パブリックプロパティはシンプルな連想配列のみ

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
            キューワーカー → Apache Tika/OCR
```

### ディレクトリ構造
```
/app/
├── Models/           # Eloquent
├── Services/         # ビジネスロジック
├── Livewire/        # インタラクティブUI
├── Filament/        # 管理画面
└── Mcp/             # LLM統合API

/docs/               # 全ドキュメント
/docs/work           # 計画、作業ドキュメント
/resources/views/    # Bladeテンプレート
/tests/              # テストコード
```

## データベース設計

### 核心テーブル
- `ledgers`: 台帳データ本体（JSON形式content）
- `ledger_defines`: 台帳テンプレート（JSON形式column_define）  
- `folders`: 階層フォルダ（権限制御基盤）
- `users`, `organizations`: マルチテナント対応
- `role_folder_permissions`: 詳細権限管理

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

### テスト環境
1. **OCR処理**: 外部プロセス連携を考慮
2. **モック**: Pest=`mock()`, PHPUnit=`$this->mock()`
3. **ファクトリ**: `ColumnDefine`は直接コンストラクタ使用

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

---

**このファイルはGitHub Copilot CLIでの開発支援のための設定情報です。**  
**LedgerLeapプロジェクトの特性と制約を理解した効率的な開発を支援します。**