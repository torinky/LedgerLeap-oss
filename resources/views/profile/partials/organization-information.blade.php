<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ledger.organization_section_title') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('ledger.organization_section_description') }}
        </p>
    </header>

    {{-- mary-ui の List を使う例 --}}
    {{--    <x-mary-list>--}}
    @forelse ($organizations as $organization)
        <x-mary-list-item :item="$organization">
            <x-slot:value>
                {{ $organization->name }}
                {{-- 主所属の場合にバッジを表示 --}}
                @if ($organization->id === $primaryOrganizationId)
                    <x-mary-badge value="{{ __('ledger.organizations.primary') }}"
                                  class="badge-primary badge-sm ms-2"/>
                @endif
            </x-slot:value>
            {{-- 必要であれば組織の説明などを表示 --}}
            <x-slot:sub-value>
                {{ $organization->description }}
            </x-slot:sub-value>
            {{-- 将来的に組織詳細へのリンクなどを追加する場合 --}}
            <x-slot:actions>
                <x-mary-button label="{{__('actions.details')}}" link="#" class="btn-ghost btn-sm"/>
            </x-slot:actions>
        </x-mary-list-item>
    @empty
        <p class="text-sm text-base-content/70">{{ __('ledger.organization_empty') }}</p>
    @endforelse
    {{--    </x-mary-list>--}}

    {{-- AD連携ステータスエリア --}}
    @if(Auth::user()->ad_last_synced_at)
        <div class="mt-8 pt-4 border-t border-base-300">
            <h3 class="text-md font-medium text-base-content mb-3">
                {{ __('ledger.ad_sync_status_title') }}
            </h3>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-base-content/70">{{ __('ledger.last_synced_at') }}</p>
                    <p class="text-lg font-medium">{{ Auth::user()->ad_last_synced_at->format('Y-m-d H:i') }}</p>
                </div>
                @if(Auth::user()->ignore_ad_org_sync_until && Auth::user()->ignore_ad_org_sync_until->isFuture())
                    <div class="text-right">
                        <x-mary-badge :value="__('ledger.manual_sync_enabled')" class="badge-warning mb-1"/>
                        <p class="text-xs text-warning">{{ __('ledger.manual_sync_until', ['date' => Auth::user()->ignore_ad_org_sync_until->format('Y-m-d')]) }}</p>
                    </div>
                @else
                    <x-mary-badge :value="__('ledger.auto_sync_enabled')" class="badge-success"/>
                @endif
            </div>
        </div>
    @endif

    {{-- シンプルな ul リストを使う例 --}}
    {{-- <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
        @forelse ($organizations as $organization)
            <li>
                {{ $organization->name }}
                @if ($organization->id === $primaryOrganizationId)
                    <span class="ml-2 text-xs font-medium text-indigo-600 dark:text-indigo-400">(主所属)</span>
                @endif
            </li>
        @empty
            <li>{{ __('所属している組織やプロジェクトはありません。') }}</li>
        @endforelse
    </ul> --}}

</section>
