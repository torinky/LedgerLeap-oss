<?php

namespace App\Imports;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithGroupedHeadingRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Maatwebsite\Excel\Row;

class LedgerImport implements ToModel, WithUpserts, WithHeadingRow, WithGroupedHeadingRow, WithCustomCsvSettings,
    WithBatchInserts, WithChunkReading
{
    protected $legerDefine;
    protected $columnDefines;

    /**
     * コンストラクタ
     *
     * @param array $columnDefines Ledgerモデルのカラム定義情報
     */
    public function __construct(LedgerDefine $ledgerDefine)
    {
        $this->legerDefine = $ledgerDefine;
        $this->columnDefines = $ledgerDefine->column_define;
        HeadingRowFormatter::default('none');

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
        return new Ledger([
            'id' => $row['[[[id]]]'] ?? '',
            'updated_at' => $row['[[[updated_at]]]'] ?? '',
            'created_at' => $row['[[[created_at]]]'] ?? '',
            'modifier_id' => $row['[[[modifier_id]]]'] ?? Auth::user()->id,
            'creator_id' => $row['[[[creator_id]]]'] ?? Auth::user()->id,
            'ledger_define_id' => $this->legerDefine->id,
            'content' => $this->generateLedgerContent($row),
        ]);

    }

    /**
     * Ledgerモデルのcontentを更新
     *
     * @param array $contentData コンテンツ行のデータ
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
        return $content;
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

}

class LedgerRowImport implements OnEachRow, WithCustomCsvSettings
{
    /**
     * @var array $columnDefines Ledgerモデルのカラム定義情報
     */
    protected $columnDefines;
    protected $legerDefine;
    protected $parameterMap;
    private $ledger;

    /**
     * コンストラクタ
     *
     * @param array $columnDefines Ledgerモデルのカラム定義情報
     */
    public function __construct(LedgerDefine $ledgerDefine)
    {
        $this->legerDefine = $ledgerDefine;
        $this->columnDefines = $ledgerDefine->column_define;
    }

    /**
     * 1行ごとの処理
     *
     * @param Row $row 行データ
     * @return void
     */
    public function onRow(Row $row)
    {
        $rowData = $row->toArray();

        // ヘッダ行の処理
        if ($row->getIndex() === 1) {
            $this->parameterMap = $this->buildParameterMap($rowData);
            return;
        }

        // コンテンツ行の処理
        $this->processContentRow($rowData);
    }

    protected function buildParameterMap($headerData)
    {
        $parameterMap = [];

        // ヘッダ行の情報をcontent以外のLedgerパラメータと紐づける
        foreach (['id', 'ledger_define_id', 'modifier_id', 'creator_id'] as $columnName) {
            $columnKey = '[[[' . $columnName . ']]]';
            $index = array_search($columnKey, $headerData);

            if ($index !== false) {
                $parameterMap[$columnName] = $index;
            }
        }

        return $parameterMap;
    }

    /**
     * コンテンツ行の処理
     *
     * @param array $contentData コンテンツ行のデータ
     * @return void
     */
    protected function processContentRow($contentData)
    {
        $this->ledger = null;
        // 新しいLedgerモデルを作成し、コンテンツデータを設定
        if (!empty($this->parameterMap['id']) && !empty($contentData[$this->parameterMap['id']])) {
            $id = $contentData[$this->parameterMap['id']];
//            $this->ledger = Ledger::where('id',$id)->get()[0];
            if (Ledger::where('id', $id)->exists()) {
                $this->ledger = Ledger::find($id);

                $newContent = $this->generateLedgerContent($contentData);
                // 既存レコードのcontentと新しいデータで生成されるcontentを比較
                if ($this->compareContent($this->ledger, $newContent)) {
                    return; // 更新の必要がないため終了
                }
                $this->ledger->content = $newContent;
            }

        }
        if (!$this->ledger) {
            $this->ledger = new Ledger();
            $this->ledger->content = $this->generateLedgerContent($contentData);
        }

        foreach ($this->parameterMap as $parameterName => $parameterIndex) {
            $this->ledger->{$parameterName} = $contentData[$parameterIndex] ?? null;
        }
        if (is_null($this->ledger->modifier_id)) {
            $this->ledger->modifier_id = Auth::user()->id;

        }
        if (is_null($this->ledger->creator_id)) {
            $this->ledger->creator_id = Auth::user()->id;

        }
        $this->ledger->ledger_define_id = $this->legerDefine->id;

        // 作成または更新されたLedgerモデルを保存
        $this->ledger->save();
    }


    /**
     * Ledgerモデルのcontentを更新
     *
     * @param Ledger $ledger 更新対象のLedgerモデル
     * @param array $contentData コンテンツ行のデータ
     * @return array
     */
    protected function generateLedgerContent($contentData)
    {
        $content = [];

        // ヘッダ行の情報を元に各カラムのデータを組み立て
        foreach ($this->columnDefines as $columnDefine) {
            $columnValue = $contentData[$columnDefine->order - 1] ?? null;
            $content[$columnDefine->id] = $columnDefine->restoreColumnValueFromText($columnValue);
        }
        ksort($content);
        $content = array_values($content);

        // コンテンツを設定
        return $content;
    }


    protected function compareContent(Ledger $ledger, $newContent)
    {
        $existingContent = $ledger->content;
        foreach ($this->columnDefines as $columnDefine) {
            if ($existingContent[$columnDefine->id] !== $newContent[$columnDefine->id]) {
                return false; // 内容が一致しないため更新が必要
            }
        }

        return true; // 内容が一致するため更新の必要がない
    }

    public function getCsvSettings(): array
    {
        return [
            /*            'delimiter' => "\t",
                        'enclosure' => '"',
                        'escape_character' => '\\',
                        'contiguous' => true,
                        'input_encoding' => 'UTF-8',*/

            'delimiter' => null,
            'enclosure' => '"',
            'escape_character' => '\\',
            'contiguous' => false,
            'input_encoding' => 'UTF-8',

        ];
    }
}
