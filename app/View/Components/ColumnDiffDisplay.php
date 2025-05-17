<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ColumnDiffDisplay extends Component
{
    public string $columnName;
    public $oldValue;
    public $newValue;
    public bool $isChanged;
    public string $columnType;

    public function __construct(string $columnName, $oldValue, $newValue, bool $isChanged, string $columnType = 'text')
    {
        $this->columnName = $columnName;
        $this->oldValue = $this->formatValue($oldValue, $columnType);
        $this->newValue = $this->formatValue($newValue, $columnType);
        $this->isChanged = $isChanged;
        $this->columnType = $columnType;
    }

    protected function formatValue($value, string $type)
    {
        if (is_null($value)) return '---';
        if (is_array($value)) return implode(', ', array_map(fn($v) => e($v), $value));
        if (is_bool($value)) return $value ? __('ledger.yes') : __('ledger.no');
        return e((string) $value);
    }

    public function render(): View
    {
        return view('components.column-diff-display');
    }
}