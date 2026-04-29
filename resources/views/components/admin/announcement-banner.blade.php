@props([
    'announcement' => null,
    'syncOffset' => true,
    'containerClass' => 'm-2',
    'respectDismissed' => true,
    'dismissible' => true,
    'stickyOverride' => null,
])

@if (is_array($announcement) && ! empty($announcement))
    @php
        $level = $announcement['level'] ?? 'info';
        $title = $announcement['title'] ?? '';
        $body = $announcement['body'] ?? '';
        $links = is_array($announcement['links'] ?? null) ? $announcement['links'] : [];
        $publishedAt = $announcement['published_at'] ?? $announcement['starts_at'] ?? $announcement['issued_at'] ?? null;
        $dismissKey = $announcement['dismiss_storage_key'] ?? 'ledgerleap.admin_announcement_banner.dismissed';
        $levelLabel = match ($level) {
            'warning' => __('ledger.warning'),
            'critical' => __('ledger.critical'),
            default => __('ledger.info'),
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
        $isSticky = is_bool($stickyOverride)
            ? $stickyOverride
            : ($isCritical || (bool) ($announcement['sticky'] ?? false));
        $syncOffsetEnabled = (bool) $syncOffset;
        $respectDismissed = (bool) $respectDismissed;
        $dismissible = (bool) $dismissible;
        $canDismiss = $dismissible && ! $isCritical;
        $levelClass = match ($level) {
            'warning' => 'alert-warning',
            'critical' => 'alert-error',
            default => 'alert-info',
        };
        $linkClass = match ($level) {
            'warning' => 'btn-warning',
            'critical' => 'btn-error',
            default => 'btn-info',
        };
    @endphp

    <div
            data-admin-announcement-banner
            wire:key="admin-announcement-banner-{{ $dismissKey }}"
            x-data="{
            visible: true,
            dismissKey: {{ json_encode($dismissKey) }},
            respectDismissed: {{ $respectDismissed ? 'true' : 'false' }},
            dismissible: {{ $dismissible ? 'true' : 'false' }},
            syncOffsetEnabled: {{ $syncOffsetEnabled ? 'true' : 'false' }},
            init() {
                this.visible = this.respectDismissed
                    ? localStorage.getItem(this.dismissKey) !== '1'
                    : true;
                this.syncOffset();
            },
            syncOffset() {
                if (! this.syncOffsetEnabled) {
                    return;
                }

                requestAnimationFrame(() => {
                    document.documentElement.style.setProperty('--admin-announcement-banner-offset', this.visible ? `${this.$el.offsetHeight}px` : '0px');
                });
            },
            dismiss() {
                if (! this.dismissible) {
                    return;
                }

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
            class="{{ $containerClass }} alert alert-soft {{ $levelClass }}
                alert-vertical md:alert-horizontal items-center gap-3 overflow-hidden border border-base-300 bg-linear-to-r p-3 m-2 shadow-none
                relative z-50 md:px-4 md:py-3.5 {{ $isSticky ? 'sticky top-0 z-50' : '' }} {{ $palette['surface'] }} "
    >
        <div class="absolute inset-x-0 top-0 h-1 bg-linear-to-r {{ $palette['accent'] }} motion-safe:animate-pulse"></div>

        <div class="tooltip tooltip-right tooltip-primary shrink-0 self-center" data-tip="{{ $levelLabel }}">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $palette['iconBg'] }} motion-safe:animate-pulse sm:h-14 sm:w-14">
                <x-mary-icon name="{{ $palette['icon'] }}" class="h-7 w-7 sm:h-8 sm:w-8"/>
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

        @if (filled($publishedAt))
            <span class="tooltip tooltip-bottom tooltip-primary" data-tip="{{ __('ledger.published_at') }}">
                        <span class="badge badge-outline badge-sm gap-1 px-2 py-3 font-mono text-[10px] sm:text-[11px]">
                            <x-mary-icon name="o-clock" class="h-3.5 w-3.5"/>
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
                            class="btn btn-soft btn-sm {{ $linkClass }}"
                    >
                        {{ $linkLabel }}
                    </a>
                @endif
            @endforeach
        @endif

        @if ($canDismiss)
            <span class="tooltip tooltip-left tooltip-primary shrink-0" data-tip="{{ __('ledger.close') }}">
                <button
                        type="button"
                        class="btn btn-ghost btn-circle btn-sm self-center"
                        @click="dismiss()"
                        aria-label="{{ __('ledger.close') }}"
                >
                    <x-mary-icon name="o-x-mark" class="h-5 w-5"/>
                </button>
            </span>
        @endif
    </div>
@endif
