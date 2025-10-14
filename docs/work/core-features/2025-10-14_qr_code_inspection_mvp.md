# URLクエリパラメータによる台帳初期値設定機能 - MVP実装計画

**作成日:** 2025年10月14日  
**機能名:** URLクエリパラメータによる台帳カラム初期値設定（汎用機能）  
**ステータス:** 実装計画フェーズ  
**対象ブランチ:** feature/qr-code-inspection

---

## 1. MVP設計方針

### 1.1 コンセプト

**URLクエリパラメータで台帳カラムの初期値を設定できる汎用機能**を実装します。QRコードは単なるURL短縮手段として使用し、様々な業務シーン（設備点検、在庫確認、受付記録、アンケート等）で活用できる柔軟な仕組みを提供します。

### 1.2 基本フロー

```
[QRコード/URL] → [スマホ/PCで読み取り/クリック] → [ブラウザでURL起動]
    ↓
[LedgerLeap台帳作成画面に遷移]
    ↓
[指定された台帳カラムに初期値が自動入力されている]
    ↓
[ユーザーが内容を確認・必要に応じて修正]
    ↓
[保存ボタンをクリック → 登録完了]
```

### 1.3 想定される利用シーン

**設備点検:**
- QRコードに設備ID、設備名を埋め込み
- 点検者が読み取ると、点検台帳に設備情報が自動入力

**在庫確認:**
- 商品棚にQRコード貼付（商品ID、棚番号）
- スタッフが読み取り、在庫数を入力して記録

**受付記録:**
- 来訪者用QRコード（訪問先部署、日時）
- 受付で読み取り、氏名と目的を追記して登録

**アンケート/フィードバック:**
- イベント会場にQRコード（イベント名、日時、会場）
- 参加者が読み取り、満足度やコメントを入力

**定型業務の効率化:**
- よく使う台帳フォーマットに、定型値を事前設定したURLをブックマーク
- 毎日の日報作成で、プロジェクト名や業務区分を自動入力

### 1.4 実装しないもの（Phase 1では見送り）

- ❌ 専用のQRコード読み取りUI
- ❌ オフライン同期機能（PWA化）
- ❌ プッシュ通知
- ❌ 一括登録モード
- ❌ 専用ダッシュボード

### 1.5 活用する既存機能

- ✅ 既存の台帳作成機能 (`/ledger/create/{ledgerDefineId}`)
- ✅ 既存の権限管理（フォルダベース）
- ✅ 既存の添付ファイル機能
- ✅ 既存の全文検索機能
- ✅ 既存の台帳一覧機能

---

## 2. MVP機能要件

### 2.1 URLクエリパラメータの設計

#### 2.1.1 URL形式

**基本URL:**
```
https://{domain}/{tenant}/ledger/create/{ledgerDefineId}?prefill[{カラムID}]={値}&prefill[{カラムID}]={値}
```

**URLパラメータの命名規則:**
- `prefill[カラムID]`: カラムIDをキーとして初期値を指定
- カラムIDは台帳定義のカラムIDを使用（数値）

**具体例（設備点検）:**
```
https://ledgerleap.example.com/demo-org/ledger/create/5?prefill[1]=EQ-001&prefill[2]=3F%E7%A9%BA%E8%AA%BF%E6%A9%9FA
```

**具体例（在庫確認）:**
```
https://ledgerleap.example.com/demo-org/ledger/create/8?prefill[3]=SKU-12345&prefill[4]=A-01-05
```

**具体例（受付記録）:**
```
https://ledgerleap.example.com/demo-org/ledger/create/10?prefill[1]=2025-10-14&prefill[5]=%E7%B7%8F%E5%8B%99%E9%83%A8
```

#### 2.1.2 対応するカラムタイプ

以下のカラムタイプでURLパラメータからの初期値設定をサポートします:

| カラムタイプ | 対応 | パラメータ例 | 備考 |
|------------|------|-------------|------|
| text | ✅ | `prefill[1]=テスト値` | 文字列をそのまま設定 |
| number | ✅ | `prefill[2]=123.45` | 数値として検証 |
| YMD | ✅ | `prefill[3]=2025-10-14` | 日付形式で検証 |
| YMDHM | ✅ | `prefill[4]=2025-10-14 15:30` | 日時形式で検証 |
| select | ✅ | `prefill[5]=選択肢A` | 選択肢に存在するか検証 |
| chk | ✅ | `prefill[6][]=項目1&prefill[6][]=項目2` | 配列形式で複数指定 |
| textarea | ✅ | `prefill[7]=複数行%0Aテキスト` | 改行も対応 |
| files | ❌ | - | セキュリティ上、非対応 |
| auto_number | ❌ | - | 自動採番のため非対応 |

#### 2.1.3 バリデーション

**サーバー側での検証項目:**
- カラムIDが台帳定義に存在するか
- カラムタイプに合った値か（数値、日付形式など）
- selectの場合、選択肢に含まれるか
- 文字列長の制限（XSS対策）
- 必須項目の検証は保存時に実施（初期値設定時は任意）

**エラーハンドリング:**
- 不正なカラムIDは無視（エラー表示せず）
- 不正な値の場合は空文字に置き換え、Toast通知で警告
- ログに記録（不正アクセスの検知）

---

### 2.2 QRコード生成機能（任意ツール）

#### 2.2.1 QRコード生成画面

**実装場所:** 台帳定義の編集画面に「URLビルダー & QRコード生成」タブを追加

**機能:**
1. **URLビルダー:**
   - 台帳定義のカラムリストを表示
   - 各カラムに初期値を入力するフォーム
   - 「URLを生成」ボタンで完成したURLを表示
   - URLをクリップボードにコピー

2. **QRコード生成:**
   - 生成したURLからQRコードを作成
   - QRコードサイズ選択（小/中/大）
   - 出力形式選択（PNG/SVG）
   - ダウンロードボタン

3. **プリセット管理（Phase 2以降）:**
   - よく使うパラメータセットを保存
   - プリセット名を付けて管理
   - プリセットから素早くQRコード生成

**UI例:**
```
┌─────────────────────────────────────┐
│ URLビルダー & QRコード生成          │
├─────────────────────────────────────┤
│ カラム選択:                         │
│ ☑ カラムID: 1 (設備ID)              │
│   初期値: [EQ-001_____________]     │
│                                     │
│ ☑ カラムID: 2 (設備名)              │
│   初期値: [3F空調機A__________]     │
│                                     │
│ ☑ カラムID: 3 (点検日)              │
│   初期値: [今日の日付を使用 ▼]     │
│                                     │
│ [URLを生成]                         │
├─────────────────────────────────────┤
│ 生成されたURL:                      │
│ https://ledgerleap.example.com/...  │
│ [コピー] [QRコード生成]             │
├─────────────────────────────────────┤
│ QRコード:                           │
│ ┌─────────┐                        │
│ │ ███ ███ │                        │
│ │ █ ███ █ │  サイズ: [中 ▼]        │
│ │ ███ ███ │  形式: [PNG ▼]         │
│ └─────────┘                        │
│ [ダウンロード]                      │
└─────────────────────────────────────┘
```

**実装クラス:**
- Livewireコンポーネント: `app/Livewire/LedgerDefine/URLBuilder.php`
- サービスクラス: `app/Services/QRCodeService.php`

---

### 2.3 台帳作成画面の拡張（コア機能）

#### 2.3.1 URLパラメータの自動入力

**実装要件:**
- `prefill[カラムID]` パラメータが存在する場合、該当カラムに自動入力
- 既存の `CreateColumn.php` Livewireコンポーネントを拡張
- `mount()` メソッドで初期値を設定

**実装方法:**

1. **CreateController の拡張:**
```php
// app/Http/Controllers/Ledger/CreateController.php
public function create(CreateRequest $request)
{
    $ledgerDefine = LedgerDefine::findOrFail($request->ledgerDefineId);
    
    // 権限チェック
    if (auth()->user()->cannot('create', [Ledger::class, $ledgerDefine])) {
        abort(403, __('ledger.not_allow_create'));
    }
    
    // prefillパラメータを取得
    $prefillParams = $request->query('prefill', []);
    
    // バリデーション & サニタイズ
    $validatedPrefill = $this->validatePrefillParams($prefillParams, $ledgerDefine);
    
    return View::make('ledger.create', [
        'ledgerDefineRecord' => $ledgerDefine,
        'prefillParams' => $validatedPrefill,
    ]);
}

private function validatePrefillParams(array $params, LedgerDefine $ledgerDefine): array
{
    $validated = [];
    $columnDefines = collect($ledgerDefine->column_define)->keyBy('id');
    
    foreach ($params as $columnId => $value) {
        // カラムIDが存在するか確認
        if (!$columnDefines->has($columnId)) {
            Log::warning("Invalid column ID in prefill: {$columnId}");
            continue;
        }
        
        $column = $columnDefines[$columnId];
        
        // カラムタイプに応じた検証
        $validatedValue = $this->validatePrefillValue($value, $column);
        
        if ($validatedValue !== null) {
            $validated[$columnId] = $validatedValue;
        }
    }
    
    return $validated;
}

private function validatePrefillValue($value, $column)
{
    switch ($column->type) {
        case 'text':
        case 'textarea':
            return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : null;
        
        case 'number':
            return is_numeric($value) ? $value : null;
        
        case 'YMD':
            try {
                $date = Carbon::parse($value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        
        case 'YMDHM':
            try {
                $date = Carbon::parse($value);
                return $date->format('Y-m-d H:i');
            } catch (\Exception $e) {
                return null;
            }
        
        case 'select':
            $options = $column->getInputType()->options ?? [];
            return in_array($value, $options) ? $value : null;
        
        case 'chk':
            if (!is_array($value)) return null;
            $options = $column->getInputType()->options ?? [];
            return array_filter($value, fn($v) => in_array($v, $options));
        
        case 'files':
        case 'auto_number':
            return null; // サポートしない
        
        default:
            return null;
    }
}
```

2. **CreateColumn Livewireコンポーネントの拡張:**
```php
// app/Livewire/Ledger/CreateColumn.php

public array $prefillParams = []; // 追加

public function mount(int $ledgerDefineId, array $prefillParams = []): void
{
    $this->ledgerDefineId = $ledgerDefineId;
    $this->prefillParams = $prefillParams;
    $this->ledgerDefineRecord = LedgerDefine::findOrFail($this->ledgerDefineId);
    
    $this->initColumns();
    $this->applyPrefillParams(); // ← 新規追加
    $this->initBackgroundImages();
    $this->initRequireColumns();
    $this->initializeDateDefaults();
    $this->updateProgress();
    $this->loadRecommendedPersonnel();
    $this->initializeGroups();
}

protected function applyPrefillParams(): void
{
    foreach ($this->prefillParams as $columnId => $value) {
        // contentにカラムIDをキーとして値を設定
        if (isset($this->content[$columnId])) {
            $this->content[$columnId] = $value;
            
            // Toast通知（任意）
            // $this->info("カラムID {$columnId} に初期値が設定されました");
        }
    }
    
    // prefillパラメータが1つ以上ある場合、Toast通知
    if (count($this->prefillParams) > 0) {
        $this->info(__('ledger.prefill_applied', ['count' => count($this->prefillParams)]));
    }
}
```

3. **Blade テンプレートの調整:**
```php
// resources/views/ledger/create.blade.php

<livewire:ledger.create-column 
    :ledger-define-id="$ledgerDefineRecord->id" 
    :prefill-params="$prefillParams ?? []" 
/>
```

#### 2.3.2 UI上の表示

**初期値が設定されたカラムの強調表示（任意）:**
- 初期値が設定されたカラムに薄い背景色を付ける
- カラムラベルの横に小さなアイコン表示（例: 🔗 または "URL"）
- ツールチップで「URLから初期値が設定されました」と表示

**実装例（Blade）:**
```blade
@if(isset($prefillParams[$column->id]))
    <div class="bg-blue-50 border-l-4 border-blue-400 p-2 rounded">
        <label class="flex items-center">
            {{ $column->name }}
            <span class="ml-2 text-xs text-blue-600" title="URLから初期値が設定されました">
                🔗
            </span>
        </label>
        <x-input wire:model="content.{{ $column->id }}" />
    </div>
@else
    <label>{{ $column->name }}</label>
    <x-input wire:model="content.{{ $column->id }}" />
@endif
```

---

### 2.4 URL短縮機能（Phase 2以降、任意）

QRコードに埋め込むURLが長くなる場合、URL短縮機能を提供します。

**実装方法:**
- 短縮URLテーブル (`short_urls`) を作成
- ランダムな短縮キーを生成（例: `https://ledgerleap.example.com/s/abc123`）
- リダイレクト処理で元のURLに転送

**テーブル定義:**
```sql
CREATE TABLE short_urls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    short_key VARCHAR(20) UNIQUE NOT NULL,
    original_url TEXT NOT NULL,
    ledger_define_id BIGINT UNSIGNED,
    access_count INT DEFAULT 0,
    created_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (ledger_define_id) REFERENCES ledger_defines(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_short_key (short_key)
);
```

---

## 3. 実装の段階分け

### Phase 1: コア機能（MVP）- 4日間

**目標:** URLパラメータによる台帳カラムの初期値設定機能を実現

**実装タスク:**

1. **CreateController の拡張**（1日）
   - `prefill` パラメータの取得
   - バリデーション & サニタイズ
   - カラムタイプに応じた検証ロジック
   - エラーハンドリング

2. **CreateColumn Livewireコンポーネントの拡張**（1日）
   - `prefillParams` プロパティの追加
   - `applyPrefillParams()` メソッドの実装
   - Toast通知の追加
   - UI上での初期値強調表示（任意）

3. **テスト実装**（1.5日）
   - `CreateControllerTest.php`（機能テスト）
     - 正常系: prefillパラメータが正しく適用される
     - 異常系: 不正なカラムIDは無視される
     - 異常系: 不正な値は空文字に置き換えられる
   - `CreateColumnTest.php`（Livewireテスト）
     - prefillParamsが正しくcontentに反映される
     - Toast通知が表示される
   - 各カラムタイプでの動作確認

4. **ドキュメント整備**（0.5日）
   - ユーザーマニュアル（URLの作り方）
   - 管理者ガイド（パラメータ仕様）
   - README更新

**成果物:**
- URLにクエリパラメータを付けるだけで、台帳カラムに初期値が設定される
- ユーザーは内容を確認し、保存ボタンを押すだけで登録完了

---

### Phase 2: URLビルダー & QRコード生成（3日間）

**目標:** 管理画面からURLとQRコードを簡単に生成できる

**実装タスク:**

1. **QRCodeService の実装**（0.5日）
   - `simple-qrcode/simple-qrcode` パッケージのインストール
   - QRコード画像生成（PNG/SVG）
   - 画像の保存・ダウンロード機能

2. **URLBuilder Livewireコンポーネント**（1.5日）
   - カラムリスト表示
   - 初期値入力フォーム
   - URL生成ロジック
   - クリップボードコピー機能
   - QRコード生成ボタン

3. **テストとドキュメント**（1日）
   - `QRCodeServiceTest.php`
   - `URLBuilderTest.php`
   - ユーザーマニュアル更新

**成果物:**
- 管理画面からGUIでURLとQRコードを生成できる
- 生成したQRコードをダウンロードし、印刷・貼付できる

---

### Phase 3: 高度な機能（2日間、任意）

**目標:** 利便性とセキュリティの向上

**実装タスク:**

1. **URL短縮機能**（1日）
   - `short_urls` テーブル作成
   - 短縮キー生成ロジック
   - リダイレクト処理
   - アクセスカウント機能

2. **プリセット管理**（1日）
   - よく使うパラメータセットの保存
   - プリセット名管理
   - 素早い再利用

**成果物:**
- 長いURLを短縮でき、QRコードがシンプルになる
- よく使うURLをプリセットとして保存し、再利用できる

---

## 4. データフロー詳細

### 4.1 URLパラメータ適用フロー

```
[ユーザー] 
  → [QRコード読み取り or URLクリック]
  → [ブラウザ起動: https://.../ledger/create/5?prefill[1]=EQ-001&prefill[2]=3F空調機A]
  → [認証チェック（未ログインの場合はログイン画面へ）]
  → [CreateController::create()]
      → [prefillパラメータを取得]
      → [バリデーション & サニタイズ]
      → [validatedPrefillをBladeに渡す]
  → [台帳作成画面表示]
  → [CreateColumn::mount($ledgerDefineId, $prefillParams)]
      → [applyPrefillParams()]
      → [content配列に初期値を設定]
  → [画面上でカラムに初期値が表示される]
  → [ユーザーが内容を確認・修正]
  → [保存ボタンをクリック]
  → [CreateColumn::save()]
  → [Ledger::create()]
  → [保存完了、一覧画面へリダイレクト]
```

### 4.2 URLビルダー & QRコード生成フロー（Phase 2）

```
[管理者] 
  → [台帳定義編集画面] 
  → [URLビルダー & QRコード生成タブ]
  → [カラムリストから初期値を入力]
  → [「URLを生成」ボタンをクリック]
  → [URLBuilder::generateURL()]
      → [選択されたカラムと値からprefillパラメータを生成]
      → [URLを構築]
  → [生成されたURLを表示・コピー]
  → [「QRコード生成」ボタンをクリック]
  → [QRCodeService::generateQRImage()]
  → [QRコード画像をダウンロード]
  → [設備・資産に貼付]
```

---

## 5. 技術的な実装詳細

### 5.1 CreateController バリデーション詳細

```php
<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class CreateController extends Controller
{
    public function create(CreateRequest $request)
    {
        // 権限チェック
        if (!auth()->user()->can('create_ledgers')) {
            abort(403, __('ledger.not_allow_create'));
        }
        
        $ledgerDefine = LedgerDefine::findOrFail($request->ledgerDefineId);
        
        if (auth()->user()->cannot('create', [Ledger::class, $ledgerDefine])) {
            abort(403, __('ledger.not_allow_create'));
        }
        
        // prefillパラメータを取得 & 検証
        $prefillParams = $request->query('prefill', []);
        $validatedPrefill = $this->validatePrefillParams($prefillParams, $ledgerDefine);
        
        return View::make('ledger.create', [
            'ledgerDefineRecord' => $ledgerDefine,
            'prefillParams' => $validatedPrefill,
        ]);
    }
    
    private function validatePrefillParams(array $params, LedgerDefine $ledgerDefine): array
    {
        $validated = [];
        $columnDefines = collect($ledgerDefine->column_define)->keyBy('id');
        
        foreach ($params as $columnId => $value) {
            // カラムIDが数値かチェック
            if (!is_numeric($columnId)) {
                Log::warning("Non-numeric column ID in prefill", ['columnId' => $columnId]);
                continue;
            }
            
            $columnId = (int) $columnId;
            
            // カラムIDが存在するか確認
            if (!$columnDefines->has($columnId)) {
                Log::warning("Invalid column ID in prefill", ['columnId' => $columnId]);
                continue;
            }
            
            $column = $columnDefines[$columnId];
            
            // カラムタイプに応じた検証
            $validatedValue = $this->validatePrefillValue($value, $column);
            
            if ($validatedValue !== null) {
                $validated[$columnId] = $validatedValue;
            } else {
                Log::info("Prefill value validation failed", [
                    'columnId' => $columnId,
                    'columnType' => $column->type,
                    'value' => $value
                ]);
            }
        }
        
        return $validated;
    }
    
    private function validatePrefillValue($value, $column)
    {
        try {
            switch ($column->type) {
                case 'text':
                case 'textarea':
                    if (!is_string($value)) return null;
                    // XSS対策
                    $sanitized = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    // 長さ制限（5000文字）
                    return mb_strlen($sanitized) <= 5000 ? $sanitized : mb_substr($sanitized, 0, 5000);
                
                case 'number':
                    return is_numeric($value) ? $value : null;
                
                case 'YMD':
                    $date = Carbon::parse($value);
                    return $date->format('Y-m-d');
                
                case 'YMDHM':
                    $date = Carbon::parse($value);
                    return $date->format('Y-m-d H:i');
                
                case 'select':
                    $inputType = $column->getInputType();
                    $options = $inputType->options ?? [];
                    return in_array($value, $options, true) ? $value : null;
                
                case 'chk':
                    if (!is_array($value)) return null;
                    $inputType = $column->getInputType();
                    $options = $inputType->options ?? [];
                    $validated = array_filter($value, fn($v) => in_array($v, $options, true));
                    return !empty($validated) ? $validated : null;
                
                case 'files':
                case 'auto_number':
                    // サポートしない
                    return null;
                
                default:
                    return null;
            }
        } catch (\Exception $e) {
            Log::warning("Prefill value validation exception", [
                'columnId' => $column->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
```

### 5.2 CreateColumn applyPrefillParams 実装

```php
// app/Livewire/Ledger/CreateColumn.php

protected function applyPrefillParams(): void
{
    if (empty($this->prefillParams)) {
        return;
    }
    
    $appliedCount = 0;
    
    foreach ($this->prefillParams as $columnId => $value) {
        // contentにカラムIDをキーとして値を設定
        // initColumns()で既に$this->content[$columnId]が初期化されているはず
        if (array_key_exists($columnId, $this->content)) {
            $this->content[$columnId] = $value;
            $appliedCount++;
        } else {
            Log::warning("Prefill column ID not found in content", ['columnId' => $columnId]);
        }
    }
    
    // Toast通知
    if ($appliedCount > 0) {
        $this->info(__('ledger.prefill_applied', ['count' => $appliedCount]));
    }
}
```

### 5.3 QRCodeService 実装（Phase 2）

```php
<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class QRCodeService
{
    /**
     * prefillパラメータ付きURLを生成
     */
    public function generateURL(
        string $tenant,
        int $ledgerDefineId,
        array $prefillData
    ): string {
        $params = [];
        
        foreach ($prefillData as $columnId => $value) {
            if (is_array($value)) {
                // 配列の場合（chkタイプなど）
                foreach ($value as $item) {
                    $params["prefill[{$columnId}][]"] = $item;
                }
            } else {
                $params["prefill[{$columnId}]"] = $value;
            }
        }
        
        $baseUrl = route('ledger.create', [
            'tenant' => $tenant,
            'ledgerDefineId' => $ledgerDefineId,
        ]);
        
        return $baseUrl . '?' . http_build_query($params);
    }
    
    /**
     * QRコード画像を生成（文字列として返す）
     */
    public function generateQRImage(
        string $url,
        string $format = 'png',
        int $size = 300
    ): string {
        return QrCode::format($format)
            ->size($size)
            ->errorCorrection('H') // 最高レベルのエラー訂正
            ->generate($url);
    }
    
    /**
     * QRコード画像をファイルとして保存
     */
    public function saveQRImage(
        string $url,
        string $identifier,
        string $format = 'png'
    ): string {
        $filename = "qr_" . md5($url) . "_{$identifier}." . $format;
        $path = "qr_codes/{$filename}";
        
        $qrCode = $this->generateQRImage($url, $format);
        
        Storage::disk('public')->put($path, $qrCode);
        
        return $path;
    }
}
```

---

## 6. セキュリティ考慮事項

### 6.1 認証・認可

- **必須条件:** ユーザーはログイン済みである必要がある
- **権限チェック:** フォルダへの書き込み権限を確認（既存の権限システムを活用）
- **URLパラメータの検証:** 不正なカラムIDや値を拒否

### 6.2 パラメータの検証とサニタイゼーション

```php
// XSS対策
$sanitized = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// 長さ制限
$truncated = mb_strlen($sanitized) <= 5000 ? $sanitized : mb_substr($sanitized, 0, 5000);

// カラムタイプに応じた厳格な検証
// - 数値: is_numeric()
// - 日付: Carbon::parse()でパース可能か
// - 選択肢: in_array()で選択肢に含まれるか
```

### 6.3 ロギング

```php
// 不正なアクセスの記録
Log::warning("Invalid column ID in prefill", [
    'user_id' => auth()->id(),
    'ledger_define_id' => $ledgerDefineId,
    'column_id' => $columnId,
    'ip' => request()->ip(),
]);

// 正常な利用も記録（分析用）
Log::info("Prefill params applied", [
    'user_id' => auth()->id(),
    'ledger_define_id' => $ledgerDefineId,
    'applied_count' => $appliedCount,
]);
```

### 6.4 その他のセキュリティ対策

- **CSRF保護:** Laravel標準のCSRF保護が有効
- **SQL Injection:** Eloquent ORMを使用するため基本的に安全
- **ファイルアップロード:** prefillパラメータではファイルタイプをサポートしない
- **Rate Limiting:** 必要に応じてAPIエンドポイントにレート制限を追加

---

## 7. テスト計画

### 7.1 機能テスト（CreateController）

```php
<?php

namespace Tests\Feature\Http\Controllers\Ledger;

use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateControllerPrefillTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_prefill_params_are_validated_and_passed_to_view()
    {
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        
        $response = $this->actingAs($user)->get(
            route('ledger.create', [
                'tenant' => tenant('id'),
                'ledgerDefineId' => $ledgerDefine->id
            ]) . '?prefill[1]=テスト値&prefill[2]=123'
        );
        
        $response->assertOk();
        $response->assertViewHas('prefillParams');
        
        $prefillParams = $response->viewData('prefillParams');
        $this->assertArrayHasKey(1, $prefillParams);
        $this->assertEquals('テスト値', $prefillParams[1]);
    }
    
    public function test_invalid_column_id_is_ignored()
    {
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        
        $response = $this->actingAs($user)->get(
            route('ledger.create', [
                'tenant' => tenant('id'),
                'ledgerDefineId' => $ledgerDefine->id
            ]) . '?prefill[9999]=不正な値'
        );
        
        $response->assertOk();
        $prefillParams = $response->viewData('prefillParams');
        $this->assertArrayNotHasKey(9999, $prefillParams);
    }
    
    public function test_unauthorized_user_cannot_access()
    {
        $ledgerDefine = LedgerDefine::factory()->create();
        
        $response = $this->get(
            route('ledger.create', [
                'tenant' => tenant('id'),
                'ledgerDefineId' => $ledgerDefine->id
            ])
        );
        
        $response->assertRedirect('/login');
    }
    
    public function test_xss_injection_is_sanitized()
    {
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        
        $response = $this->actingAs($user)->get(
            route('ledger.create', [
                'tenant' => tenant('id'),
                'ledgerDefineId' => $ledgerDefine->id
            ]) . '?prefill[1]=<script>alert("XSS")</script>'
        );
        
        $response->assertOk();
        $prefillParams = $response->viewData('prefillParams');
        
        // HTMLエンティティにエスケープされている
        $this->assertStringContainsString('&lt;script&gt;', $prefillParams[1]);
        $this->assertStringNotContainsString('<script>', $prefillParams[1]);
    }
}
```

### 7.2 Livewireテスト（CreateColumn）

```php
<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\CreateColumn;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateColumnPrefillTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_prefill_params_are_applied_to_content()
    {
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        
        $prefillParams = [
            1 => 'テスト設備ID',
            2 => 'テスト設備名',
        ];
        
        Livewire::actingAs($user)
            ->test(CreateColumn::class, [
                'ledgerDefineId' => $ledgerDefine->id,
                'prefillParams' => $prefillParams,
            ])
            ->assertSet('content.1', 'テスト設備ID')
            ->assertSet('content.2', 'テスト設備名');
    }
    
    public function test_toast_notification_is_displayed_when_prefill_applied()
    {
        $user = User::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create();
        
        $prefillParams = [1 => 'テスト値'];
        
        Livewire::actingAs($user)
            ->test(CreateColumn::class, [
                'ledgerDefineId' => $ledgerDefine->id,
                'prefillParams' => $prefillParams,
            ])
            ->assertDispatched('mary-toast');
    }
}
```

### 7.3 QRCodeServiceテスト（Phase 2）

```php
<?php

namespace Tests\Unit\Services;

use App\Services\QRCodeService;
use Tests\TestCase;

class QRCodeServiceTest extends TestCase
{
    public function test_generate_url_with_prefill_params()
    {
        $service = new QRCodeService();
        
        $url = $service->generateURL('demo-org', 5, [
            1 => 'EQ-001',
            2 => '3F空調機A',
        ]);
        
        $this->assertStringContainsString('ledger/create/5', $url);
        $this->assertStringContainsString('prefill%5B1%5D=EQ-001', $url);
        $this->assertStringContainsString('prefill%5B2%5D=3F', $url);
    }
    
    public function test_generate_qr_image_returns_png()
    {
        $service = new QRCodeService();
        $image = $service->generateQRImage('https://example.com', 'png');
        
        $this->assertNotEmpty($image);
        // PNGマジックバイトを確認
        $this->assertStringStartsWith("\x89PNG", $image);
    }
    
    public function test_generate_qr_image_returns_svg()
    {
        $service = new QRCodeService();
        $image = $service->generateQRImage('https://example.com', 'svg');
        
        $this->assertStringContainsString('<svg', $image);
    }
}
```

---

## 8. リスクと対策

### リスク1: ログイン失効問題
- **内容:** URL読み取り時にログインセッションが切れている
- **対策:** 
  - ログイン画面にリダイレクトし、ログイン後に元のURL（prefillパラメータ付き）に戻る
  - Laravel標準の `intended()` メソッドを活用

### リスク2: URLパラメータの改ざん
- **内容:** 悪意あるユーザーがURLパラメータを改ざん
- **対策:**
  - サーバー側での厳格なバリデーション
  - 権限チェックの徹底（フォルダレベル）
  - 改ざん検出ログの記録

### リスク3: QRコードの印刷品質
- **内容:** QRコードが小さすぎる、または汚れで読み取れない
- **対策:**
  - 推奨サイズのガイドライン提供（最小3cm × 3cm）
  - エラー訂正レベルを「H」（最高）に設定
  - 定期的な再印刷を推奨

### リスク4: 長いURLによるQRコードの複雑化
- **内容:** 多数のパラメータでURLが長くなり、QRコードが複雑になる
- **対策:**
  - Phase 3でURL短縮機能を実装
  - 必要最小限のパラメータのみを含める設計

---

## 9. ユーザーマニュアル（概要）

### 9.1 管理者向け: URLとQRコードの作成方法

#### 方法1: 手動でURLを作成（Phase 1）

1. 台帳定義のIDを確認（URL or 画面上部）
2. 初期値を設定したいカラムのIDを確認（台帳定義編集画面）
3. URLを手動で構築:
   ```
   https://ledgerleap.example.com/demo-org/ledger/create/5?prefill[1]=EQ-001&prefill[2]=3F空調機A
   ```
4. 外部のQRコードジェネレーター（例: [qr-code-generator.com](https://www.qr-code-generator.com/)）でQRコードを生成
5. QRコードを印刷し、設備に貼付

#### 方法2: URLビルダーを使用（Phase 2）

1. 台帳定義の編集画面を開く
2. 「URLビルダー & QRコード生成」タブをクリック
3. カラムリストから初期値を設定するカラムを選択
4. 各カラムに初期値を入力
5. 「URLを生成」ボタンをクリック
6. 生成されたURLを確認・コピー
7. 「QRコード生成」ボタンをクリック
8. QRコード画像をダウンロード
9. 設備に貼付

### 9.2 ユーザー向け: QRコードからの台帳登録方法

1. スマートフォンのカメラアプリを起動
2. 設備・資産に貼られたQRコードを読み取る
3. ブラウザが起動し、LedgerLeapの台帳作成画面が表示される
4. ログインしていない場合はログイン
5. 初期値が自動入力されていることを確認（🔗アイコン付きのカラム）
6. 必要に応じて追加情報を入力（写真、コメントなど）
7. 「保存」ボタンをタップ
8. 登録完了

---

## 10. 今後の拡張案（Phase 4以降）

### 10.1 URL有効期限機能
- URLに有効期限パラメータを追加
- 期限切れURLへのアクセスをブロック
- 一時的なQRコード配布に有用

### 10.2 ワンタイムURL
- 1回のみ使用可能なURLを生成
- アンケートや受付記録などで重複登録を防止

### 10.3 プリセット管理の高度化
- チーム間でのプリセット共有
- プリセットのテンプレート化
- プリセットの利用統計

### 10.4 動的初期値
- 現在日時を自動挿入（`prefill[3]=NOW`）
- ログインユーザー名を自動挿入（`prefill[5]=CURRENT_USER`）
- 位置情報を自動挿入（`prefill[7]=LOCATION`）

---

## 11. まとめ

本MVP実装計画では、**URLクエリパラメータによる台帳カラム初期値設定機能**を汎用的に設計しました。

### メリット
- ✅ 設備点検だけでなく、様々な業務シーンで活用可能
- ✅ QRコードは単なるURL短縮手段として利用
- ✅ 既存の台帳作成機能を最大限活用
- ✅ 実装コストの最小化（Phase 1は4日間）
- ✅ スマートフォンの標準カメラアプリで対応可能
- ✅ 段階的な機能拡張が容易

### MVP完了後の成果
- URLにクエリパラメータを付けるだけで、台帳カラムに初期値が設定される
- ユーザーは内容を確認し、保存ボタンを押すだけで登録完了
- QRコードを活用した効率的な業務フローを実現

### 実装期間
- **Phase 1（コア機能）: 4日間** ← まずはこれを実装
- Phase 2（URLビルダー & QRコード生成）: 3日間
- Phase 3（高度な機能）: 2日間
- **合計: 9日間で完全版が完成**

---

**次のステップ:**
1. ステークホルダーレビュー（本機能が要件を満たすか確認）
2. Phase 1実装開始（CreateController & CreateColumn の拡張）
3. 実証実験（設備点検、在庫確認など）
4. フィードバック収集とPhase 2へ

---

**文書履歴:**
- 2025-10-14: MVP実装計画作成（汎用URL初期値設定機能）
- 2025-10-14: 設備点検特化からカラムID指定方式へ変更
