<?php

namespace App\Imports;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithGroupedHeadingRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class LedgerImport implements ToModel, WithBatchInserts, WithChunkReading, WithCustomCsvSettings, WithEvents, WithGroupedHeadingRow, WithHeadingRow, WithUpserts
{
    protected $ledgerDefine;

    protected $columnDefines;

    protected $id;

    private $currentRows = 0;

    private $updateRows = 0;

    private $insertRows = 0;

    private $importMode = self::MODE_UPDATE;

    const MODE_UPDATE = 1;

    const MODE_DESTOROY = 2;

    const MODE_INSERT = 3;

    /**
     * コンストラクタ
     *
     * @param  array  $columnDefines  Ledgerモデルのカラム定義情報
     */
    public function __construct(LedgerDefine $ledgerDefine, $mode = self::MODE_UPDATE)
    {
        $this->ledgerDefine = $ledgerDefine;
        $this->columnDefines = $ledgerDefine->column_define;
        $this->id = $ledgerDefine->id;
        $this->importMode = $mode;
        //        デフォルトだと日本語の列名が無視される
        HeadingRowFormatter::default('none');

        if ($this->importMode == self::MODE_DESTOROY) {
            // 外部キー制約を一時的に無効にして既存レコードを全削除する
            Schema::disableForeignKeyConstraints();

            Ledger::where('ledger_define_id', $ledgerDefine->id)->delete();

            Schema::enableForeignKeyConstraints();
        }

        Cache::forget("total_rows_{$this->id}");
        Cache::forget("start_date_{$this->id}");
        Cache::forget("current_rows_{$this->id}");
        Cache::forget("insert_rows_{$this->id}");
        Cache::forget("update_rows_{$this->id}");

    }

    /**
     * @return string|array
     */
    public function uniqueBy()
    {
        return ['id', 'content'];
    }

    public function model(array $row)
    {
        $this->currentRows++;
        Cache::forever("current_rows_{$this->id}", $this->currentRows);
        // dd($this->currentRows);
        $id = '';
        if ($this->importMode == self::MODE_UPDATE) {
            $id = $row['[[[id]]]'] ?? '';
        }

        if (empty($id)) {
            $this->insertRows++;
            Cache::forever("insert_rows_{$this->id}", $this->insertRows);
        } else {
            $this->updateRows++;
            Cache::forever("update_rows_{$this->id}", $this->updateRows);
        }

        $ledger = new Ledger([
            'id' => $id ?: null,
            'updated_at' => $row['[[[updated_at]]]'] ?? '',
            'created_at' => $row['[[[created_at]]]'] ?? '',
            'modifier_id' => $row['[[[modifier_id]]]'] ?? Auth::user()->id,
            'creator_id' => $row['[[[creator_id]]]'] ?? Auth::user()->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $this->generateLedgerContent($row),
        ]);

        // generateDefaultSortValue() のためにリレーションをセット
        $ledger->setRelation('define', $this->ledgerDefine);
        $ledger->default_sort_value = $ledger->generateDefaultSortValue();

        return $ledger;
    }

    /**
     * Ledgerモデルのcontentを更新
     *
     * @param  array  $contentData  コンテンツ行のデータ
     * @return array
     */
    protected function generateLedgerContent($contentData)
    {
        $content = [];

        // ヘッダ行の情報を元に各カラムのデータを組み立て
        foreach ($this->columnDefines as $columnDefine) {
            $columnValue = $contentData[$columnDefine->name] ?? null;
            $content[$columnDefine->id] = $columnDefine->restoreColumnValueFromText($columnValue);
        }

        // コンテンツを設定
        return $this->ledgerDefine->normalizeByColumnDefine($content);
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => null,
            'enclosure' => '"',
            'escape_character' => '\\',
            'contiguous' => false,
            'input_encoding' => 'UTF-8',

        ];
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $totalRows = $event->getReader()->getTotalRows();

                if (filled($totalRows)) {
                    Cache::forever("total_rows_{$this->id}", array_values($totalRows)[0]);
                    Cache::forever("start_date_{$this->id}", now()->unix());
                }
            },
            AfterImport::class => function (AfterImport $event) {
                Cache::put(["end_date_{$this->id}" => now()], now()->addMinute());
                //                Cache::forget("total_rows_{$this->id}");
                //                Cache::forget("start_date_{$this->id}");
                //                Cache::forget("current_rows_{$this->id}");
            },
        ];
    }

    /*    public function onRow(Row $row)
        {
            $rowIndex = $row->getIndex();
            $row      = array_map('trim', $row->toArray());
            cache()->forever("current_row_{$this->id}", $rowIndex);
            // sleep(0.2);

            Product::create([ ... ]);
        }*/
    public function getRowCount(): int
    {
        return $this->currentRows;
    }
}
