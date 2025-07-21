@props([
    'class'=>'',
    'hintClass'=>'label-text-alt text-gray-400 ps-1 ',
    'columnDefine'=>[],
    'ledgerDefineId',
    'initialFiles' => [],
    ])

<div class="form-control" wire:key="filepond-{{ $columnDefine->id }}">
    @error('content.' . $columnDefine->id)
    <span class="label-text-alt text-red-500 text-xs space-x-2">
        <i class="fas fa-times-circle"></i>
        <span class="error">{{ $message }}</span>
    </span>
    @enderror

    <label for="content_{{ $this->id() }}_{{ $columnDefine->id }}">
        <div class="pt-0 label label-text font-semibold">
            <span>
                {{ $columnDefine->name }}
                @if($columnDefine->required)
                    <span class="text-error">*</span>
                @endif
            </span>
        </div>

        <div class="rounded-lg opacity-70 hover:opacity-100 @if($columnDefine->required) bg-warning/70 @else bg-neutral/50 @endif"
             wire:ignore
             x-data="{ pond: null }"
             data-initial-files="{{ json_encode($initialFiles) }}"
             data-column-id="{{ $columnDefine->id }}"
             data-component-id="{{ $this->id() }}"
             x-init="() => {
                const container = $el;
                const initialFiles = JSON.parse(container.dataset.initialFiles || '[]');
                const columnId = parseInt(container.dataset.columnId);
                const componentId = container.dataset.componentId;

                pond = FilePond.create($refs[`content_${columnId}`]);

                pond.setOptions({
                    allowMultiple: {{ $attributes->has('multiple') ? 'true' : 'false' }},
                    allowImagePreview: true,
                    imagePreviewMaxHeight: {{ $attributes->get('imagePreviewMaxHeight', '256') }},
                    allowFileTypeValidation: {{ $attributes->has('allowFileTypeValidation') ? 'true' : 'false' }},
                    acceptedFileTypes: {{ Illuminate\Support\Js::from($attributes->get('acceptedFileTypes')) }},
                    allowFileSizeValidation: {{ $attributes->has('allowFileSizeValidation') ? 'true' : 'false' }},
                    maxFileSize: {{ Illuminate\Support\Js::from($attributes->get('maxFileSize')) }},
                    credits: false,
                    filePosterMaxHeight: 200,
                    itemInsertInterval: initialFiles.length > 10 ? 0 : 10,

                    server: {
                        process: (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                            window.Livewire.find(componentId).upload(`content.${columnId}`, file,
                                (uploadedFilename) => load(uploadedFilename),
                                (uploadError) => error(uploadError),
                                (event) => progress(event.detail.progress, event.detail.progress, 100)
                            );
                        },
                        revert: (filename, load) => {
                            window.Livewire.find(componentId).removeUpload(`content.${columnId}`, filename, load);
                        }
                    },

                    onremovefile: (error, file) => {
                        if (error) return;
                        const hashedBasename = file.getMetadata('hashedBasename');
                        if (hashedBasename && !window.Livewire.find(componentId).get(`deletedContent.${columnId}`).includes(hashedBasename)) {
                            window.Livewire.find(componentId).set(`deletedContent.${columnId}`, [...window.Livewire.find(componentId).get(`deletedContent.${columnId}`), hashedBasename]);
                        }
                    },

                    onerror: (error, file) => {
                        console.error('FilePond Error:', error);
                        window.dispatchEvent(new CustomEvent('filepond-error', {
                            detail: { error, file, columnId }
                        }));
                    }
                });

                // 初期ファイルの追加
                initialFiles.forEach(file => {
                    pond.addFile(file.source, file.options);
                });

                // クリーンアップ
                $el._pond = pond;
             }"
             x-on:remove-files.window="pond && pond.removeFiles()">

            <input id="content_{{ $this->id() }}_{{ $columnDefine->id }}"
                   type="file"
                   name="content[{{ $columnDefine->id }}]"
                   wire:model.defer="content.{{ $columnDefine->id }}"
                   x-ref="content_{{ $columnDefine->id }}">
        </div>

        @if($columnDefine->hint)
            <div class="{{ $hintClass ?? 'label-text-alt text-gray-400 ps-1 mt-2' }}">
                {{ $columnDefine->hint }}
            </div>
        @endif
    </label>
</div>
