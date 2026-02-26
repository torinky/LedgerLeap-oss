@php use App\Services\AutoLinkService; @endphp
<div>
    <div class="background-image-change h-full"
         x-data="{
            currentBg: null,
            allExpanded: false,
            updateBackground(columnId) {
                this.currentBg = $wire.backgroundImages[columnId] || null;
                if(this.currentBg == null || this.currentBg.length == 0) {
                    document.querySelector('.background-image-change').style.backgroundImage = ``;
                }else{
                    document.querySelector('.background-image-change').style.backgroundImage = `url('${this.currentBg}')`;
                }
            }
        }">

        <div class="flex flex-col gap-4">
            {{-- 操作ツールバー (一括操作のみに集約) --}}
            <div class="flex items-center justify-end mb-2">
                <div class="flex items-center gap-2 bg-base-200/50 px-3 py-1 rounded-lg border border-base-300">
                    <span class="text-xs font-black text-base-content/40 uppercase tracking-widest">{{ __('ledger.column.expand_all') }}</span>
                    <x-mary-toggle x-model="allExpanded" @change="allExpanded ? $wire.expandAllGroups() : $wire.collapseAllGroups()" right tight class="toggle-xs toggle-primary" />
                </div>
            </div>

            {{-- プレビュー専用のタイトルや台帳情報の表示は冗長なため完全に削除。
                 上の基本設定セクションで同じ「台帳名/フォルダ名」が表示されているため。 --}}

            {{-- 説明文セクション --}}
            <div class="card bg-base-100 border border-base-300 shadow-sm overflow-hidden">
                <div x-data="{
                         descriptionGroup: @entangle('descriptionGroup'),
                         toggle(name) {
                             this.descriptionGroup = (this.descriptionGroup === name) ? '' : name;
                         }
                     }" class="divide-y divide-base-200">

                    {{-- Create Description --}}
                    <div class="collapse collapse-arrow rounded-none" :class="{ 'collapse-open': descriptionGroup === 'createDescription' }">
                        <div class="collapse-title text-xs font-bold cursor-pointer hover:bg-base-200/30 transition-colors py-3 min-h-0" @click="toggle('createDescription')">
                            {{__('ledger.define.create_description')}}
                        </div>
                        <div class="collapse-content bg-base-200/10">
                            <div class="pt-4 text-sm leading-relaxed prose prose-sm max-w-none prose-secondary">
                                {!! app(AutoLinkService::class)->convert(app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->create_description ?? '', null, $ledgerDefineRecord)) !!}
                            </div>
                        </div>
                    </div>

                    {{-- List Description --}}
                    <div class="collapse collapse-arrow rounded-none" :class="{ 'collapse-open': descriptionGroup === 'listDescription' }">
                        <div class="collapse-title text-xs font-bold cursor-pointer hover:bg-base-200/30 transition-colors py-3 min-h-0" @click="toggle('listDescription')">
                            {{__('ledger.define.list_description')}}
                        </div>
                        <div class="collapse-content bg-base-200/10">
                            <div class="pt-4 text-sm leading-relaxed prose prose-sm max-w-none prose-secondary">
                                {!! app(AutoLinkService::class)->convert(app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->list_description ?? '', null, $ledgerDefineRecord)) !!}
                            </div>
                        </div>
                    </div>

                    {{-- Detail Description --}}
                    <div class="collapse collapse-arrow rounded-none" :class="{ 'collapse-open': descriptionGroup === 'detailDescription' }">
                        <div class="collapse-title text-xs font-bold cursor-pointer hover:bg-base-200/30 transition-colors py-3 min-h-0" @click="toggle('detailDescription')">
                            {{__('ledger.define.detail_description')}}
                        </div>
                        <div class="collapse-content bg-base-200/10">
                            <div class="pt-4 text-sm leading-relaxed prose prose-sm max-w-none prose-secondary">
                                {!! app(AutoLinkService::class)->convert(app(Spatie\LaravelMarkdown\MarkdownRenderer::class)->toHtml($ledgerDefineRecord->detail_description ?? '', null, $ledgerDefineRecord)) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- プレビュー表示（グループ化対応） --}}
            <div class="space-y-4">
                @foreach ($groupedColumns as $groupName => $columnsInGroup)
                    <div class="collapse collapse-plus bg-base-100 border border-base-200 shadow-sm transition-all duration-300"
                         wire:key="preview-group-{{ $groupName }}"
                         x-data="{ isCollapsed: @entangle('collapsedStates.' . $groupName) }"
                         :class="{ 'collapse-open': !isCollapsed, 'collapse-close': isCollapsed }">

                        <div class="collapse-title text-sm font-bold flex items-center gap-2 cursor-pointer bg-base-200/30 py-3 min-h-0 uppercase tracking-widest"
                             @click="isCollapsed = !isCollapsed">
                            <x-mary-icon name="o-folder" class="w-4 h-4 opacity-40 text-primary" />
                            {{ $groupName }}
                        </div>

                        <div class="collapse-content bg-base-100 p-0">
                            <div class="px-4 py-6 space-y-8">
                                @foreach($columnsInGroup as $columnDefine)
                                    @if(!$columnDefine->isHidden())
                                        <div wire:key="preview-column-{{ $columnDefine->id }}"
                                             x-on:mouseenter="updateBackground('{{ $columnDefine->id }}')"
                                             class="group relative pl-4 border-l-2 border-transparent hover:border-secondary transition-all">

                                            @if($columnDefine->type === 'files')
                                                <div class="form-control">
                                                    <div class="label pt-0 pb-2">
                                                        <span class="label-text font-bold flex items-center gap-1">
                                                            {{$columnDefine->name}}
                                                            @if($columnDefine->required)
                                                                <span class="text-error">*</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-center p-6 border-2 border-dashed border-base-300 rounded-lg bg-base-200/30 text-base-content/30 text-xs">
                                                        <x-mary-icon name="o-arrow-up-tray" class="w-4 h-4 mr-2" />
                                                        {{__('ledger.column.file.upload')}} ({{ __('ledger.preview') }})
                                                    </div>
                                                    @if($columnDefine->hint)
                                                        <div class="label-text-alt text-base-content/40 mt-2 italic">{{ $columnDefine->hint }}</div>
                                                    @endif
                                                </div>
                                            @else
                                                @php
                                                    $typeName = str_replace('_', '-', $columnDefine->type);
                                                    $componentName = 'ledger.form.'. ($columnDefine->type === 'auto_number' ? 'text' : $typeName);
                                                @endphp
                                                <x-dynamic-component
                                                        :component="$componentName"
                                                        wire:model.live="content"
                                                        :columnDefine="$columnDefine"
                                                        :ledgerRecord="$ledgerRecord??[]"
                                                        :isDemo="true"
                                                />
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

