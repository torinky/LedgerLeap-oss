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
