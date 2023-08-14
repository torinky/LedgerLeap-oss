<?php

namespace App\Http\Controllers\Ledger;

use App\Exports\LedgerExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\SearchRequest;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// maatwebsite/excelを使用

class ExportController extends Controller
{

    /**
     * CSVファイルとしてLedgerの内容をダウンロードする
     *
     * @param SearchRequest $request 検索条件を指定するリクエスト
     * @return BinaryFileResponse
     */
    public function downloadExcelCSV(SearchRequest $request)
    {
        // ダウンロードするCSVのカラム定義を取得
        $ledgerDefineId = $request->ledgerDefineId();
        $columnDefines = LedgerDefine::where('id', $ledgerDefineId)->pluck('column_define')->sortBy('order')->all()[0];

        // Ledgerレコードを検索してエクスポート用のクエリを構築
        $query = Ledger::where('ledger_define_id', $ledgerDefineId)
            ->search(implode(' ', $request->keywords()))
            ->contentsFilter($request->filter())
            ->with('define.folder');

        // maatwebsite/excelパッケージを使用してCSVファイルを出力する
        /**
         * 第一引数: インスタンス化したExportクラスを指定
         * 第二引数: ダウンロードするCSVファイルの名前
         * 第三引数: ファイルの形式を指定 (\Maatwebsite\Excel\Excel::XLSX など)
         * ファイルの形式は第二引数の拡張子から判別されるため、基本的に指定不要
         * 第四引数: ヘッダーに含める情報を指定する配列
         */
        return Excel::download(new LedgerExport($query, $columnDefines), 'ledger_records.csv', \Maatwebsite\Excel\Excel::CSV, ['X-Vapor-Base64-Encode' => 'True']);
    }
}
