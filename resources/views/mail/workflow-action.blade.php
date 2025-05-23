<x-mail::message>
# {{ $greeting }}

{{ $line1 }}


@isset($actionText)
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>
@endisset


@if($line2)
{{ $line2 }}
@endif

@if($comment)
<x-mail::panel>
    **{{ __('ledger.mail.label.comment') }}**: {{ $comment }}
</x-mail::panel>
@endif


{{ __('ledger.mail.footer.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>