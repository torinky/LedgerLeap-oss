@props([
    'folders' => [],
    'currentFolderId' => 1,
    'selectedFolderIds' => [],
    'selectedFolderChildrenIds' => [],
    'selectedFolderAncestorIds' => [],
    'writableFolderIds' => [],
    'readableFolderIds' => [],
    'manageableFolderIds' => [],
    'interactive' => true,
    'clickNavigatesToLedgerList' => false,
    'showPermissionTooltip' => true,
    'parentComponentId' => null,
])
<ul class="tree">
    @foreach ($folders as $folder)
        @php
            $isOpen = $folder->isRoot()
                || $folder->id == $currentFolderId
                || in_array($folder->id, $selectedFolderIds)
                || in_array($folder->id, $selectedFolderChildrenIds)
                || in_array($folder->id, $selectedFolderAncestorIds);
            $hasChildren = $folder->children->isNotEmpty();
        @endphp
        <li class="{{ $folder->isRoot() ? 'root' : '' }}" wire:key="f_li_{{ $folder->id }}_{{ $isOpen ? 1 : 0 }}"
            x-data="{
                open: (function() {
                    {{-- 選択フォルダ・先祖フォルダは localStorage より強制展開を優先 --}}
                    @if ($isOpen) return true; @endif
                    try {
                        var state = JSON.parse(localStorage.getItem('folderTree') || '{}');
                        return state[{{ $folder->id }}] !== undefined ? state[{{ $folder->id }}] : false;
                    } catch(e) { return false; }
                })(),
                toggleOpen() {
                    this.open = !this.open;
                    try {
                        var state = JSON.parse(localStorage.getItem('folderTree') || '{}');
                        state[{{ $folder->id }}] = this.open;
                        localStorage.setItem('folderTree', JSON.stringify(state));
                    } catch(e) {}
                }
            }">
            {{--
                2カラム構造（Sprint 7 → Sprint 8 改善）:
                  - .tree-row: width: 100% の flex コンテナ
                  - .tree-row-left: flex-1 min-w-0 + overflow-x: auto で横スクロール可能
                    アイコン・タイトル・バッジを whitespace-nowrap で 1 行表示し、
                    タイトルが長い場合に左部分のみスクロール。
                  - ボタン: shrink-0 で常に .tree-row の右端に固定（スクロールに追従しない）
                li は width: 100% でコンテナ幅に収まるため、
                どんなに深い階層でもボタンが表示領域外に出ない。
            --}}
            <div class="tree-row flex items-center" wire:key="f_row_{{ $folder->id }}_{{ $isOpen ? 1 : 0 }}">
                {{-- 左部分: スクロール可能エリア（flex-1 min-w-0 overflow-x-auto） --}}
                <div class="tree-row-left">
                    <a @if ($clickNavigatesToLedgerList)
                            href="{{ route('ledgersByFolderId', ['tenant' => tenant()?->id, 'folderId' => $folder->id]) }}"
                        @elseif ($interactive)
                            @if ($parentComponentId)
                                x-on:click.prevent="Livewire.find('{{ $parentComponentId }}').call('changeCurrentFolder', {{ $folder->id }})"
                            @else
                                x-on:click.prevent="Livewire.dispatch('currentFolderChangeRequested', { newFolderId: {{ $folder->id }} })"
                            @endif
                        @endif
                        @if ($folder->id == $currentFolderId)
                            {{-- Sprint 2: 選択中ノードを独立スクロール領域内に自動スクロール --}}
                            x-init="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))"
                        @endif
                        @class([
                            'flex items-center gap-1 px-1 py-0.5 rounded cursor-pointer whitespace-nowrap',
                            'bg-secondary/30 text-secondary-content font-bold shadow-inner' => $folder->id == $currentFolderId,
                            'bg-info/10' => in_array($folder->id, $selectedFolderIds) && $folder->id != $currentFolderId,
                        ]) wire:key="f_lnk_{{ $folder->id }}">
                        {{--
                            アイコン部: daisyUI tooltip で権限ラベルを表示。
                            tooltip-right で右にポップアップ（サイドバー内での向き）。
                        --}}
                        @php
                            $permissionLabel = in_array($folder->id, $manageableFolderIds)
                                ? __('ledger.folder.manageable')
                                : (in_array($folder->id, $writableFolderIds)
                                    ? __('ledger.folder.writable')
                                    : (in_array($folder->id, $readableFolderIds)
                                        ? __('ledger.folder.readable')
                                        : __('ledger.no_view_permissions')));
                        @endphp
                        <span @class(['shrink-0', 'tooltip tooltip-right' => $showPermissionTooltip])
                            @if($showPermissionTooltip)
                                data-tip="{{ $permissionLabel }}"
                            @else
                                title="{{ $permissionLabel }}"
                            @endif
                            aria-label="{{ $permissionLabel }}">
                            @if ($folder->isRoot())
                                <i class="fas fa-home text-primary"></i>
                            @else
                                @php
                                    $color = 'text-secondary';
                                    if (in_array($folder->id, $manageableFolderIds)) {
                                        $color = 'text-accent';
                                    } elseif (in_array($folder->id, $writableFolderIds)) {
                                        $color = 'text-accent/90';
                                    } elseif (in_array($folder->id, $readableFolderIds)) {
                                        $color = 'text-accent/80';
                                    }
                                @endphp
                                <span class="fa-stack">
                                    @if (in_array($folder->id, $selectedFolderIds) || in_array($folder->id, $selectedFolderChildrenIds) || $folder->id == $currentFolderId)
                                        <i class="fas fa-folder-open {{ $color }} fa-stack-2x"></i>
                                    @else
                                        <i class="fas fa-folder {{ $color }} fa-stack-2x"></i>
                                    @endif
                                    @if (in_array($folder->id, $manageableFolderIds))
                                        <i class="fas fa-fw fa-gear text-base-100 fa-stack-1x"></i>
                                    @elseif(in_array($folder->id, $writableFolderIds))
                                        <i class="fas fa-fw fa-pen text-base-100 fa-stack-1x"></i>
                                    @elseif(in_array($folder->id, $readableFolderIds))
                                        <i class="fas fa-fw fa-eye text-base-100 fa-stack-1x"></i>
                                    @endif
                                </span>
                            @endif
                        </span>

                        {{-- タイトル: whitespace-nowrap で全文表示（左部分がスクロールするため truncate 不要） --}}
                        <span class="text-sm leading-tight">
                            @if ($folder->isRoot()) {{ __('ledger.folder.root') }} @else {{ $folder->title }} @endif
                        </span>

                        {{-- バッジ: 台帳定義数が0のフォルダは非表示 --}}
                        @if ($folder->ledgerDefines->count() > 0)
                            <span class="shrink-0" title="{{ __('ledger.folder.ledger_count') }}">
                                <span class="badge badge-info badge-xs text-base-100 gap-0.5">
                                    <i class="fas fa-book" style="font-size:8px;"></i>{{ $folder->ledgerDefines->count() }}
                                </span>
                            </span>
                        @endif
                    </a>
                </div>

                {{--
                    右端固定のボタン列: .tree-row の flex 子として shrink-0 で固定。
                    .tree-row 自体は width: 100% のため、ボタンは常に表示領域の右端に位置する。
                --}}
                <div class="shrink-0 flex items-center gap-1">
                    @if ($hasChildren)
                        <button
                            x-on:click="toggleOpen()"
                            :style="open ? 'transform: rotate(90deg); transition: transform 0.2s ease;' : 'transform: rotate(0deg); transition: transform 0.2s ease;'"
                            class="shrink-0 btn btn-ghost btn-xs p-0 w-6 h-6 min-h-0 text-base-content/40 hover:text-base-content tree-toggle-btn"
                            :aria-label="open ? '{{ __('ledger.folder.collapse') }}' : '{{ __('ledger.folder.expand') }}'"
                            :aria-expanded="open">
                            <i class="fas fa-chevron-right text-xs"></i>
                        </button>
                    @else
                        <span class="shrink-0 w-6 tree-toggle-btn-placeholder"></span>
                    @endif
                </div>
            </div>

            @if ($hasChildren)
                {{--
                    Sprint 3: x-show + CSS height アニメーション
                --}}
                <div class="tree-collapse" x-show="open"
                    style="overflow: hidden;"
                    x-transition:enter="transition-all duration-200 ease-out"
                    x-transition:enter-start="opacity-0 max-h-0"
                    x-transition:enter-end="opacity-100 max-h-screen"
                    x-transition:leave="transition-all duration-150 ease-in"
                    x-transition:leave-start="opacity-100 max-h-screen"
                    x-transition:leave-end="opacity-0 max-h-0">
                    <x-folder.tree
                        :folders="$folder->children"
                        :interactive="$interactive"
                        :clickNavigatesToLedgerList="$clickNavigatesToLedgerList"
                        :showPermissionTooltip="$showPermissionTooltip"
                        :writableFolderIds="$writableFolderIds"
                        :readableFolderIds="$readableFolderIds"
                        :manageableFolderIds="$manageableFolderIds"
                        :currentFolderId="$currentFolderId ?? null"
                        :selectedFolderIds="$selectedFolderIds ?? []"
                        :selectedFolderChildrenIds="$selectedFolderChildrenIds ?? []"
                        :selectedFolderAncestorIds="$selectedFolderAncestorIds ?? []"
                        :parentComponentId="$parentComponentId" />
                </div>
            @endif
        </li>
    @endforeach
</ul>
