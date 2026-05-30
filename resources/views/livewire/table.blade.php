<div>
    {{-- Success is as dangerous as failure. --}}
    <form wire:submit="search">
        <div class="mt-1">
            <input wire:model.live="keyword" name="keyword" id="tweet-keyword" type="text"
                   class="focus:ring-blue-400 focus:border-blue-400 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md p-2"
                   placeholder="探しちゃいなよ"></input>
        </div>
        <button
            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-500 hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-bule-500">
            Search
        </button>
    </form>
    @if(isset($ledgers))
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
</div>
