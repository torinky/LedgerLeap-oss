<x-app-layout title="{{ __('ledger.define.edit_title') }}" class="bg-warning/30">
    @push('scripts')
        @vite(['resources/js/ledgerDefineEdit.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerDefineEdit.scss'])
    @endpush

    <div class="container max-w-full px-0 md:px-4 mt-4">
        {{-- Unified header card --}}
        <x-mary-card shadow class="bg-base-100/30 border border-base-300 mb-6">
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
                                        <span class="text-base-content/50">{{ __('ledger.define.edit_title') }}</span>
                                        <span class="divider divider-horizontal"></span>
                                        <span>{{ $ledgerDefineRecord->title }}</span>
                                    </h2>
                                </div>

                                {{-- Metadata area: version --}}
                                <div class="flex flex-wrap items-center gap-3 text-sm md:text-base shrink-0 bg-base-200/60 p-1.5 rounded-lg border border-base-300">
                                    <div class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-primary/10 border border-primary/20">
                                        <span class="text-primary font-bold uppercase tracking-tighter text-sm md:text-base">{{ __('ledger.version') }}</span>
                                        <span class="font-bold text-primary text-base md:text-lg">{{ $ledgerDefineRecord->version }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-base-content/30">
                                        <span class="text-xs md:text-sm font-medium text-base-content/50">{{ __('ledger.modified_by') }}:</span>
                                        <x-mary-icon name="o-user" class="size-5 text-base-content/40" />
                                        <x-ledger.user-card-popover :user="$ledgerDefineRecord->modifier" />
                                    </div>
                                    <div class="flex items-center gap-1.5 text-base-content/40 border-l border-base-300 pl-3">
                                        <span class="text-xs md:text-sm font-medium text-base-content/50">{{ __('ledger.last_updated_at') }}:</span>
                                        <x-mary-icon name="o-calendar" class="size-5" />
                                        <span class="text-sm md:text-base">{{ $ledgerDefineRecord->updated_at->format('Y-m-d H:i') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-slot:title>
        </x-mary-card>
        @if ($ledgerDefineRecord)
            <div class="grid grid-cols-1 gap-6 items-start mb-20">

                {{-- 上段: 基本設定 (常に表示) --}}
                <x-mary-card separator shadow class="border border-base-300 overflow-hidden" body-class="p-6 md:p-8">
                    <x-slot:title>
                        <div class="flex items-center gap-2 text-neutral">
                            <x-mary-icon name="o-cog-6-tooth" />
                            {{ __('ledger.define.basic_setting') }}
                        </div>
                    </x-slot:title>
                    <livewire:ledger-define.edit />
                </x-mary-card>

                @if ($ledgerDefineRecord->column_define)
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                        {{-- 下段左: 項目設定 --}}
                        <x-mary-card separator shadow class="border border-base-300 overflow-hidden h-fit" body-class="p-4 md:p-6">
                            <x-slot:title>
                                <div class="flex items-center gap-2 text-neutral">
                                    <x-mary-icon name="o-queue-list" />
                                    {{ __('ledger.column.group_title') }}
                                </div>
                            </x-slot:title>
                            <livewire:ledger-define.modify-column />
                        </x-mary-card>

                        {{-- 下段右: プレビュー (追従) --}}
                        <x-mary-card separator shadow class="border border-base-300 overflow-hidden lg:sticky lg:top-24 h-fit" body-class="p-4 md:p-6">
                            <x-slot:title>
                                <div class="flex items-center gap-2 text-neutral">
                                    <x-mary-icon name="o-magnifying-glass" />
                                    {{ __('ledger.define.preview') }}
                                </div>
                            </x-slot:title>
                            <livewire:ledger-define.preview />
                        </x-mary-card>
                    </div>
                @endif

            </div>

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
        @endif
    </div>
</x-app-layout>
