<?php

namespace App\Jobs\Ledger;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class AttachedFileOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $attachedFileId;

    public function __construct(int $attachedFileId)
    {
        $this->attachedFileId = $attachedFileId;
    }

    public function handle(): void
    {
        $attachedFile = AttachedFile::find($this->attachedFileId);

        if (! $attachedFile) {
            Log::warning("AttachedFile not found for OCR job: {$this->attachedFileId}");

            return;
        }

        // PDF 以外のファイルは OCR 対象外
        if ($attachedFile->mime !== 'application/pdf') {
            Log::info("Skipping OCR for non-PDF file: {$attachedFile->id} ({$attachedFile->mime})");
            $attachedFile->status = AttachedFileStatus::EXTRACTED_AND_SAVED->value; // OCR不要なのでステータスを更新
            $attachedFile->save();

            return;
        }

        $attachedFile->status = AttachedFileStatus::OCR_PROCESSING->value;
        $attachedFile->save();

        $inputPath = Storage::path('public/'.$attachedFile->path);
        $outputPath = Storage::path('public/Ledger/OCR/'.basename($attachedFile->path)); // OCR後のファイルを別の場所に保存

        // ocrmypdf コマンドの実行
        // Docker コンテナ内でのパスは /data/public/Ledger/Attachments/hashedfilename.ext となる
        $dockerInputPath = '/data/public/'.$attachedFile->path;
        $dockerOutputPath = '/data/public/Ledger/OCR/'.basename($attachedFile->path);

        // ocrmypdf コマンドの構築
        // --force-ocr: 既存のテキストレイヤーがあってもOCRを強制
        // --output-type pdfa: PDF/A形式で出力 (オプション)
        // --skip-text: テキストレイヤーがある場合はOCRをスキップ (Tikaでテキストが取れなかった場合のみ実行されるので不要かも)
        // -l jpn+eng: 日本語と英語の言語パックを使用
        $command = [
            'ocrmypdf',
            '--force-ocr',
            '-l', 'jpn+eng',
            $dockerInputPath,
            $dockerOutputPath,
        ];

        $process = new Process($command);
        $process->setTimeout(3600); // 1時間のタイムアウト
        $process->setIdleTimeout(600); // 10分のアイドルタイムアウト

        try {
            $process->run();

            if (! $process->isSuccessful()) {
                throw new Exception('OCR failed: '.$process->getErrorOutput());
            }

            Log::info("OCR successful for file ID: {$attachedFile->id}");

            // OCR 後のファイルを元のパスに上書きするか、新しいパスを保存するか検討
            // 今回は新しいパスに保存し、AttachedFile の path を更新する
            $attachedFile->path = 'Ledger/OCR/'.basename($attachedFile->path);
            $attachedFile->status = AttachedFileStatus::EXTRACTED_AND_SAVED->value; // OCR後、再度Tikaで抽出されることを想定
            $attachedFile->save();

            // OCR 後のファイルに対して再度 AttachedFileScanJob をディスパッチし、Tika でテキストを再抽出
            AttachedFileScanJob::dispatch($attachedFile->id);

        } catch (Exception $e) {
            Log::error("OCR job failed for file ID: {$attachedFile->id}. Error: {$e->getMessage()}");
            $attachedFile->status = AttachedFileStatus::OCR_FAILED->value;
            $attachedFile->save();
            // ジョブを失敗させる
            throw $e;
        }
    }
}
