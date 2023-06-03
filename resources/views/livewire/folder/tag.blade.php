<div>
    {{-- Knowing others is intelligence; knowing yourself is true wisdom. --}}
    <p>folder:{{$folderId}}</p>


    {{--    @once--}}
    {{--    For Tags funcrions    --}}
    @push('scripts')
        {{--            <script src="{{ asset('js/jquery/dist/jquery.slim.js') }}"></script>--}}
        {{--            <script src="{{ asset('js/select2/dist/js/select2.min.js') }}"></script>--}}

        <script>
            console.log('Folder Tag Js Block Call!!')
            // document.addEventListener("livewire:load", () => {
            $(document).ready(function () {
                initSelect()
                // Livewire.hook('message.processed', (message, component) => {
                Livewire.hook('component.initialized', (message, component) => {
                    initSelect()
                })

                function initSelect() {
                    console.log('initFolderTagSelect!');
                    // function内部でjqueryを初期化しなければselect2が適用されない
                    let el2 = window.$('.js-attachSelect2FolderTag')

                    el2.select2({
                        placeholder: '{{__('Select your option')}}',
                        allowClear: !el2.attr('required'),
                        tags: true,
                        tokenSeparators: [',', ' '],
                        createTag: function (tag) {
                            return {
                                id: tag.term,
                                text: tag.term,
                                newTag: true
                            };
                        }
                    }).on('change', function (e) {
                        var data = window.$(this).select2('data');
                        console.log(data);
                        var tags = data.map(function (obj) {
                            // return obj.newTag !== undefined ? obj.text : obj.id;
                            // return {name:obj.text};
                            return {name: obj.text};
                        });
                        // tags = {...tags};
                        console.log(tags);
                    @this.set('tags', tags);
                        // console.log(@this.tags)
                        // @this.set('tags', {name:'test'});
                    });
                }
            })
        </script>
    @endpush
    {{--    @endonce--}}

</div>
