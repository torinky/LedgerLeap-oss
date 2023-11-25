<div>

    <x-ledger.search/>

    <x-ledger.livewire-breadcrumbs
        :breadcrumbs="$breadcrumbs"
    />

    {{--
        <div class="flex flex-row">
            <livewire:folder.tag :folderId="$currentFolderId" :wire:key="$currentFolderId"/>
        </div>
    --}}

    <x-folder.folder-and-ledger-panels
        :folderRecords="$folderRecords"
        :selectedFolderIds="$selectedFolderIds"
        :ledgerDefineRecords="$ledgerDefineRecords"
        :selectedLedgerDefineIds="$selectedLedgerDefineIds"
    />


    <div class="divider"></div>

    <div wire:loading style="width:100%;">
        <div class="flex flex-row justify-center ">
            <span class="loading loading-dots loading-lg"></span>
        </div>
    </div>

    <div class="">
        @if($ledgerRecords->count() > 0)

            {!! $ledgerRecords->links('components.ledger.pagination-links',['position'=>'top']) !!}

            @php($defineId = null)
            @foreach($ledgerRecords as $lKey=> $ledgerRecord)
                {{--                台帳定義が変わったら--}}
                @if($ledgerRecord->define && $defineId!=$ledgerRecord->define->id)
                        <?php $defineId = $ledgerRecord->define->id; ?>
                    {{--                    最初の台帳ブロックならテーブル終了は出さない--}}
                    @if($lKey!=0)
                        </tbody></table></div>
    <div class="divider"></div>
    @endif
    <div wire:key="ledger_record_{{$ledgerRecord->id}}">
        <x-ledgerDefine.header
            :ledgerRecord="$ledgerRecord"
            :breadcrumbsPerLedgerDefine="$breadcrumbsPerLedgerDefine"
            :search="$search"
            :filter="$filter"
            :keywords="$keywords"
        />

        <div class="overflow-x-auto max-h-screen" wire:key="ledgerDefine_block-{{$ledgerRecord->define->id}}">
            <table class="relative table table-zebra table-compact table-auto table-pin-rows table-pin-cols max-h-fit">
                <thead>
                <x-ledger.table-header
                    :ledgerRecord="$ledgerRecord"
                    :orderBy="$orderBy"
                    :orderAsc="$orderAsc"
                />
                </thead>
                <tbody>
                @endif
                <x-ledger.table-row
                    :ledgerRecord="$ledgerRecord"
                />
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    {!! $ledgerRecords->links('components.ledger.pagination-links',['position'=>'bottom']) !!}
    @else
        {{--
                        <x-ledger.alert
                            message="{{__('Select Ledger or Folder')}}"
                            icon="fa-circle-info"
                            type="warning"
                            refreshParentWindow ={{false}}
                        />
        --}}
        @include('components.ledger.alert',[
            'message'=>__('Select Ledger or Folder'),
            'icon'=> 'fa-circle-info',
            'type'=>'warning',
            'refreshParentWindow'=>false,
        ])

    @endif
</div>
