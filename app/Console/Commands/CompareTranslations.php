<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class CompareTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:compare {--dry-run} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile ledger translations from PHP to JSON (One-Way)';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ledgerPhpPath = lang_path('ja/ledger.php');
        $ledgerJsonPath = lang_path('ja.json');

        if (! file_exists($ledgerPhpPath) || ! file_exists($ledgerJsonPath)) {
            $this->error('Translation files missing.');

            return;
        }

        $ledgerPhpArray = require $ledgerPhpPath;
        $ledgerJsonArray = json_decode(file_get_contents($ledgerJsonPath), true);

        // Flatten PHP array and prefix with 'ledger.'
        $flatPhpArray = Arr::dot($ledgerPhpArray);
        $newLedgerJsonKeys = [];
        foreach ($flatPhpArray as $key => $value) {
            $newLedgerJsonKeys["ledger.{$key}"] = $value;
        }

        // Gather existing non-ledger JSON keys
        $cleanedJsonArray = [];
        $existingLedgerKeys = [];
        foreach ($ledgerJsonArray as $key => $value) {
            if (str_starts_with($key, 'ledger.')) {
                $existingLedgerKeys[$key] = $value;
            } else {
                $cleanedJsonArray[$key] = $value;
            }
        }

        // Compare to see if there are changes
        $added = array_diff_key($newLedgerJsonKeys, $existingLedgerKeys);
        $removed = array_diff_key($existingLedgerKeys, $newLedgerJsonKeys);
        $modified = [];
        foreach (array_intersect_key($newLedgerJsonKeys, $existingLedgerKeys) as $k => $v) {
            if ($existingLedgerKeys[$k] !== $v) {
                $modified[$k] = ['old' => $existingLedgerKeys[$k], 'new' => $v];
            }
        }

        if (empty($added) && empty($removed) && empty($modified)) {
            $this->info('No changes detected. ja.json is up to date.');

            return;
        }

        // Print differences
        $this->info('Changes to be applied (PHP -> JSON):');
        foreach ($added as $k => $v) {
            $this->line("<info>+ Added:</info> $k = $v");
        }
        foreach ($removed as $k => $v) {
            $this->line("<error>- Removed:</error> $k = $v");
        }
        foreach ($modified as $k => $v) {
            $this->line("<comment>~ Modified:</comment> $k ({$v['old']} -> {$v['new']})");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run completed. No files modified.');

            return;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Apply changes to ja.json?')) {
                return;
            }
        }

        // Merge arrays: cleaned + new ledger keys
        $finalJsonArray = array_merge($cleanedJsonArray, $newLedgerJsonKeys);
        ksort($finalJsonArray);

        // Update JSON
        $json = json_encode($finalJsonArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
        file_put_contents($ledgerJsonPath, $json);

        $this->info('lang/ja.json successfully updated with One-Way Sync from PHP!');
    }
}
