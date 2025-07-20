<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Models\AttachedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ShowDiff extends Component
{
    public array $attachmentIdMap = []; // 添付ファイルのIDマップ
    public array $attachmentsInfoByColumn = []; // カラムごとの添付ファイル情報

    // ledgerRecord は表示する Diff の内容を入れるように変更
    public ?LedgerDiff $currentDiffRecord = null; // 表示中の Diff
    public $ledgerDefineRecord; // Define は必要

    public $ledgerRecord;

    public $ledgerId;
    public $targetDiffId = null; // URL から受け取る diffId

    public int $offset = 0; // スライダーの位置 (0が最新)
    public int $ledgerDiffCount = 0; // 全 Diff 数

    public ?\Illuminate\Support\Collection $allAttachments = null;

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

        if (!empty($fileHashedBasenames)) {
            $this->ledgerRecord->setRelation('attachedFiles', \App\Models\AttachedFile::whereIn('hashedbasename', $fileHashedBasenames)->get());
        } else {
            $this->ledgerRecord->setRelation('attachedFiles', collect());
        }
    }

    public function prepareAttachmentsInfo(): void
    {
        $this->attachmentsInfoByColumn = [];

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->type !== 'files') {
                continue;
            }

            $columnId = $column->id;
            $filesForColumn = [];

            if (!empty($this->ledgerRecord->content[$columnId]) && is_array($this->ledgerRecord->content[$columnId])) {
                $loopIndex = 0;
                foreach ($this->ledgerRecord->content[$columnId] as $hashedBasename => $originalFilename) {
                    $attachmentId = $this->attachmentIdMap[$hashedBasename] ?? null;
                    /** @var AttachedFile|null $currentAttachedFile */
                    $currentAttachedFile = $attachmentId ? AttachedFile::find($attachmentId) : null;

                    $storagePath = '';
                    $displayMimeType = '';

                    if ($currentAttachedFile) {
                        if (
                            in_array($currentAttachedFile->status->value, [
                                AttachedFileStatus::TIKA_FAILED->value,
                                AttachedFileStatus::OCR_FAILED->value,
                            ], true)
                        ) {
                            $storagePath = $currentAttachedFile->original_file_path;
                            $displayMimeType = $currentAttachedFile->original_mime_type;
                        } else {
                            $storagePath = $currentAttachedFile->path;
                            $displayMimeType = $currentAttachedFile->mime;
                        }
                    } else {
                        $storagePath = AttachedFilePathHelper::getAttachmentPath(
                            $this->ledgerDefineRecord->id,
                            $hashedBasename
                        );
                    }

                    $fileExists = $storagePath && Storage::disk('public')->exists($storagePath);
                    $posterUrl = '';

                    if ($fileExists) {
                        if (str_starts_with((string)$displayMimeType, 'image/')) {
                            $posterUrl = route('file.download', ['attachedFile' => $attachmentId, 'thumbnail' => true]);
                        } else {
                            switch ($displayMimeType) {
                                case 'application/pdf':
                                    $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-pdf']);
                                    break;
                                case 'application/zip':
                                case 'application/x-zip-compressed':
                                    $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-zipper']);
                                    break;
                                case 'application/msword':
                                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                                    $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-word']);
                                    break;
                                case 'application/vnd.ms-excel':
                                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                                    $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-excel']);
                                    break;
                                case 'application/vnd.ms-powerpoint':
                                case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                                    $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-powerpoint']);
                                    break;
                                case 'text/plain':
                                    $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-lines']);
                                    break;
                                case 'text/html':
                                case 'text/css':
                                case 'application/javascript':
                                case 'application/json':
                                case 'application/xml':
                                    $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-code']);
                                    break;
                                case 'text/csv':
                                    $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-csv']);
                                    break;
                                default:
                                    if (str_starts_with($displayMimeType, 'audio/')) {
                                        $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-audio']);
                                    } elseif (str_starts_with($displayMimeType, 'video/')) {
                                        $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-video']);
                                    } elseif (str_starts_with($displayMimeType, 'image/')) {
                                        $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file-image']);
                                    } else {
                                        $posterUrl = route('fontawesome.icon', ['style' => 'solid', 'icon' => 'file']);
                                    }
                                    break;
                            }
                        }
                    }

                    $filesForColumn[] = [
                        'originalFilename' => $originalFilename,
                        'hashedBasename' => $hashedBasename,
                        'downloadUrl' => $attachmentId ? route('file.download', ['attachedFile' => $attachmentId]) : '',
                        'mimeType' => $displayMimeType,
                        'size' => $fileExists ? Storage::disk('public')->size($storagePath) : 0,
                    ];
                    $loopIndex++;
                }
            }

            $this->attachmentsInfoByColumn[$columnId] = $filesForColumn;
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

        $this->prepareAttachmentsInfo();

//        \Illuminate\Support\Facades\Log::info('ShowDiff: loadDiffRecord - ledgerRecord->content after setting', ['content' => $this->ledgerRecord->content]);
//        \Illuminate\Support\Facades\Log::info('ShowDiff: loadDiffRecord - ledgerRecord->attachedFiles after setting', ['attachedFiles' => $this->ledgerRecord->attachedFiles ? $this->ledgerRecord->attachedFiles->toArray() : 'null']);
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
