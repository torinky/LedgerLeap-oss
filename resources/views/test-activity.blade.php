{{-- resources/views/test-activity.blade.php --}}
<x-app-layout title="{{__('ledger.details')}}">
{{--<x-mary-main>--}}
    <x-mary-header title="アクティビティテスト" />

    <div class="p-6">
        {{-- 例1: Ledger ID: 1 の活動履歴 (関連リソース含む) --}}
        <h2 class="text-2xl font-bold mb-4">Ledger ID: 1 の活動履歴 (関連リソース含む)</h2>
        @livewire('common.activity-history-display', ['resourceId' => 1, 'resourceType' => 'Ledger', 'includeRelatedResources' => true])

        <hr class="my-8">

        {{-- 例2: LedgerDefine ID: 1 の活動履歴 --}}
        <h2 class="text-2xl font-bold mb-4">LedgerDefine ID: 1 の活動履歴</h2>
        @livewire('common.activity-history-display', ['resourceId' => 1, 'resourceType' => 'LedgerDefine'])

        <hr class="my-8">

        {{-- 例3: Folder ID: 1 の活動履歴 --}}
        <h2 class="text-2xl font-bold mb-4">Folder ID: 1 の活動履歴</h2>
        @livewire('common.activity-history-display', ['resourceId' => 1, 'resourceType' => 'Folder'])

        <hr class="my-8">

        {{-- 例4: 全件表示モード (UserActivityLog の代替) --}}
        <h2 class="text-2xl font-bold mb-4">全システム活動履歴</h2>
        @livewire('common.activity-history-display') {{-- resourceId, resourceType を指定しない --}}
    </div>
</x-app-layout>
{{--</x-mary-main>--}}