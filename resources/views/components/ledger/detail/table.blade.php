@props([
    'ledgerRecord' => [],
    'canView' => false,
    'allAttachments' => collect(),
    'filteredColumns' => null, // ★ 追加
])

<div class="container mx-auto">
    @if($ledgerRecord && $ledgerRecord->content && $ledgerRecord->define)
        <div class="card bg-base-100 shadow-xl mt-10">
            <div class="card-body">
                <table class="table table-zebra table-compact table-hover table-fixed w-full">
                    <tbody>
                    @php
                        // ★ filteredColumns があればそれ、なければ元の column_define を使う
                        $columnsToDisplay = $filteredColumns ?? $ledgerRecord->define->column_define;
                    @endphp
                    @foreach($columnsToDisplay as $cKey => $columnDefine)
                        <tr class="hover:bg-base-300">
                            <th class="w-1/3 lg:w-1/4 break-words">
                                {{-- $columnDefine が配列かオブジェクトか判定 --}}
                                {{ data_get($columnDefine, 'name') }}
                            </th>
                            <td class="break-words">
                                @php
                                    $columnId = data_get($columnDefine, 'id');
                                @endphp
                                @if (!$canView)
                                    <x-ledger.not-authorized-message />
                                @elseif (empty($ledgerRecord->content[$columnId]))
                                    <x-ledger.empty-message />
                                @else
                                    {!! ColumnHtml::setAttachmentCollection($allAttachments->keyBy('hashedbasename'))
                                        ->setAttachmentContents($ledgerRecord->content_attached[$columnId] ?? [])
                                        ->show($columnDefine, $ledgerRecord->content[$columnId] ?? '', $canView, [], '', false, $ledgerRecord) !!}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
