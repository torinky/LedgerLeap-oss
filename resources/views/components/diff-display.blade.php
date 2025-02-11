{{-- resources/views/components/notification-diff.blade.php --}}
@props(['attribute', 'old', 'new', 'mode' => 'table', 'showLabel' => true])

@if($mode === 'table')
    <tr>
        <td>
            @if($showLabel)
                {{ __('ledger.' . $attribute) }}
            @endif
        </td>
        <td>
            <pre class="text-xs whitespace-pre-wrap"><code>{!! $oldHtml !!}</code></pre>
        </td>
        <td>
            <pre class="text-xs whitespace-pre-wrap"><code>{!! $newHtml !!}</code></pre>
        </td>
    </tr>
@elseif($mode === 'inline')
    <span>
        @if(isset($old))
            <del class="text-error text-xs">{{ $old }}</del> →
        @endif
        <span class="text-success text-xs">{{ $new }}</span>
    </span>
@elseif($mode === 'new')
    <span class="text-success text-xs">{{  $new }}</span>
@elseif($mode === 'old')
    <span class="text-error text-xs">{{ $old }}</span>
@elseif($mode === 'json')
    <pre
        class="text-xs whitespace-pre-wrap">{{ json_encode(compact('old', 'new'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
@endif
