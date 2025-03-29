<div>
    @push('stylesheets')
        @vite(['resources/css/tree.css'])
    @endpush

    <x-slot name="header">
        {{-- 修正: maryUI Header を追加 --}}
        <x-mary-header :title="__('ledger.my_portal_title')" subtitle="ようこそ、{{ Auth::user()->name }} さん！"
                       size="text-xl" separator progress-indicator>
            {{-- 必要であれば右側にアクションボタンなどを追加できる --}}
            <x-slot:actions>
                <x-mary-button label="設定" icon="o-cog-6-tooth" link="{{ route('profile.edit') }}" class="btn-ghost"/>
            </x-slot:actions>
        </x-mary-header>
    </x-slot>


    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 3xl:grid-cols-3 gap-6">

        {{-- 役割と所属エリア --}}
        <x-mary-card title="{{ __('ledger.roles_and_affiliations_title') }}" shadow="sm"> {{-- 翻訳キー --}}
            {{-- 主な役割/担当 --}}
            <div class="mb-4">
                <h3 class="text-md font-medium text-base-content mb-1">
                    {{ __('ledger.main_role_and_affiliation_title') }} {{-- 翻訳キー --}}
                </h3>
                <p class="text-base-content/90">{{ $roleDisplayString }}</p>
            </div>

            {{-- その他の所属 (あれば表示) --}}
            @if($otherOrganizations->isNotEmpty())
                <div class="mt-4">
                    <h3 class="text-md font-medium text-base-content mb-1">
                        {{ __('ledger.other_affiliations_title') }} {{-- 翻訳キー --}}
                    </h3>
                    {{--                        <x-mary-list>--}}
                    @foreach($otherOrganizations as $organization)
                        <x-mary-list-item :item="$organization" no-separator no-hover>
                            <x-slot:value>
                                {{ $organization->name }}
                            </x-slot:value>
                        </x-mary-list-item>
                    @endforeach
                    {{--                        </x-mary-list>--}}
                </div>
            @endif

            {{-- 全ロールリスト（デバッグ用や初期確認用として表示） --}}
            <div class="mt-4 pt-4 border-t border-base-300"> {{-- 区切り線 --}}
                <h3 class="text-md font-medium text-base-content mb-1">
                    {{ __('ledger.your_effective_roles_title') }} {{-- 翻訳キー --}}
                </h3>
                @if($activeRoles->isNotEmpty())
                    <div class="flex flex-wrap gap-1">
                        @foreach($activeRoles as $role)
                            {{-- 翻訳キー例: ledgerleap.role_label.xxx --}}
                            @php
                                $roleKey = $role->label ?? $role->name;
                                $translationKey = 'ledger.role_label.' . $roleKey;
                                $displayName = trans()->has($translationKey) ? __($translationKey) : $roleKey;
                            @endphp
                            <x-mary-badge :value="$displayName" class="badge-neutral"/>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-base-content/70">{{ __('ledger.no_roles_assigned') }}</p> {{-- 翻訳キー --}}
                @endif
            </div>

        </x-mary-card>

        <x-mary-card title="{{ __('ledger.main_abilities_title') }}" shadow="sm">
            @if(!empty($majorPermissions))
                <ul class="space-y-2"> {{-- アイコンとテキストの間のスペース調整 --}}
                    @foreach($majorPermissions as $permission)
                        <li class="flex items-center">
                            @if($permission['has'])
                                {{-- daisyUI check icon in success color --}}
                                <x-mary-icon name="o-check-circle" class="w-5 h-5 text-success mr-2 shrink-0"/>
                            @else
                                {{-- daisyUI x icon in error or muted color --}}
                                <x-mary-icon name="o-x-circle"
                                             class="w-5 h-5 text-error/50 mr-2 shrink-0"/> {{-- 少し薄く表示 --}}
                            @endif
                            <span class="{{ $permission['has'] ? 'text-base-content' : 'text-base-content/70' }}"> {{-- できない項目は少し薄く --}}
                                {{ $permission['description'] }}
                                </span>
                        </li>
                    @endforeach
                </ul>
            @else
                {{-- 基本的な操作権限があることを示すテキスト --}}
                <p class="text-sm text-base-content/70">{{ __('ledger.basic_operations_permission') }}</p>
            @endif
        </x-mary-card>

        {{-- あなたの担当フォルダエリア (ステップ3で追加) --}}
        {{--
                    <x-mary-card title="{{ __('ledger.assigned_folders_title') }}" shadow="sm">
                        @forelse($assignedFolders as $folder)
                            --}}
        {{-- リストアイテムで表示する例 --}}{{--

                            <x-mary-list-item :item="$folder" no-separator> --}}
        {{-- hover 効果はあっても良いかも --}}{{--

                                --}}
        {{-- アイコンとフォルダ名 --}}{{--

                                <x-slot:value>
                                    <div class="flex items-center">
                                        @php
                                            $icon = 'o-folder'; // デフォルト (念のため)
                                            $iconColor = 'text-secondary'; // デフォルト
                                            $permissionText = '';
                                            if (in_array($folder->id, $manageableFolderIds)) {
                                                $icon = 'o-cog-6-tooth'; // 管理可能アイコン例
                                                $iconColor = 'text-accent';
                                                $permissionText = __('ledger.folder_permission_manageable');
                                            } elseif (in_array($folder->id, $writableFolderIds)) {
                                                $icon = 'o-pencil-square'; // 書き込み可能アイコン例
                                                $iconColor = 'text-accent/90';
                                                $permissionText = __('ledger.folder_permission_editable');
                                            }
                                        @endphp
                                        <x-mary-icon :name="$icon" class="w-5 h-5 mr-2 shrink-0 {{ $iconColor }}" />
                                        <span class="text-base-content">{{ $folder->title }}</span>
                                        --}}
        {{-- 権限テキストをバッジで表示 --}}{{--

                                        @if($permissionText)
                                            <x-mary-badge :value="$permissionText" class="badge-sm ml-2 {{ $iconColor }}" />
                                        @endif
                                    </div>
                                </x-slot:value>

                                --}}
        {{-- フォルダへのリンクボタン --}}{{--

                                <x-slot:actions>
                                    --}}
        {{-- リンク先は台帳・フォルダ表示画面のURLを想定 (ルート名は適宜変更) --}}{{--

                                    <x-mary-button label="{{ __('ledger.go_to_folder_button') }}"
                                                   link="{{ route('ledgersByFolderId', ['folderId' => $folder->id]) }}" --}}
        {{-- ルート名とパラメータは要確認 --}}{{--

                                                   class="btn-ghost btn-sm"
                                                   icon="o-arrow-right-circle" />
                                </x-slot:actions>
                            </x-mary-list-item>



                        @empty
                            <p class="text-sm text-base-content/70">{{ __('ledger.no_assigned_folders') }}</p>
                        @endforelse
                    </x-mary-card>
        --}}


        {{-- あなたの担当フォルダエリア (ステップ3で追加) --}}
        <x-mary-card title="{{ __('ledger.assigned_folders_title') }}" shadow="sm">
            @forelse($assignedFolders as $folder)

                {{-- カードで表示する例 (リストとどちらかを選択) --}}
                <div class="border border-base-300 rounded-lg p-4 mb-2 flex justify-between items-center">
                    <div>
                        <div class="flex items-center mb-1">
                            @php
                                $icon = 'o-folder'; // デフォルト (念のため)
                                $iconColor = 'text-secondary'; // デフォルト
                                $permissionText = '';
                                if (in_array($folder->id, $manageableFolderIds)) {
                                    $icon = 'o-cog-6-tooth'; // 管理可能アイコン例
                                    $iconColor = 'text-accent';
                                    $permissionText = __('ledger.folder_permission_manageable');
                                } elseif (in_array($folder->id, $writableFolderIds)) {
                                    $icon = 'o-pencil-square'; // 書き込み可能アイコン例
                                    $iconColor = 'text-accent/90';
                                    $permissionText = __('ledger.folder_permission_editable');
                                }
                            @endphp
                            <x-mary-icon :name="$icon" class="w-5 h-5 mr-2 shrink-0 {{ $iconColor }}"/>
                            <span class="font-semibold text-base-content">{{ $folder->title }}</span>
                        </div>
                        <span class="text-xs {{ $iconColor }}">{{ $permissionText }}</span>
                    </div>
                    <x-mary-button label="{{ __('ledger.go_to_folder_button') }}"
                                   link="{{ route('ledgersByFolderId', ['folderId' => $folder->id]) }}"
                                   {{-- ルート名とパラメータは要確認 --}}
                                   class="btn-primary btn-sm"
                                   icon="o-arrow-right-circle"/>
                </div>

            @empty
                <p class="text-sm text-base-content/70">{{ __('ledger.no_assigned_folders') }}</p>
            @endforelse
        </x-mary-card>

        {{-- 詳細情報エリア (ステップ4で追加) --}}
        <x-mary-card title="{{ __('ledger.detailed_information_title') }}" shadow="sm">
            <x-mary-collapse>
                <x-slot:heading>
                    {{ __('ledger.all_accessible_folders_link') }}
                </x-slot:heading>
                <x-slot:content>
                    {{-- 既存のフォルダツリーコンポーネントを呼び出す --}}
                    {{-- wire:click によるアクションは不要なので、props のみを渡す --}}
                    <div class="p-4 menu w-full"> {{-- 内側に少しパディングを追加 --}}
                        <x-folder.tree
                            :folders="$allRootFolders"
                            :writableFolderIds="$writableFolderIds"
                            :readableFolderIds="$readableFolderIds"
                            :manageableFolderIds="$manageableFolderIds"
                            :interactive="false"
                            {{-- currentFolderId や selectedFolderIds はここでは不要 --}}
                            {{-- 必要であれば :interactive="false" のようなプロパティを追加して --}}
                            {{-- Bladeコンポーネント側でクリック動作を無効化する --}}
                        />
                    </div>
                </x-slot:content>
            </x-mary-collapse>

            {{-- 必要であれば、ここに全ロールリストや通知設定の詳細を追加 --}}

        </x-mary-card>

    </div>
</div>
