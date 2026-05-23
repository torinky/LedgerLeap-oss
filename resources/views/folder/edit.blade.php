<x-app-layout title="{{__('ledger.folder.edit')}}">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-info leading-tight flex items-center">
            <span class="relative inline-flex items-center justify-center w-10 h-10 mr-2">
                <i class="fa-solid fa-folder text-3xl"></i>
                <i class="fa-solid fa-pencil text-xl text-primary drop-shadow-md absolute -top-1 -right-1"></i>
            </span>
            {{ __('ledger.folder.edit') }}
        </h2>
    </x-slot>

    <div class="container ">
        @if (session('status'))
            @include('components.ledger.alert',[
               'type'=>'success',
               'message'=>session('status'),
               'refreshParentWindow'=>true,
            ])
        @endif

        @if($currentFolderRecord)
                <div class="flex w-full justify-center">
                <form action="{{ route('folder.update', ['tenant' => tenant()?->id, 'folder' => $currentFolderRecord->id]) }}" method="post">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{ $currentFolderRecord->id }}">

                    {{--                    <div class="flex-1 m-5">--}}
                    <label for="title" class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{__('ledger.folder.title')}}</span>
                        </div>
                        <input type="hidden" name="title" value="{{$currentFolderRecord->title}}">
                        <input name="title" type="text"
                               value="{{$currentFolderRecord->title}}"
                               placeholder="{{__('ledger.type_here')}}"
                               class="input input-bordered w-full "/>
                    </label>

                    {{--                    </div>--}}
                    {{--                    <div class="flex-1 m-5">--}}
                    <label for="folder_id" class="folder-control w-full">
                        <div class="label">
                            <span class="label-text">{{__('ledger.folder.containing')}}</span>

                        </div>
                        <select
                            name="parent_id"
                            class="select input-bordered w-full">
                            @foreach($folderRecords as $folderRecord)
                                <option
                                    value="{{$folderRecord->id}}" {{  $currentFolderRecord->parent_id == $folderRecord->id ? 'selected' : '' }}
                                >{{str_pad('',$folderRecord->lvl,'-').$folderRecord->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    {{--                    </div>--}}


                    <div
                        class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                        <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                            <div class="card-body ">
                                <div class="card-actions justify-center place-items-center">
                                    <button type="submit" class="btn btn-primary btn-lg btn-wide"><i
                                            class="fas fa-plus-circle mr-2"></i> {{__('ledger.save')}}</button>
                                    <label for="delete-modal"
                                           class="btn btn-outline btn-error btn-sm ml-5"><i
                                            class="fas fa-trash mr-2"></i> {{__('ledger.folder.remove')}}</label>
                                    {{--                                    <input type="button" class="btn btn-outline btn-info btn-wide ml-5" onclick="window.close();"--}}
                                    {{--                                           value="close">--}}
                                    <x-ledger.close-window-button/>

                                </div>
                            </div>
                        </div>

                    </div>

                </form>
            </div>

            <input type="checkbox" id="delete-modal" class="modal-toggle"/>
            <div class="modal">
                <div class="modal-box bg-error/70 text-error-content ">
                    <h3 class="font-bold text-lg "><i
                            class="fas fa-trash mr-2"></i>{{__('ledger.folder.remove_message')}}</h3>
                    <p class="py-4">{{__('ledger.folder.will_remove_message')}}</p>
                    <div class="modal-action">
                        <div class="btnContainer">
                            <form method="POST" action="{{ route('folder.delete', ['tenant' => tenant()?->id, 'folder' => $currentFolderRecord->id]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-error"
                                        name="deleteFolder"><i
                                        class="fas fa-trash mr-2"></i>{{__('ledger.folder.remove')}}</button>
                            </form>
                        </div>
                        <label for="delete-modal" class="btn btn-outline ml-5">{{__('ledger.cancel')}}</label>
                    </div>
                </div>
            </div>
        @endif
    </div>


</x-app-layout>
