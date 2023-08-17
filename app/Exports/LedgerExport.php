<?php

namespace App\Exports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LedgerExport implements FromArray, WithHeadings, WithMapping, ShouldQueue
{
    use Exportable;

    protected $query;
    protected $columnDefines;

    /**
     * Create a new export instance.
     *
     * @param mixed $query
     * @param array $columnDefines
     */
    public function __construct($query, $columnDefines)
    {
        $this->query = $query;
        $this->columnDefines = $columnDefines;
    }

    /**
     * Define the data to be exported.
     *
     * @return array
     */
    public function array(): array
    {
        $records = [];


        foreach ($this->query->cursor() as $record) {
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

            $records[] = $row;
        }

        return $records;
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
        return $record;
    }
}
