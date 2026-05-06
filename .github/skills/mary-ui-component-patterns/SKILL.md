# Mary UI Component Patterns SKILL

LedgerLeap の Blade ビューで Mary UI コンポーネント（`x-mary-card`, `x-mary-modal`, `x-mary-header` 等）を標準的に使用するためのパターン集。
新規画面作成や既存画面のリファクタリング時に、コンポーネントの属性・スロットを最大限活用し、余分なラッパー HTML を削減することを目的とする。

## 1. Card Pattern (`x-mary-card`)

セクションや機能ブロックをグループ化する際の標準的なカード。

### 1.1 Basic Section Card
```blade
<x-mary-card separator shadow class="border border-base-300 overflow-hidden" body-class="p-6 md:p-8">
    <x-slot:title>
        <div class="flex items-center gap-2 text-primary">
            <x-mary-icon name="o-cog-6-tooth" />
            {{ __('ledger.define.basic_setting') }}
        </div>
    </x-slot:title>

    {{-- Card body content --}}
    <livewire:ledger-define.edit />
</x-mary-card>
```

### 1.2 Attribute Reference
| Attribute | Purpose |
|---|---|
| `separator` | タイトルと本文の間に区切り線を表示 |
| `shadow` | カードに影を付与（`shadow-xs` 相当） |
| `shadow="sm"` / `shadow="md"` / `shadow="lg"` | 影の大きさを調整 |
| `body-class="p-6 md:p-8"` | 本文エリアの padding を上書き |
| `class="border border-base-300 overflow-hidden"` | 枠線と角丸クリップを追加 |

### 1.3 Slot Reference
| Slot | Purpose |
|---|---|
| `<x-slot:title>` | カードヘッダー。Mary UI の組み込みスタイルが適用される |
| `<x-slot:menu>` | タイトル行の右端に配置される操作エリア |
| `<x-slot:actions>` | カード下部に配置されるアクションボタン群（separator 併用時に線が入る） |
| default slot | カード本文 |

### 1.4 Anti-Patterns to Avoid
- **[PROTECT]** カードタイトルを `<h2 class="card-title ...">` で自前構築しない。`<x-slot:title>` を使うことで Mary UI の一貫した余白・フォントサイズ・区切り線が得られる。
- **[PROTECT]** `body-class` でなく `<div class="card-body p-6">` でラッピングしない。不要な div を増やさない。
- **[CLEANUP]** `uppercase tracking-tighter font-black` 等の過剰な装飾をカードタイトルに与えない。Mary UI のデフォルトサイズ（text-xl font-bold）に任せ、必要に応じて色や gap のみ調整する。

## 2. Modal Pattern (`x-mary-modal`)

削除確認などのダイアログを Mary UI のモーダルで構築する。

### 2.1 Basic Delete Confirmation Modal
```blade
{{-- Trigger --}}
<x-mary-button label="{{ __('ledger.define.remove') }}" icon="o-trash"
               class="btn-outline btn-error"
               onclick="document.getElementById('delete-modal').showModal()" />

{{-- Modal --}}
<x-mary-modal id="delete-modal" title="{{ __('ledger.define.remove') }}" separator
              box-class="bg-error text-error-content">
    <p class="text-sm leading-relaxed">
        {{ __('ledger.define.remove_message') }}
        <br />
        {{ __('ledger.remove_records_message') }}
    </p>

    <x-slot:actions>
        @can('delete_ledger_defines')
            <form method="POST"
                  action="{{ route('ledgerDefine.delete', ['tenant' => tenant()?->id, 'ledgerDefineId' => $ledgerDefineRecord->id]) }}"
                  class="contents">
                @csrf
                @method('DELETE')
                <x-mary-button type="submit" label="{{ __('ledger.define.remove') }}" icon="o-trash"
                               class="btn-error" />
            </form>
            <x-mary-button label="{{ __('actions.cancel') }}" class="btn-outline"
                           onclick="document.getElementById('delete-modal').close()" />
        @else
            <span class="text-error text-sm">{{ __('ledger.define.no_permission_to_delete') }}</span>
        @endcan
    </x-slot:actions>
</x-mary-modal>
```

### 2.2 Attribute Reference
| Attribute | Purpose |
|---|---|
| `id` | モーダルの ID。トリガー側から `document.getElementById(id).showModal()` で開く |
| `title` | モーダルヘッダーに表示されるタイトル |
| `subtitle` | タイトル下に表示される補足テキスト |
| `separator` | タイトルと本文の間に区切り線を表示 |
| `box-class="bg-error text-error-content"` | モーダルボックスの背景色・文字色を上書き |
| `persistent` | `true` にすると ESC キーや backdrop クリックで閉じなくなる |

### 2.3 Slot Reference
| Slot | Purpose |
|---|---|
| default slot | モーダル本文（テキストやフォーム等） |
| `<x-slot:actions>` | モーダル下部のアクションボタン群。`<div class="modal-action">` として自動配置される |

### 2.4 Trigger Patterns
```blade
{{-- Button trigger (recommended) --}}
<x-mary-button label="削除" icon="o-trash"
               onclick="document.getElementById('delete-modal').showModal()" />

{{-- Link trigger --}}
<a href="#" onclick="event.preventDefault(); document.getElementById('delete-modal').showModal();">
    削除
</a>
```

### 2.5 Anti-Patterns to Avoid
- **[PROTECT]** DaisyUI の `.modal` + hidden checkbox (`<input type="checkbox" class="modal-toggle">`) で自前構築しない。アクセシビリティや backdrop 処理が Mary UI に任せられる。
- **[PROTECT]** モーダル内のフォームを actions スロットの外に出さない。actions スロットに入れることで `<div class="modal-action">` の適切なレイアウトが得られる。
- **[PROTECT]** キャンセルボタンに `<label for="delete-modal">` を使わない。Mary UI Modal はネイティブ `<dialog>` 要素を使用するため、`.close()` メソッドで閉じる。

## 3. Header Pattern (`x-mary-header`)

ページ最上部のタイトルブロック。

### 3.1 Basic Page Header
```blade
<x-slot name="header">
    <x-mary-header :title="__('ledger.define.edit_title')" subtitle="{{ $ledgerDefineRecord->title }}"
                   size="text-xl" separator progress-indicator
                   icon="o-pencil">
        <x-slot:actions>
            <x-mary-button label="{{ __('ledger.save') }}" icon="o-check" class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>
</x-slot>
```

### 3.2 Attribute Reference
| Attribute | Purpose |
|---|---|
| `:title` | メインタイトル |
| `subtitle` | タイトル下の補足テキスト |
| `size="text-xl"` | タイトルのフォントサイズ |
| `separator` | タイトル下に区切り線を表示 |
| `progress-indicator` | Livewire 通信時に上部にプログレスバーを表示 |
| `icon="o-pencil"` | タイトル左のアイコン（Mary UI アイコン名） |

### 3.3 Slot Reference
| Slot | Purpose |
|---|---|
| `<x-slot:actions>` | タイトル行の右端に配置されるアクションボタン群 |

## 4. Component Selection Hierarchy Reminder

ビューを構築する際の優先順位（`design.instructions.md` より引用）：

1. **Mary UI component** if it exists.
2. **daisyUI semantic class** if it expresses the role clearly.
3. **Tailwind utility adjustment** if layout or spacing needs tuning.
4. **Custom CSS only** if the first three options cannot express the need safely.

## 5. Evidence & Maintenance

- このスキルは `ledgerDefine/edit.blade.php` のリファクタリング（Issue #208）から抽出された。
- Mary UI の各コンポーネント API は `vendor/robsontenorio/mary/src/View/Components/` で確認可能。
- 新しい Mary UI コンポーネントの使用パターンが確立した場合は、このスキルに追加セクションとして追記すること。
