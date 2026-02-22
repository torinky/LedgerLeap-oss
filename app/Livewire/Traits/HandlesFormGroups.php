<?php

namespace App\Livewire\Traits;

use Livewire\Attributes\Renderless;

trait HandlesFormGroups
{
    /**
     * グループの折りたたみ状態
     */
    public array $collapsedStates = [];

    /**
     * 「すべて展開」の状態
     */
    public bool $allExpanded = false;

    /**
     * グループの開閉を切り替える
     */
    #[Renderless]
    public function toggleGroup(string $groupName, ?bool $force = null): void
    {
        if (isset($this->collapsedStates[$groupName])) {
            $this->collapsedStates[$groupName] = $force ?? ! $this->collapsedStates[$groupName];

            // 「すべて展開」の状態を同期
            $this->syncAllExpandedState();
        }
    }

    /**
     * 個別の折りたたみ状態が更新された際の同期
     */
    public function updatedCollapsedStates($value, $key): void
    {
        $this->syncAllExpandedState();
    }

    /**
     * 「すべて展開」の状態を現在の折りたたみ状態に合わせる
     */
    protected function syncAllExpandedState(): void
    {
        if (empty($this->collapsedStates)) {
            $this->allExpanded = false;

            return;
        }

        // 1つでも閉じている（true）ものがあれば、allExpanded は false
        $hasCollapsed = in_array(true, $this->collapsedStates, true);
        $this->allExpanded = ! $hasCollapsed;
    }

    /**
     * 「すべて展開」の状態が更新された際のフック
     */
    public function updatedAllExpanded(bool $value): void
    {
        if ($value) {
            $this->expandAllGroups();
        } else {
            $this->collapseAllGroups();
        }
    }

    /**
     * すべてのグループを折りたたむ
     */
    #[Renderless]
    public function collapseAllGroups(): void
    {
        foreach ($this->collapsedStates as $groupName => $value) {
            $this->collapsedStates[$groupName] = true;
        }
        $this->allExpanded = false;
    }

    /**
     * すべてのグループを展開する
     */
    #[Renderless]
    public function expandAllGroups(): void
    {
        foreach ($this->collapsedStates as $groupName => $value) {
            $this->collapsedStates[$groupName] = false;
        }
        $this->allExpanded = true;
    }

    /**
     * グループの状態を初期化する
     */
    protected function initializeGroups(): void
    {
        $allGroups = collect($this->ledgerDefineRecord->column_define)
            ->pluck('group')
            ->map(fn ($group) => $group ?? __('ledger.form.group_default'))
            ->unique()
            ->all();

        $this->collapsedStates = array_fill_keys($allGroups, true); // 全てを折りたたむ

        // 以前は推奨で必須グループを展開していたが、ユーザー要望によりデフォルトは全折りたたみとする
        /*
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->required) {
                ...
            }
        }
        */
    }
}
