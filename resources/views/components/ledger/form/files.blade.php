<div
    class=" card @if($columnDefine->required) bg-accent @else bg-base-200 @endif"
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
                acceptedFileTypes: {!! $attributes->get('acceptedFileTypes') ?? 'null' !!},
                allowFileSizeValidation: {{ $attributes->has('allowFileSizeValidation') ? 'true' : 'false' }},
                maxFileSize: {!! $attributes->has('maxFileSize') ? "'".$attributes->get('maxFileSize')."'" : 'null' !!},
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
                        @php($position=0)
                        files: [
                        @foreach ($ledgerRecord->content[$columnDefine->id] as $originalFilename => $filename)
                            {
                                source: '{{ Storage::url($filename) }}',
                                options: {
                                   type: 'local',
                                   file: {
                                        name: '{{$originalFilename}}',
                                        size: {{Storage::size($filename)}},
                                        type: '{{Storage::mimeType($filename)}}',
                                   },
                                   metadata:{
                                        poster:'{{$this->getThumbnailUrl( $filename)}}',
                                        position: {{$position}},
                                        filename:'{{$filename}}'
                                   },
                                },
                            },
                            @php($position++)
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
