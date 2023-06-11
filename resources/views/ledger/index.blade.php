<x-appWithDrawer-layout title="TOP | DocumentCabinet">
{{--
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight">
            {{ __('Ledger Records') }}
        </h2>
    </x-slot>
--}}
    @push('stylesheets')
        {{--        <link rel="stylesheet" href="{{ asset('css/ledgerIndex.css') }}">--}}
        @vite(['resources/sass/ledgerIndex.scss'])
    @endpush

    <x-slot name="drawer">
        <li><a>Sidebar Item 1</a></li>
        <li><a>Sidebar Item 2</a></li>
        <div class="tree">
            <ul>
                <li><a><i class="fa fa-folder-open"></i> Project</a>
                    <ul>
                        <li><a><i class="fa fa-folder-open"></i> Opened Folder <span>- 15kb</span></a>
                            <ul>
                                <li><a><i class="fa fa-folder-open"></i> css</a>
                                    <ul>
                                        <li><a><i class="fa fa-code"></i> CSS Files <span>- 3kb</span></a>
                                        </li>
                                        <li><a><i class="fa fa-code"></i> CSS Files <span>- 3kb</span></a>
                                        </li>
                                        <li><a><i class="fa fa-code"></i> CSS Files <span>- 3kb</span></a>
                                        </li>
                                    </ul>
                                </li>
                                <li><a><i class="fa fa-folder"></i> Folder close <span>- 10kb</span></a>
                                </li>
                                <li><a><i class="fab fa-html5"></i> index.html</li>
                                </a>
                                <li><a><i class="fa fa-picture-o"></i> favicon.ico</li>
                                </a>
                            </ul>
                        </li>
                        <li><a><i class="fa fa-folder"></i> Folder close <span>- 420kb</span></a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

    </x-slot>

    {{--    <livewire:counter/>--}}

    {{--
        <x-layout.single>
            <h2 class="text-center text-black-500 text-4xl font-bold mt-8 mb-8">
                Document Cabinet
            </h2>
            <livewire:counter/>
        </x-layout.single>
    --}}
    {{--
        <x-ledger.form.search></x-ledger.form.search>
        @if($ledgers)
        <table>
            <tbody>
            @foreach($ledgers as $ledger)
                <tr>
                    <td>{{$ledger->id}}</td>
                    @foreach($ledger->content as $column)
                        <td>{{$column}}</td>
                    @endforeach
                    <td>{{$ledger->created_at}}</td>
                    <td>{{$ledger->modified_at}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
        <livewire:table :ledgers="$ledgers"/>
    --}}

    <div class="container mx-auto">
        <livewire:ledger.records-table/>
    </div>

</x-appWithDrawer-layout>
