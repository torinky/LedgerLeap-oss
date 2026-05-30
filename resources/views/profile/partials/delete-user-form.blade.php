<section class="space-y-6">
    <header>
        {{-- テキスト色変更 --}}
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    {{-- モーダルを開くボタン: maryUI Button (エラー/危険を示すスタイル) --}}
    <x-mary-button
        label="{{ __('Delete Account') }}"
        class="btn-error"
        wire:click="$dispatch('open-modal', { id: 'confirm-user-deletion' })" {{-- maryUI のモーダルを開くイベント --}}
    />

    {{-- maryUI モーダル --}}
    <x-mary-modal id="confirm-user-deletion" title="{{ __('Are you sure you want to delete your account?') }}">
        {{-- モーダル内のコンテンツ --}}
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf
            @method('delete')

            <p class="mt-1 text-sm text-base-content/70">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                {{-- maryUI Input --}}
                <x-mary-input
                    label="{{ __('Password') }}" {{-- ラベルを表示（sr-only は不要に） --}}
                id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full" {{-- 幅調整が必要な場合がある --}}
                    placeholder="{{ __('Password') }}"
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            {{-- アクションボタンは actions スロットに入れる --}}
            <x-slot:actions>
                {{-- maryUI Button でキャンセル (閉じるイベントを発行) --}}
                <x-mary-button label="{{ __('Cancel') }}" @click.prevent="$dispatch('close-modal')"/>
                {{-- maryUI Button で削除実行 (エラー/危険を示すスタイル) --}}
                <x-mary-button type="submit" label="{{ __('Delete Account') }}" class="btn-error"/>
            </x-slot:actions>
        </form>
    </x-mary-modal>
</section>
