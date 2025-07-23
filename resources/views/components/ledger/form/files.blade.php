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

                /**
                 * MIMEタイプからポスター画像のURLを生成します。
                 * @param {string} mimeType ファイルのMIMEタイプ
                 * @returns {string|null} アイコンのURL or null (画像の場合)
                 */
                const createPosterUrlFromMime = (mimeType) => {
                    // 画像の場合はプレビューを表示するためnullを返す
                    if (!mimeType || mimeType.startsWith('image/')) {
                        return null;
                    }
                    // FontAwesomeIconControllerのAPIエンドポイントを指す
                    return `{{ route('api.fontawesome.icon.by_mime') }}?type=${encodeURIComponent(mimeType)}`;
                };

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
                    filePosterMaxHeight: 100,
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
                            // Livewireサーバーからテンポラリファイルを削除
                            window.Livewire.find(componentId).removeUpload(`content.${columnId}`, filename, () => {
                                // removeUploadが成功したら、FilePondに完了を通知
                                load();

                                // サーバー側のメソッドを呼び出してラベルとプログレスバーを更新
                                window.Livewire.find(componentId).call('handleNewFileRemoval', columnId);
                            });
                        }
                    },

                    onprocessfile: (error, file) => {
                        if (error) {
                            return;
                        }
                        // アップロード成功時にポスターを設定
                        const posterUrl = createPosterUrlFromMime(file.fileType);
                        if (posterUrl) {
                            // file.setMetadata('poster', url, silent)
                            // 第3引数をtrueにすると、更新イベントを発火させずにUIを更新します
                            file.setMetadata('poster', posterUrl, true);
                        }
                    },

                    onremovefile: (error, file) => {
                        if (error) return;
                        const hashedBasename = file.getMetadata('hashedBasename');

                        // 'handleFileRemoval' というサーバー側メソッドを直接呼び出す
                        if (hashedBasename) {
                            window.Livewire.find(componentId).call('handleFileRemoval', columnId, hashedBasename);
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
