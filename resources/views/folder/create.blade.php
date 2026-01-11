<x-app-layout title="{{__('ledger.folder.create')}}">
    <x-slot name="header" class="sticky top-0 z-10">
        <div class="ttl_3d5 warn md:flex md:items-center space-x-4">
            <h2 class="font-black text-lg text-warning-content md:text-2xl">
                <i class="fas fa-plus-circle mr-2"></i>
                {{ __('Ledger.folder.create') }}
            </h2>

        </div>
    </x-slot>


    <div class="container mx-auto">
        <livewire:folder.create/>
        {{--
                @if (session('status'))
                    @include('components.ledger.alert',[
                       'type'=>'success',
                       'message'=>session('status'),
                       'refreshParentWindow'=>true,
                    ])
                @endif

                    <div class="flex justify-center w-full">
                    <form action="{{ route('folder.store', ['tenant' => tenant()?->id]) }}" method="post">
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
        --}}

    </div>


</x-app-layout>
