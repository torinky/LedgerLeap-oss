@props([
    'class'=>'',
    'hintClass'=> 'label-text-alt text-gray-400 ps-1 ',
    'columnDefine'=>[],
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
        window.addEventListener('load', () => {
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

    {{--
                    load: (uniqueFileId, load, error, progress, abort, headers) => {
                        fetch(`{{ route('media.restore', ['course', $filename]) }}`).then((res) => {
                            return res.blob();
                         }).then(load);
                    },
    --}}

                    load: (source, load, error, progress, abort, headers) => {
                        var request = new Request(source);
                        fetch(request).then(function(response) {
                          response.blob().then(function(myBlob) {
                            load(myBlob)
                          });
                        });
                    },
                },

                @if(!empty( $ledgerRecord->content[$columnDefine->id]) && is_array($ledgerRecord->content[$columnDefine->id]))
                    files: [
                    @foreach ($ledgerRecord->content[$columnDefine->id] as $hashedBasename => $originalFilename )
                        @php($attachmentId = $this->attachmentIdMap[$hashedBasename] ?? null)
                        @php($fullPath = 'public/Ledger/Attachments/Originals/' . $hashedBasename)
                        {
                        @if(!$attachmentId || !Storage::exists($fullPath))
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
                                    size: {{Storage::size($fullPath)}},
                                    type: '{{Storage::mimeType($fullPath)}}',
                               },
                               metadata:{
                                    poster:'{{ route('file.download', ['attachedFile' => $attachmentId, 'thumbnail' => true]) }}',
                                    position: {{ $loop->index }},
                                    filename:'{{$fullPath}}'
                               },
                            },
                        @endif
                        },
                    @endforeach
                    ],
                onremovefile: (error, file) => {
                    @this.set('deletedContent.{{$columnDefine->id}}.'+file.getMetadata('position'),file.getMetadata('filename'));
                }
                @endif
            });
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
