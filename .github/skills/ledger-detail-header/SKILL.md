# Ledger Detail Header & State Management SKILL

台帳（Ledger）詳細・編集・新規作成画面における、統一されたヘッダーレイアウトと、コンテンツの動的な開閉状態管理（一括操作を含む）を実装するためのスキル。

## 1. Unified Header Structure for Ledger Pages

台帳画面の最上部には、ページ全体のコンテキストを定義する統一ヘッダーカードを配置する。詳細・編集・新規作成のいずれも同じベース構造を用い、画面種別に応じてタイトル表現とメタ情報を調整する。

- **Component**: `<x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">`
- **Background**: `bg-primary/30` を指定し、ブランドカラーを背景に敷くことで視覚的な重み（アンカー）を出す。
- **Common Slots**:
    - `title`: パンくずリスト（`<x-ledger.livewire-breadcrumbs>`）とメタ情報を同一行に集約。
    - `default`: 詳細な説明文やガイドライン。`x-collapse` を用いてデフォルトで折りたたみ可能にする。
    - `menu`: ページ全体に影響する操作（すべて展開トグル、エクスポート等）を配置（詳細画面のみ）。

### 1.1 Detail Page Header

詳細画面では、台帳タイトルを主軸に、バージョン・更新者・更新日時等のメタ情報を並列して配置する。

```blade
<x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">
    <x-slot:title>
        <div class="flex flex-col w-full">
            <div class="flex items-center gap-3 w-full">
                <div class="shrink-0 hidden md:block">
                    <x-mary-icon name="o-document-text" class="text-info w-15" />
                </div>
                <div class="flex flex-col min-w-0 w-full">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full mb-3">
                        <div class="min-w-0">
                            <x-ledger.livewire-breadcrumbs
                                :thisLedgerDefine="$ledgerDefineRecord"
                                :breadcrumbs="$breadcrumbs"
                                :isLivewire="false" />
                            <h2 class="text-xl md:text-2xl font-black tracking-tighter text-base-content truncate mt-2">
                                {{ $ledgerDefineRecord->title }}
                            </h2>
                        </div>

                        {{-- Metadata area --}}
                        <div class="flex flex-wrap items-center gap-3 text-sm md:text-base shrink-0 bg-base-200/60 p-1.5 rounded-lg border border-base-300">
                            <div class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-primary/10 border border-primary/20">
                                <span class="text-primary font-bold uppercase tracking-tighter text-sm md:text-base">{{ __('ledger.version') }}</span>
                                <span class="font-bold text-primary text-base md:text-lg">{{ $ledgerRecord->version }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-base-content/40 border-l border-base-300 pl-3">
                                <span class="text-xs md:text-sm font-medium text-base-content/50">{{ __('ledger.modified_by') }}:</span>
                                <x-ledger.user-card-popover :user="$ledgerRecord->modifier" />
                            </div>
                            <div class="flex items-center gap-1.5 text-base-content/40 border-l border-base-300 pl-3">
                                <span class="text-xs md:text-sm font-medium text-base-content/50">{{ __('ledger.updated_at') }}:</span>
                                <span class="text-sm md:text-base">{{ $ledgerRecord->updated_at->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:title>

    @if($ledgerDefineRecord->detail_description)
        <div class="mt-4 text-base-content" x-data="{ expanded: false }">
            <div class="bg-base-200/70 rounded-lg p-3 border border-base-300 transition-colors hover:bg-base-200/90">
                <div class="flex justify-between items-center cursor-pointer opacity-80 hover:opacity-100 transition-opacity" @click="expanded = !expanded">
                    <div class="font-bold text-base md:text-lg flex items-center gap-2">
                        <x-mary-icon name="o-information-circle" class="size-5 text-info" />
                        {{ __('ledger.description') }} / {{ __('ledger.guideline') }}
                    </div>
                    <span class="inline-flex transition-transform duration-300" :class="expanded ? 'rotate-180' : ''">
                        <x-mary-icon name="o-chevron-down" class="size-5" />
                    </span>
                </div>
                <div x-show="expanded" x-collapse>
                    <div class="pt-3 mt-2 border-t border-base-300">
                        @php
                            $descriptionHtml = app(App\Services\AutoLinkService::class)->convert(
                                app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->detail_description),
                                null,
                                $ledgerDefineRecord
                            );
                        @endphp
                        <div class="prose prose-sm md:prose-base text-sm md:text-base leading-relaxed max-w-none">
                            {!! $descriptionHtml !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <x-slot:menu>
        {{-- Global Expand/Collapse Toggle --}}
        <x-mary-toggle 
            x-model="$store.ledgerState.__global__"
            label="{{ __('ledger.column.expand_all') }}"
            class="toggle-sm toggle-primary text-sm font-black text-base-content/40 uppercase tracking-widest"
        />
    </x-slot:menu>
</x-mary-card>
```

### 1.2 Edit Page Header

編集画面では、**アクション（編集）と台帳タイトルを `<span class="divider divider-horizontal"></span>` で区切って1行に統合**する。メタ情報にはワークフローステータスと次のバージョンを表示し、編集対象のコンテキストを明確にする。

```blade
<x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">
    <x-slot:title>
        <div class="flex flex-col w-full">
            <div class="flex items-center gap-3 w-full">
                <div class="shrink-0 hidden md:block">
                    <x-mary-icon name="o-pencil-square" class="text-info w-15" />
                </div>
                <div class="flex flex-col min-w-0 w-full">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full mb-3">
                        <div class="min-w-0">
                            <x-ledger.livewire-breadcrumbs
                                :thisLedgerDefine="$ledgerDefineRecord"
                                :breadcrumbs="$breadcrumbs"
                                :isLivewire="false" />
                            <h2 class="flex text-xl md:text-2xl font-black tracking-tighter text-base-content truncate mt-2 space-x-4">
                                <span class="text-base-content/50">{{ __('ledger.editTitle') }}</span>
                                <span class="divider divider-horizontal"></span>
                                <span>{{ $ledgerDefineRecord->title }}</span>
                            </h2>
                        </div>

                        {{-- Metadata area: status + next version --}}
                        <div class="flex flex-wrap items-center gap-3 text-sm md:text-base shrink-0 bg-base-200/60 p-1.5 rounded-lg border border-base-300">
                            <div class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-warning/10 border border-warning/20">
                                <span class="font-bold text-warning text-base md:text-lg">{{ $ledger->status->label() ?? '-' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-base-content/40 border-l border-base-300 pl-3">
                                <span class="text-xs md:text-sm font-medium text-base-content/50">{{ __('ledger.version') }}:</span>
                                <span class="text-sm md:text-base">{{ $ledger->version+1 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:title>

    @if($ledgerDefineRecord->create_description)
        <div class="mt-4 text-base-content" x-data="{ expanded: false }">
            <div class="bg-base-200/70 rounded-lg p-3 border border-base-300 transition-colors hover:bg-base-200/90">
                <div class="flex justify-between items-center cursor-pointer opacity-80 hover:opacity-100 transition-opacity" @click="expanded = !expanded">
                    <div class="font-bold text-base md:text-lg flex items-center gap-2">
                        <x-mary-icon name="o-information-circle" class="size-5 text-info" />
                        {{ __('ledger.description') }} / {{ __('ledger.guideline') }}
                    </div>
                    <span class="inline-flex transition-transform duration-300" :class="expanded ? 'rotate-180' : ''">
                        <x-mary-icon name="o-chevron-down" class="size-5" />
                    </span>
                </div>
                <div x-show="expanded" x-collapse>
                    <div class="pt-3 mt-2 border-t border-base-300">
                        @php
                            $descriptionHtml = app(App\Services\AutoLinkService::class)->convert(
                                app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->create_description),
                                null,
                                $ledgerDefineRecord
                            );
                        @endphp
                        <div class="prose prose-sm md:prose-base text-sm md:text-base leading-relaxed max-w-none">
                            {!! $descriptionHtml !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-mary-card>
```

### 1.3 Create Page Header

新規作成画面では、**アクション（新規作成）と台帳タイトルを `<span class="divider divider-horizontal"></span>` で区切って1行に統合**する。メタ情報は最小限（または省略）とし、作成対象の台帳種別を明確にする。

```blade
<x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">
    <x-slot:title>
        <div class="flex flex-col w-full">
            <div class="flex items-center gap-3 w-full">
                <div class="shrink-0 hidden md:block">
                    <x-mary-icon name="o-plus-circle" class="text-warning w-15" />
                </div>
                <div class="flex flex-col min-w-0 w-full">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 w-full mb-3">
                        <div class="min-w-0">
                            <x-ledger.livewire-breadcrumbs
                                :thisLedgerDefine="$ledgerDefineRecord"
                                :breadcrumbs="$breadcrumbs"
                                :isLivewire="false" />
                            <h2 class="flex text-xl md:text-2xl font-black tracking-tighter text-base-content truncate mt-2 space-x-4">
                                <span class="text-base-content/50">{{ __('ledger.create') }}</span>
                                <span class="divider divider-horizontal"></span>
                                <span>{{ $ledgerDefineRecord->title }}</span>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot:title>

    @if($ledgerDefineRecord->create_description)
        <div class="mt-4 text-base-content" x-data="{ expanded: false }">
            <div class="bg-base-200/70 rounded-lg p-3 border border-base-300 transition-colors hover:bg-base-200/90">
                <div class="flex justify-between items-center cursor-pointer opacity-80 hover:opacity-100 transition-opacity" @click="expanded = !expanded">
                    <div class="font-bold text-base md:text-lg flex items-center gap-2">
                        <x-mary-icon name="o-information-circle" class="size-5 text-warning" />
                        {{ __('ledger.description') }} / {{ __('ledger.guideline') }}
                    </div>
                    <span class="inline-flex transition-transform duration-300" :class="expanded ? 'rotate-180' : ''">
                        <x-mary-icon name="o-chevron-down" class="size-5" />
                    </span>
                </div>
                <div x-show="expanded" x-collapse>
                    <div class="pt-3 mt-2 border-t border-base-300">
                        @php
                            $descriptionHtml = app(App\Services\AutoLinkService::class)->convert(
                                app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->create_description),
                                null,
                                $ledgerDefineRecord
                            );
                        @endphp
                        <div class="prose prose-sm md:prose-base text-sm md:text-base leading-relaxed max-w-none">
                            {!! $descriptionHtml !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-mary-card>
```

### 1.4 Title Expression Rules

台帳画面のタイトル表現は、画面種別に応じて以下のルールに従う：

| 画面 | 表現 | 例 |
|------|------|-----|
| 詳細 | 台帳タイトルのみ | `台帳A` |
| 編集 | `編集 — 台帳タイトル` | `編集 — 台帳A` |
| 新規作成 | `新規追加 — 台帳タイトル` | `新規追加 — 台帳A` |

- `h2` には `flex` クラスを付与し、横並びレイアウトを確保する。
- アクション部分（編集/新規追加）は `text-base-content/50` で淡く表示する。
- セパレーターは daisyUI の `<span class="divider divider-horizontal"></span>` を使用し、視覚的な区切りを自然にする。
- 台帳タイトルは通常の濃さで表示する。
- 下部に独立したアクションコンテキスト行（「編集中」「新規作成中」等の補足行）は設置しない。タイトル行に全て集約する。

### 1.5 Breadcrumbs in Edit/Create Pages

編集・新規作成画面でもパンくずリストを表示する。コントローラー側で `$breadcrumbs` を生成し、ビューに渡す必要がある。

```php
// ── パンくずリストの取得 ──────────────────────────────────────
$breadcrumbs = [];
if ($ledgerDefine && $ledgerDefine->folder_id) {
    $folder = \App\Models\Folder::with('ancestors')->find($ledgerDefine->folder_id);
    if ($folder) {
        $breadcrumbs = $folder->ancestors->all();
        $breadcrumbs[] = $folder;
    }
}

return View::make('ledger.edit', [
    'ledgerDefineRecord' => $ledgerRecord->define,
    'ledger' => $ledgerRecord,
    'breadcrumbs' => $breadcrumbs,
]);
```

## 2. Global State Management Pattern

台帳内の複数のグループ（列グループ等）の展開/折りたたみ状態を、一元的に管理・同期するパターン。

### Alpine.js Store (`ledgerState`)
- `ledgerState` ストアに各グループの ID をキーとした真偽値と、`__global__` フラグを持たせる。
- `__global__` が変更された際、すべての個別状態を `undefined` またはリセットし、グローバル設定に従わせる。

### Sub-component Reactivity (`checkStorage`)
- 個別のグループ（例: `LedgerDiffViewer`）は、自身のローカルな状態（`collapsed`）と共有ストアの `__global__` を `setInterval` 等のウォッチ機構で同期させる。これにより、ヘッダーのトグル操作が即座に配下の全コンポーネントに伝播する。

## 3. Mandatory Indicator Pattern

「必須」バッジなどのテキスト表現を避け、モダンなインジケータードットとツールチップに置き換える。

- **Implementation**: `indicator` クラスを持つラッパー内に、`indicator-item badge badge-error badge-xs` を配置。
- **Tooltip**: `tooltip` クラスを併用し、hover 時に「必須項目を含む」等の説明を表示。

### Code Example (Indicator)
```blade
<div class="indicator tooltip tooltip-right" data-tip="{{ __('ledger.diff.contains_required_items') }}">
    <span class="indicator-item badge badge-error badge-xs p-0 w-2 h-2 border-none"></span>
    <x-mary-icon name="o-folder-open" class="text-primary/70" />
</div>
```

## 4. Anti-Patterns to Avoid

- **[PROTECT]**: ヘッダーカードの背景色 `bg-primary/30` を `bg-base-100` 等に戻さない。ブランドの一貫性を保つため。
- **[CLEANUP]**: ヘッダーを `x-collapse` で包む場合、古いアコーディオンコンポーネント（`<x-expandable-content>` 等）を内部に残したままにせず、プレーンな出力を心掛ける。
- **[SYNCHRONIZATION]**: Alpine.js の反映を「リロード待ち」にしない。必ずリアクティブな同期ロジック（`checkStorage` 等）を実装すること。
- **[FOOTER REVIEW]**: 詳細画面のヘッダーを見直すときは、同じ画面の persistent footer / action bar も合わせて確認し、badge-first の状態表示や tooltip への説明逃がしを見落とさないこと。
- **[LEGACY HEADER]**: 編集・新規作成画面で旧式の `<x-slot name="header">` + `ttl_3d5 warn` + `bg-warning/40` パターンを使わない。詳細画面と同じ `<x-mary-card class="bg-primary/30">` ベースのヘッダーに統一する。
- **[REDUNDANT CONTEXT]**: タイトル行の下に独立した「編集中」「新規作成中」等の補足行を追加しない。アクション情報はタイトル行内の `text-base-content/50` で表現し、1行に集約する。

## 5. Freshness

- status: confirmed
- last_confirmed_at: 2026-05-06
- recheck_after: 90d
- recheck_trigger:
  - a new ledger page type appears (e.g., duplicate, bulk-edit)
  - the title expression rules change
  - Mary UI or daisyUI card/icon component guidance changes upstream
