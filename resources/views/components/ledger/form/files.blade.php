@props([
    'class' => '',
    'hintClass' => 'label-text-alt text-gray-400 ps-1 ',
    'columnDefine' => [],
    'ledgerDefineId',
    'initialFiles' => [],
])

@once
    @push('styles')
        <style>
            /* 既存ファイル：青色の枠線とポインターカーソル */
            .filepond--item[data-is-existing="true"] {
                border: 2px solid #3b82f6 !important;
                background-color: #eff6ff !important;
            }

            /* アイテム全体と主要な構成要素にポインターカーソルを強制 */
            .filepond--item[data-is-existing="true"],
            .filepond--item[data-is-existing="true"] .filepond--file,
            .filepond--item[data-is-existing="true"] .filepond--panel-root,
            .filepond--item[data-is-existing="true"] .filepond--content {
                cursor: pointer !important;
            }

            /* 新規ファイル：オレンジ色の枠線 */
            .filepond--item:not([data-is-existing]) {
                border: 2px solid #f59e0b !important;
                background-color: #fffbeb !important;
            }
        </style>
    @endpush
@endonce

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
                @if ($columnDefine->required)
                    <span class="text-error">*</span>
                @endif
            </span>
        </div>

        <div class="rounded-lg opacity-70 hover:opacity-100 @if ($columnDefine->required) bg-warning/70 @else bg-neutral/50 @endif"
            wire:ignore x-data="{ pond: null }" data-initial-files="{{ json_encode($initialFiles) }}"
            data-column-id="{{ $columnDefine->id }}" data-component-id="{{ $this->id() }}" x-init="() => {
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
            
                // メタデータとDOM属性の同期ヘルパー
                const syncIconAttribute = (file) => {
                    // FilePondのDOM構造から該当するアイテムを検索
                    const items = container.querySelectorAll('.filepond--item');
                    let targetItem = null;
            
                    // ファイル名で照合
                    items.forEach(item => {
                        const infoElement = item.querySelector('.filepond--file-info-main');
                        if (infoElement && infoElement.textContent.trim() === file.filename) {
                            targetItem = item;
                        }
                    });
            
                    if (targetItem) {
                        // アイコン表示属性の同期
                        const isIcon = file.getMetadata('is_icon');
                        if (isIcon) {
                            targetItem.setAttribute('data-is-icon', 'true');
                        } else {
                            targetItem.removeAttribute('data-is-icon');
                        }
            
                        // 既存ファイル属性の同期
                        const isExisting = file.getMetadata('isExisting');
                        if (isExisting) {
                            targetItem.setAttribute('data-is-existing', 'true');
                            // インラインスタイルでもcursorを設定（念のため）
                            targetItem.style.cursor = 'pointer';
            
                            // 子要素にも伝播させる
                            const fileEl = targetItem.querySelector('.filepond--file');
                            if (fileEl) fileEl.style.cursor = 'pointer';
                            console.log('[FilePond] Applied data-is-existing and cursor to:', file.filename);
                        }
                    } else {
                        console.warn('[FilePond] Could not find DOM element for file:', file.filename);
                    }
                };
            
                pond.setOptions({
                    allowPaste: true,
                    allowMultiple: {{ $attributes->has('multiple') ? 'true' : 'false' }},
                    allowImagePreview: true,
                    imagePreviewMaxHeight: {{ $attributes->get('imagePreviewMaxHeight', '256') }},
                    allowFileTypeValidation: {{ $attributes->has('allowFileTypeValidation') ? 'true' : 'false' }},
                    acceptedFileTypes: {{ Illuminate\Support\Js::from($attributes->get('acceptedFileTypes')) }},
                    allowFileSizeValidation: {{ $attributes->has('allowFileSizeValidation') ? 'true' : 'false' }},
                    maxFileSize: {{ Illuminate\Support\Js::from($attributes->get('maxFileSize')) }},
                    credits: false,
                    // filePosterMaxHeightをimagePreviewMaxHeightと統一
                    filePosterMaxHeight: {{ $attributes->get('imagePreviewMaxHeight', '256') }},
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
                            window.Livewire.find(componentId).removeUpload(`content.${columnId}`, filename, () => {
                                load();
                                window.Livewire.find(componentId).call('handleNewFileRemoval', columnId);
                            });
                        }
                    },
            
                    onaddfile: (error, file) => {
                        if (error) return;
            
                        const metadata = file.getMetadata();
                        const isIcon = !file.fileType || !file.fileType.startsWith('image/');
            
                        // 既存ファイル（初期ロード時）はmetadataにis_iconが既にセットされている
                        // 新規アップロードファイルは判定が必要
                        if (metadata.is_icon === undefined) {
                            file.setMetadata('is_icon', isIcon, true);
                        }
            
                        // posterがまだ設定されていない、かつアイコン表示の場合
                        if (!metadata.poster && (metadata.is_icon || isIcon)) {
                            const posterUrl = createPosterUrlFromMime(file.fileType);
                            if (posterUrl) {
                                file.setMetadata('poster', posterUrl, true);
                            }
                        }
            
                        // DOM属性を同期（少し遅延させてFilePondのDOM構築を待つ）
                        setTimeout(() => syncIconAttribute(file), 300);
                    },
            
                    onprocessfile: (error, file) => {
                        if (error) return;
            
                        // アップロード完了後、is_iconフラグに基づいてposterを設定
                        const metadata = file.getMetadata();
                        if (metadata.is_icon) {
                            const posterUrl = createPosterUrlFromMime(file.fileType);
                            if (posterUrl) {
                                file.setMetadata('poster', posterUrl, true);
                                // 再度DOM属性を同期
                                setTimeout(() => syncIconAttribute(file), 200);
                            }
                        }
                    },
            
                    onremovefile: (error, file) => {
                        if (error) return;
                        const hashedBasename = file.getMetadata('hashedBasename');
                        if (hashedBasename) {
                            window.Livewire.find(componentId).call('handleFileRemoval', columnId, hashedBasename);
                        }
                    },
            
                    onactivatefile: (file) => {
                        console.log('[FilePond] onactivatefile triggered', file);
                        // 既存ファイルかどうかを確認
                        const isExisting = file.getMetadata('isExisting');
                        console.log('[FilePond] isExisting:', isExisting);
                        console.log('[FilePond] All metadata:', file.getMetadata());
                        if (isExisting) {
                            const attachmentId = file.getMetadata('attachmentId');
                            console.log('[FilePond] attachmentId:', attachmentId);
                            if (attachmentId) {
                                // ファイルインスペクターを開く
                                console.log('[FilePond] Opening file inspector for:', attachmentId);
                                window.Livewire.dispatch('open-file-inspector', { id: attachmentId });
                            } else {
                                console.warn('[FilePond] No attachmentId found in metadata');
                            }
                        } else {
                            console.log('[FilePond] File is not existing, skipping inspector');
                        }
                    },
            
                    onerror: (error, file) => {
                        console.error('FilePond Error:', error);
                        window.dispatchEvent(new CustomEvent('filepond-error', {
                            detail: { error, file, columnId }
                        }));
                    }
                });
            
                pond.on('setmetadata', (file) => {
                    syncIconAttribute(file);
                });
            
                // ファイルリストが更新された際に全ファイルの属性を同期
                pond.on('updatefiles', (files) => {
                    files.forEach(file => {
                        syncIconAttribute(file);
                    });
                });
            
                // 初期ファイルの追加
                initialFiles.forEach(file => {
                    pond.addFile(file.source, file.options);
                });
            
                // 初期ファイル追加後、遅延させて属性を同期（DOM構築を待つ）
                setTimeout(() => {
                    pond.getFiles().forEach(file => {
                        syncIconAttribute(file);
                    });
                }, 500);
            
                // クリーンアップ
                $el._pond = pond;
            }"
            x-on:remove-files.window="pond && pond.removeFiles()">

            <input id="content_{{ $this->id() }}_{{ $columnDefine->id }}" type="file"
                name="content[{{ $columnDefine->id }}]" wire:model.defer="content.{{ $columnDefine->id }}"
                x-ref="content_{{ $columnDefine->id }}">
        </div>

        @if ($columnDefine->hint)
            <div class="{{ $hintClass ?? 'label-text-alt text-gray-400 ps-1 mt-2' }}">
                {{ $columnDefine->hint }}
            </div>
        @endif
    </label>
</div>
