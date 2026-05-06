<x-app-layout title="{{ __('ledger.define.edit_title') }}" class="bg-warning/30">
    @push('scripts')
        @vite(['resources/js/ledgerDefineEdit.js'])
    @endpush
    @push('stylesheets')
        @vite(['resources/sass/ledgerDefineEdit.scss'])
    @endpush

    <x-slot name="header">
        <x-mary-header :title="__('ledger.define.edit_title')" subtitle="{{$ledgerDefineRecord->title}}"
                       size="text-xl" separator progress-indicator
                       icon="o-pencil"
        >
        </x-mary-header>

{{--
        <x-mary-card shadow class="!bg-warning/30 border border-warning/20 !p-4">
            <x-slot:title>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="relative inline-flex items-center justify-center w-8 h-8">
                        <i class="fa-solid fa-book text-2xl text-warning-content/80"></i>
                        <i class="fa-solid fa-pencil text-base text-warning-content/90 absolute -top-1 -right-1 drop-shadow-sm"></i>
                    </span>
                    <div>
                        <div class="font-bold text-lg md:text-xl text-warning-content/80">
                            {{ __('ledger.define.edit_title') }}
                        </div>
                        <div class="text-warning-content/50 text-sm flex items-center gap-1.5">
                            <i class="fas fa-book-open"></i>
                            <span>{{ $ledgerDefineRecord->title }}</span>
                        </div>
                    </div>
                </div>
            </x-slot:title>
        </x-mary-card>
--}}
    </x-slot>

    <div class="mx-auto px-4 py-0 max-w-400">
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
