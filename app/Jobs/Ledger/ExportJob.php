<?php

namespace App\Jobs\Ledger;

use App\Exports\LedgerExport;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ExportJob implements ShouldQueue
{
    use Exportable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Ledger定義のID
     *
     * @var int
     */
    protected $ledgerDefineId;

    /**
     * キーワード情報
     *
     * @var array
     */
    protected $keywords = [];

    /**
     * フィルター情報
     *
     * @var array
     */
    protected $filter = [];

    /**
     * カラム定義情報
     *
     * @var object
     */
    protected $columnDefines;

    /**
     * ファイル名
     *
     * @var string
     */
    protected $filename = 'ledgerRecords.csv';

    /**
     * コンストラクタメソッド
     *
     * @param int $ledgerDefineId Ledger定義のID
     * @param array $keywords キーワード情報
     * @param array $filter フィルター情報
     * @param object $columnDefines カラム定義情報
     * @param string $filename ファイル名
     */
    public function __construct(int $ledgerDefineId, array $keywords, array $filter, object $columnDefines, string $filename)
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->keywords = $keywords;
        $this->filter = $filter;
        $this->columnDefines = $columnDefines;
        $this->filename = $filename;
    }

    /**
     * Jobを実行するメソッド
     */
    public function handle()
    {
        $export = new LedgerExport($this->ledgerDefineId, $this->keywords, $this->filter, $this->columnDefines);
        Excel::store($export, $this->filename, 'public');
    }
}
