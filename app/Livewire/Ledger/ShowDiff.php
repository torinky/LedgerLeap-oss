<?php

namespace App\Livewire\Ledger;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Services\Ledger\LedgerContentProcessor; // 追加
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ShowDiff extends BaseLivewireComponent
{
    use InitializesTenantContext;

    // ledgerRecord は表示する Diff の内容を入れるように変更
    public ?LedgerDiff $currentDiffRecord = null; // 表示中の Diff

    public $ledgerDefineRecord; // Define は必要

    public $ledgerRecord;

    public $ledgerId;

    public $targetDiffId = null; // URL から受け取る diffId

    public int $offset = 0; // スライダーの位置 (0が最新)

    public int $ledgerDiffCount = 0; // 全 Diff 数

    public ?Collection $allAttachments = null;

    public array $displayColumns = []; // 追加

    protected LedgerContentProcessor $ledgerContentProcessor; // 追加

    public function boot(LedgerContentProcessor $ledgerContentProcessor): void
    {
        $this->ledgerContentProcessor = $ledgerContentProcessor;
    }

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

        $this->allAttachments = $this->ledgerRecord->attachedFiles->keyBy('hashedbasename');

        // --- Attachment ID マップの作成 --- (ModifyColumn と同様)
        $this->attachmentIdMap = $this->ledgerRecord->attachedFiles
            ->pluck('id', 'hashedbasename')
            ->toArray();
        // --------------------------------
    }

    protected function setAttachedFilesFromContent(array $content): void
    {
        $fileHashedBasenames = [];
        foreach ($this->ledgerRecord->define->column_define as $columnDefine) {
            if ($columnDefine->type === 'files') {
                $columnId = $columnDefine->id;
                // content配列のインデックスはcolumnIdと一致する
                if (isset($content[$columnId]) && is_array($content[$columnId])) {
                    foreach ($content[$columnId] as $hashedbasename => $originalFilename) {
                        $fileHashedBasenames[] = $hashedbasename;
                    }
                }
            }
        }

        if (! empty($fileHashedBasenames)) {
            $this->ledgerRecord->setRelation('attachedFiles', AttachedFile::where('ledger_id', $this->currentDiffRecord->ledger_id)
                ->where('ledger_define_id', $this->currentDiffRecord->ledger_define_id)
                ->whereIn('hashedbasename', $fileHashedBasenames)
                ->get());
        } else {
            $this->ledgerRecord->setRelation('attachedFiles', new \Illuminate\Database\Eloquent\Collection); // 空のEloquentCollectionをセット
        }
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
                // 添付ファイル情報を取得し、ledgerRecordにセット
                $this->setAttachedFilesFromContent($latestNonEmptyDiff->content);
            } else {
                // 空でないDiffが見つからない場合、contentとcolumn_defineをnullにする
                $this->ledgerRecord->content = null;
                $this->ledgerRecord->define->column_define = null;
                $this->ledgerRecord->setRelation('attachedFiles', collect()); // 空のコレクションをセット
            }
        } else {
            // contentが空でない場合、そのままセット
            $this->ledgerRecord->content = $this->currentDiffRecord->content;
            $this->ledgerRecord->define->column_define = $this->currentDiffRecord->column_define;
            // 添付ファイル情報を取得し、ledgerRecordにセット
            $this->setAttachedFilesFromContent($this->currentDiffRecord->content);
        }

        $this->ledgerRecord->modifier = $this->currentDiffRecord->modifier;
        $this->ledgerRecord->updated_at = $this->currentDiffRecord->updated_at;

        // Diff の content に基づいて添付ファイル情報を再構築
        $this->allAttachments = $this->ledgerRecord->attachedFiles->keyBy('hashedbasename');
        $this->attachmentIdMap = $this->ledgerRecord->attachedFiles
            ->pluck('id', 'hashedbasename')
            ->toArray();

        // LedgerContentProcessor を使用して displayColumns を生成
        $result = $this->ledgerContentProcessor->processContentForDisplay(
            $this->ledgerRecord,
            null, // 履歴表示では差分比較はしない
            3,    // 全ての項目を表示
            $this->allAttachments,
            null,  // ハイライトなし
            null,  // baseDiffId なし
            false  // showChanges: 通常表示モード
        );
        $this->displayColumns = $result['displayData'];
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
