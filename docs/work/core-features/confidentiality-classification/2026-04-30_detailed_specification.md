# 秘密区分表示機能 詳細仕様書

**作成日:** 2026-04-29
**最終更新:** 2026-04-30 (Rev.4)
**対象機能:** フォルダ・台帳ごとの「秘密区分」表示機能

> 📄 **基本仕様・設計判断・機能概要はこちら**: [秘密区分表示機能 基本仕様書](./2026-04-30_basic_specification.md)

---

## 1. データモデル設計指針

### 1.1 マイグレーション（追加カラム）

```php
// フォルダテーブル
Schema::table('folders', function (Blueprint $table) {
    $table->string('confidentiality_level')->nullable()->after('title');
    $table->json('confidentiality_scopes')->nullable()->after('confidentiality_level');
});

// 台帳定義テーブル
Schema::table('ledger_defines', function (Blueprint $table) {
    $table->string('confidentiality_level')->nullable()->after('title');
    $table->json('confidentiality_scopes')->nullable()->after('confidentiality_level');
});
```

- `ledgers` テーブル（Mroonga）への変更は**不要**（秘密区分は台帳定義層で管理）。
- `nullable()` を必須とし、既存データへの互換性を保証する。
- `confidentiality_scopes` はJSON配列として保存し、複数の組織名・ロール名を保持する。

### 1.2 モデル変更

**`Folder` モデル** – `$fillable` に追加:
```php
protected $fillable = [
    'title', 'modifier_id', 'creator_id', 'parent_id', 'tenant_id',
    'confidentiality_level',
    'confidentiality_scopes',
];
protected $casts = [
    'confidentiality_level' => 'string',
    'confidentiality_scopes' => 'array',
];
```

**`LedgerDefine` モデル** – `$fillable` に追加:
```php
protected $fillable = [
    // ...existing...
    'confidentiality_level',
    'confidentiality_scopes',
];
protected $casts = [
    // ...existing...
    'confidentiality_level' => 'string',
    'confidentiality_scopes' => 'array',
];
```

- `LogsActivity` の `logFillable()` が既に適用済み。`$fillable` に追加するだけで変更ログが自動記録される。
- `confidentiality_level` が `null` の既存レコードはスタンプを非表示とする。
- `confidentiality_scopes` はラベル表現であり、権限システムとの連携はMVPでは行わない。

---

## 2. サービス設計指針

`App\Services\ConfidentialityLevelService` を新規作成し、設定ファイルへのアクセスと解決ロジックを集約する。

```php
namespace App\Services;

class ConfidentialityLevelService
{
    /** 設定ファイルから全秘密区分定義を取得 */
    public static function allLevels(): array
    {
        return config('confidentiality.levels', []);
    }

    /** コード値からラベルを取得（未定義時はコード値を返す） */
    public static function label(string $code): string
    {
        return config("confidentiality.levels.{$code}.label") ?? $code;
    }

    /** セレクトボックス用オプション配列 */
    public static function selectOptions(): array
    {
        return collect(self::allLevels())->map(fn ($cfg, $code) => [
            'id'   => $code,
            'name' => $cfg['label'] ?? $code,
        ])->values()->all();
    }

    /** 設定ファイルから全公開範囲定義を取得 */
    public static function allScopes(): array
    {
        return config('confidentiality.scopes', []);
    }

    /** 公開範囲コード配列からラベル配列を取得 */
    public static function scopeLabels(?array $codes): array
    {
        if (empty($codes)) return [];
        return collect($codes)->map(
            fn ($code) => config("confidentiality.scopes.{$code}.label") ?? $code
        )->all();
    }

    /** フォルダ・台帳定義の秘密区分を解決（継承・上書き） */
    public static function resolve($model): ?array
    {
        // モデルが台帳定義の場合
        if ($model instanceof \App\Models\LedgerDefine) {
            if ($model->confidentiality_level) {
                return [
                    'level'  => $model->confidentiality_level,
                    'scopes' => $model->confidentiality_scopes,
                    'source' => [
                        'type' => 'ledger_define',
                        'name' => $model->title,
                        'id'   => $model->id,
                    ],
                ];
            }
            $folder = $model->folder;
            while ($folder) {
                if ($folder->confidentiality_level) {
                    return [
                        'level'  => $folder->confidentiality_level,
                        'scopes' => $folder->confidentiality_scopes,
                        'source' => [
                            'type' => 'folder',
                            'name' => $folder->title,
                            'id'   => $folder->id,
                        ],
                    ];
                }
                $folder = $folder->parent;
            }
            return null;
        }

        // モデルがフォルダの場合
        if ($model instanceof \App\Models\Folder) {
            $folder = $model;
            while ($folder) {
                if ($folder->confidentiality_level) {
                    return [
                        'level'  => $folder->confidentiality_level,
                        'scopes' => $folder->confidentiality_scopes,
                        'source' => [
                            'type' => 'folder',
                            'name' => $folder->title,
                            'id'   => $folder->id,
                        ],
                    ];
                }
                $folder = $folder->parent;
            }
            return null;
        }

        return null;
    }
}
```

---

## 3. 翻訳キー体系

`lang/ja/ledger/` に新ファイル `confidentiality.php` を追加する（既存ファイルと分割管理）。

```php
// lang/ja/ledger/confidentiality.php
return [
    'confidentiality' => [
        'level' => [
            'public'               => '公開',
            'internal'             => '社内制限',
            'confidential'         => '社外秘',
            'strictly_confidential' => '極秘',
        ],
        'description' => [
            'public'               => '制限なく公開可能な情報',
            'internal'             => '社内に限り共有可能な情報',
            'confidential'         => '社外への共有禁止の情報',
            'strictly_confidential' => '厳重に管理が必要な極秘情報',
        ],
        'scope' => [
            'all_employees'      => '全社員',
            'hr_department'      => '人事部',
            'executives'         => '経営層',
        ],
        'form' => [
            'level_label'       => '秘密区分',
            'level_placeholder' => '秘密区分を選択（任意）',
            'level_helper'      => '未選択の場合は親フォルダの設定を継承します',
            'scope_label'       => '公開範囲',
            'scope_placeholder' => '公開範囲を選択（任意）',
            'scope_helper'      => 'あくまでラベル表現です。権限管理とは連動しません',
        ],
        'stamp' => [
            'separator' => '・',
            'fallback'  => '未定義の秘密区分',
        ],
        'tooltip' => [
            'source_label'     => '設定元',
            'ledger_define'    => '台帳定義「:name」',
            'folder'           => 'フォルダ「:name」',
            'inherited_from'   => '（:name から継承）',
            'edit_settings'    => '設定を変更',
        ],
    ],
];
```

翻訳キー追加後は `artisan translations:compare --force` で同期する。

---

## 4. 実装コンポーネントマッピング

| 修正対象ファイル | 変更内容 |
|----------------|---------|
| `config/confidentiality.php` | 秘密区分・公開範囲の設定ファイル新規作成 |
| `database/migrations/xxxx_add_confidentiality_level.php` | `folders`, `ledger_defines` への `confidentiality_level` / `confidentiality_scopes` カラム追加 |
| `app/Services/ConfidentialityLevelService.php` | 設定ファイルアクセス・解決ロジック・由来情報構築の新規作成 |
| `app/Models/Folder.php` | `$fillable`, `$casts` に追加 |
| `app/Models/LedgerDefine.php` | `$fillable`, `$casts` に追加 |
| `app/Livewire/Folder/FolderForm.php` | 秘密区分・公開範囲の保存処理 |
| `app/Livewire/LedgerDefine/Create.php` | 同上 |
| `app/Livewire/LedgerDefine/Edit.php` | 同上 |
| `app/Livewire/Ledger/IndexManager.php` | スクロール連動の `updateActiveConfidentiality()` メソッド追加 |
| `resources/views/components/confidentiality-stamp.blade.php` | 統一スタンプコンポーネント（ツールチップ・保守動線内包）新規作成 |
| `resources/views/layouts/app.blade.php` | 右上オーバーレイスタンプの配置 |
| `resources/views/livewire/folder/folder-form.blade.php` | `x-mary-select`（秘密区分）+ `x-mary-choices`（公開範囲）の追加 |
| `resources/views/livewire/ledger-define/create.blade.php` | 同上 |
| `resources/views/livewire/ledger-define/edit.blade.php` | 同上 |
| `resources/views/components/folder/tree.blade.php` | フォルダ選択時のスタンプ更新トリガー（イベント発火） |
| `resources/views/components/ledger/table-row.blade.php` | 行選択時のスタンプ更新トリガー（イベント発火） |
| `resources/views/livewire/ledger/index.blade.php` | 台帳定義セクションに `data-ledger-define-id` 属性・Intersection Observer バインディング追加 |
| `resources/views/livewire/ledger/show.blade.php` | 詳細画面でのスタンプデータ解決・表示 |
| `resources/js/confidentiality-scroll-tracker.js` | Intersection Observer によるスクロール連動スクリプト新規作成 |
| `lang/ja/ledger/confidentiality.php` | 新規作成 |

---

## 5. スクロール連動スタンプ表示の実装詳細

### 5.1 Alpine.js + Intersection Observer

```javascript
// resources/js/confidentiality-scroll-tracker.js
function confidentialityScrollTracker() {
    return {
        activeLedgerDefineId: null,
        observers: [],

        init() {
            const sections = document.querySelectorAll('[data-ledger-define-section]');

            const observer = new IntersectionObserver(
                (entries) => {
                    let maxRatio = 0;
                    let activeId = null;

                    entries.forEach((entry) => {
                        if (entry.intersectionRatio > maxRatio) {
                            maxRatio = entry.intersectionRatio;
                            activeId = entry.target.dataset.ledgerDefineId;
                        }
                    });

                    if (activeId && activeId !== this.activeLedgerDefineId) {
                        this.activeLedgerDefineId = activeId;
                        this.$wire.updateActiveConfidentiality(activeId);
                    }
                },
                {
                    root: null,
                    threshold: [0, 0.25, 0.5, 0.75, 1.0],
                }
            );

            sections.forEach((section) => observer.observe(section));
            this.observers.push(observer);
        },

        destroy() {
            this.observers.forEach((obs) => obs.disconnect());
        },
    };
}
```

### 5.2 Livewire 側のメソッド

```php
// app/Livewire/Ledger/IndexManager.php
class IndexManager extends Component
{
    public ?string $activeConfidentialityLevel = null;
    public ?array $activeConfidentialityScopes = null;
    public ?array $activeConfidentialitySource = null;

    public function updateActiveConfidentiality(int $ledgerDefineId): void
    {
        $ledgerDefine = LedgerDefine::find($ledgerDefineId);
        if (!$ledgerDefine) {
            $this->resetConfidentiality();
            return;
        }

        $resolved = ConfidentialityLevelService::resolve($ledgerDefine);
        if ($resolved) {
            $this->activeConfidentialityLevel = $resolved['level'];
            $this->activeConfidentialityScopes = $resolved['scopes'];
            $this->activeConfidentialitySource = $resolved['source'];
        } else {
            $this->resetConfidentiality();
        }
    }

    private function resetConfidentiality(): void
    {
        $this->activeConfidentialityLevel = null;
        $this->activeConfidentialityScopes = null;
        $this->activeConfidentialitySource = null;
    }
}
```

---

## 6. スタンプコンポーネントの実装詳細

```blade
{{-- resources/views/components/confidentiality-stamp.blade.php --}}
@props(['level', 'scopes' => null, 'source' => null])

@php
$levelLabel = \App\Services\ConfidentialityLevelService::label($level);
$scopeLabels = $scopes ? \App\Services\ConfidentialityLevelService::scopeLabels($scopes) : [];
$displayText = $levelLabel;
if (!empty($scopeLabels)) {
    $displayText .= '・' . implode('、', $scopeLabels);
}
@endphp

<div class="confidentiality-stamp-wrapper" x-data="{ open: false }">
    <div
        class="confidentiality-stamp"
        @mouseenter="open = true"
        @mouseleave="open = false"
    >
        <div class="stamp-border">
            <span class="stamp-level">{{ $levelLabel }}</span>
            @if(!empty($scopeLabels))
                <span class="stamp-separator">・</span>
                <span class="stamp-scope">{{ implode('、', $scopeLabels) }}</span>
            @endif
        </div>
    </div>

    {{-- ツールチップ --}}
    <div
        x-show="open"
        x-cloak
        x-transition
        class="confidentiality-tooltip"
    >
        <div class="tooltip-header">
            <span class="tooltip-label">{{ __('ledger.confidentiality.tooltip.source_label') }}</span>
        </div>
        <div class="tooltip-body">
            @if($source)
                <p class="tooltip-source">
                    @if($source['type'] === 'ledger_define')
                        {{ __('ledger.confidentiality.tooltip.ledger_define', ['name' => $source['name']]) }}
                    @else
                        {{ __('ledger.confidentiality.tooltip.folder', ['name' => $source['name']]) }}
                    @endif
                </p>
            @endif
        </div>

        {{-- 保守動線 --}}
        @if($source && isset($source['id'], $source['type']))
            @php
                $editRoute = $source['type'] === 'ledger_define'
                    ? route('ledger-define.edit', ['id' => $source['id']])
                    : route('folder.edit', ['id' => $source['id']]);
                $canEdit = $source['type'] === 'ledger_define'
                    ? Gate::allows('edit', \App\Models\LedgerDefine::find($source['id']))
                    : Gate::allows('edit', \App\Models\Folder::find($source['id']));
            @endphp

            @if($canEdit)
                <div class="tooltip-actions">
                    <a
                        href="{{ $editRoute }}#confidentiality-section"
                        class="tooltip-edit-link"
                        wire:navigate
                    >
                        <x-mary-icon name="o-pencil-square" class="w-4 h-4" />
                        {{ __('ledger.confidentiality.tooltip.edit_settings') }}
                    </a>
                </div>
            @endif
        @endif
    </div>
</div>
```

```css
/* 統一スタンプスタイル */
.confidentiality-stamp {
    display: inline-flex;
    align-items: center;
}
.stamp-border {
    border: 3px solid #dc2626; /* red-600 */
    padding: 0.5rem 1rem;
    font-weight: 700;
    font-size: 1.125rem; /* text-lg */
    color: #dc2626;
    background-color: transparent;
    white-space: nowrap;
}
.stamp-level {
    letter-spacing: 0.05em;
}
.stamp-separator {
    margin: 0 0.25rem;
}
.stamp-scope {
    font-size: 0.875rem; /* text-sm */
}

/* オーバーレイ配置 */
.confidentiality-overlay {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 50;
    pointer-events: auto; /* ツールチップ・保守動線のクリックを受け付ける */
}

/* ツールチップ */
.confidentiality-tooltip {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    z-index: 60;
}
.tooltip-header {
    font-size: 0.75rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}
.tooltip-body {
    font-size: 0.875rem;
    color: #1f2937;
    margin-bottom: 0.5rem;
}
.tooltip-actions {
    border-top: 1px solid #e5e7eb;
    padding-top: 0.5rem;
    margin-top: 0.5rem;
}
.tooltip-edit-link {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.875rem;
    color: #dc2626;
    text-decoration: none;
}
.tooltip-edit-link:hover {
    text-decoration: underline;
}
```

---

## 7. 設定ファイルサンプル

```php
// config/confidentiality.php
return [
    'levels' => [
        'public' => [
            'label'       => __('ledger.confidentiality.level.public'),
            'description' => __('ledger.confidentiality.description.public'),
        ],
        'internal' => [
            'label'       => __('ledger.confidentiality.level.internal'),
            'description' => __('ledger.confidentiality.description.internal'),
        ],
        'confidential' => [
            'label'       => __('ledger.confidentiality.level.confidential'),
            'description' => __('ledger.confidentiality.description.confidential'),
        ],
        'strictly_confidential' => [
            'label'       => __('ledger.confidentiality.level.strictly_confidential'),
            'description' => __('ledger.confidentiality.description.strictly_confidential'),
        ],
    ],

    'scopes' => [
        'all_employees'      => ['label' => '全社員'],
        'hr_department'      => ['label' => '人事部'],
        'executives'         => ['label' => '経営層'],
        'project_nmrr'       => ['label' => 'NMR共同研究プロジェクト'],
        'partner_a'          => ['label' => 'A社'],
    ],
];
```

---

## 8. インポート機能との整合性

- 秘密区分は台帳定義（LedgerDefine）に保存するため、CSVインポート（`LedgerImport`）で作成される台帳レコード（Ledger）には直接影響しない。
- フォルダ/台帳定義のCSVインポート機能が将来追加される場合は、`confidentiality_level` / `confidentiality_scopes` カラムの対応を別途検討する。

---

## 9. MCP ツールへの影響範囲

- **MVPでは MCP ツールへの変更は行わない**。
- `GetLedgerDetailTool`, `SearchLedgersTool` 等が返すレスポンスへの秘密区分情報の追加は Phase 2 で検討。
- 理由: 秘密区分情報の MCP 経由公開はクライアント向け契約の変更を伴い、`client-facing-contract-maintenance` エージェントによる別途レビューが必要。

---

## 10. 改訂履歴

| 日付 | 版 | 変更内容 |
|------|-----|---------|
| 2026-04-29 | 1.0 | 初版作成。データモデル・Enum設計・翻訳キー・実装マッピングを記載。 |
| 2026-04-30 | 2.0 | 設定ファイルベースへ変更。サービス設計指針を追加。スタンプコンポーネント実装詳細を追加。 |
| 2026-04-30 | 3.0 | スクロール連動（Intersection Observer）の実装詳細を追加。ツールチップ・保守動線の実装詳細を追加。 |
| 2026-04-30 | 3.1 | **ドキュメント分割**。基本仕様書と詳細仕様書に分離。データモデル・サービスコード・翻訳キー・実装マッピング・実装詳細を本ドキュメントに集約。 |

