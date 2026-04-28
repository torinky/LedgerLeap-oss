@props([
    'announcement' => null,
])

@if (is_array($announcement) && ! empty($announcement))
    @php
        $level = $announcement['level'] ?? 'info';
        $title = $announcement['title'] ?? '';
        $body = $announcement['body'] ?? '';
        $links = is_array($announcement['links'] ?? null) ? $announcement['links'] : [];
        $scope = $announcement['scope'] ?? 'current_tenant';
        $sticky = (bool) ($announcement['sticky'] ?? false);
        $publishedAt = $announcement['published_at'] ?? $announcement['starts_at'] ?? $announcement['issued_at'] ?? null;
        $dismissKey = $announcement['dismiss_storage_key'] ?? 'ledgerleap.admin_announcement_banner.dismissed';
        $levelLabel = match ($level) {
            'warning' => __('ledger.warning'),
            'critical' => __('ledger.critical'),
            default => __('ledger.info'),
        };
        $scopeLabel = match ($scope) {
            'all_tenants' => __('ledger.admin_announcement_banner_scope_all_tenants'),
            default => __('ledger.admin_announcement_banner_scope_current_tenant'),
        };

        $palette = match ($level) {
            'warning' => [
                'accent' => 'from-transparent via-warning/75 to-transparent',
                'card' => 'border-warning/30',
                'surface' => 'from-warning/15 via-warning/10 to-base-100/85',
                'glow' => 'from-warning/30 via-warning/18 to-warning/24',
                'glowColor' => 'var(--color-warning)',
                'iconBg' => 'bg-warning/20 text-warning',
                'icon' => 'o-exclamation-triangle',
            ],
            'critical' => [
                'accent' => 'from-transparent via-error/75 to-transparent',
                'card' => 'border-error/30',
                'surface' => 'from-error/15 via-error/10 to-base-100/85',
                'glow' => 'from-error/30 via-error/18 to-error/24',
                'glowColor' => 'var(--color-error)',
                'iconBg' => 'bg-error/20 text-error',
                'icon' => 'o-exclamation-circle',
            ],
            default => [
                'accent' => 'from-transparent via-info/75 to-transparent',
                'card' => 'border-info/30',
                'surface' => 'from-info/15 via-info/10 to-base-100/85',
                'glow' => 'from-info/30 via-info/18 to-info/24',
                'glowColor' => 'var(--color-info)',
                'iconBg' => 'bg-info/20 text-info',
                'icon' => 'o-information-circle',
            ],
        };

        $isCritical = $level === 'critical';
    @endphp

    <div
        data-admin-announcement-banner
        wire:key="admin-announcement-banner-{{ $dismissKey }}"
        x-data="{
            visible: true,
            dismissKey: @js($dismissKey),
            init() {
                this.visible = localStorage.getItem(this.dismissKey) !== '1';
                this.syncOffset();
            },
            syncOffset() {
                requestAnimationFrame(() => {
                    document.documentElement.style.setProperty('--admin-announcement-banner-offset', this.visible ? `${this.$el.offsetHeight}px` : '0px');
                });
            },
            dismiss() {
                localStorage.setItem(this.dismissKey, '1');
                this.visible = false;
            },
        }"
        x-init="init()"
        x-show="visible"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.985]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-220"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.985]"
        @transitionend="if (! visible) syncOffset()"
        x-cloak
        @resize.window="syncOffset()"
        role="alert"
        aria-live="{{ $isCritical ? 'assertive' : 'polite' }}"
        class="alert alert-soft {{ $level === 'warning' ? 'alert-warning' : ($level === 'critical' ? 'alert-error' : 'alert-info') }} alert-vertical md:alert-horizontal items-center gap-3 overflow-hidden border border-base-300/40 bg-gradient-to-r px-3 py-3 shadow-none relative z-40 md:px-4 md:py-3.5 {{ $isCritical ? 'sticky top-0 z-50' : '' }} {{ $palette['surface'] }}"
    >
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $palette['accent'] }} motion-safe:animate-pulse"></div>
        <div
            class="admin-announcement-banner-gradient pointer-events-none absolute inset-0 opacity-90 mix-blend-soft-light"
            style="--admin-announcement-banner-glow: {{ $palette['glowColor'] }};"
        ></div>

        <div class="relative z-10 flex w-full items-start gap-3 px-0 py-0 md:items-center md:gap-4">
            <div class="flex min-w-0 flex-1 items-start gap-3">
                <div class="tooltip tooltip-right tooltip-primary shrink-0 self-center" data-tip="{{ $levelLabel }}">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $palette['iconBg'] }} motion-safe:animate-pulse sm:h-14 sm:w-14">
                        <x-mary-icon name="{{ $palette['icon'] }}" class="h-7 w-7 sm:h-8 sm:w-8" />
                    </div>
                </div>

                <div class="min-w-0 flex-1 space-y-1.5">
                    @if ($title !== '')
                        <h3 class="text-sm font-semibold leading-5 text-base-content sm:text-[15px]">
                            {{ $title }}
                        </h3>
                    @endif

                    @if ($body !== '')
                        <p class="text-sm leading-5 text-base-content/75 sm:text-[15px] sm:leading-6">
                            {{ $body }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="ml-auto flex shrink-0 flex-wrap items-center justify-end gap-2 md:flex-nowrap md:pl-4">
                <span class="badge badge-ghost badge-sm">
                    {{ $scopeLabel }}
                </span>

                @if ($sticky)
                    <span class="badge badge-primary badge-sm">
                        {{ __('ledger.admin_announcement_banner_sticky_on') }}
                    </span>
                @endif

                @if (filled($publishedAt))
                    <span class="tooltip tooltip-bottom tooltip-primary" data-tip="{{ __('ledger.published_at') }}">
                        <span class="badge badge-outline badge-sm gap-1 px-2 py-3 font-mono text-[10px] sm:text-[11px]">
                            <x-mary-icon name="o-clock" class="h-3.5 w-3.5" />
                            {{ $publishedAt }}
                        </span>
                    </span>
                @endif

                @if ($links !== [])
                    @foreach ($links as $link)
                        @php
                            $linkUrl = is_array($link) ? ($link['url'] ?? null) : null;
                            $linkLabel = is_array($link) ? ($link['label'] ?? __('ledger.details')) : __('ledger.details');
                        @endphp

                        @if (filled($linkUrl))
                            <a
                                href="{{ $linkUrl }}"
                                class="btn btn-soft btn-sm {{ $level === 'warning' ? 'btn-warning' : ($level === 'critical' ? 'btn-error' : 'btn-info') }}"
                            >
                                {{ $linkLabel }}
                            </a>
                        @endif
                    @endforeach
                @endif

                @if (! $isCritical)
                    <span class="tooltip tooltip-left tooltip-primary shrink-0" data-tip="{{ __('ledger.close') }}">
                        <button
                            type="button"
                            class="btn btn-ghost btn-circle btn-sm self-center"
                            @click="dismiss()"
                            aria-label="{{ __('ledger.close') }}"
                        >
                            <x-mary-icon name="o-x-mark" class="h-5 w-5" />
                        </button>
                    </span>
                @endif
            </div>
        </div>
    </div>
@endif