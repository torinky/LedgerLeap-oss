<?php

namespace App\Console\Commands\AttachedFiles;

use App\Helpers\AttachedFilePathHelper;
use App\Models\AttachedFile;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DetectDuplicateHashedBasenames extends Command
{
    protected $signature = 'attached-files:detect-duplicates
                            {--tenant= : 特定のテナントIDのみを対象にする}
                            {--fix : 重複レコードの hashedbasename を再生成して修正する}
                            {--dry-run : 修正を実行せずに対象のみ表示する}';

    protected $description = 'attached_files テーブル内の hashedbasename 重複を検出・修正します。';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $shouldFix = $this->option('fix');
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

        $totalDuplicates = 0;
        $totalFixed = 0;

        foreach ($tenants as $tenant) {
            if (! $tenant) {
                continue;
            }

            tenancy()->initialize($tenant);
            $this->info("テナント [{$tenant->id}] をチェック中...");

            $duplicates = $this->findDuplicates();

            if ($duplicates->isEmpty()) {
                $this->line('  <fg=green>✓</> 重複はありません。');
                continue;
            }

            foreach ($duplicates as $hashedbasename => $records) {
                $totalDuplicates += count($records) - 1;
                $this->warn("  hashedbasename: {$hashedbasename} — ".count($records).' 件重複');

                $this->displayDuplicateRecords($records);

                if ($shouldFix && ! $dryRun) {
                    $fixed = $this->fixDuplicates($hashedbasename, $records);
                    $totalFixed += $fixed;
                }
            }
        }

        $this->newLine();
        $this->info("=== 集計 ===");
        $this->line("重複レコード数（超過分）: {$totalDuplicates}");
        if ($shouldFix && ! $dryRun) {
            $this->line("修正完了レコード数: {$totalFixed}");
        }

        if ($dryRun) {
            $this->warn('--dry-run モードのため、実際の修正は行われませんでした。');
        }

        return self::SUCCESS;
    }

    private function findDuplicates(): \Illuminate\Support\Collection
    {
        return AttachedFile::select('hashedbasename', DB::raw('COUNT(*) as count'))
            ->groupBy('hashedbasename')
            ->having('count', '>', 1)
            ->pluck('count', 'hashedbasename')
            ->mapWithKeys(function ($count, $hashedbasename) {
                $records = AttachedFile::where('hashedbasename', $hashedbasename)
                    ->orderBy('id')
                    ->get();

                return [$hashedbasename => $records];
            });
    }

    private function displayDuplicateRecords(\Illuminate\Support\Collection $records): void
    {
        $headers = ['id', 'ledger_id', 'filename', 'column_id', 'status', 'created_at', 'path_preview'];
        $rows = [];

        foreach ($records as $record) {
            $rows[] = [
                $record->id,
                $record->ledger_id,
                Str::limit($record->filename, 40),
                $record->column_id,
                $record->status->value ?? $record->status,
                $record->created_at->format('Y-m-d H:i:s'),
                Str::limit($record->path, 60),
            ];
        }

        $this->table($headers, $rows);
    }

    private function fixDuplicates(string $hashedbasename, \Illuminate\Support\Collection $records): int
    {
        $fixed = 0;

        $records->shift();

        foreach ($records as $record) {
            $extension = pathinfo($record->hashedbasename, PATHINFO_EXTENSION);
            $newHashedBasename = Str::random(40).'.'.$extension;

            if ($record->path) {
                $oldPath = $record->path;
                $newPath = AttachedFilePathHelper::getAttachmentPath(
                    $record->ledger_define_id,
                    $newHashedBasename
                );

                $storage = \Illuminate\Support\Facades\Storage::disk('public');
                if ($storage->exists($oldPath)) {
                    $storage->move($oldPath, $newPath);
                }

                $thumbOldPath = AttachedFilePathHelper::getThumbnailStoragePath(
                    $hashedbasename,
                    $record->tenant_id
                );
                if ($storage->exists($thumbOldPath)) {
                    $thumbNewPath = AttachedFilePathHelper::getThumbnailStoragePath(
                        $newHashedBasename,
                        $record->tenant_id
                    );
                    $storage->move($thumbOldPath, $thumbNewPath);
                }

                $originalsDir = dirname(AttachedFilePathHelper::getOriginalAttachmentPath(
                    $record->ledger_define_id,
                    'dummy'
                ));
                $oldOriginalPath = $originalsDir.'/'.$hashedbasename;
                if ($storage->exists($oldOriginalPath)) {
                    $newOriginalPath = AttachedFilePathHelper::getOriginalAttachmentPath(
                        $record->ledger_define_id,
                        $newHashedBasename
                    );
                    $storage->move($oldOriginalPath, $newOriginalPath);
                }
            }

            $record->update([
                'hashedbasename' => $newHashedBasename,
                'path' => $newPath ?? $record->path,
            ]);

            $this->line("    <fg=green>✓</> ID {$record->id}: {$hashedbasename} → {$newHashedBasename}");
            $fixed++;
        }

        return $fixed;
    }
}
