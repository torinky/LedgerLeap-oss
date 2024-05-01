<x-app-layout title="SETTING | DocumentCabinet">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Folder Setting') }}
        </h2>
    </x-slot>

    <div class="container mx-auto">
        @if (session('status'))
            @include('components.ledger.alert',[
               'type'=>'success',
               'message'=>session('status'),
               'refreshParentWindow'=>true,
            ])
        @endif

        <div class="flex flex-wrap items-center justify-center w-full">
            <form action="{{ route('folder.store')}}" method="post">
                @csrf
                @method('POST')

                <div class="flex-1 m-5">
                    <label for="title" class="ml-3">{{__('ledger.title')}}</label>
                    <input name="title" type="text"
                           value=""
                           placeholder="Type here"
                           class="input input-bordered w-full max-w-xs"/>

                </div>
                <div class="flex-1 m-5">
                    <label for="folder_id" class="ml-3">{{__('Parent folder')}}</label>
                    <select
                        name="parent_id"
                        class="select input-bordered">
                        @foreach($folderRecords as $folderRecord)
                            <option
                                value="{{$folderRecord->id}}" {{  $initialFolderId == $folderRecord->id ? 'selected' : '' }}
                            >{{str_pad('',$folderRecord->lvl,'-').$folderRecord->title }}</option>
                        @endforeach
                    </select>

                </div>


                <div class=" flex min-h-[6rem] flex-wrap items-center justify-center">
                    <button type="submit" class="btn btn-outline btn-primary btn-wide">{{__('save')}}</button>
                    <input type="button" class="btn btn-outline btn-info btn-wide ml-5" onclick="window.close();"
                           value="close">
                    <label for="delete-modal" class="btn btn-outline ml-10">{{__('delete folder')}}</label>


                </div>

            </form>
        </div>

    </div>


</x-app-layout>
