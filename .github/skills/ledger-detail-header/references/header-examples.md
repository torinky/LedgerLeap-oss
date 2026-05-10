# Ledger Detail / Edit / Create Header Examples

## Detail Page Header

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
        <x-mary-toggle 
            x-model="$store.ledgerState.__global__"
            label="{{ __('ledger.column.expand_all') }}"
            class="toggle-sm toggle-primary text-sm font-black text-base-content/40 uppercase tracking-widest"
        />
    </x-slot:menu>
</x-mary-card>
```

## Edit Page Header

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

## Create Page Header

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

## Breadcrumbs Controller Snippet

```php
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
