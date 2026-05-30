# Mary UI Card / Modal / Header Code Examples

## Card Pattern

```blade
<x-mary-card separator shadow class="border border-base-300 overflow-hidden" body-class="p-6 md:p-8">
    <x-slot:title>
        <div class="flex items-center gap-2 text-primary">
            <x-mary-icon name="o-cog-6-tooth" />
            {{ __('ledger.define.basic_setting') }}
        </div>
    </x-slot:title>
    <livewire:ledger-define.edit />
</x-mary-card>
```

| Attribute | Purpose |
|---|---|
| `separator` | Divider between title and body |
| `shadow` | Shadow (default `shadow-xs`) |
| `shadow="sm/md/lg"` | Shadow size |
| `body-class="p-6 md:p-8"` | Override body padding |
| `class="border border-base-300 overflow-hidden"` | Border + corner clip |

| Slot | Purpose |
|---|---|
| `<x-slot:title>` | Card header with Mary UI built-in styles |
| `<x-slot:menu>` | Action area at the right end of the title row |
| `<x-slot:actions>` | Action buttons at the bottom of the card |
| default slot | Card body |

## Modal Pattern

```blade
<x-mary-button label="{{ __('ledger.define.remove') }}" icon="o-trash"
               class="btn-outline btn-error"
               onclick="document.getElementById('delete-modal').showModal()" />

<x-mary-modal id="delete-modal" title="{{ __('ledger.define.remove') }}" separator
              box-class="bg-error text-error-content">
    <p class="text-sm leading-relaxed">
        {{ __('ledger.define.remove_message') }}
    </p>
    <x-slot:actions>
        <form method="POST" action="{{ route('ledgerDefine.delete', ...) }}" class="contents">
            @csrf @method('DELETE')
            <x-mary-button type="submit" label="{{ __('ledger.define.remove') }}" icon="o-trash" class="btn-error" />
        </form>
        <x-mary-button label="{{ __('actions.cancel') }}" class="btn-outline"
                       onclick="document.getElementById('delete-modal').close()" />
    </x-slot:actions>
</x-mary-modal>
```

| Attribute | Purpose |
|---|---|
| `id` | Modal ID; trigger opens via `document.getElementById(id).showModal()` |
| `title` | Modal header title |
| `subtitle` | Supplementary text under title |
| `separator` | Divider between title and body |
| `box-class` | Override modal box background/text color |
| `persistent` | `true` prevents closing with ESC or backdrop click |

## Header Pattern

```blade
<x-slot name="header">
    <x-mary-header :title="__('ledger.define.edit_title')" subtitle="{{ $ledgerDefineRecord->title }}"
                   size="text-xl" separator progress-indicator icon="o-pencil">
        <x-slot:actions>
            <x-mary-button label="{{ __('ledger.save') }}" icon="o-check" class="btn-primary" />
        </x-slot:actions>
    </x-mary-header>
</x-slot>
```

| Attribute | Purpose |
|---|---|
| `:title` | Main title |
| `subtitle` | Subtitle text |
| `size="text-xl"` | Title font size |
| `separator` | Divider under title |
| `progress-indicator` | Top progress bar during Livewire communication |
| `icon` | Icon left of title (Mary UI icon name) |
