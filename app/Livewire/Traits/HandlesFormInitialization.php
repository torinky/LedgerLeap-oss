<?php

namespace App\Livewire\Traits;

use App\Models\ColumnTypes\AutoNumberType;
use App\Models\ColumnTypes\UserNameType;
use Illuminate\Support\Facades\Auth;

trait HandlesFormInitialization
{
    /**
     * カラムの初期化処理
     */
    protected function initColumns(): void
    {
        foreach ($this->ledgerDefineRecord->column_define ?? [] as $column) {
            // 初期値の決定
            $defaultValue = match ($column->type) {
                'files', 'chk' => [],
                'auto_number' => $column->getInputType() instanceof AutoNumberType
                    ? $this->numberingService->getNextNumber($column, $this->ledgerDefineId)
                    : '',
                'user_name' => $column->getInputType() instanceof UserNameType
                    ? $column->getInputType()->generateValue(Auth::user())
                    : '',
                default => '',
            };

            // content がまだセットされていない場合のみデフォルト値を設定
            if (! isset($this->content[$column->id])) {
                if ($this->ledgerRecord && isset($this->ledgerRecord->content)) {
                    $this->content[$column->id] = $this->ledgerRecord->content[$column->id] ?? $defaultValue;
                } else {
                    $this->content[$column->id] = $defaultValue;
                }
            }

            // labelColor の初期設定
            if (method_exists($this, 'updateContentStatusLabel')) {
                $this->updateContentStatusLabel($column);
            }
        }
    }

    /**
     * 必須カラムの初期化
     */
    public function initRequireColumns(): void
    {
        $columns = collect($this->ledgerDefineRecord->column_define)->reject(fn ($column) => $column->isHidden());
        $this->totalRequireColumnCount = $columns->filter(fn ($column) => $column->required)->count();
        $this->requredColumnIds = $columns->filter(fn ($column) => $column->required)->pluck('id')->toArray();
    }

    /**
     * カラムIDからカラム定義を取得
     */
    protected function getColumnById(int $columnId): ?object
    {
        return collect($this->ledgerDefineRecord->column_define)
            ->firstWhere('id', $columnId);
    }

    /**
     * 背景画像の初期化
     */
    public function initBackgroundImages(): void
    {
        $this->backgroundImages = collect($this->ledgerDefineRecord->column_define)
            ->pluck('file', 'id')
            ->map(function ($value) {
                if (empty($value['path'])) {
                    return null;
                }

                return asset('storage/'.$value['path']);
            })->toArray();
    }

    /**
     * 日付カラムのデフォルト値を初期化する
     */
    protected function initializeDateDefaults(): void
    {
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->type !== 'YMD') {
                continue;
            }

            $columnId = $column->id;
            $existingValue = $this->content[$columnId] ?? null;
            $inputType = $column->getInputType();

            if (method_exists($inputType, 'getDefaultDate')) {
                $defaultDate = $inputType->getDefaultDate($existingValue);

                if ($defaultDate !== null) {
                    $this->content[$columnId] = $defaultDate;
                }
            }
        }
    }
}
