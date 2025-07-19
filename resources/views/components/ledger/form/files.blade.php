@php
    use App\Enums\AttachedFileStatus;use App\Helpers\AttachedFilePathHelper;
    use App\Models\AttachedFile;use \Illuminate\Support\Facades\Storage;
@endphp
@props([
    'class'=>'',
    'hintClass'=>'label-text-alt text-gray-400 ps-1 ',
    'columnDefine'=>[],
    'ledgerDefineId',
    ])

<div class="form-control">
    @error('content.' . $columnDefine->id)
    <span class="label-text-alt text-red-500 text-xs space-x-2">
        <i class="fas fa-times-circle"></i>
        <span class="error">{{ $message }}</span>
    </span>
    @enderror

    <label for="content[{{$columnDefine->id}}]" class="">

        <div class="pt-0 label label-text font-semibold">
        <span>
             {{$columnDefine->name}}
            @if($columnDefine->required)
                <span class="text-error">*</span>
            @endif
        </span>
        </div>

        <div
                class=" rounded-lg opacity-70 hover:opacity-100 @if($columnDefine->required) bg-warning/70 @else bg-neutral/50 @endif "
                wire:ignore
                x-data
                x-init="() => {
            const post = FilePond.create($refs.content_{{$columnDefine->id}});
            post.setOptions({
                allowMultiple: {{ $attributes->has('multiple') ? 'true' : 'false' }},
                allowImagePreview: {{ $attributes->has('allowImagePreview') ? 'true' : 'false' }},
                imagePreviewMaxHeight: {{ $attributes->has('imagePreviewMaxHeight') ? $attributes->get('imagePreviewMaxHeight') : '256' }},
                allowFileTypeValidation: {{ $attributes->has('allowFileTypeValidation') ? 'true' : 'false' }},
                acceptedFileTypes: {!! json_encode($attributes->get('acceptedFileTypes')) !!},
                allowFileSizeValidation: {{ $attributes->has('allowFileSizeValidation') ? 'true' : 'false' }},
                maxFileSize: {!! json_encode($attributes->get('maxFileSize')) !!},
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
                        // route() ヘルパーに直接パラメータを渡すことで、Laravelが正しいURLを生成する
                        fetch(`{{ route('filepond.load', ['attachedFile' => '__ATTACHED_FILE_ID__']) }}`.replace('__ATTACHED_FILE_ID__', source)).then(response => response.blob()).then(load);
                    },
                },

                @if(!empty( $ledgerRecord->content[$columnDefine->id]) && is_array($ledgerRecord->content[$columnDefine->id]))
                    files: [
                    @foreach ($ledgerRecord->content[$columnDefine->id] as $hashedBasename => $originalFilename )
                        @php
                            $attachmentId = $this->attachmentIdMap[$hashedBasename] ?? null;
                            // 実際のファイルパスを決定 (処理失敗時はオリジナルパスを優先)
                            $currentAttachedFile = AttachedFile::find($attachmentId);
                            $storagePath = AttachedFilePathHelper::getAttachmentPath($ledgerDefineId, $hashedBasename);
                            $displayMimeType = '';

                            if ($currentAttachedFile) {
                                if (in_array($currentAttachedFile->status->value, [AttachedFileStatus::TIKA_FAILED->value, AttachedFileStatus::OCR_FAILED->value])) {
                                    $storagePath = $currentAttachedFile->original_file_path; // 失敗時はオリジナルパス
                                    $displayMimeType = $currentAttachedFile->original_mime_type; // 失敗時はオリジナルMIMEタイプ
                                } else {
                                    $displayMimeType = $currentAttachedFile->mime; // 成功時は現在のMIMEタイプ
                                }
                            }

                            $fileExists = Storage::disk('public')->exists($storagePath);
                            $posterUrl = '';

                            if ($fileExists) {
                                if (\Illuminate\Support\Str::startsWith($displayMimeType, 'image/')) {
                                    $posterUrl = route('file.download', ['attachedFile' => $attachmentId, 'thumbnail' => true]);
                                } else {
                                    switch ($displayMimeType) {
                                        case 'application/pdf':
                                            $posterUrl = asset('images/icons/file-pdf-solid.svg');
                                            break;
                                        case 'application/zip':
                                        case 'application/x-zip-compressed':
                                            $posterUrl = asset('images/icons/file-earmark-zip.svg');
                                            break;
                                        case 'application/msword':
                                        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                                            $posterUrl = asset('images/icons/file-earmark-word.svg');
                                            break;
                                        case 'application/vnd.ms-excel':
                                        case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                                            $posterUrl = asset('images/icons/file-earmark-excel.svg');
                                            break;
                                        case 'application/vnd.ms-powerpoint':
                                        case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                                            $posterUrl = asset('images/icons/file-earmark-ppt.svg');
                                            break;
                                        default:
                                            $posterUrl = asset('images/icons/file-earmark.svg');
                                    }
                                }
                            }
                        @endphp
                        {
                        @if(!$attachmentId || !$fileExists)
                            source: '',
                            options: {
                               type: 'local',
                               file: {
                                    name: '[Not Found] {{$originalFilename}}',
                                    size: 0,
                                    type: 'application/octet-stream',
                               },
                               metadata:{
                                    poster:'',
                                    position: {{ $loop->index }},
                                    filename:'not_exist'
                               },
                            },
                        @else
                            source: '{{ $attachmentId }}',
                            options: {
                                type: 'local',
                                file: {
                                    name: '{{$originalFilename}}',
                                    size: {{Storage::disk('public')->size($storagePath)}},
                                    type: '{{Storage::disk('public')->mimeType($storagePath)}}',
                                },
                                metadata:{
                                    poster:'{{ $posterUrl }}',
                                    position: {{ $loop->index }},
                                    filename:'{{$storagePath}}'
                                },
                            },
                        @endif
                        },
                    @endforeach
                    ],
                onremovefile: (error, file) => {
                    @this.set('deletedContent.{{$columnDefine->id}}.'.concat(file.getMetadata('position')),file.getMetadata('filename'));
                }
                @endif
            });
    }
    "
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