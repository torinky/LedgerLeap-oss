<x-app-layout title="DETAIL | DocumentCabinet">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ledger Record History') }}
        </h2>
    </x-slot>


    <livewire:ledger.show-diff/>

    <div class=" flex min-h-[6rem] flex-wrap items-center justify-center">
        {{--
                <a href="{{ route('ledger.edit', ['ledgerId'=>$ledgerRecord->id]) }}"
                   class="btn btn-outline btn-primary btn-wide"
                ><i class="fa-solid fa-pencil mr-2"></i>{{__('edit')}}</a>
        --}}

        <a href="#" class="btn btn-outline btn-info ml-5" onclick="window.close();"><i
                class="fa-solid fa-close mr-2"></i>{{__('close')}}</a>
    </div>

</x-app-layout>
