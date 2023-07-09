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

        @if($currentFolderRecord)
            <div class="flex flex-wrap items-center justify-center w-full">
                <form action="{{ route('folder.update',$currentFolderRecord->id)}}" method="post">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{ $currentFolderRecord->id }}">

                    <div class="flex-1 m-5">
                        <label for="title" class="ml-3">{{__('title')}}</label>
                        <input type="hidden" name="title" value="{{$currentFolderRecord->title}}">
                        <input name="title" type="text"
                               value="{{$currentFolderRecord->title}}"
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
                                    value="{{$folderRecord->id}}" {{  $currentFolderRecord->parent_id == $folderRecord->id ? 'selected' : '' }}
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

            <input type="checkbox" id="delete-modal" class="modal-toggle"/>
            <div class="modal">
                <div class="modal-box">
                    <h3 class="font-bold text-lg">{{__('delete folder')}}</h3>
                    <p class="py-4">{{__('This folder will be deleted')}}<br/>
                        {{__('Ledgers in this Folder will be moved to upper folder')}}</p>
                    <div class="modal-action">
                        <div class="btnContainer">
                            <form method="POST" action="{{route('folder.delete',$currentFolderRecord->id)}}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn"
                                        name="deleteFolder">{{__('delete folder')}}</button>
                            </form>
                        </div>
                        <label for="delete-modal" class="btn btn-outline ml-5">{{__('cancel')}}</label>
                    </div>
                </div>
            </div>
        @endif
    </div>


</x-app-layout>
