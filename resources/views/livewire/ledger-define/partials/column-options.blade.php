@props(['column', 'index', 'columnUploadedFile'])

{{-- File Upload Section --}}
@if(isset($column['file']['path']))
    <a href="{{ asset('storage/'.$column['file']['path']) }}" target="_blank">
        <img src="{{ asset('storage/thumbnails/'.$column['file']['path']) }}" alt="{{ $column['file']['name'] }}">
    </a>
    <label for="delete-file-modal-{{$column['id']}}" class="btn btn-sm tooltip"
           data-tip="{{__('ledger.column.delete_file')}}">
        <i class="fa-solid fa-trash"></i>
    </label>
    <div x-data>
        <template x-teleport="body">
            <div>
                {{-- 背景画像削除確認モーダル --}}
                <input type="checkbox" id="delete-file-modal-{{$column['id']}}" class="modal-toggle hidden"/>
                <div class="modal !z-[9999]" role="dialog">
                    <div class="modal-box">
                        <h3 class="font-bold text-lg">{{__('ledger.column.delete_file')}}</h3>
                        <p class="py-4">{{__('ledger.column.delete_file_message', ['name' => $column['name']])}}</p>
                        <div class="modal-action">
                            <label for="delete-file-modal-{{$column['id']}}"
                                   wire:click.prevent="deleteFile({{$column['id']}})"
                                   class="btn btn-error">{{__('ledger.delete')}}</label>
                            <label for="delete-file-modal-{{$column['id']}}" class="btn">{{__('ledger.cancel')}}</label>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
@else
    <x-mary-file label="{{__('ledger.column.bg_file')}}"
                 wire:model.live="columnUploadedFile.{{$column['id']}}"
                 class="input-accent" wire:key="file-{{$column['id']}}"
                 hint="png, jpg, jpeg, gif, svg"/>
@endif

{{-- Type-specific Options --}}
@if($column['useOptions'])
    @if($column['type'] === 'auto_number')
        <x-mary-input label="{{__('ledger.column.auto_number.prefix')}}"
                      wire:model.live="columns.{{$index}}.options.prefix"
                      wire:key="prefix-{{$column['id']}}"
                      hint="{{__('ledger.column.auto_number.prefix_hint')}}"/>
        <x-mary-input label="{{__('ledger.column.auto_number.digits')}}"
                      wire:model.live="columns.{{$index}}.options.digits"
                      wire:key="digits-{{$column['id']}}"
                      type="number" min="1"
                      hint="{{__('ledger.column.auto_number.digits_hint')}}"/>
        <x-mary-input label="{{__('ledger.column.auto_number.revision')}}"
                      wire:model.live="columns.{{$index}}.options.revision"
                      wire:key="revision-{{$column['id']}}"
                      hint="{{__('ledger.column.auto_number.revision_hint')}}"/>
    @elseif($column['type'] === 'YMD' || $column['type'] === 'YMDHM')
        <x-mary-input label="{{__('ledger.column.date.default_offset')}}"
                      wire:model.live="columns.{{$index}}.options.default_offset"
                      wire:key="default-offset-{{$column['id']}}"
                      placeholder="0d"
                      hint="{{__('ledger.column.date.default_offset_combined_hint')}}"/>
        <x-mary-checkbox label="{{__('ledger.column.date.overwrite_existing')}}"
                         wire:model.live="columns.{{$index}}.options.overwrite_existing"
                         wire:key="overwrite-existing-{{$column['id']}}"
                         hint="{{__('ledger.column.date.overwrite_existing_hint')}}"/>
    @elseif($column['type'] === 'phone')
        <x-mary-checkbox label="{{__('ledger.column.phone.allow_extension')}}"
                         wire:model.live="columns.{{$index}}.options.allow_extension"
                         wire:key="allow-extension-{{$column['id']}}"
                         hint="{{__('ledger.column.phone.allow_extension_hint')}}"/>
        <x-mary-checkbox label="{{__('ledger.column.phone.normalize')}}"
                         wire:model.live="columns.{{$index}}.options.normalize"
                         wire:key="normalize-{{$column['id']}}"
                         hint="{{__('ledger.column.phone.normalize_hint')}}"/>
    @elseif($column['type'] === 'user_name')
        <x-mary-select label="{{__('ledger.column.user_name.format')}}"
                       wire:model.live="columns.{{$index}}.options.format"
                       wire:key="format-{{$column['id']}}"
                       :options="[
                           ['id' => 'full_name', 'name' => __('ledger.column.user_name.format_full_name')],
                           ['id' => 'family_name_only', 'name' => __('ledger.column.user_name.format_family_name_only')]
                       ]"
                       option-value="id"
                       option-label="name"/>
        <x-mary-select label="{{__('ledger.column.user_name.organization_prefix')}}"
                       wire:model.live="columns.{{$index}}.options.organization_prefix"
                       wire:key="organization-prefix-{{$column['id']}}"
                       :options="[
                           ['id' => 'none', 'name' => __('ledger.column.user_name.organization_prefix_none')],
                           ['id' => 'bottom_only', 'name' => __('ledger.column.user_name.organization_prefix_bottom_only')],
                           ['id' => 'bottom_3_levels', 'name' => __('ledger.column.user_name.organization_prefix_bottom_3_levels')]
                       ]"
                       option-value="id"
                       option-label="name"/>
        <x-mary-select label="{{__('ledger.column.user_name.edit_mode')}}"
                       wire:model.live="columns.{{$index}}.options.edit_mode"
                       wire:key="edit-mode-{{$column['id']}}"
                       :options="[
                           ['id' => 'overwrite', 'name' => __('ledger.column.user_name.edit_mode_overwrite')],
                           ['id' => 'append', 'name' => __('ledger.column.user_name.edit_mode_append')]
                       ]"
                       option-value="id"
                       option-label="name"/>
    @elseif($column['type'] === 'number')
        <div class="grid grid-cols-2 gap-4">
            <x-mary-input label="{{__('ledger.column.number.min')}}"
                          wire:model.live="columns.{{$index}}.options.min"
                          wire:key="min-{{$column['id']}}"
                          type="number"
                          placeholder="{{__('ledger.column.number.min_placeholder')}}"/>
            <x-mary-input label="{{__('ledger.column.number.max')}}"
                          wire:model.live="columns.{{$index}}.options.max"
                          wire:key="max-{{$column['id']}}"
                          type="number"
                          placeholder="{{__('ledger.column.number.max_placeholder')}}"/>
            <x-mary-input label="{{__('ledger.column.number.step')}}"
                          wire:model.live="columns.{{$index}}.options.step"
                          wire:key="step-{{$column['id']}}"
                          type="number"
                          placeholder="{{__('ledger.column.number.step_placeholder')}}"/>
            <x-mary-input label="{{__('ledger.column.number.unit')}}"
                          wire:model.live="columns.{{$index}}.options.unit"
                          wire:key="unit-{{$column['id']}}"
                          placeholder="{{__('ledger.column.number.unit_placeholder')}}"/>
        </div>
    @else
        <x-mary-tags label="{{__('ledger.options')}}"
                     wire:model.live="columns.{{$index}}.options"
                     wire:key="options-{{$column['id']}}" icon="o-tag"
                     hint="Hit enter to create a new tag"/>
    @endif
@endif
