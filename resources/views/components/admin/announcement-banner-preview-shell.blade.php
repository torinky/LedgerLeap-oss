@props([
    'announcement' => null,
])

@if (is_array($announcement) && ! empty($announcement))
    @php
        $scopeLabels = \App\Filament\Resources\AdminAnnouncementResource::scopeDisplayLabels($announcement['scope'] ?? []);
        $sticky = (bool) ($announcement['sticky'] ?? false);
        $publishedAt = $announcement['published_at'] ?? $announcement['starts_at'] ?? $announcement['issued_at'] ?? null;
    @endphp

    <section data-theme="{{ config('daisyui.themes.light') }}" class="overflow-hidden rounded-[1.75rem] border border-base-300 bg-base-100 shadow-[0_18px_40px_rgba(15,23,42,0.08)]">
        <div class="flex flex-col gap-3 border-b border-base-200 bg-base-100 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-info/15 text-info">
                        <x-mary-icon name="o-window" class="h-4 w-4" />
                    </span>
                    <h3 class="text-sm font-semibold text-base-content sm:text-base">
                        {{ __('ledger.admin_announcement_banner_display_state_title') }}
                    </h3>
                </div>
                <p class="text-xs leading-5 text-base-content/60 sm:text-sm">
                    {{ __('ledger.admin_announcement_banner_preview_summary') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @foreach ($scopeLabels as $scopeLabel)
                    <span class="badge badge-outline badge-sm gap-1">
                        <x-mary-icon name="o-squares-2x2" class="h-3.5 w-3.5" />
                        {{ $scopeLabel }}
                    </span>
                @endforeach

                <span class="badge {{ $sticky ? 'badge-primary' : 'badge-ghost' }} badge-sm gap-1">
                    <x-mary-icon name="o-bookmark" class="h-3.5 w-3.5" />
                    {{ $sticky ? __('ledger.admin_announcement_banner_sticky_on') : __('ledger.admin_announcement_banner_sticky_off') }}
                </span>

                @if (filled($publishedAt))
                    <span class="badge badge-outline badge-sm gap-1 font-mono text-[10px] sm:text-[11px]">
                        <x-mary-icon name="o-clock" class="h-3.5 w-3.5" />
                        {{ $publishedAt }}
                    </span>
                @endif
            </div>
        </div>

        <div class="relative isolate bg-base-200/25 px-4 py-4 sm:px-5 sm:py-5">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,_rgba(14,165,233,0.10),_transparent_24%),radial-gradient(circle_at_bottom_left,_rgba(34,197,94,0.08),_transparent_28%)]"></div>

            <div class="space-y-4">
                <x-admin.announcement-banner :announcement="$announcement" />

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]">
                    <section class="rounded-2xl border border-base-300 bg-base-100/90 p-4 shadow-sm backdrop-blur">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-semibold text-base-content">{{ __('ledger.detailed_information_title') }}</h4>
                                <span class="badge badge-ghost badge-sm">{{ __('ledger.details') }}</span>
                            </div>

                            <p class="text-sm leading-6 text-base-content/70">
                                {{ __('ledger.admin_announcement_banner_description') }}
                            </p>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-base-300 bg-base-200/50 p-4">
                                    <p class="text-sm font-semibold">{{ __('ledger.admin_announcement_banner_management_title') }}</p>
                                    <p class="mt-1 text-sm leading-6 text-base-content/70">{{ __('ledger.admin_announcement_banner_management_hint') }}</p>
                                </div>
                                <div class="rounded-2xl border border-base-300 bg-base-200/50 p-4">
                                    <p class="text-sm font-semibold">{{ __('ledger.admin_announcement_banner_preview_title') }}</p>
                                    <p class="mt-1 text-sm leading-6 text-base-content/70">{{ __('ledger.admin_announcement_banner_preview_summary') }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <aside class="space-y-4">
                        <div class="rounded-2xl border border-base-300 bg-base-100/90 p-4 shadow-sm backdrop-blur">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ __('ledger.details') }}</p>
                            <div class="mt-3 space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-base-content/55">{{ __('ledger.admin_announcement_banner_field_title') }}</span>
                                    <span class="font-medium text-right">{{ $announcement['title'] ?? '' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-base-content/55">{{ __('ledger.admin_announcement_banner_level_label') }}</span>
                                    <span class="font-medium capitalize text-right">{{ $announcement['level'] ?? 'info' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-base-content/55">{{ __('ledger.published_at') }}</span>
                                    <span class="font-mono text-xs text-right">{{ $publishedAt }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-base-300 bg-base-100/90 p-4 shadow-sm backdrop-blur">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ __('ledger.admin_announcement_banner_display_state_title') }}</p>
                            <ul class="mt-3 space-y-2 text-sm leading-6 text-base-content/75">
                                <li>・{{ __('ledger.admin_announcement_banner_publish_scope') }}</li>
                                <li>・{{ __('ledger.admin_announcement_banner_sticky_label') }}</li>
                                <li>・{{ __('ledger.admin_announcement_banner_preview_title') }}</li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </section>
@endif
