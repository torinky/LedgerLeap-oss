@props([
    'class'=>'',
    'hintClass'=>'label-text-alt text-gray-400 ps-1 ',
    'columnDefine'=>[],
    'ledgerDefineId',
    'initialFiles' => [],
    ])

<div class="form-control">
    @error('content.' . $columnDefine->id)
    <span class="label-text-alt text-red-500 text-xs space-x-2">
        <i class="fas fa-times-circle"></i>
        <span class="error">{{ $message }}</span>
    </span>
    @enderror

    <label for="content[{{$columnDefine->id}}]">

        <div class="pt-0 label label-text font-semibold">
            <span>
                 {{$columnDefine->name}}
                @if($columnDefine->required)
                    <span class="text-error">*</span>
                @endif
            </span>
        </div>

        <div
            class="rounded-lg opacity-70 hover:opacity-100 @if($columnDefine->required) bg-warning/70 @else bg-neutral/50 @endif "
            wire:ignore
            x-data
            x-init="() => {
                const post = FilePond.create($refs.content_{{$columnDefine->id}});
                post.setOptions({
                    allowMultiple: {{ $attributes->has('multiple') ? 'true' : 'false' }},
                    allowImagePreview: true,
                    imagePreviewMaxHeight: {{ $attributes->has('imagePreviewMaxHeight') ? $attributes->get('imagePreviewMaxHeight') : '256' }},
                    allowFileTypeValidation: {{ $attributes->has('allowFileTypeValidation') ? 'true' : 'false' }},
                    acceptedFileTypes: {{ Illuminate\Support\Js::from($attributes->get('acceptedFileTypes')) }},
                    allowFileSizeValidation: {{ $attributes->has('allowFileSizeValidation') ? 'true' : 'false' }},
                    maxFileSize: {{ Illuminate\Support\Js::from($attributes->get('maxFileSize')) }},
                    credits: false,
                    filePosterMaxHeight: 200,
                    server: {
                        process: (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
                            @this.upload('content.{{$columnDefine->id}}', file,
                            (uploadedFilename) => {
                                load(uploadedFilename);
                            }, error, progress)
                        },

                        revert: (filename, load) => {
                            @this.removeUpload('content.{{$columnDefine->id}}', filename, load)
                        },

                        load: (source, load, error, progress, abort, headers) => {
                            // source は AttachedFile の ID
                            // apiルートに変更し、認証情報をヘッダーに含める
                            fetch(`/api/filepond/load/${source}`, {
                                credentials: 'include', // ★★★ Cookieをリクエストに含めるために必須 ★★★
                                headers: {
                                    'Accept': 'application/json',
                                    // CSRFトークンはCookieベースのSanctum認証では通常不要だが、念のため追加
                                    'X-XSRF-TOKEN': document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1],
                                }
                            })
                            .then(async response => {
                                if (!response.ok) {
                                    // サーバーからのエラーレスポンスを処理
                                    const text = await response.text();
                                    error(`Server error: ${response.status} ${response.statusText} - ${text}`);
                                    return;
                                }
                                return response.blob();
                            })
                            .then(blob => {
                                if (blob) {
                                    load(blob);
                                }
                            })
                            .catch(e => {
                                console.error('FilePond load error:', e);
                                error('Network error while loading file.');
                            });
                        },
                    },
                    onremovefile: (error, file) => {
                        const columnId = {{ $columnDefine->id }};
                        const position = file.getMetadata('position');
                        const filename = file.getMetadata('filename');
                        window.Livewire.find('{{ $this->id() }}').set(`deletedContent.${columnId}.${position}`, filename);
                    }
                });

                // ★★★ ここから追加 ★★★
                const initialFiles = {{ Illuminate\Support\Js::from($initialFiles) }};
                initialFiles.forEach(file => {
                    post.addFile(file.source, file.options); // sourceとoptionsを渡す
                });
                // ★★★ ここまで追加 ★★★
            }"
        >
            <input id="content[{{$columnDefine->id}}]" type="file" name="content[{{$columnDefine->id}}]"
                   x-ref="content_{{$columnDefine->id}}"
            >
        </div>
        @if($columnDefine->hint)
            <div class="{{ $hintClass }}"
                 x-classes="label-text-alt text-gray-400 ps-1 mt-2">{{ $columnDefine->hint }}</div>
        @endif
    </label>

</div>