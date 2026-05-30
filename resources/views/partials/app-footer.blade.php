@php
    $yearStart  = (int) config('ledgerleap.branding.copyright_year_start', date('Y'));
    $yearNow    = (int) date('Y');
    $yearRange  = $yearStart < $yearNow ? "{$yearStart}–{$yearNow}" : (string) $yearNow;
    $owner      = config('ledgerleap.branding.copyright_owner', config('app.name'));
    $supportUrl = config('ledgerleap.branding.support_url');
    $email      = config('ledgerleap.branding.support_email');
    $forumUrl   = config('ledgerleap.branding.forum_url');
    $hasLinks   = $supportUrl || $email || $forumUrl;
@endphp

<footer id="app-footer" class="border-t border-base-300 bg-base-100 text-base-content/60 text-xs py-4 px-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 max-w-7xl mx-auto">
        <span>© {{ $yearRange }} {{ $owner }}. {{ __('ledger.footer.all_rights_reserved') }}</span>

        @if ($hasLinks)
            <div class="flex flex-wrap gap-4 items-center">
                <span class="font-medium text-base-content/50">{{ __('ledger.footer.contact') }}:</span>

                @if ($supportUrl)
                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener noreferrer"
                       class="link link-hover flex items-center gap-1">
                        <i class="fa-solid fa-circle-question text-sm" aria-hidden="true"></i>
                        <span>{{ __('ledger.footer.support') }}</span>
                    </a>
                @endif

                @if ($email)
                    <a href="mailto:{{ $email }}" class="link link-hover flex items-center gap-1">
                        <i class="fa-solid fa-envelope text-sm" aria-hidden="true"></i>
                        <span>{{ __('ledger.footer.support_email') }}</span>
                    </a>
                @endif

                @if ($forumUrl)
                    <a href="{{ $forumUrl }}" target="_blank" rel="noopener noreferrer"
                       class="link link-hover flex items-center gap-1">
                        <i class="fa-solid fa-comments text-sm" aria-hidden="true"></i>
                        <span>{{ __('ledger.footer.forum') }}</span>
                    </a>
                @endif
            </div>
        @endif
    </div>
</footer>
