<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Imports\LedgerImport;
use App\Models\LedgerDefine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function showUploadForm(Request $request)
    {
        return view('ledger.import', ['ledgerDefineId' => $request->ledgerDefineId]);
    }

    /**
     * CSVファイルからLedgerの内容をインポートする
     *
     * @return RedirectResponse
     */
    public function importExcelCSV(Request $request)
    {
        // ファイルアップロードのバリデーションなどを行う必要があるかもしれません

        $file = $request->file('csv_file');

        $ledgerDefine = LedgerDefine::where('id', $request->input('ledger_define_id'))
            ->first();
        $import = new LedgerImport($ledgerDefine);

        //        $columnDefines= $ledgerDefine->column_define;

        // カラム定義を取得して、適切な変換ロジックを適用
        /*        foreach ($columnDefines as $columnDefine) {
                    $import->applyColumnMapping($columnDefine->id, $columnDefine->restoreColumnValue($columnDefine->id));
                }*/

        // maatwebsite/excel パッケージを使用してインポートを実行
        Excel::import($import, $file, null, \Maatwebsite\Excel\Excel::CSV);

        return redirect()->back()->with('success', 'CSVファイルのインポートが完了しました。');
    }
}
