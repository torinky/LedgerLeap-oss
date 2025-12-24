# Phase 4.4 (履歴タブ) 実装ガイド - WBS順

**作成日:** 2025-12-24  
**最終更新:** 2025-12-24  
**対象フェーズ:** Phase 4.4 (History Tab Implementation)  
**総見積工数:** 5.5h  
**関連ドキュメント:** `2025-12-20_phase4_detailed_plan.md`

---

## 📋 目次

1. [プロジェクト概要](#プロジェクト概要)
2. [事前準備・前提条件](#事前準備前提条件)
3. [WBS: 作業タスク一覧](#wbs-作業タスク一覧)
4. [実装ガイド（タスク別詳細）](#実装ガイドタスク別詳細)
5. [テスト手順](#テスト手順)
6. [完了チェックリスト](#完了チェックリスト)
7. [トラブルシューティング](#トラブルシューティング)
8. [参考資料](#参考資料)

---

## プロジェクト概要

### 🎯 目的
File Inspectorに**履歴（History）タブ**を実装し、ファイルのライフサイクル全体を可視化します。

### 📊 表示する情報
1. **システム処理イベント**: アップロード、OCR/VLM処理完了などの自動処理履歴
2. **ユーザーアクティビティ**: ダウンロード、削除などのユーザー操作履歴

### ✅ 成功基準
- ✅ ダウンロード操作後、即座に履歴タブに反映される
- ✅ システムイベントとユーザー操作が時系列順に表示される
- ✅ 「誰が」操作したかが明確に区別できる（System/ユーザー名）

### 🏗️ アーキテクチャ方針
- **2セクション分離方式**を採用（システムログ + ユーザーアクティビティ）
- タイムスタンプカラムからシステムイベントを生成
- `Spatie\Activitylog` からユーザーイベントを取得
- `ActivityLogFormatter` でイベント名を日本語化

---

## 事前準備・前提条件

### ✅ 実装前チェック
- [ ] Phase 4.1（基本UI）が完了している
- [ ] Phase 4.2（Content/Metadata/VLMタブ）が完了している
- [ ] `AttachedFileDownloadController` でアクティビティログが記録されている
- [ ] 開発環境（Sail）が起動している
- [ ] テストデータベースが準備されている

### 📚 必要な知識
- Laravel Eloquent（リレーション、Accessor）
- Livewire（プロパティ、リフレッシュ）
- Blade（ループ、条件分岐）
- DaisyUI（Steps、Card コンポーネント）

### 🔧 必要なツール
```bash
# コード整形
./vendor/bin/sail pint

# テスト実行
./vendor/bin/sail pest

# ログ確認
./vendor/bin/sail logs -f
```

---

## WBS: 作業タスク一覧

| タスクID | タスク名 | 見積 | 優先度 | 依存関係 | 状態 |
|---------|---------|------|--------|---------|------|
| **4.4.0** | **LogsActivityトレイト追加（オプショナル）** | **0.5h** | **低** | なし | ⚠️ 条件付き |
| **4.4.1** | **ActivityLogFormatter拡張と翻訳追加** | **1h** | **必須** | なし | ⬜ 未着手 |
| **4.4.2** | **タイムラインデータ生成ロジック実装** | **2h** | **必須** | 4.4.1 | ⬜ 未着手 |
| **4.4.3** | **履歴タブUI実装** | **1.5h** | **必須** | 4.4.2 | ⬜ 未着手 |
| **4.4.4** | **動作検証とテスト** | **0.5h** | **必須** | 4.4.3 | ⬜ 未着手 |

**合計工数**: 5.5h（オプショナル含む） / 5h（必須のみ）

---

## 実装ガイド（タスク別詳細）

### 📦 タスク 4.4.0: LogsActivityトレイト追加（オプショナル） [0.5h]

#### ⚠️ 実装判断基準
**実装を推奨するケース**:
- ファイル削除操作の監査ログが必須要件
- DB容量に余裕がある
- 1日のファイルアップロード数が10,000件未満

**見送るケース（推奨）**:
- DB負荷が懸念される
- 現時点でダウンロードログのみで十分
- Phase 4.5で再評価可能

#### 実装内容（実施する場合）

**ファイル**: `app/Models/AttachedFile.php`

```php
// use句の追加
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class AttachedFile extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \Stancl\Tenancy\Database\Concerns\BelongsToTenant;
    
    // ...existing code...
    
    /**
     * アクティビティログ設定
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['filename', 'status', 'mime', 'size'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "File {$eventName}");
    }
}
```

#### チェックリスト
- [ ] `use LogsActivity` トレイト追加
- [ ] `getActivitylogOptions()` メソッド実装
- [ ] タイムスタンプカラムを除外設定
- [ ] 単体テストで動作確認
- [ ] パフォーマンステスト（1000ファイル作成）

#### 💡 Tips
- このタスクは**スキップ推奨**。Phase 4.5で再評価。
- スキップしても、ダウンロードログは正常に記録される。

---

### 📦 タスク 4.4.1: ActivityLogFormatter拡張と翻訳追加 [1h]

#### 🎯 目的
`AttachedFile` のアクティビティログを正しく表示できるようにする。

#### 実装手順

##### ステップ1: ActivityLogFormatterにAttachedFile対応追加

**ファイル**: `app/Helpers/ActivityLogFormatter.php`

```php
use App\Models\AttachedFile; // 追加

public static function getSubjectNameForDisplay(CustomActivity|array $activity): string
{
    // ...existing code...
    
    // RoleFolderPermissionの後に追加
    } elseif ($subject instanceof AttachedFile) {
        $title = $subject->original_filename ?? $subject->filename ?? ('ID: '.$subject->id);
        $type = __('ledger.activity.model_name.attached_file');
    } else {
        // ...existing code...
    }
}
```

##### ステップ2: ダウンロードイベントの翻訳対応

**同じファイル**: `app/Helpers/ActivityLogFormatter.php`

```php
public static function getOperationDescription(CustomActivity|array $activity): string
{
    // ...existing code...
    
    // ログイン・ログアウトの後に追加
    if (in_array($eventKey, ['login', 'logout'])) {
        return __("ledger.activity.event.{$eventKey}", ['resource' => $subjectName]);
    }
    
    // ファイル操作イベント（新規追加）
    if (in_array($eventKey, ['downloaded', 'downloaded_original', 'viewed_thumbnail', 'downloaded_vlm'])) {
        return __("ledger.activity.event.{$eventKey}", ['resource' => $subjectName]);
    }
    
    // ...existing code...
}
```

##### ステップ3: 翻訳キー追加

**ファイル**: `lang/ja/ledger.php`

```php
'activity' => [
    'event' => [
        // ...既存のキー...
        
        // ファイル操作イベント（追加）
        'downloaded' => 'ファイルをダウンロードしました。',
        'downloaded_original' => 'オリジナルファイルをダウンロードしました。',
        'viewed_thumbnail' => 'サムネイルを表示しました。',
        'downloaded_vlm' => 'VLM解析結果をダウンロードしました。',
    ],
    'model_name' => [
        // ...既存のキー...
        
        // AttachedFile（追加）
        'attached_file' => '添付ファイル',
    ],
],
```

#### チェックリスト
- [ ] `ActivityLogFormatter.php` に `AttachedFile` の分岐追加
- [ ] ダウンロードイベントの分岐追加
- [ ] `lang/ja/ledger.php` に翻訳キー追加（4キー）
- [ ] `./vendor/bin/sail pint` でコード整形
- [ ] 既存のActivityHistoryDisplayでエラーが出ないか確認

#### 🧪 テスト方法
```php
// tests/Unit/Helpers/ActivityLogFormatterTest.php
it('formats attached file subject name correctly', function () {
    $file = AttachedFile::factory()->make(['filename' => 'test.pdf']);
    $activity = Mockery::mock(CustomActivity::class);
    $activity->shouldReceive('getAttribute')->with('subject')->andReturn($file);
    
    $result = ActivityLogFormatter::getSubjectNameForDisplay($activity);
    
    expect($result)->toContain('test.pdf')
        ->and($result)->toContain('添付ファイル');
});
```

---

### 📦 タスク 4.4.2: タイムラインデータ生成ロジック実装 [2h]

#### 🎯 目的
`AttachedFile` モデルにタイムライン生成機能を追加する。

#### 実装手順

##### ステップ1: システムタイムライン生成（Accessor）

**ファイル**: `app/Models/AttachedFile.php`

```php
/**
 * システム処理イベントのタイムラインを取得
 * 
 * @return \Illuminate\Support\Collection
 */
public function getSystemTimelineAttribute(): Collection
{
    $events = collect();
    
    // 1. アップロード
    if ($this->created_at) {
        $events->push([
            'type' => 'system',
            'icon' => 'o-paper-clip',
            'color' => 'neutral',
            'title' => __('ledger.file_inspector.history.uploaded'),
            'description' => $this->creator?->name ?? 'System',
            'timestamp' => $this->created_at,
            'user' => $this->creator?->name ?? 'System',
        ]);
    }
    
    // 2. Tika処理完了
    if ($this->tika_processed_at) {
        $events->push([
            'type' => 'system',
            'icon' => 'o-document-text',
            'color' => 'info',
            'title' => __('ledger.file_inspector.history.tika_extraction'),
            'description' => null,
            'timestamp' => $this->tika_processed_at,
            'user' => 'System',
        ]);
    }
    
    // 3. OCR処理完了
    if ($this->ocr_processed_at) {
        $events->push([
            'type' => 'system',
            'icon' => 'o-eye',
            'color' => 'secondary',
            'title' => __('ledger.file_inspector.history.ocr_processing'),
            'description' => null,
            'timestamp' => $this->ocr_processed_at,
            'user' => 'System',
        ]);
    }
    
    // 4. VLM処理完了
    if ($this->vlm_processed_at) {
        $description = $this->vlm_confidence 
            ? sprintf('%s: %.1f%%', __('ledger.file_inspector.info.confidence'), $this->vlm_confidence * 100)
            : null;
        if ($this->vlm_processing_time_ms) {
            $description .= sprintf(' | %.1f秒', $this->vlm_processing_time_ms / 1000);
        }
        
        $events->push([
            'type' => 'system',
            'icon' => 'o-cpu-chip',
            'color' => 'primary',
            'title' => __('ledger.file_inspector.history.vlm_analysis'),
            'description' => $description,
            'timestamp' => $this->vlm_processed_at,
            'user' => 'System',
        ]);
    }
    
    // 5. VLM処理失敗
    if ($this->vlm_failed_at) {
        $events->push([
            'type' => 'system',
            'icon' => 'o-exclamation-circle',
            'color' => 'error',
            'title' => __('ledger.file_inspector.history.vlm_failed'),
            'description' => null,
            'timestamp' => $this->vlm_failed_at,
            'user' => 'System',
        ]);
    }
    
    // 6. OCR処理失敗
    if ($this->ocr_failed_at) {
        $events->push([
            'type' => 'system',
            'icon' => 'o-exclamation-circle',
            'color' => 'error',
            'title' => __('ledger.file_inspector.history.ocr_failed'),
            'description' => null,
            'timestamp' => $this->ocr_failed_at,
            'user' => 'System',
        ]);
    }
    
    // 7. 処理確定
    if ($this->processing_finalized_at) {
        $events->push([
            'type' => 'system',
            'icon' => 'o-check-circle',
            'color' => 'success',
            'title' => __('ledger.file_inspector.history.processing_finalized'),
            'description' => $this->finalized_source ? "Source: {$this->finalized_source}" : null,
            'timestamp' => $this->processing_finalized_at,
            'user' => 'System',
        ]);
    }
    
    // 新しい順にソート
    return $events->sortByDesc('timestamp')->values();
}
```

##### ステップ2: ユーザーアクティビティタイムライン生成

**同じファイル**: `app/Models/AttachedFile.php`

```php
/**
 * ユーザー操作のタイムラインを取得
 * 
 * @return \Illuminate\Support\Collection
 */
public function getUserTimelineAttribute(): Collection
{
    // activities リレーションは FileInspector::loadData() で eager load 済み
    return $this->activities()
        ->with('causer:id,name')
        ->latest()
        ->limit(50) // 最新50件のみ
        ->get()
        ->map(function ($activity) {
            // イベント名から表示情報を決定
            $icon = match($activity->event) {
                'downloaded', 'downloaded_original' => 'o-arrow-down-tray',
                'viewed_thumbnail' => 'o-eye',
                'downloaded_vlm' => 'o-cpu-chip',
                'deleted' => 'o-trash',
                default => 'o-clock',
            };
            
            $color = match($activity->event) {
                'downloaded', 'downloaded_original', 'downloaded_vlm' => 'success',
                'viewed_thumbnail' => 'info',
                'deleted' => 'error',
                default => 'neutral',
            };
            
            return [
                'type' => 'user',
                'icon' => $icon,
                'color' => $color,
                'title' => \App\Helpers\ActivityLogFormatter::getOperationDescription($activity),
                'description' => null,
                'timestamp' => $activity->created_at,
                'user' => $activity->causer?->name ?? __('ledger.activity.subject.unknown'),
                'properties' => $activity->properties, // IP/UA等
            ];
        });
}
```

##### ステップ3: 翻訳キー追加（History関連）

**ファイル**: `lang/ja/ledger.php`

```php
'file_inspector' => [
    'history' => [
        'uploaded' => 'ファイルアップロード',
        'tika_extraction' => 'テキスト抽出（Tika）',
        'ocr_processing' => 'OCR処理',
        'vlm_analysis' => 'VLM解析',
        'vlm_failed' => 'VLM処理失敗',
        'ocr_failed' => 'OCR処理失敗',
        'processing_finalized' => '処理確定',
        'processing_log' => 'システム処理ログ',
        'activity' => 'ユーザーアクティビティ',
        'recent_30days' => '直近30日間',
        // ...既存のキー...
    ],
    // ...existing code...
],
```

#### チェックリスト
- [ ] `getSystemTimelineAttribute()` 実装（7イベント対応）
- [ ] `getUserTimelineAttribute()` 実装（50件制限）
- [ ] 翻訳キー追加（`file_inspector.history.*`）
- [ ] `./vendor/bin/sail pint` でコード整形
- [ ] 単体テストでタイムライン生成確認

#### 🧪 テスト方法
```php
// tests/Unit/Models/AttachedFileTest.php
it('generates system timeline from timestamp columns', function () {
    $file = AttachedFile::factory()->create([
        'created_at' => now()->subDays(5),
        'tika_processed_at' => now()->subDays(5)->addMinutes(2),
        'ocr_processed_at' => now()->subDays(5)->addMinutes(5),
        'vlm_processed_at' => now()->subDays(5)->addMinutes(8),
    ]);
    
    $timeline = $file->system_timeline;
    
    expect($timeline)->toHaveCount(4)
        ->and($timeline->first()['title'])->toContain('VLM')
        ->and($timeline->first()['type'])->toBe('system');
});

it('generates user timeline from activities', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $file = AttachedFile::factory()->create();
    
    activity()
        ->performedOn($file)
        ->causedBy($user)
        ->event('downloaded')
        ->log('File downloaded');
    
    $timeline = $file->user_timeline;
    
    expect($timeline)->toHaveCount(1)
        ->and($timeline->first()['user'])->toBe('Test User')
        ->and($timeline->first()['type'])->toBe('user');
});
```

---

### 📦 タスク 4.4.3: 履歴タブUI実装 [1.5h]

#### 🎯 目的
既存のモックUIを動的データで置き換える。

#### 実装手順

##### ステップ1: History タブの動的データ化

**ファイル**: `resources/views/livewire/attached-file/file-inspector.blade.php`

**変更箇所**: History タブ内のモックデータ部分を置き換え

```blade
<x-mary-tab name="history" label="{{ __('ledger.file_inspector.tabs.history') }}"
            icon="o-clock" class="tab-lg gap-2">
    {{-- History Tab --}}
    <div class="p-4 space-y-4" x-data="{
        showAllLogs: false,
        showAllActivity: false
    }">
        {{-- モックデータ判定 --}}
        @if($this->isMockFile())
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle"></i>
                {{ __('ledger.file_inspector.history.mock_notice') }}
            </div>
        @else
            {{-- セクション1: システム処理ログ --}}
            <div>
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-success"></i>
                    {{ __('ledger.file_inspector.history.processing_log') }}
                </h3>

                <div class="relative">
                    <div class="overflow-y-auto max-h-64" style="scrollbar-width: thin;">
                        <ul class="steps steps-vertical text-sm">
                            @foreach($file->system_timeline as $event)
                                <li class="step step-{{ $event['color'] }}">
                                    <div class="text-left ml-3">
                                        <div class="font-semibold flex items-center gap-2">
                                            <i class="fa-solid fa-{{ $event['icon'] }}"></i>
                                            {{ $event['title'] }}
                                        </div>
                                        <div class="text-xs text-base-content/60">
                                            {{ $event['timestamp']->format('Y-m-d H:i:s') }}
                                        </div>
                                        @if($event['description'])
                                            <div class="text-xs text-base-content/70">
                                                {{ $event['description'] }}
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                            
                            @if($file->system_timeline->isEmpty())
                                <li class="step">
                                    <div class="text-left ml-3">
                                        <div class="text-sm text-base-content/50">
                                            {{ __('ledger.file_inspector.history.no_system_logs') }}
                                        </div>
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            {{-- セクション2: ユーザーアクティビティ --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                        {{ __('ledger.file_inspector.history.activity') }}
                    </h3>
                    <span class="text-xs text-base-content/50">
                        {{ __('ledger.file_inspector.history.recent_30days') }}
                    </span>
                </div>

                <div class="relative">
                    <div class="space-y-2 overflow-y-auto max-h-64" style="scrollbar-width: thin;">
                        @forelse($file->user_timeline as $activity)
                            <div class="card card-compact bg-base-200 hover:bg-base-300 transition-colors">
                                <div class="card-body">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-{{ $activity['icon'] }} text-{{ $activity['color'] }}"></i>
                                            <span class="font-medium text-sm">{{ $activity['title'] }}</span>
                                        </div>
                                        <span class="text-xs text-base-content/60">
                                            {{ $activity['timestamp']->diffForHumans() }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-base-content/70 mt-1">
                                        <div class="flex items-center gap-1">
                                            <i class="fa-solid fa-user text-[10px]"></i>
                                            <span>{{ $activity['user'] }}</span>
                                        </div>
                                        
                                        {{-- 詳細情報（IP/UA）はツールチップで表示 --}}
                                        @if(isset($activity['properties']['ip_address']))
                                            <div class="tooltip" data-tip="IP: {{ $activity['properties']['ip_address'] }}">
                                                <i class="fa-solid fa-info-circle text-base-content/50 hover:text-primary cursor-help"></i>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="card card-compact bg-base-200">
                                <div class="card-body">
                                    <div class="text-sm text-base-content/50 text-center">
                                        {{ __('ledger.file_inspector.history.no_user_activity') }}
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-mary-tab>
```

##### ステップ2: 翻訳キー追加（メッセージ）

**ファイル**: `lang/ja/ledger.php`

```php
'file_inspector' => [
    'history' => [
        // ...既存のキー...
        'mock_notice' => 'モックデータのため、履歴情報は表示されません。',
        'no_system_logs' => 'システム処理ログはありません。',
        'no_user_activity' => 'ユーザーアクティビティはありません。',
    ],
    // ...existing code...
],
```

##### ステップ3: モックデータ対応（FileInspector）

**ファイル**: `app/Livewire/AttachedFile/FileInspector.php`

既存の `isMockFile()` メソッドをそのまま利用（変更不要）

#### チェックリスト
- [ ] History タブのBladeコードを動的データに置き換え
- [ ] システムログとユーザーアクティビティを分離表示
- [ ] 空データ時のメッセージ表示
- [ ] IP/UAをツールチップで表示
- [ ] モックデータ時の注意メッセージ表示
- [ ] レスポンシブデザイン確認（モバイル）
- [ ] `./vendor/bin/sail pint` でコード整形

#### 💡 Tips
- 既存のモックUIはコメントアウトして残しておく（参考用）
- DaisyUIの`steps`と`card`コンポーネントは既存のスタイルを維持

---

### 📦 タスク 4.4.4: 動作検証とテスト [0.5h]

#### 🎯 目的
実装した機能が正常に動作することを確認する。

#### テスト手順

##### 1. 単体テスト実行

```bash
# AttachedFileモデルのテスト
./vendor/bin/sail pest tests/Unit/Models/AttachedFileTest.php

# ActivityLogFormatterのテスト
./vendor/bin/sail pest tests/Unit/Helpers/ActivityLogFormatterTest.php
```

**期待結果**:
- ✅ すべてのテストがパス
- ✅ タイムライン生成が正常動作
- ✅ Formatterが正しい文字列を返す

##### 2. 統合テスト実行

```bash
# FileInspectorのテスト
./vendor/bin/sail pest tests/Feature/Livewire/FileInspectorTest.php
```

**テストコード例**:

```php
// tests/Feature/Livewire/FileInspectorTest.php
it('displays history tab with timeline', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $user = User::factory()->create(['name' => 'Test User']);
    $file = AttachedFile::factory()->create([
        'vlm_processed_at' => now()->subHours(2),
        'tika_processed_at' => now()->subHours(3),
    ]);
    
    // ダウンロード操作を実行
    $this->actingAs($user)
        ->get(route('attached-file.download', $file));
    
    // FileInspectorを開く
    Livewire::test(FileInspector::class)
        ->call('openInspector', $file->id)
        ->set('selectedTab', 'history')
        ->assertSee('VLM解析')
        ->assertSee('テキスト抽出')
        ->assertSee('ファイルをダウンロード')
        ->assertSee('Test User');
});
```

##### 3. 手動テスト

**シナリオ1: システムイベント表示確認**
1. 処理完了済みのファイルを選択
2. History タブを開く
3. ✅ アップロード→Tika→OCR→VLM の順序で表示される
4. ✅ 各イベントに正しい日時が表示される
5. ✅ アイコンと色が適切に表示される

**シナリオ2: ユーザーアクティビティ表示確認**
1. ファイルをダウンロード
2. History タブをリフレッシュ
3. ✅ 「ファイルをダウンロード」が追加される
4. ✅ ユーザー名が表示される
5. ✅ 「○分前」の相対時間が表示される

**シナリオ3: エラーケース表示確認**
1. VLM処理失敗のファイルを選択
2. History タブを開く
3. ✅ 「VLM処理失敗」がエラー色で表示される
4. ✅ 失敗日時が正しく表示される

**シナリオ4: 空データ表示確認**
1. アップロード直後のファイルを選択
2. History タブを開く
3. ✅ システムログに「アップロード」のみ表示
4. ✅ ユーザーアクティビティに「なし」メッセージ表示

**シナリオ5: モバイル表示確認**
1. ブラウザをモバイルサイズに変更
2. History タブを開く
3. ✅ スクロール可能
4. ✅ レイアウトが崩れない
5. ✅ タップ操作が可能

##### 4. 回帰テスト

```bash
# 既存の台帳詳細画面のテスト
./vendor/bin/sail pest tests/Feature/Ledger/LedgerDetailTest.php

# アクティビティログ表示のテスト
./vendor/bin/sail pest tests/Feature/ActivityHistoryTest.php
```

**期待結果**:
- ✅ 既存機能にエラーが発生しない
- ✅ ActivityHistoryDisplay が正常動作

#### チェックリスト
- [ ] 単体テスト: すべてパス
- [ ] 統合テスト: すべてパス
- [ ] 手動テスト: 5シナリオすべて成功
- [ ] 回帰テスト: エラーなし
- [ ] パフォーマンス: 50件表示が1秒以内
- [ ] モバイル: レイアウト正常

---

## 完了チェックリスト

### 実装完了基準

#### コード品質
- [ ] `./vendor/bin/sail pint` でコード整形済み
- [ ] `./vendor/bin/sail pest` ですべてのテストがパス
- [ ] `get_errors` でエラーなし
- [ ] コメントが適切に記載されている

#### 機能要件
- [ ] システムイベントが時系列順に表示される
- [ ] ユーザーアクティビティが時系列順に表示される
- [ ] ダウンロード操作が即座に履歴に反映される
- [ ] 「誰が」操作したかが明確に区別できる
- [ ] モックデータで適切なメッセージが表示される

#### 非機能要件
- [ ] 50件のログを1秒以内に表示できる
- [ ] N+1クエリが発生していない（Debugbarで確認）
- [ ] モバイル表示が正常
- [ ] テナント間でログが混在しない

#### ドキュメント
- [ ] 実装内容をコミットメッセージに記載
- [ ] 必要に応じて `docs/features/file-inspector.md` を更新
- [ ] 後続フェーズへの引き継ぎ事項を記録

---

## トラブルシューティング

### 問題1: タイムラインが表示されない

**症状**: History タブが空白、または「データなし」メッセージ

**原因**:
- `activities` リレーションが eager load されていない
- `system_timeline` アクセサーでエラー発生

**対策**:
```bash
# ログ確認
./vendor/bin/sail logs -f | grep FileInspector

# デバッグ
dd($file->system_timeline);
dd($file->user_timeline);
```

### 問題2: 「Unknown」と表示される

**症状**: ファイル名が「Unknown」になる

**原因**:
- `ActivityLogFormatter` の `AttachedFile` 分岐が未実装
- 翻訳キーが見つからない

**対策**:
```php
// デバッグ
dd(ActivityLogFormatter::getSubjectNameForDisplay($activity));

// 翻訳キー確認
dd(__('ledger.activity.model_name.attached_file'));
```

### 問題3: パフォーマンスが遅い

**症状**: History タブの表示に3秒以上かかる

**原因**:
- N+1クエリが発生している
- 大量のログを取得している

**対策**:
```bash
# Debugbarでクエリ数確認
# N+1が発生していたら eager load を追加

# ログ件数制限確認
dd($file->activities()->count()); // 50件以下か確認
```

### 問題4: テナント間でログが混在する

**症状**: 他テナントのログが表示される

**原因**:
- テナント初期化が不足
- `activities` の取得時にテナントフィルタが効いていない

**対策**:
```php
// テスト追加
it('does not show activities from other tenants', function () {
    // テストコードは「タスク 4.4.4」参照
});
```

### 問題5: モックデータで例外発生

**症状**: モックファイルで `activities` リレーションエラー

**原因**:
- `isMockFile()` の判定が機能していない
- Bladeで`activities`を直接参照している

**対策**:
```blade
@if($this->isMockFile())
    {{-- モック用メッセージ --}}
@else
    {{-- 実データ表示 --}}
@endif
```

---

## 参考資料

### 関連ファイル一覧

| ファイル | 役割 | 変更内容 |
|---------|------|---------|
| `app/Models/AttachedFile.php` | モデル | `system_timeline`, `user_timeline` 追加 |
| `app/Helpers/ActivityLogFormatter.php` | フォーマッター | `AttachedFile` 対応追加 |
| `lang/ja/ledger.php` | 翻訳 | History関連キー追加 |
| `resources/views/livewire/attached-file/file-inspector.blade.php` | UI | History タブ動的化 |
| `tests/Unit/Models/AttachedFileTest.php` | テスト | タイムライン生成テスト |
| `tests/Feature/Livewire/FileInspectorTest.php` | テスト | 統合テスト |

### コマンドリファレンス

```bash
# コード整形
./vendor/bin/sail pint

# 全テスト実行
./vendor/bin/sail pest

# 特定ファイルのテスト
./vendor/bin/sail pest tests/Unit/Models/AttachedFileTest.php

# ログ確認
./vendor/bin/sail logs -f

# データベース確認
./vendor/bin/sail mysql
> SELECT * FROM activity_log WHERE subject_type = 'App\\Models\\AttachedFile' LIMIT 10;
```

### 設計ドキュメント

- **Phase 4全体計画**: `docs/work/ui-ux/attachment/2025-12-20_phase4_detailed_plan.md`
- **Phase 4.4詳細計画**: `docs/work/ui-ux/attachment/2025-12-24_phase4-4_detailed_plan.md`
- **アクティビティログ設計**: `docs/architecture/activity-log.md`（未作成 → Phase 4.4完了後に作成）

### データ構造

**タイムラインイベント構造**:
```php
[
    'type' => 'system' | 'user',
    'icon' => 'o-paper-clip',  // FontAwesome icon name
    'color' => 'success' | 'error' | 'info' | 'warning' | 'neutral',
    'title' => 'VLM解析完了',
    'description' => '信頼度: 92.5% | 処理時間: 3.2秒',
    'timestamp' => Carbon,
    'user' => 'System' | User->name,
    'properties' => [], // ユーザーアクティビティのみ
]
```

**システムイベントマッピング**:

| タイムスタンプカラム | イベント名 | アイコン | 色 |
|------------------|----------|---------|-----|
| `created_at` | Uploaded | `o-paper-clip` | neutral |
| `tika_processed_at` | Text Extracted | `o-document-text` | info |
| `ocr_processed_at` | OCR Processed | `o-eye` | secondary |
| `vlm_processed_at` | VLM Analyzed | `o-cpu-chip` | primary |
| `vlm_failed_at` | VLM Failed | `o-exclamation-circle` | error |
| `ocr_failed_at` | OCR Failed | `o-exclamation-circle` | error |
| `processing_finalized_at` | Finalized | `o-check-circle` | success |

---

## リスクと対策

| リスク | 影響度 | 発生確率 | 対策 | 対応状況 |
|--------|--------|---------|------|---------|
| N+1クエリ発生 | 高 | 低 | eager load実装済み | ✅ 対策済み |
| 大量ログでメモリ不足 | 中 | 中 | 50件制限 | ✅ 対策済み |
| テナント間混在 | 高 | 低 | テストで確認 | ⚠️ 要確認 |
| モバイル表示崩れ | 低 | 中 | レスポンシブCSS | ⚠️ 要確認 |
| 翻訳漏れ | 低 | 中 | 翻訳キー一覧作成 | ✅ 対策済み |

---

## 次のステップ

### Phase 4.4完了後
1. ✅ 本ドキュメントの完了チェックリストを確認
2. ✅ コミット＆プッシュ
3. ✅ Phase 4.5（Actions Tab）の準備開始

### Phase 4.5への引き継ぎ
- 「再処理」アクション実行時にアクティビティログを記録する
- イベント名: `reprocessing_requested`
- History タブで再処理履歴が追跡可能になる

### ドキュメント更新タスク
- [ ] `docs/features/file-inspector.md`: スクリーンショット追加
- [ ] `docs/architecture/activity-log.md`: 設計書作成
- [ ] `docs/development/testing-guide.md`: テスト方法記載
- [ ] `README.md`: 機能一覧に追加

---

**最終更新**: 2025-12-24  
**作成者**: GitHub Copilot  
**レビュー**: Phase 4.4実装完了後

