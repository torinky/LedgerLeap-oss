@props(['columnName', 'oldValue', 'newValue', 'isChanged', 'columnType'])

<div class="grid grid-cols-1 md:grid-cols-12 gap-2 py-2 border-b border-base-300 {{ $isChanged ? 'bg-warning/10' : '' }}">
    <div class="md:col-span-3 font-semibold text-sm text-base-content/80 break-words">
        {{ $columnName }}
        @if($isChanged)
            <span class="text-xs text-warning ml-1">({{ __('ledger.changed') }})</span>
        @endif
    </div>
    <div class="md:col-span-4 text-sm break-words">
        @if($isChanged)
            <div class="text-xs text-base-content/60 mb-1">{{ __('ledger.before_change_colon') }}</div>
            <div class="{{ $columnType === 'textarea' ? 'whitespace-pre-wrap' : '' }}">
                {!! $oldValue !!}
            </div>
        @endif
    </div>
    <div class="md:col-span-5 text-sm break-words {{ $isChanged ? '' : 'md:col-start-4' }}">
        @if($isChanged)
            <div class="text-xs text-base-content/60 mb-1">{{ __('ledger.after_change_colon') }}</div>
        @endif
        <div class="{{ $columnType === 'textarea' ? 'whitespace-pre-wrap' : '' }}">
            {!! $newValue !!}
        </div>
    </div>
</div>