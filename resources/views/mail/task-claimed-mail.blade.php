<x-mail::message>
# {{ $greeting }}

{{ $line1 }}

@if($line2)
{{ $line2 }}
@endif

{{-- 状況に応じて詳細情報を表示 --}}
@if($recipientType === 'original_assignee')
{{ __('ledger.workflow.assignee') }}: {{ $newAssigneeName }}
@elseif($recipientType === 'applicant')
{{ __('ledger.workflow.original_assignee') }}: {{ $originalAssigneeName ?? __('ledger.workflow.no_original_assignee') }} <br>
{{ __('ledger.workflow.new_assignee') }}: {{ $newAssigneeName }}
@endif

@if($comment && $recipientType !== 'new_assignee') {{-- new_assignee には line2 でコメント表示済み --}}
<x-mail::panel>
**{{ __('ledger.mail.label.comment') }}**: {{ $comment }}
</x-mail::panel>
@endif

@isset($actionText)
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>
@endisset

{{ __('ledger.mail.footer.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>