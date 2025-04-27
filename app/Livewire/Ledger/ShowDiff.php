<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ShowDiff extends Component
{
    // ledgerRecord は表示する Diff の内容を入れるように変更
    public ?LedgerDiff $currentDiffRecord = null; // 表示中の Diff
    public $ledgerDefineRecord; // Define は必要

    public $ledgerRecord;

    public $ledgerId;
    public $targetDiffId = null; // URL から受け取る diffId

    public int $offset = 0; // スライダーの位置 (0が最新)
    public int $ledgerDiffCount = 0; // 全 Diff 数


    // mount メソッドを修正
    public function mount(int $ledgerId, ?int $diffId = null): void // Request の代わりに ID を受け取る
    {
        $this->ledgerId = $ledgerId;
        $this->targetDiffId = $diffId; // URL からの diffId を保持

        $this->ledgerRecord = Ledger::with('define')->findOrFail($ledgerId); // まず Ledger を取得
        $this->ledgerDefineRecord = $this->ledgerRecord->define;

        // 全 Diff 数をカウント
        $this->ledgerDiffCount = $this->ledgerRecord->ledgerDiff()->count();

        // 表示する Diff を決定
        $this->loadDiffRecord();
    }

    // 表示する Diff レコードをロードするメソッド
    protected function loadDiffRecord(): void
    {
        $query = LedgerDiff::with(['modifier:id,name', 'inspector:id,name', 'approver:id,name']) // 関連ユーザー情報取得
        ->where('ledger_id', $this->ledgerId);

        if ($this->targetDiffId) {
            // diffId 指定がある場合
            $this->currentDiffRecord = $query->findOrFail($this->targetDiffId);
            // この Diff が最新から何番目かを計算してオフセットを設定 (やや複雑)
            $newerDiffCount = LedgerDiff::where('ledger_id', $this->ledgerId)
                ->where('id', '>', $this->targetDiffId)
                ->count();
            $this->offset = $newerDiffCount; // 最新が0なので、自分より新しいものの数がオフセット

        } else {
            // diffId 指定がない場合 (オフセットで指定 or 最新)
            $this->currentDiffRecord = $query->latest('id') // 最新から数える
            ->skip($this->offset)
                ->firstOrFail();
            $this->targetDiffId = $this->currentDiffRecord->id; // 表示中の Diff ID を更新

        }

        // contentが空の時はcontentが空でないledgerDiffのレコードのcontentとcolumn_defineを流用してセットしたい
        if (empty($this->currentDiffRecord->content)) {
            // contentが空の場合、空でない最新のDiffを探す
            $latestNonEmptyDiff = LedgerDiff::where('ledger_id', $this->ledgerId)
                ->where('id', '<', $this->currentDiffRecord->id)
                ->whereNotNull('content')
                ->where('content', '<>', '[]')
                ->latest('id')
                ->first();

            if ($latestNonEmptyDiff) {
                // 空でないDiffが見つかった場合、そのcontentとcolumn_defineを流用
                $this->ledgerRecord->content = $latestNonEmptyDiff->content;
                $this->ledgerRecord->define->column_define = $latestNonEmptyDiff->column_define;
            } else {
                // 空でないDiffが見つからない場合、contentとcolumn_defineをnullにする
                $this->ledgerRecord->content = null;
                $this->ledgerRecord->define->column_define = null;
            }
        } else {
            // contentが空でない場合、そのままセット
            $this->ledgerRecord->content = $this->currentDiffRecord->content;
            $this->ledgerRecord->define->column_define = $this->currentDiffRecord->column_define;
        }

        $this->ledgerRecord->modifier = $this->currentDiffRecord->modifier;
        $this->ledgerRecord->updated_at = $this->currentDiffRecord->updated_at;
//        dd($this->currentDiffRecord->column_define);

    }

    // スライダー操作時の処理を修正
    public function changeOffset(int $newOffset = 0): void // $newOffset は 0 から始まる
    {
        if ($newOffset >= $this->ledgerDiffCount) {
            $this->offset = $this->ledgerDiffCount > 0 ? $this->ledgerDiffCount - 1 : 0; // 範囲内に収める
        } elseif ($newOffset < 0) {
            $this->offset = 0;
        } else {
            $this->offset = $newOffset;
        }

        $this->targetDiffId = null; // オフセットで移動した場合は targetDiffId をクリア
        $this->loadDiffRecord(); // 新しいオフセットで Diff を再ロード
    }

    public function render(): View // View を use
    {
        return view('livewire.ledger.show-diff')
            ->layout('layouts.app'); // レイアウト指定
    }

}
