@props([
    'ledgerRecord'=>null,
    'highlightKeyword'=>null,
    'canUpdate'=>false,
    'canView'=>false,
    'allAttachments' => [],
    'filteredColumnDefines' => [],
    'currentTenantId' => null,
    ])
<tr class="hover group hover:bg-accent/20">
    <th class=" border flex-col bg-accent/20">
        <div class="tooltip tooltip-right"
             data-tip="{{__('ledger.edit')}}"
        >
            @if($canUpdate && !$ledgerRecord->isLocked())
                <a href="{{ route('ledger.edit', ['tenant' => tenant()?->id, 'ledgerId'=>$ledgerRecord->id]) }}"
                   class="btn btn-neutral opacity-70 hover:opacity-100 btn-sm my-1 btn-square"
                   target="ledgerEdit_{{$ledgerRecord->define->id}}}}"
                >
                    <i class="fas fa-pencil"></i>
                </a>
            @else
                <div class="tooltip tooltip-right"
                     data-tip="{{ $ledgerRecord->isLocked() ? __('ledger.workflow.record_locked') : __('ledger.no_edit_permission') }}">
                    <button class="btn btn-neutral opacity-70 btn-sm my-1 btn-square" disabled>
                        <i class="fas fa-pencil"></i>
                    </button>
                </div>
            @endif
        </div>


        <div class="tooltip tooltip-right"
             data-tip="{{__('ledger.show_details')}}"
        >
            <a href="{{ route('ledger.show', ['tenant' => $ledgerRecord->tenant_id ?? tenant()?->id, 'ledgerId'=>$ledgerRecord->id, 'highlight' => $highlightKeyword]) }}"
               class="btn btn-outline btn-info btn-sm my-1 btn-square opacity-70 hover:opacity-100"
               target="ledgerShow_{{$ledgerRecord->define->id}}}}">
                <i class="fas fa-table-list"></i>
            </a>
        </div>

    </th>

    @foreach($filteredColumnDefines as $cKey=>$columnDefine)
        <td class="hover:bg-accent/20 border px-4 py-2">
            @if (!$canView)
                <x-ledger.not-authorized-message/>
            @elseif (empty($ledgerRecord->content[$columnDefine->id]))
                <x-ledger.empty-message/>
            @else
                @php
                    $columnHtml = ColumnHtml::setAttachmentCollection($allAttachments->get($ledgerRecord->id, collect())->keyBy('hashedbasename'))
                                 ->setAttachmentContents($ledgerRecord->content_attached[$columnDefine->id] ?? [])
                                 ->show($columnDefine, $ledgerRecord->content[$columnDefine->id], $canView, [], '', false, $ledgerRecord, $highlightKeyword, tenant()?->id);
                    $columnHtmlString = $columnHtml->toHtml();
                @endphp
                
                <x-expandable-content 
                    :content="$columnHtmlString"
                    max-height="6rem"
                />
            @endif
        </td>
    @endforeach
    {{--                        <td class="border px-4 py-2 break-words whitespace-pre-wrap">{{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}--}}


    <td class="border px-4 py-2 relative">
        {{$ledgerRecord->updated_at->format('Y-m-d H:i:s')}}
        <span class="text-gray-500">{{JpDatetime::date('(bk)',$ledgerRecord->updated_at->timestamp)}}</span>
        <br/>( {{ $ledgerRecord->updated_at->diffForHumans() }} )

        <!-- スコア・ステータスのオーバーレイ表示 -->
        <div class="absolute top-1 right-2 z-10 flex items-center gap-2 transition-opacity duration-300 opacity-30 group-hover:opacity-100 backdrop-blur-sm p-1 rounded-lg">
            @php
                $scoreClass = match(true) {
                    $ledgerRecord->composite_score >= 70 => 'badge-success',
                    $ledgerRecord->composite_score >= 40 => 'badge-primary',
                    $ledgerRecord->composite_score >= 20 => 'badge-info',
                    $ledgerRecord->composite_score > 0 => 'badge-ghost',
                    default => ''
                };
                // ステータスに応じたアイコンを決定 (Enumから取得)
                $statusIcon = $ledgerRecord->status->icon();
            @endphp
            @if($ledgerRecord->composite_score > 0)
                <span class="badge badge-xl {{ $scoreClass }} flex items-center gap-1">
                    <i class="fas fa-star"></i> {{-- スコアアイコン --}}
                    {{ number_format($ledgerRecord->composite_score, 1) }}
                </span>
            @endif

            @if($ledgerRecord->define->workflow_enabled && $ledgerRecord->status)
                <span class="badge badge-lg {{ $ledgerRecord->status->colorClass() }} flex items-center gap-1">
                    <i class="{{ $statusIcon }}"></i> {{-- ステータスアイコン --}}
                    {{ $ledgerRecord->status->label() }}
                </span>
            @endif
        </div>
    </td>
    {{--                <td class="border px-4 py-2">{{ $ledgerRecords->created_at }}</td>--}}
</tr>
