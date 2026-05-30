<?php

namespace App\Livewire\Traits;

trait HandlesValidationUX
{
    /**
     * バリデーションエラーの一覧
     */
    public array $validationErrors = [];

    /**
     * グループごとのエラー数
     */
    public array $errorsByGroup = [];

    /**
     * フィールドごとのエラー有無
     */
    public array $errorsByField = [];

    /**
     * 前回のバリデーションエラー数
     */
    public int $previousErrorCount = 0;

    /**
     * バリデーションエラーが解消されたフィールドの追跡
     */
    public array $fixedFields = [];

    /**
     * 各カラムのラベル色（success, warning, error, muted）
     */
    public array $labelColor = [];

    /**
     * カラムのラベル色を更新します
     */
    public function updateContentStatusLabel($column, $overWrite = false): void
    {
        $columnId = $column->id;
        if (isset($this->labelColor[$columnId]) && $this->labelColor[$columnId] == 'error' && ! $overWrite) {
            return;
        }

        $tmpColumnValue = $this->content[$columnId] ?? null;
        if (is_array($tmpColumnValue)) {
            $checkCount = count(array_filter($tmpColumnValue, 'strlen'));
            if ($checkCount > 0) {
                $this->labelColor[$columnId] = 'success';
            } else {
                if ($column->required) {
                    $this->labelColor[$columnId] = 'warning';
                } else {
                    $this->labelColor[$columnId] = 'muted';
                }
            }
        } else {
            $tmpColumnValue = trim((string) ($tmpColumnValue ?? ''));
            if (! empty($tmpColumnValue)) {
                $this->labelColor[$columnId] = 'success';
            } else {
                if ($column->required) {
                    $this->labelColor[$columnId] = 'warning';
                } else {
                    $this->labelColor[$columnId] = 'muted';
                }
            }
        }
    }

    /**
     * バリデーション状態を一元管理するプロパティを更新します
     *
     * @param  array|null  $errors  引数を指定した場合はそのエラー配列を使用し、指定しない場合は現在のエラーバッグから取得します
     */
    public function updateValidationState(?array $errors = null): void
    {
        if ($errors === null) {
            // 全体のエラーバッグから取得（成功時など）
            $this->validationErrors = $this->getErrorBag()->toArray();
        } else {
            // 渡されたエラーを現在の状態にマージ/更新（失敗時など）
            // 該当するキーがあれば一旦削除
            foreach (array_keys($errors) as $key) {
                unset($this->validationErrors[$key]);
            }
            // 最新のエラーを追加
            $this->validationErrors = array_merge($this->validationErrors, $errors);
        }

        // エラー解決トースト通知 (Issue #25)
        $currentErrorCount = count($this->validationErrors);
        if ($currentErrorCount < $this->previousErrorCount) {
            $fixedCount = $this->previousErrorCount - $currentErrorCount;

            $message = '';
            if ($currentErrorCount === 0) {
                // すべて解消された場合 (Issue #25 改良)
                $message = __('ledger.validation.all_errors_fixed');
            } elseif ($fixedCount > 1) {
                // 複数同時に解消された場合 (Issue #25 改良)
                $message = __('ledger.validation.errors_fixed', ['count' => $fixedCount]);
            }

            if ($message) {
                $this->success($message);

                if (app()->runningUnitTests()) {
                    $this->dispatch('mary-toast', type: 'success', title: $message);
                }
            }
        }
        $this->previousErrorCount = $currentErrorCount;

        $this->errorsByField = [];
        $this->errorsByGroup = [];

        foreach ($this->validationErrors as $key => $messages) {
            $this->errorsByField[$key] = true;

            // フィールドがどのグループに属するか特定
            if (str_starts_with($key, 'content.')) {
                $columnId = (int) str_replace('content.', '', $key);
                $column = $this->getColumnById($columnId);
                if ($column) {
                    $groupName = $column->group ?? __('ledger.form.group_default');
                    $this->errorsByGroup[$groupName] = ($this->errorsByGroup[$groupName] ?? 0) + 1;
                }
            }
        }

        // エラーグループの自動展開 (Issue #16)
        if (method_exists($this, 'expandErrorGroups')) {
            $this->expandErrorGroups();
        }
    }

    /**
     * エラーが含まれるグループを自動的に展開します (Issue #16)
     */
    protected function expandErrorGroups(): void
    {
        if (! property_exists($this, 'collapsedStates')) {
            return;
        }

        foreach (array_keys($this->errorsByGroup) as $groupName) {
            if (isset($this->collapsedStates[$groupName])) {
                $this->collapsedStates[$groupName] = false; // 展開
            }
        }
    }
}
