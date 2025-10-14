# URLクエリパラメータによる台帳初期値設定機能 - 超最小限MVP

**作成日:** 2025年10月14日  
**機能名:** URLクエリパラメータによる台帳カラム初期値設定（超最小限実装）  
**ステータス:** 実装計画フェーズ  
**対象ブランチ:** feature/qr-code-inspection

---

## 1. 超最小限MVP設計方針

### 1.1 コンセプト

**台帳登録画面だけで完結する最小限の実装:**

1. **コア機能:** URLクエリパラメータで台帳カラムの初期値を設定
2. **URL生成機能:** 台帳登録画面で入力内容から登録用URLを生成・表示

**QRコード生成は外部サービスに完全に任せる**

### 1.2 ユーザーの操作フロー

```
【URL作成フロー】
[ユーザー]
→ 台帳登録画面を開く (/ledger/create/{ledgerDefineId})
→ 想定する点検結果を入力（例: 設備ID「EQ-001」、動作状態「正常」）
→ 「登録用URLを確認」ボタンをクリック
→ ダイアログに現在の入力内容を反映したURLが表示される
→ URLをコピー
→ 外部QRコード生成サービスでQRコード化
→ 印刷して設備に貼付

【URL使用フロー】
[現場作業員]
→ QRコードをスマホで読み取り
→ ブラウザで台帳作成画面が開く（初期値が設定済み）
→ ログイン（必要な場合）
→ 内容を確認・必要に応じて修正
→ 「登録」ボタンをクリック
→ 完了
```

### 1.3 実装範囲

**✅ 実装する:**
- URLパラメータによる初期値設定（CreateController & CreateColumn拡張）
- 台帳登録画面に「登録用URLを確認」ボタン追加
- URLをモーダルで表示・コピー機能

**❌ 実装しない:**
- QRコード画像生成機能（外部サービスを推奨）
- 台帳定義画面でのサンプルURL機能（不要）
- URL短縮機能（Phase 2以降）
- URL管理・履歴機能（Phase 2以降）

---

## 2. 機能要件

### 2.1 コア機能: URLパラメータによる初期値設定

#### 2.1.1 URL形式

```
https://{domain}/{tenant}/ledger/create/{ledgerDefineId}?prefill[{カラムID}]={値}&prefill[{カラムID}]={値}...
```

**具体例（設備点検）:**
```
https://ledgerleap.example.com/demo-org/ledger/create/5
  ?prefill[1]=EQ-001
  &prefill[2]=3F空調機A
  &prefill[5]=正常
  &prefill[8]=2025-10-14
```

#### 2.1.2 対応カラムタイプ

| カラムタイプ | 対応 | パラメータ例 |
|------------|------|-------------|
| text | ✅ | `prefill[1]=設備A` |
| number | ✅ | `prefill[2]=123.45` |
| YMD | ✅ | `prefill[3]=2025-10-14` |
| YMDHM | ✅ | `prefill[4]=2025-10-14 15:30` |
| select | ✅ | `prefill[5]=正常` |
| chk | ✅ | `prefill[6][]=項目1&prefill[6][]=項目2` |
| textarea | ✅ | `prefill[7]=説明文` |
| files | ❌ | - |
| auto_number | ❌ | - |

#### 2.1.3 実装（CreateController）

```php
// app/Http/Controllers/Ledger/CreateController.php

public function create(CreateRequest $request)
{
    if (!auth()->user()->can('create_ledgers')) {
        abort(403, __('ledger.not_allow_create'));
    }
    
    $ledgerDefine = LedgerDefine::findOrFail($request->ledgerDefineId);
    
    if (auth()->user()->cannot('create', [Ledger::class, $ledgerDefine])) {
        abort(403, __('ledger.not_allow_create'));
    }
    
    // prefillパラメータを取得 & 検証
    $prefillParams = $this->validatePrefillParams(
        $request->query('prefill', []), 
        $ledgerDefine
    );
    
    return View::make('ledger.create', [
        'ledgerDefineRecord' => $ledgerDefine,
        'prefillParams' => $prefillParams,
    ]);
}

private function validatePrefillParams(array $params, LedgerDefine $ledgerDefine): array
{
    $validated = [];
    $columns = collect($ledgerDefine->column_define)->keyBy('id');
    
    foreach ($params as $columnId => $value) {
        // カラムIDの検証
        if (!is_numeric($columnId) || !$columns->has((int)$columnId)) {
            Log::warning('Invalid prefill column ID', ['columnId' => $columnId]);
            continue;
        }
        
        $columnId = (int)$columnId;
        $column = $columns[$columnId];
        
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
    try {
        switch ($column->type) {
            case 'text':
            case 'textarea':
                if (!is_string($value)) return null;
                return htmlspecialchars(mb_substr($value, 0, 5000), ENT_QUOTES, 'UTF-8');
            
            case 'number':
                return is_numeric($value) ? $value : null;
            
            case 'YMD':
                $date = Carbon::parse($value);
                return $date->format('Y-m-d');
            
            case 'YMDHM':
                $date = Carbon::parse($value);
                return $date->format('Y-m-d H:i');
            
            case 'select':
                $options = $column->getInputType()->options ?? [];
                return in_array($value, $options, true) ? $value : null;
            
            case 'chk':
                if (!is_array($value)) return null;
                $options = $column->getInputType()->options ?? [];
                $validated = array_filter($value, fn($v) => in_array($v, $options, true));
                return !empty($validated) ? array_values($validated) : null;
            
            default:
                return null;
        }
    } catch (\Exception $e) {
        Log::warning('Prefill validation error', [
            'columnId' => $column->id,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}
```

#### 2.1.4 実装（CreateColumn Livewire）

```php
// app/Livewire/Ledger/CreateColumn.php

public array $prefillParams = [];

public function mount(int $ledgerDefineId, array $prefillParams = []): void
{
    $this->ledgerDefineId = $ledgerDefineId;
    $this->prefillParams = $prefillParams;
    $this->ledgerDefineRecord = LedgerDefine::findOrFail($this->ledgerDefineId);
    
    $this->initColumns();
    $this->applyPrefillParams(); // ← 初期値を適用
    $this->initBackgroundImages();
    $this->initRequireColumns();
    $this->initializeDateDefaults();
    $this->updateProgress();
    $this->loadRecommendedPersonnel();
    $this->initializeGroups();
}

protected function applyPrefillParams(): void
{
    if (empty($this->prefillParams)) {
        return;
    }
    
    $appliedCount = 0;
    
    foreach ($this->prefillParams as $columnId => $value) {
        if (array_key_exists($columnId, $this->content)) {
            $this->content[$columnId] = $value;
            $appliedCount++;
        }
    }
    
    if ($appliedCount > 0) {
        $this->info(__('ledger.prefill_applied_count', ['count' => $appliedCount]));
    }
}
```

**Bladeビュー調整:**
```blade
<!-- resources/views/ledger/create.blade.php -->

<livewire:ledger.create-column 
    :ledger-define-id="$ledgerDefineRecord->id" 
    :prefill-params="$prefillParams ?? []" 
/>
```

---

### 2.2 URL生成機能: 台帳登録画面に「登録用URLを確認」ボタン

#### 2.2.1 UI配置

**場所:** 保存ボタンの横

```
┌─────────────────────────────────────────┐
│ カラム入力フォーム                      │
│ ・設備ID: [EQ-001________]             │
│ ・設備名: [3F空調機A______]            │
│ ・動作状態: [正常 ▼]                   │
│ ・点検日: [2025-10-14___]              │
├─────────────────────────────────────────┤
│ [登録]  [登録用URLを確認]              │
└─────────────────────────────────────────┘
```

**「登録用URLを確認」ボタンをクリック後:**

```
┌─────────────────────────────────────────┐
│ モーダル: 登録用URL                     │
├─────────────────────────────────────────┤
│ 現在の入力内容から登録用URLを生成しました │
│                                         │
│ https://ledgerleap.example.com/demo-... │
│ [📋 コピー]                             │
│                                         │
│ ℹ️ このURLの使い方:                     │
│ 1. URLをコピーする                      │
│ 2. QRコード生成サービスでQRコード化     │
│    推奨: https://www.qr-code-generator.com/ │
│ 3. QRコードを印刷して設備に貼付         │
│                                         │
│ URLを開くと、この内容で登録画面が開きます │
│                                         │
│ [閉じる]                                │
└─────────────────────────────────────────┘
```

#### 2.2.2 実装（CreateColumn Livewire）

```php
// app/Livewire/Ledger/CreateColumn.php

public string $generatedURL = '';
public bool $showURLModal = false;

public function generateRegistrationURL(): void
{
    $params = [];
    
    foreach ($this->content as $columnId => $value) {
        // 空の値はスキップ
        if ($this->isEmptyValue($value)) {
            continue;
        }
        
        // 配列の場合（chkタイプ）
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!$this->isEmptyValue($item)) {
                    $params["prefill[{$columnId}][]"] = $item;
                }
            }
        } else {
            $params["prefill[{$columnId}]"] = $value;
        }
    }
    
    // URLが空の場合の警告
    if (empty($params)) {
        $this->warning('入力内容がありません。何か入力してからURLを生成してください。');
        return;
    }
    
    $baseUrl = route('ledger.create', [
        'tenant' => tenant('id'),
        'ledgerDefineId' => $this->ledgerDefineId,
    ]);
    
    $this->generatedURL = $baseUrl . '?' . http_build_query($params);
    $this->showURLModal = true;
    
    $this->info('登録用URLを生成しました');
}

private function isEmptyValue($value): bool
{
    if (is_null($value)) return true;
    if (is_string($value) && trim($value) === '') return true;
    if (is_array($value) && empty($value)) return true;
    
    return false;
}

public function copyURL(): void
{
    $this->dispatch('copy-to-clipboard', text: $this->generatedURL);
    $this->success('URLをクリップボードにコピーしました');
}
```

#### 2.2.3 実装（Bladeビュー）

```blade
<!-- resources/views/livewire/ledger/create-column.blade.php -->

<!-- 保存ボタンエリア（既存の保存ボタンの横に追加） -->
<div class="flex gap-2 mt-6">
    <button wire:click="save" 
            class="btn btn-primary"
            wire:loading.attr="disabled">
        <i class="fas fa-save"></i>
        {{ __('ledger.save') }}
    </button>
    
    <button wire:click="generateRegistrationURL" 
            class="btn btn-outline btn-info"
            type="button">
        <i class="fas fa-link"></i>
        登録用URLを確認
    </button>
</div>

<!-- URLモーダル -->
<x-modal wire:model="showURLModal" title="登録用URL" class="w-11/12 max-w-3xl">
    <div class="space-y-4">
        <p class="text-base">
            現在の入力内容から登録用URLを生成しました。
        </p>
        
        <!-- URL表示エリア -->
        <div class="form-control">
            <label class="label">
                <span class="label-text font-bold">生成されたURL:</span>
            </label>
            <div class="flex gap-2">
                <input 
                    type="text" 
                    value="{{ $generatedURL }}" 
                    readonly 
                    class="input input-bordered flex-1 font-mono text-xs"
                    onclick="this.select()" />
                <button 
                    wire:click="copyURL" 
                    class="btn btn-square btn-primary"
                    title="コピー">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        </div>
        
        <!-- 使い方ガイド -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div class="text-sm space-y-2">
                <p class="font-bold">📝 このURLの使い方:</p>
                <ol class="list-decimal list-inside space-y-1 ml-2">
                    <li>上記のURLをコピーする</li>
                    <li>QRコード生成サービスでQRコード化する
                        <div class="mt-1 ml-4">
                            推奨: <a href="https://www.qr-code-generator.com/" 
                                    target="_blank" 
                                    class="link link-primary">
                                QR Code Generator
                            </a>
                        </div>
                    </li>
                    <li>QRコードを印刷して設備・資産に貼付</li>
                </ol>
                <p class="mt-2 pt-2 border-t border-base-300">
                    💡 URLを開くと、この入力内容で登録画面が開きます。<br>
                    同じ内容を繰り返し登録する場合に便利です。
                </p>
            </div>
        </div>
    </div>
    
    <x-slot:actions>
        <button 
            wire:click="$set('showURLModal', false)" 
            class="btn">
            閉じる
        </button>
    </x-slot:actions>
</x-modal>

<!-- クリップボードコピー用のJavaScript -->
@script
<script>
    $wire.on('copy-to-clipboard', (event) => {
        navigator.clipboard.writeText(event.text).then(() => {
            console.log('Copied to clipboard');
        }).catch(err => {
            console.error('Failed to copy:', err);
        });
    });
</script>
@endscript
```

---

## 3. 実装スケジュール

### 1日目（6時間）

**午前（3時間）:**
- CreateController の `validatePrefillParams()` 実装（1.5時間）
- CreateColumn の `applyPrefillParams()` 実装（1時間）
- 各カラムタイプのバリデーション実装（0.5時間）

**午後（3時間）:**
- CreateColumn の `generateRegistrationURL()` 実装（1時間）
- Bladeビュー（ボタン & モーダル）実装（1時間）
- 動作確認とバグ修正（1時間）

### 2日目（6時間）

**午前（3時間）:**
- 機能テスト実装（CreateController）（1.5時間）
- Livewireテスト実装（CreateColumn）（1.5時間）

**午後（3時間）:**
- 統合テスト（E2Eシナリオ）（1時間）
- ドキュメント整備（ユーザーマニュアル）（1時間）
- 最終動作確認とデモ準備（1時間）

**合計: 12時間（1.5日間）**

---

## 4. 初度ユースケースのカバー範囲

### ✅ ユースケース1: 通常点検（理想ケース）

**カバー度: 100%**

1. 管理者が台帳登録画面で想定する点検結果を入力 → ✅
2. 「登録用URLを確認」ボタンでURL生成 → ✅
3. 外部サービスでQRコード化 → ✅
4. 作業員がQRコードを読み取り → ✅
5. 初期値が設定された状態で画面表示 → ✅
6. 内容確認して登録 → ✅

---

### ✅ ユースケース2: 異常発見シナリオ

**カバー度: 95%**

- 基本フロー: ✅ 完全対応
- 写真撮影: ✅ 既存機能（FilePond）
- ⚠️ 管理者への即時通知: ❌ 未実装（既存のワークフロー機能で代替可能）

---

### ❌ ユースケース3: 電波不通エリア（オフライン）

**カバー度: 0% → Phase 2以降**

---

### ✅ ユースケース4: QRコード破損

**カバー度: 100%**

- 手動で台帳作成画面にアクセス → ✅ 既存機能

---

### 🔶 ユースケース5: 複数設備の一括点検

**カバー度: 50%**

- 各設備のQRコードを順次読み取り → ✅
- ⚠️ 進捗管理: ❌ 未実装（台帳一覧で確認可能）

---

### ✅ ユースケース6: 臨時作業員の初回利用

**カバー度: 90%**

- QRコードで設備特定 → ✅
- 初期値が設定されている → ✅
- ⚠️ チュートリアル: ❌ 未実装（台帳定義の説明文で代替）

---

### ✅ ユースケース7: 部分更新（測定値のみ更新）

**カバー度: 100%**

- 既存台帳の編集機能で対応 → ✅ 既存機能

---

### ✅ ユースケース8: 点検スキップ

**カバー度: 100%**

- 「点検不可」を選択肢に追加 → ✅
- 理由を記録 → ✅

---

## 5. カバー範囲サマリー

| ユースケース | カバー度 | 備考 |
|------------|---------|------|
| UC1: 通常点検 | ✅ 100% | 完全対応 |
| UC2: 異常発見 | ✅ 95% | 通知以外は対応 |
| UC3: オフライン | ❌ 0% | Phase 2以降 |
| UC4: QRコード破損 | ✅ 100% | 既存機能で対応 |
| UC5: 一括点検 | 🔶 50% | 基本フローのみ対応 |
| UC6: 初心者利用 | ✅ 90% | ほぼ対応 |
| UC7: 部分更新 | ✅ 100% | 既存機能で対応 |
| UC8: 点検スキップ | ✅ 100% | 完全対応 |

**総合カバー率: 約 79%**

**コアユースケース（UC1, UC2, UC4, UC7, UC8）: 99%**

---

## 6. テスト計画

### 6.1 機能テスト

```php
// tests/Feature/Http/Controllers/Ledger/CreateControllerPrefillTest.php

public function test_prefill_params_are_validated_and_applied()
{
    $user = User::factory()->create();
    $ledgerDefine = LedgerDefine::factory()->create([
        'column_define' => [
            ['id' => 1, 'name' => '設備ID', 'type' => 'text'],
            ['id' => 2, 'name' => '動作状態', 'type' => 'select', 'options' => ['正常', '異常']],
        ]
    ]);
    
    $response = $this->actingAs($user)->get(
        route('ledger.create', [
            'tenant' => tenant('id'),
            'ledgerDefineId' => $ledgerDefine->id
        ]) . '?prefill[1]=EQ-001&prefill[2]=正常'
    );
    
    $response->assertOk();
    $response->assertViewHas('prefillParams', [
        1 => 'EQ-001',
        2 => '正常',
    ]);
}

public function test_invalid_select_value_is_ignored()
{
    // 選択肢に存在しない値は無視される
}

public function test_xss_attack_is_prevented()
{
    // XSS攻撃が防がれることを確認
}
```

### 6.2 Livewireテスト

```php
// tests/Feature/Livewire/Ledger/CreateColumnURLGenerationTest.php

public function test_generate_url_from_current_content()
{
    $user = User::factory()->create();
    $ledgerDefine = LedgerDefine::factory()->create();
    
    Livewire::actingAs($user)
        ->test(CreateColumn::class, [
            'ledgerDefineId' => $ledgerDefine->id,
            'prefillParams' => []
        ])
        ->set('content.1', 'EQ-001')
        ->set('content.2', '3F空調機A')
        ->call('generateRegistrationURL')
        ->assertSet('showURLModal', true)
        ->assertSet('generatedURL', fn($url) => 
            str_contains($url, 'prefill%5B1%5D=EQ-001')
        );
}

public function test_empty_content_shows_warning()
{
    Livewire::test(CreateColumn::class, ['ledgerDefineId' => $ledgerDefine->id])
        ->call('generateRegistrationURL')
        ->assertDispatched('mary-toast', function($event) {
            return $event['type'] === 'warning';
        });
}
```

---

## 7. ユーザーマニュアル

### 7.1 管理者向け: QRコードの作成手順

**ステップ1: URLの生成**

1. 台帳作成画面を開く
   - 例: `/demo-org/ledger/create/5`（設備点検台帳）
2. 想定する標準的な点検結果を入力
   - 設備ID: `EQ-001`
   - 設備名: `3F空調機A`
   - 動作状態: `正常`
   - 点検日: `2025-10-14`
3. 「登録用URLを確認」ボタンをクリック
4. モーダルに表示されたURLをコピー

**ステップ2: QRコードの生成**

1. [QR Code Generator](https://www.qr-code-generator.com/) を開く
2. 「URL」を選択
3. コピーしたURLを貼り付け
4. QRコードサイズを調整（推奨: 中〜大）
5. 「QRコードを作成」をクリック
6. QRコード画像をダウンロード

**ステップ3: 印刷と貼付**

1. QRコード画像を印刷（推奨: 3cm × 3cm以上）
2. 設備名・設備IDをQRコードの下に印字（読み取り失敗時の予備）
3. 防水・耐久性のあるシール紙に印刷
4. 設備の見やすい場所に貼付

---

### 7.2 現場作業員向け: QRコードからの登録方法

1. スマートフォンのカメラでQRコードを読み取る
2. ブラウザが起動し、台帳作成画面が開く
3. ログインしていない場合はログイン
4. 設備情報が自動入力されていることを確認
   - 設備ID: `EQ-001`（自動入力済み）
   - 設備名: `3F空調機A`（自動入力済み）
5. 実際の点検結果を入力・修正
   - 動作状態: 異常がなければそのまま「正常」
   - 異常がある場合は「異常」に変更
   - 写真が必要な場合は撮影して添付
6. 「登録」ボタンをタップ
7. 完了

---

### 7.3 応用: 繰り返し登録用のブックマーク

**シーン:** 毎日同じ形式の日報を記録する場合

1. 台帳作成画面で1回目の日報を入力
   - プロジェクト名: `新製品開発`
   - 業務区分: `設計作業`
2. 「登録用URLを確認」ボタンをクリック
3. URLをコピーしてブラウザのブックマークに保存
4. 次回から、ブックマークを開くだけで同じ内容が自動入力される
5. 日付や作業内容だけを変更して登録

---

## 8. リスクと対策

### リスク1: 長いURLの取り扱い

- **内容:** 多数のカラムでURLが非常に長くなる（QRコードが複雑化）
- **対策:** 
  - 必要最小限のカラムのみ入力を推奨
  - Phase 2でURL短縮機能を検討

### リスク2: selectの選択肢変更

- **内容:** 台帳定義の選択肢を変更すると、既存QRコードの値が無効になる
- **対策:**
  - バリデーションで不正な値を検出し、空文字に置き換え
  - ログに警告を記録
  - QRコードの再生成を促すアラート機能（Phase 2）

### リスク3: QRコード生成の習熟

- **内容:** ユーザーが外部サービスでのQRコード生成に不慣れ
- **対策:**
  - モーダルに推奨サービスへのリンクを表示
  - ユーザーマニュアルに詳細な手順を記載（スクリーンショット付き）

---

## 9. まとめ

### 超最小限MVPの特徴

**✅ 実装する機能:**
1. URLパラメータによる台帳カラム初期値設定（コア機能）
2. 台帳登録画面で「登録用URLを確認」ボタン
3. 現在の入力内容からURLを生成してモーダル表示

**❌ 実装しない機能:**
- QRコード画像生成（外部サービスに完全に任せる）
- 台帳定義画面でのサンプルURL機能（不要）
- URL管理・履歴機能（Phase 2以降）

### 実装期間

**1.5日間（12時間）で完成**

### ユースケースカバー率

**総合: 79% / コアユースケース: 99%**

- 通常点検: 100%
- 異常発見: 95%
- 部分更新: 100%
- 点検スキップ: 100%

### 次のステップ

1. ✅ 実装開始（1.5日間）
2. ✅ 実証実験（設備点検、在庫確認など）
3. ✅ フィードバック収集
4. 🔜 Phase 2検討（URL短縮、通知など）

---

**文書履歴:**
- 2025-10-14: 超最小限MVP実装計画作成（台帳登録画面のみで完結する設計）
