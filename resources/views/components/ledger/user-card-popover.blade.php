@props(['user'])
<div x-data="{ open: false, copying: false }" @click.outside="open = false" class="relative inline-block">
    <!-- Trigger -->
    <button type="button" @click="open = !open"
        class="link link-primary font-medium hover:underline focus:outline-none flex items-center gap-1">
        {{ $user->name }}
    </button>

    <!-- Popover Content -->
    <div x-show="open" x-transition
        class="absolute left-0 top-full mt-2 z-50 w-72 bg-base-100 rounded-lg shadow-xl border border-base-200 p-4 text-left"
        style="display: none;">
        <!-- Header: Avatar & Name -->
        <div class="flex items-center gap-3 mb-3">
            <x-mary-avatar :title="$user->name" class="!w-12 !h-12" />
            <div>
                <div class="font-bold text-lg leading-tight">{{ $user->name }}</div>
                @php
                    $primaryOrg = $user->organizations->firstWhere('pivot.is_primary', true);
                @endphp
                @if ($primaryOrg)
                    <div class="text-sm text-gray-500 mt-0.5">
                        {{ $primaryOrg->name }}
                    </div>
                @endif
            </div>
        </div>

        <div class="divider my-1"></div>

        <!-- Email Copy -->
        <div class="flex items-center justify-between text-sm py-2">
            <span class="text-gray-500 font-medium">{{ __('ledger.access_and_permissions.column.email') }}</span>
            <div class="flex items-center gap-2">
                <span class="text-xs truncate max-w-[120px]" title="{{ $user->email }}">{{ $user->email }}</span>
                <button type="button" class="btn btn-ghost btn-xs btn-square text-gray-500 tooltip tooltip-left"
                    :class="copying ? 'text-success' : ''"
                    :data-tip="copying ? '{{ __('ledger.vlm.copied_short') }}' :
                        '{{ __('ledger.file_inspector.actions.copy') }}'"
                    @click="
                        navigator.clipboard.writeText('{{ $user->email }}').then(() => {
                            copying = true;
                            $dispatch('mary-toast', {type: 'success', title: '{{ __('ledger.file_inspector.messages.text_copied') }}'});
                            setTimeout(() => copying = false, 2000);
                        });
                    ">
                    <x-mary-icon name="o-clipboard" class="w-4 h-4" x-show="!copying" />
                    <x-mary-icon name="o-check" class="w-4 h-4" x-show="copying" x-cloak />
                </button>
            </div>
        </div>

        <!-- Chat Link -->
        @if ($user->chat_link)
            <div class="flex items-center justify-between text-sm py-2 border-t border-base-200">
                <span class="text-gray-500 font-medium">{{ __('ledger.user_info.chat') }}</span>
                <a href="{{ $user->chat_link }}" target="_blank"
                    class="btn btn-sm btn-primary btn-outline gap-2 no-underline">
                    <x-mary-icon name="o-chat-bubble-left-right" class="w-4 h-4" />
                    {{ __('ledger.user_info.chat_link') }}
                </a>
            </div>
        @endif
    </div>
</div>
