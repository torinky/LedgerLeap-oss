<div class="p-4">
    <form action="{{ $action ?? route('ledger.search') }}" method="post">
        @csrf
        <div class="mt-1">
            <input name="keyword" id="tweet-keyword" type="text"
                   class="focus:ring-blue-400 focus:border-blue-400 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md p-2"
                   placeholder="探しちゃいなよ"></input>
        </div>

        <div class="flex flex-wrap justify-end">
            <x-element.button>探す</x-element.button>
        </div>
    </form>
</div>
