# 台帳レコード複製機能の設計

**作成日:** 2025年12月11日  
**更新日:** 2025年12月11日  
**目的:** 既存の台帳レコードの内容を元に新規レコードを作成する機能の設計と実装計画  
**関連:** 台帳作成機能、Prefill機能

---

## 🎯 背景と要件

### 背景
現在、台帳レコードを作成する際は、全てのフィールドを手入力する必要がある。しかし、実務では類似したレコードを繰り返し作成するケースが多く、既存レコードの内容をベースに新規作成できる機能が求められている。

特に以下のユースケースが想定される：
- 日報や週報など、定期的に類似した内容を記録する台帳
- 前回の点検結果を参考にして新しい点検記録を作成
- 修正版を別レコードとして作成（元のレコードは保持）
- テンプレート的なレコードから実際の記録を作成

### 要件

#### 1. 機能要件
- **複製元の指定**: 詳細画面で表示中のレコードを複製元とする
- **初期値の設定**: 複製元の`content`データを新規作成画面の初期値として使用
- **権限チェック**: 複製元の閲覧権限と、対象台帳定義への作成権限が必要
- **除外項目の制御**: 
  - 自動採番フィールド（`auto_number`）は新規採番
  - 添付ファイル（`files`）は複製しない（セキュリティ、ストレージ容量の観点）
  - ワークフロー情報（`status`, `version`等）は新規レコードとしてリセット
- **UI配置**: 詳細画面のアクションボタンエリアに配置

#### 2. 非機能要件
- **既存機能の活用**: 既存のPrefill機能を最大限活用し、重複実装を避ける
- **セキュリティ**: XSS対策、データサニタイズは既存のバリデーション機能を利用
- **拡張性**: 将来的に部分的な複製（特定フィールドのみ）にも対応可能な設計
- **パフォーマンス**: 大量のデータでも処理が遅延しないこと

---

## 🏗 アーキテクチャ設計

### 基本方針

**方針: 専用ルートとコントローラーメソッドによるDB再構成方式**

Prefill URLパラメータ方式ではなく、以下の理由から専用ルートを作成する方針とする：

**採用理由:**
1. **URLの長さ制限回避**: ブラウザのURL長さ制限（約2000文字）を超える可能性がある
2. **データ整合性**: DBから最新のデータを取得し、整形・検証を経てから渡せる
3. **セキュリティ**: URLパラメータに機密情報を含めない
4. **監査ログ**: 複製元のレコードIDを明確に記録できる
5. **拡張性**: 将来的に複製オプション（部分複製等）を追加しやすい

**Prefill機能との関係:**
- 内部的にはPrefill機能を活用（`CreateController::create()`の`prefillParams`引数）
- ユーザーからはURLパラメータを隠蔽し、専用ルート経由で処理

---

## 💾 データフロー設計

### 1. リクエストフロー

```
[詳細画面] 
   ↓ ユーザーが「この内容で新規作成」ボタンをクリック
[ルート: /ledger/duplicate/{ledgerId}]
   ↓ リクエスト
[DuplicateController::duplicate()] ← 新規作成
   ↓ 
   1. ledgerIdからLedgerレコードをロード（with define）
   2. 閲覧権限チェック（can('view', $ledger)）
   3. 作成権限チェック（can('create', [Ledger::class, $ledgerDefine])）
   4. contentデータを抽出・フィルタリング
      - auto_number, files タイプを除外
      - XSS対策（既存のvalidatePrefillParams利用）
   5. prefillParamsを構成
   ↓
[CreateController::create()] ← 既存メソッドを呼び出し
   ↓ prefillParamsを渡す
[ledger.create ビュー]
   ↓
[CreateColumn コンポーネント]
   ↓ mount()でprefillParamsを適用
[フォーム表示（初期値あり）]
```

### 2. データ変換ロジック

```php
// 複製元レコードから初期値を構成
foreach ($ledgerRecord->content as $columnId => $value) {
    $column = collect($ledgerDefine->column_define)
        ->firstWhere('id', $columnId);
    
    // 除外条件
    if (!$column || in_array($column->type, ['auto_number', 'files'])) {
        continue;
    }
    
    // 値の検証とサニタイズ
    $prefillParams[$columnId] = $this->sanitizeValue($value, $column);
}
```

---

## 🛣️ ルート設計

### 新規ルート定義

**ファイル:** `routes/tenant.php`

```php
// 台帳レコード複製ルート（詳細画面の後に追加）
Route::get('/ledger/duplicate/{ledgerId}', 
    [\App\Http\Controllers\Ledger\DuplicateController::class, 'duplicate'])
    ->name('ledger.duplicate')
    ->where('ledgerId', '[0-9]+');
```

**配置位置:**
```php
// 既存のルート順序を維持
Route::get('/ledger/{ledgerId}', LedgerShowController::class)->name('ledger.show');
Route::get('/ledger', LedgerIndexController::class)->name('ledger.index');

// ↓ ここに追加（詳細表示と台帳定義別リストの間）
Route::get('/ledger/duplicate/{ledgerId}', ...)->name('ledger.duplicate');

Route::get('/ledger/define/{defineId}', ...)->name('ledgerByDefineId');
// ...
```

---

## 💻 実装設計

### 1. 新規コントローラー作成

**ファイル:** `app/Http/Controllers/Ledger/DuplicateController.php`

```php
<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class DuplicateController extends Controller
{
    /**
     * 既存の台帳レコードを元に新規作成画面を表示
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function duplicate(Request $request)
    {
        // 複製元のレコードを取得
        $sourceLedgerId = (int) $request->route('ledgerId');
        $sourceLedger = Ledger::with(['define'])->findOrFail($sourceLedgerId);
        
        // 複製元の閲覧権限チェック
        $this->authorize('view', $sourceLedger);
        
        $ledgerDefine = $sourceLedger->define;
        
        // 新規作成権限チェック
        if (auth()->user()->cannot('create', [Ledger::class, $ledgerDefine])) {
            abort(403, __('ledger.not_allow_create'));
        }
        
        // 複製元のcontentからprefillパラメータを構成
        $prefillParams = $this->buildPrefillParamsFromLedger($sourceLedger, $ledgerDefine);
        
        // 既存のCreateController::create()と同じビューを返す
        return View::make('ledger.create', [
            'ledgerDefineRecord' => $ledgerDefine,
            'prefillParams' => $prefillParams,
            'sourceLedgerId' => $sourceLedgerId, // 監査ログ用（オプション）
        ]);
    }
    
    /**
     * 複製元レコードからprefillパラメータを構成
     *
     * @param Ledger $sourceLedger
     * @param LedgerDefine $ledgerDefine
     * @return array
     */
    private function buildPrefillParamsFromLedger(Ledger $sourceLedger, LedgerDefine $ledgerDefine): array
    {
        $prefillParams = [];
        $columnDefines = collect($ledgerDefine->column_define);
        
        // 除外するカラムタイプ
        $excludedTypes = ['auto_number', 'files'];
        
        foreach ($sourceLedger->content as $columnId => $value) {
            $column = $columnDefines->firstWhere('id', (int) $columnId);
            
            // カラム定義が存在しない、または除外タイプの場合はスキップ
            if (!$column || in_array($column->type, $excludedTypes)) {
                continue;
            }
            
            // 値のサニタイズ（文字列の場合）
            if (is_string($value)) {
                $value = strip_tags($value);
                $value = mb_substr($value, 0, 5000); // 最大5000文字
            }
            
            // 配列の場合（chk など）
            if (is_array($value)) {
                $value = array_map(function ($item) {
                    return is_string($item) ? strip_tags(mb_substr($item, 0, 255)) : $item;
                }, $value);
                
                // select/chk の場合、現在の選択肢に存在するもののみ
                if (in_array($column->type, ['select', 'chk']) && !empty($column->options)) {
                    $value = array_filter($value, fn($v) => in_array($v, $column->options, true));
                }
            }
            
            // select の単一値の場合も選択肢チェック
            if ($column->type === 'select' && !empty($column->options)) {
                if (!in_array($value, $column->options, true)) {
                    continue; // 現在の選択肢に存在しない場合はスキップ
                }
            }
            
            $prefillParams[$columnId] = $value;
        }
        
        return $prefillParams;
    }
}
```

**特徴:**
- `CreateController`の`validatePrefillParams()`と同様のロジックを実装
- 複製元レコードの閲覧権限チェック
- 新規作成権限チェック
- カラムタイプに応じた適切なフィルタリング

---

### 2. UI実装（ボタン追加）

**ファイル:** `resources/views/livewire/ledger/workflow-action-buttons.blade.php`

**配置位置:** 編集ボタンと変更履歴ボタンの間

```blade
{{-- 編集ボタン --}}
@php $canUpdate = auth()->user()->can('ledgerUpdate', $ledgerRecord->define); @endphp
@if($canUpdate && !$ledgerRecord->isLocked())
    <a href="{{ route('ledger.edit', ['tenant' => tenant('id'), 'ledgerId'=>$ledgerRecord->id]) }}"
       class="join-item btn btn-primary btn-wide"
    ><i class="fa-solid fa-pencil mr-2"></i>{{__('ledger.edit')}}</a>
@else
    <!-- ...既存コード... -->
@endif

{{-- 複製ボタン（新規追加） --}}
@php $canCreate = auth()->user()->can('create', [App\Models\Ledger::class, $ledgerRecord->define]); @endphp
@if($canCreate)
    <a href="{{ route('ledger.duplicate', ['tenant' => tenant('id'), 'ledgerId'=>$ledgerRecord->id]) }}"
       class="join-item btn btn-outline btn-sm md:btn-md"
       target="_blank"
    >
        <i class="fa-solid fa-copy mr-2"></i>{{__('ledger.duplicate_from_this')}}
    </a>
@else
    <div class="tooltip" data-tip="{{ __('ledger.no_create_permission') }}">
        <button class="join-item btn btn-outline btn-sm md:btn-md" disabled>
            <i class="fa-solid fa-copy mr-2"></i>{{__('ledger.duplicate_from_this')}}
        </button>
    </div>
@endif

{{-- ワークフローアクションボタン --}}
<!-- ...既存コード... -->

{{-- 変更履歴ボタン --}}
@if($ledgerRecord->ledgerDiff()->where(DB::raw('content'), '!=', '')->count() > 0)
    <!-- ...既存コード... -->
@endif
```

**UI設計のポイント:**
- `target="_blank"`: 新しいタブで開く（元の詳細画面を維持）
- `btn-outline`: 編集ボタンと視覚的に区別
- `btn-sm md:btn-md`: レスポンシブ対応
- 権限がない場合はツールチップ付きでdisabled表示

---

### 3. 翻訳キー追加

**ファイル:** `lang/ja.json`

```json
{
    // ...既存のキー...
    "ledger.duplicate_from_this": "この内容で新規作成",
    "ledger.no_create_permission": "新規作成の権限がありません",
    "ledger.duplicated_from": "複製元",
    // ...
}
```

---

## 🔒 セキュリティ設計

### 1. 権限チェック

**2段階の権限チェックを実施:**

```php
// 1. 複製元レコードの閲覧権限
$this->authorize('view', $sourceLedger);

// 2. 対象台帳定義への作成権限
if (auth()->user()->cannot('create', [Ledger::class, $ledgerDefine])) {
    abort(403);
}
```

### 2. データサニタイズ

**XSS対策:**
- `strip_tags()`: HTMLタグを除去
- 文字数制限: 5000文字（テキスト）、255文字（配列要素）
- 選択肢の検証: `select`/`chk`タイプは定義された選択肢のみ許可

### 3. 除外項目

**複製しない項目:**
- `auto_number`: 重複防止、新規採番が必要
- `files`: セキュリティリスク、ストレージ容量の問題
- ワークフロー情報: `status`, `version`, `inspector_id`, `approver_id` 等
- `content_attached`: 添付ファイルメタデータ

---

## 🧪 テスト設計

### 1. 単体テスト (Unit Test)

**テスト対象:** `DuplicateController::buildPrefillParamsFromLedger()`

**テストケース:**
- ✅ 通常のテキストフィールドの複製
- ✅ 配列値（chk）の複製
- ✅ auto_numberタイプの除外
- ✅ filesタイプの除外
- ✅ XSS攻撃パターンのサニタイズ
- ✅ 文字数制限の適用
- ✅ select/chkの選択肢検証

### 2. 統合テスト (Feature Test)

**テストケース:**
- ✅ 正常系: 権限があるユーザーが複製できる
- ✅ 異常系: 閲覧権限がない（403エラー）
- ✅ 異常系: 作成権限がない（403エラー）
- ✅ 異常系: 存在しないレコードID（404エラー）
- ✅ 正常系: 初期値が正しく設定される
- ✅ 正常系: 除外項目が含まれない

**実装例:**

```php
// tests/Feature/Ledger/DuplicateControllerTest.php

public function test_user_can_duplicate_ledger_with_proper_permissions(): void
{
    $user = User::factory()->create();
    $ledgerDefine = LedgerDefine::factory()->create();
    $sourceLedger = Ledger::factory()->create([
        'ledger_define_id' => $ledgerDefine->id,
        'content' => [
            0 => 'テストタイトル',
            1 => '12345',
        ],
    ]);
    
    // 権限付与
    $user->givePermissionTo('create_ledgers');
    
    $response = $this->actingAs($user)
        ->get(route('ledger.duplicate', [
            'tenant' => tenant('id'),
            'ledgerId' => $sourceLedger->id
        ]));
    
    $response->assertStatus(200);
    $response->assertViewHas('prefillParams');
    
    $prefillParams = $response->viewData('prefillParams');
    $this->assertEquals('テストタイトル', $prefillParams[0]);
    $this->assertEquals('12345', $prefillParams[1]);
}

public function test_auto_number_and_files_are_excluded_from_duplication(): void
{
    // ...テスト実装
}
```

---

## 📊 実装スケジュール

### Phase 1: 基本機能実装（優先度: 高）

**所要時間:** 約2-3時間

| タスク | 担当 | 所要時間 | 備考 |
|--------|------|----------|------|
| `DuplicateController`作成 | 開発者 | 60分 | コントローラー本体 |
| ルート定義追加 | 開発者 | 10分 | `tenant.php` |
| UIボタン追加 | 開発者 | 30分 | `workflow-action-buttons.blade.php` |
| 翻訳キー追加 | 開発者 | 10分 | `ja.json` |
| 手動動作確認 | 開発者 | 30分 | 各種パターン確認 |

### Phase 2: テスト実装（優先度: 中）

**所要時間:** 約2時間

| タスク | 担当 | 所要時間 | 備考 |
|--------|------|----------|------|
| 単体テスト作成 | 開発者 | 60分 | `buildPrefillParamsFromLedger()` |
| 統合テスト作成 | 開発者 | 60分 | `DuplicateControllerTest.php` |

### Phase 3: ドキュメント整備（優先度: 低）

**所要時間:** 約1時間

| タスク | 担当 | 所要時間 | 備考 |
|--------|------|----------|------|
| ユーザーマニュアル更新 | ドキュメント担当 | 30分 | スクリーンショット付き |
| API仕様書更新 | ドキュメント担当 | 30分 | ルート追加を反映 |

**合計所要時間:** 約5-6時間

---

## 🔄 将来の拡張案

### 1. 部分複製機能

**概要:** ユーザーが複製するフィールドを選択できる機能

**実装案:**
```blade
<!-- モーダルでフィールド選択 -->
<x-mary-modal wire:model="showDuplicateOptionsModal">
    <x-mary-checkbox label="タイトル" wire:model="selectedFields.0" />
    <x-mary-checkbox label="説明" wire:model="selectedFields.1" />
    <!-- ... -->
</x-mary-modal>
```

### 2. テンプレート機能

**概要:** 特定のレコードを「テンプレート」としてマークし、新規作成時に選択できる

**実装案:**
- `ledgers`テーブルに`is_template`カラム追加
- テンプレート一覧画面の作成
- 新規作成画面でテンプレート選択UI

### 3. 一括複製

**概要:** 複数のレコードを一度に複製

**実装案:**
- リスト画面でチェックボックス選択
- バッチ処理での複製実行

### 4. 複製履歴の記録

**概要:** どのレコードがどのレコードから複製されたかを記録

**実装案:**
- `ledgers`テーブルに`duplicated_from_id`カラム追加
- 詳細画面に「複製元を表示」リンク

---

## 📚 関連ドキュメント

### 既存ドキュメント
- [台帳作成機能](../../features/ledger-creation.md)
- [権限管理](../access-control/README.md)
- [ワークフロー機能](./workflow/2025-06-27_workflow-feature-implementation.md)

### 関連ファイル
- `app/Http/Controllers/Ledger/CreateController.php` - 既存の作成機能
- `app/Http/Requests/Ledger/SearchRequest.php` - パラメータ処理
- `app/Livewire/Ledger/CreateColumn.php` - 作成フォームコンポーネント
- `app/Models/Ledger.php` - 台帳モデル
- `app/Models/LedgerDefine.php` - 台帳定義モデル

---

## 📝 変更履歴

| 日付 | 変更内容 | 担当者 |
|------|----------|--------|
| 2025-12-11 | 初版作成 | GitHub Copilot |

---

## ✅ レビューチェックリスト

実装前に以下を確認：

- [ ] 既存のPrefill機能との整合性確認
- [ ] 権限チェックの妥当性確認
- [ ] セキュリティレビュー（XSS、CSRF対策）
- [ ] パフォーマンス影響の評価
- [ ] テストカバレッジの確認
- [ ] ドキュメントの完全性確認
- [ ] UI/UXのレビュー
- [ ] 多言語対応の確認

---

**このドキュメントは実装の公式な設計書として使用されます。**  
**変更がある場合は、このドキュメントを更新してから実装を進めてください。**
