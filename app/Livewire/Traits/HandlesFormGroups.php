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
     * グループの状態を初期化する
     */
    protected function initializeGroups(): void
    {
        $allGroups = collect($this->ledgerDefineRecord->column_define)
            ->pluck('group')
            ->map(fn ($group) => $group ?? __('ledger.form.group_default'))
            ->unique()
            ->all();

        $this->collapsedStates = array_fill_keys($allGroups, true); // まず全てを折りたたむ

        // 必須項目を含むグループを展開する
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->required) {
                $groupName = $column->group ?? __('ledger.form.group_default');
                if (isset($this->collapsedStates[$groupName])) {
                    $this->collapsedStates[$groupName] = false; // 展開
                }
            }
        }
    }
}
