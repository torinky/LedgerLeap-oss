<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use App\Models\Ledger;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Vaites\ApacheTika\Client;

class AttachedFileScanJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $attachedFileId;

    /**
     * Create a new job instance.
     */
    public function __construct($attachedFileId)
    {
        $this->attachedFileId = $attachedFileId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $targetFile = AttachedFile::where('id', $this->attachedFileId)->firstOrFail();
        $this->generateMetaAndStore($targetFile);

//        未処理分を実行
        foreach (AttachedFile::where('status', AttachedFileStatus::UPLOADED->value) as $targetFile) {
            $this->generateMetaAndStore($targetFile);
        }

    }

    /**
     * @param $targetFile
     * @return void
     * @throws Exception
     */
    public function generateMetaAndStore($targetFile): void
    {
        $ledger = Ledger::where('id', $targetFile->ledger_id)->firstOrFail();

//        castさせて取り出す
        $contentAttached = $ledger->content_attached;
        $result = $contentAttached[$targetFile->column_id][$targetFile->filename] ?? (object)['meta' => ['content' => ''],];

        $filePath = storage_path('app/' . $targetFile->path);
//        dd(storage_path(),$filePath);
//        dd($targetFile,$ledger,$ledger->content_attached,$result);

        //        ファイルからメタ情報、テキストを抽出する
        $tikaClient = Client::make('tika', 9998);
        $result->meta = $tikaClient->getMetadata($filePath);
        if (empty($result->meta->content)) {
            $result->meta->content = $tikaClient->getText($filePath);
        }
        if (!empty($result->meta->content)) {
            $targetFile->contain_content = true;
        }
        if (!empty($result->meta->mime)) {
            $targetFile->mime = $result->meta->mime;
            $targetFile->status = AttachedFileStatus::EXTRACTED_AND_SAVED->value;
        }
//        var_dump( $result);
        $contentAttached[$targetFile->column_id][$targetFile->filename] = $result;

        // ミューテータを使って値を設定
        $ledger->content_attached = $contentAttached;
        $ledger->save();

        $targetFile->save();
        var_dump($ledger);
        var_dump($targetFile);
    }
}
