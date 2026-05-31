<?php

namespace App\Console\Commands\AttachedFiles;

use App\Models\AttachedFile;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckStorageConsistency extends Command
{
    protected $signature = 'attached-files:check-storage-consistency
                            {--tenant= : 特定のテナントIDのみを対象にする}
                            {--clean : 孤立ファイルを削除する}
                            {--dry-run : 削除を実行せずに対象のみ表示する}';

    protected $description = 'attached_files テーブルとストレージの整合性をチェックします。';

    private array $orphanRecords = [];
    private array $orphanFiles = [];

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $shouldClean = $this->option('clean');
        $dryRun = $this->option('dry-run');

        if ($tenantId) {
            $tenants = [Tenant::find($tenantId)];
            if (! $tenants[0]) {
                $this->error("テナント ID {$tenantId} が見つかりません。");

                return self::FAILURE;
            }
        } else {
            $tenants = Tenant::all();
        }

        foreach ($tenants as $tenant) {
            if (! $tenant) {
                continue;
            }

            tenancy()->initialize($tenant);
            $this->info("テナント [{$tenant->id}] をチェック中...");

            $this->checkOrphanRecords();
            $this->checkOrphanFiles($tenant);
        }

        $this->reportSummary();

        if ($shouldClean && ! $dryRun) {
            $this->cleanOrphanFiles();
        } elseif ($shouldClean && $dryRun) {
            $this->warn('--dry-run モードのため、実際の削除は行われませんでした。');
        }

        $totalIssues = count($this->orphanRecords) + count($this->orphanFiles);

        return $totalIssues > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkOrphanRecords(): void
    {
        $this->line('  DB レコード → ストレージ実ファイルの確認...');

        $records = AttachedFile::all();
        $orphanCount = 0;

        foreach ($records as $record) {
            if (! $record->path) {
                $this->orphanRecords[] = [
                    'id' => $record->id,
                    'path' => '(null)',
                    'filename' => $record->filename,
                    'issue' => 'path が空',
                ];
                $orphanCount++;
                continue;
            }

            if (! Storage::disk('public')->exists($record->path)) {
                $this->orphanRecords[] = [
                    'id' => $record->id,
                    'path' => $record->path,
                    'filename' => $record->filename,
                    'issue' => 'ファイルが存在しない',
                ];
                $orphanCount++;
            }
        }

        if ($orphanCount > 0) {
            $this->warn("    ⚠ {$orphanCount} 件の孤立レコード（実ファイル不在）があります。");
            $headers = ['ID', 'ファイル名', 'パス', '問題'];
            $rows = array_map(fn ($r) => [$r['id'], $r['filename'], $r['path'], $r['issue']], $this->orphanRecords);
            $this->table($headers, $rows);
        } else {
            $this->line('    <fg=green>✓</> 孤立レコードはありません。');
        }
    }

    private function checkOrphanFiles(Tenant $tenant): void
    {
        $this->line('  ストレージ → DB レコードの確認...');

        $dbHashedBasenames = AttachedFile::pluck('hashedbasename')->toArray();
        $dbHashedBasenameLookup = array_flip($dbHashedBasenames);

        $attachmentDir = 'tenants/'.$tenant->id.'/Ledger/Attachments';
        $thumbsDir = 'tenants/'.$tenant->id.'/Ledger/thumbs';

        $disk = Storage::disk('public');
        $orphanCount = 0;

        foreach ([$attachmentDir, $thumbsDir] as $dir) {
            if (! $disk->exists($dir)) {
                continue;
            }

            $allFiles = $disk->allFiles($dir);

            foreach ($allFiles as $file) {
                $basename = basename($file);

                if (str_starts_with($basename, '.')) {
                    continue;
                }

                if (! isset($dbHashedBasenameLookup[$basename])) {
                    $isThumb = str_contains($dir, 'thumbs');
                    $this->orphanFiles[] = [
                        'path' => $file,
                        'type' => $isThumb ? 'サムネイル' : '添付ファイル',
                        'size' => $disk->size($file),
                    ];
                    $orphanCount++;
                }
            }
        }

        if ($orphanCount > 0) {
            $this->warn("    ⚠ {$orphanCount} 件の孤立ファイル（DB未参照）があります。");
            $headers = ['パス', '種類', 'サイズ (bytes)'];
            $rows = array_map(fn ($f) => [$f['path'], $f['type'], $f['size']], $this->orphanFiles);
            $this->table($headers, $rows);
        } else {
            $this->line('    <fg=green>✓</> 孤立ファイルはありません。');
        }
    }

    private function reportSummary(): void
    {
        $this->newLine();
        $this->info('=== 整合性チェック集計 ===');
        $this->line('孤立レコード（実ファイル不在）: '.count($this->orphanRecords).' 件');
        $this->line('孤立ファイル（DB未参照）: '.count($this->orphanFiles).' 件');
    }

    private function cleanOrphanFiles(): void
    {
        if (empty($this->orphanFiles)) {
            $this->info('削除対象の孤立ファイルはありません。');

            return;
        }

        $disk = Storage::disk('public');
        $deleted = 0;
        $bar = $this->output->createProgressBar(count($this->orphanFiles));
        $bar->start();

        foreach ($this->orphanFiles as $file) {
            if ($disk->exists($file['path'])) {
                $disk->delete($file['path']);
                $deleted++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ {$deleted} 件の孤立ファイルを削除しました。");
    }
}
