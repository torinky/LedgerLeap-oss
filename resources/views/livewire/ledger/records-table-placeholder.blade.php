{{-- RecordsTable ローディングプレースホルダー
     #[Lazy] により、フォルダ切替時に IndexManager の応答（~100ms）に含まれて表示される。
     実コンテンツは別リクエストで非同期レンダリングされる。 --}}
<div class="records-list-container mt-6">
    @foreach (range(1, 2) as $i)
        <div class="card bg-base-100 shadow-xl my-10 border border-base-200 overflow-hidden">
            <div class="card-body pt-0 px-0">
                <div class="bg-base-300 mt-0 px-4 py-4 rounded-t-box flex items-center gap-4 shimmer">
                    <div class="h-8 w-8 bg-base-content/10 rounded-full"></div>
                    <div class="h-6 bg-base-content/10 rounded-lg w-1/3"></div>
                </div>
                <x-element.skeleton-table rows="8" cols="10"/>
            </div>
        </div>
    @endforeach
</div>

