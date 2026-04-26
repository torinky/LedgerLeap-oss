# Ledger Detail Header & State Management SKILL

台帳（Ledger）詳細画面における、統一されたヘッダーレイアウトと、コンテンツの動的な開閉状態管理（一括操作を含む）を実装するためのスキル。

## 1. Unified Detail Header Structure

詳細画面の最上部には、ページ全体のコンテキストを定義する統一ヘッダーカードを配置する。

- **Component**: `<x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">`
- **Background**: `bg-primary/30` を指定し、ブランドカラーを背景に敷くことで視覚的な重み（アンカー）を出す。
- **Slots**:
    - `title`: パンくずリスト（`<x-mary-breadcrumbs>`）とメタ情報（バージョン、更新者等）を同一行に集約。
    - `default`: 詳細な説明文やガイドライン。`x-collapse` を用いてデフォルトで折りたたみ可能にする。
    - `menu`: ページ全体に影響する操作（すべて展開トグル、エクスポート等）を配置。

### Code Example (Header)
```blade
<x-mary-card shadow class="bg-primary/30 border border-base-300 mb-6">
    <x-slot:title>
        <div class="flex items-center justify-between gap-4">
            <x-mary-breadcrumbs :items="$breadcrumbs" class="p-0" />
            <div class="flex items-center gap-4">
                {{-- Meta Info Capsule --}}
                <div class="flex items-center gap-1 bg-base-100/50 px-2 py-0.5 rounded-full border border-base-300">
                    <span class="text-xs font-black opacity-40 uppercase tracking-tighter">Ver</span>
                    <span class="text-sm font-bold text-primary">{{ $ledger->version }}</span>
                </div>
                {{-- Secondary Meta --}}
                <div class="text-xs text-base-content/30 italic">
                    {{ __('ledger.show.updated_at', ['time' => $ledger->updated_at->diffForHumans(), 'user' => $ledger->updater->name]) }}
                </div>
            </div>
        </div>
    </x-slot:title>

    {{-- Progressive Disclosure Area --}}
    <div x-data="{ expanded: false }" class="mt-2">
        <div @click="expanded = !expanded" class="flex items-center gap-1 cursor-pointer select-none text-xs text-primary/70 hover:text-primary transition-colors font-bold uppercase tracking-widest">
            <x-mary-icon :name="expanded ? 'o-chevron-up' : 'o-chevron-down'" class="w-3 h-3" />
            {{ __('ledger.show.guidelines') }}
        </div>
        <div x-show="expanded" x-collapse x-cloak class="mt-4 p-4 bg-base-100/40 rounded-xl border border-primary/10 text-sm leading-relaxed">
            {!! nl2br(e($ledger->description)) !!}
        </div>
    </div>

    <x-slot:menu>
        {{-- Global Expand/Collapse Toggle --}}
        <x-mary-toggle 
            x-model="$store.ledgerState.__global__"
            label="{{ __('ledger.column.expand_all') }}"
            class="toggle-xs toggle-primary text-[10px] font-black text-base-content/40 uppercase tracking-widest"
        />
    </x-slot:menu>
</x-mary-card>
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
