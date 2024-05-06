<x-app-layout title="SETTING | DocumentCabinet">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-info leading-tight">
                <span class="fa-layers fa-fw mr-2">
                    <i class="fa-solid fa-folder text-3xl" data-fa-transform="left-6 "></i>
                    <i class="fa-solid  fa-plus-circle text-primary/70"
                       data-fa-transform=" right-6 up-10"></i>
                </span>
            {{ __('ledger.folder.create') }}
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

            <div class="flex justify-center w-full">
            <form action="{{ route('folder.store')}}" method="post">
                @csrf
                @method('POST')

                <label for="title" class="form-control">
                    <div class="label">
                        <span class="label-text">{{__('ledger.folder.title')}}</span>
                    </div>
                    <input name="title" type="text"
                           value=""
                           placeholder="{{__('ledger.type_here')}}"
                           class="input input-bordered w-full max-w-xs"/>
                </label>

                <label for="folder_id" class="form-control">
                    <div class="label">
                        <span class="label-text">{{__('ledger.folder.containing')}}</span>
                    </div>
                    <select
                        name="parent_id"
                        class="select input-bordered">
                        @foreach($folderRecords as $folderRecord)
                            <option
                                value="{{$folderRecord->id}}" {{  $initialFolderId == $folderRecord->id ? 'selected' : '' }}
                            >{{str_pad('',$folderRecord->lvl,'-').$folderRecord->title }}</option>
                        @endforeach
                    </select>
                </label>


                <div
                    class="mx-auto md:w-full lg:w-2/3 inset-x-0 fixed bottom-3">
                    <div class="card shadow-lg bg-base-300 opacity-70 hover:opacity-100 transition-opacity ">
                        <div class="card-body ">
                            <div class="card-actions justify-center items-center">
                                <button type="submit"
                                        class="btn btn-lg btn-primary btn-wide"><i
                                        class="fas fa-plus-circle mr-2"></i>{{__('ledger.folder.create')}}</button>
                                <x-ledger.close-window-button/>
                            </div>
                        </div>
                    </div>

                </div>

            </form>
        </div>

    </div>


</x-app-layout>
