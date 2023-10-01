<?php

namespace App\Exports;

use App\Models\Ledger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LedgerExport implements FromQuery, WithHeadings, WithMapping, ShouldQueue, WithCustomCsvSettings
{
    use Exportable;

    protected $ledgerDefineId;
    protected $keywords;
    protected $filter;
    protected $columnDefines;

    /**
     * Create a new export instance.
     *
     * @param int $ledgerDefineId
     * @param array $keywords
     * @param array $filter
     * @param object $columnDefines
     */
    public function __construct(int $ledgerDefineId, array $keywords, array $filter, object $columnDefines)
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->keywords = $keywords;
        $this->filter = $filter;
        $this->columnDefines = $columnDefines;
    }

    /**
     * Define the query to be exported.
     *
     * @return Builder
     */
    public function query()
    {
        return Ledger::where('ledger_define_id', $this->ledgerDefineId)
            ->search(implode(' ', $this->keywords))
            ->contentsFilter($this->filter)
            ->with('define.folder');
    }

    /**
     * Define the headers for the exported file.
     *
     * @return array
     */
    public function headings(): array
    {
        $header = collect($this->columnDefines)->pluck('name')->toArray();

        // id, ledger_define_idのヘッダも追加
        $header[] = '[[[id]]]';
        $header[] = '[[[ledger_define_id]]]';
        $header[] = '[[[updated_at]]]';
        $header[] = '[[[modifier_id]]]';
        $header[] = '[[[created_at]]]';
        $header[] = '[[[creator_id]]]';

        return $header;
    }

    /**
     * Map the values of each row.
     *
     * @param mixed $record
     * @return array
     */
    public function map($record): array
    {
        $row = [];

        foreach ($this->columnDefines as $columnDefine) {
            $columnValue = $record->content[$columnDefine->id] ?? '';

            $columnValue = $columnDefine->convertColumnValue2Text($columnValue);

            $row[] = $columnValue;
        }

        // id, ledger_define_idなどの値もヘッダに追加
        $row[] = $record->id;
        $row[] = $record->ledger_define_id;
        $row[] = $record->updated_at;
        $row[] = $record->modifire_id;
        $row[] = $record->created_at;
        $row[] = $record->creator_id;

        return $row;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
            'line_ending' => PHP_EOL,
            'use_bom' => false,
            'include_separator_line' => false,
            'excel_compatibility' => false,
            'output_encoding' => '',
            'test_auto_detect' => true,
        ];
    }

}
