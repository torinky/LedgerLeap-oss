{{-- resources/views/profile/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        {{-- ヘッダーのテキスト色も daisyUI テーマに合わせる --}}
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Profile Information セクション --}}
            <x-mary-card shadow="sm"> {{-- shadow で影の調整が可能 --}}
                {{-- コンテンツの最大幅は維持 --}}
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </x-mary-card>

            {{-- 所属情報セクション (ステップ1で追加する場合) --}}
            <x-mary-card shadow="sm">
                <div class="max-w-xl">
                    @include('profile.partials.organization-information')
                </div>
            </x-mary-card>

            {{-- Update Password セクション --}}
            <x-mary-card shadow="sm">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </x-mary-card>

            {{-- Delete Account セクション --}}
            <x-mary-card shadow="sm">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </x-mary-card>
        </div>
    </div>
</x-app-layout>
