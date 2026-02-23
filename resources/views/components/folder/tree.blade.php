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
        <li class="{{ $folder->isRoot() ? 'root' : '' }}" wire:key="f_li_{{ $folder->id }}"
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
                Sprint 3: <a> と折りたたみ <button> を横並びフレックスで並べる。
                <button> を <a> の外に出すことで:
                  1. button > i の :class バインディングが x-data スコープ内で正しく評価され、アイコンが即時更新される
                  2. <a> 内に <button> をネストするアクセシビリティ違反を解消
                タイトルエリアは min-w-0 + truncate で可変幅に、バッジ・ボタンは shrink-0 で幅固定。
            --}}
            {{--
                行全体のflexコンテナ:
                - overflow: visible にして折りたたみボタンがクリップされないようにする
                - <a> が flex-1 でタイトル幅を使い切り、button.shrink-0 が右端に固定
                - <a> 内の overflow-hidden でタイトルが truncate される
            --}}
            <div class="tree-row flex items-center gap-1 overflow-visible">
                <a @if ($interactive)
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
                        'flex items-center gap-1 px-1 py-0.5 rounded cursor-pointer min-w-0 flex-1 overflow-hidden',
                        'bg-secondary/30 text-secondary-content font-bold shadow-inner' => $folder->id == $currentFolderId,
                        'bg-info/10' => in_array($folder->id, $selectedFolderIds) && $folder->id != $currentFolderId,
                    ]) wire:key="f_lnk_{{ $folder->id }}">
                    {{--
                        アイコン部: daisyUI tooltip で権限ラベルを表示。
                        tooltip-right で右にポップアップ（サイドバー内での向き）。
                        <a> 内に tooltip をネストすると daisyUI がホバーハイライトを付与するため
                        pointer-events-none で tooltip の疑似要素のみ表示し、クリックは <a> が担う。
                    --}}
                    <span class="shrink-0 tooltip tooltip-right"
                        data-tip="{{ in_array($folder->id, $manageableFolderIds) ? __('ledger.folder.manageable') : (in_array($folder->id, $writableFolderIds) ? __('ledger.folder.writable') : (in_array($folder->id, $readableFolderIds) ? __('ledger.folder.readable') : __('ledger.no_view_permissions'))) }}"
                        aria-label="{{ in_array($folder->id, $manageableFolderIds) ? __('ledger.folder.manageable') : (in_array($folder->id, $writableFolderIds) ? __('ledger.folder.writable') : (in_array($folder->id, $readableFolderIds) ? __('ledger.folder.readable') : __('ledger.no_view_permissions'))) }}">
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

                    {{-- タイトル: min-w-0 + truncate で残り幅をすべて使いつつはみ出しを省略 --}}
                    <span class="min-w-0 truncate text-sm leading-tight">
                        @if ($folder->isRoot()) {{ __('ledger.folder.root') }} @else {{ $folder->title }} @endif
                    </span>
                </a>

                {{-- バッジ: <a>の外に出してdaisyUI tooltip/flexの干渉を回避。台帳定義数が0のフォルダは非表示 --}}
                @if ($folder->ledgerDefines->count() > 0)
                    <span class="shrink-0" title="{{ __('ledger.folder.ledger_count') }}">
                        <span class="badge badge-info badge-xs text-base-100 gap-0.5">
                            <i class="fas fa-book" style="font-size:8px;"></i>{{ $folder->ledgerDefines->count() }}
                        </span>
                    </span>
                @else
                    <span class="shrink-0 w-4"></span>
                @endif

                {{--
                    折りたたみボタン — <a> の外に配置。
                    回転アニメーションは <button> 自体に inline style で適用する。
                    Font Awesome が <i> を <svg> に置換するため、<i> への :style バインドは
                    置換後に失われる。<button> 自体を回転させることで確実に動作させる。
                    全ノードで右端に揃えるため ml-auto は <a> が flex-1 を持つことで自動的に押し出される。
                --}}
                @if ($hasChildren)
                    <button
                        x-on:click="toggleOpen()"
                        :style="open ? 'transform: rotate(90deg); transition: transform 0.2s ease;' : 'transform: rotate(0deg); transition: transform 0.2s ease;'"
                        class="shrink-0 btn btn-ghost btn-xs p-0 w-6 h-6 min-h-0 text-base-content/40 hover:text-base-content"
                        :aria-label="open ? '{{ __('ledger.folder.collapse') }}' : '{{ __('ledger.folder.expand') }}'"
                        :aria-expanded="open">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </button>
                @else
                    <span class="shrink-0 w-6"></span>
                @endif
            </div>

            @if ($hasChildren)
                {{--
                    Sprint 3: x-show + CSS height アニメーション
                    x-transition では height が 0 → auto にならないため、
                    max-height を transition させる CSS クラスを使う。
                    x-if は Livewire/wire:key との競合リスクがあるため不使用。
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
