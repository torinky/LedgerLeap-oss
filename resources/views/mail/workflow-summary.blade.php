<x-mail::message>
# {{ __('ledger.mail.greeting.summary') }}

{{ __('ledger.mail.body.line1.summary') }}

* **{{ __('ledger.workflow.status.pending_inspection') }}**: {{ $inspectionCount }}
* **{{ __('ledger.workflow.status.pending_approval') }}**: {{ $approvalCount }}
* **{{ __('ledger.mail.label.total') }}**: {{ $totalCount }}

<x-mail::button :url="$actionUrl">
{{ __('ledger.mail.action.view_tasks') }}
</x-mail::button>

{{ __('ledger.mail.body.line2.summary') }}

{{ __('ledger.mail.footer.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>