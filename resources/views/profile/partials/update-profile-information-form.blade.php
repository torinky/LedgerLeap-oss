@php use App\Enums\LoginLandingPage;use Illuminate\Contracts\Auth\MustVerifyEmail; @endphp
<section>
    <header>
        {{-- テキスト色をテーマに合わせる --}}
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70"> {{-- 説明文は少し薄く --}}
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- Name Input --}}
        <div>
            {{-- maryUI Input: label プロパティでラベルを指定 --}}
            <x-mary-input
                label="{{ __('Name') }}"
                id="name"
                name="name"
                type="text"
                class="mt-1 block w-full"
                :value="old('name', $user->name)"
                required autofocus autocomplete="name"
            />
            {{-- Breeze のエラー表示は流用可能 --}}
            <x-input-error class="mt-2" :messages="$errors->get('name')"/>
        </div>

        {{-- Email Input --}}
        <div>
            <x-mary-input
                label="{{ __('Email') }}"
                id="email"
                name="email"
                type="email"
                class="mt-1 block w-full"
                :value="old('email', $user->email)"
                required autocomplete="username"
            />
            <x-input-error class="mt-2" :messages="$errors->get('email')"/>

            {{-- Email Verification 部分 --}}
            @if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2"> {{-- マージン調整 --}}
                    <p class="text-sm text-base-content"> {{-- テキスト色変更 --}}
                        {{ __('Your email address is unverified.') }}

                        {{-- ボタンのスタイル調整: maryUI の button or daisyUI link --}}
                        <button form="send-verification" class="link link-info text-sm"> {{-- daisyUI link スタイル例 --}}
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        {{-- maryUI の Alert コンポーネントを使う例 --}}
                        <x-mary-alert title="{{ __('A new verification link has been sent to your email address.') }}"
                                      class="alert-success mt-2 text-sm" icon="o-check-circle"/>
                        {{-- またはシンプルなテキスト --}}
                        {{-- <p class="mt-2 font-medium text-sm text-success">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p> --}}
                    @endif
                </div>
            @endif
        </div>

        {{--
                @php
                    // 現在の選択値を取得 (old ヘルパー優先)
                    $currentLandingPage = old('login_landing_page', $user->login_landing_page->value);
                    // Enum から MaryUI 用の options 配列を取得
                    $landingPageOptions = \App\Enums\LoginLandingPage::optionsForMaryUI($currentLandingPage);
                @endphp
                <x-mary-select
                    label="{{ __('ledger.landing_page_select_label') }}"
                    :options="$landingPageOptions"
                    name="login_landing_page"
                    id="login_landing_page"
                    class="mt-1 block w-full"
                />
                <x-input-error class="mt-2" :messages="$errors->get('login_landing_page')"/>
        --}}

        {{-- DaisyUI Select を使う場合 --}}
        <fieldset class="fieldset py-0 w-full"> {{-- DaisyUI の form-control で囲む --}}
            <legend class="fieldset-legend mb-0.5">{{ __('ledger.landing_page_select_label') }}</legend>
            <select name="login_landing_page" id="login_landing_page" class="select w-full mt-1 block w-full">
                @php
                    $currentLandingPage = old('login_landing_page', $user->login_landing_page);
                @endphp
                @foreach(LoginLandingPage::cases() as $case)
                    <option value="{{ $case->value }}" @selected($currentLandingPage === $case->value)>
                        {{ $case->label() }}
                    </option>
                @endforeach
            </select>
            {{-- エラー表示 --}}
            <div class="label">
                @error('login_landing_page')
                <span class="label-text-alt text-error">{{ $message }}</span>
                @enderror
            </div>
        </fieldset>
        <x-input-error class="mt-1" :messages="$errors->get('login_landing_page')"/> {{-- ← <x-input-error> は残してもOK --}}



        {{-- Save Button & Status --}}
        <div class="flex items-center gap-4">
            {{-- maryUI Button --}}
            <x-mary-button type="submit" class="btn-primary" spinner="save"> {{-- spinner を追加すると処理中に表示可能 --}}
                {{ __('Save') }}
            </x-mary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-base-content/70" {{-- テキスト色変更 --}}
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
