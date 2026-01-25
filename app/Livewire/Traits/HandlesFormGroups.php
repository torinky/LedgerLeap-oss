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
     * グループの開閉を切り替える
     */
    #[Renderless]
    public function toggleGroup(string $groupName, ?bool $force = null): void
    {
        if (isset($this->collapsedStates[$groupName])) {
            $this->collapsedStates[$groupName] = $force ?? ! $this->collapsedStates[$groupName];
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
