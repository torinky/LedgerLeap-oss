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
    protected $description = 'Compare translations between lang/ja/ledger.php and lang/ja.json';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ledgerPhpPath = lang_path('ja/ledger.php');
        $ledgerJsonPath = lang_path('ja.json');

        $ledgerPhpArray = require $ledgerPhpPath;
        $ledgerJsonArray = json_decode(file_get_contents($ledgerJsonPath), true);

        $flatPhpArray = Arr::dot($ledgerPhpArray);

        $this->compareTranslations($flatPhpArray, $ledgerJsonArray);
    }

    private function compareTranslations($phpArray, $jsonArray)
    {
        $mergedArray = $phpArray;

        $changes = [];

        foreach ($jsonArray as $key => $value) {
            if (! str_contains($key, 'ledger.')) {
                continue;
            }
            $phpKey = strtr($key, ['ledger.' => '']);
            $this->info('php ['.$phpKey.'] : json ['.$key.']');
            if (! isset($phpArray[$phpKey])) {
                $this->info("Adding missing key to php: $phpKey");
                $changes[] = "Added $key = $value";
                $mergedArray[$phpKey] = $value;
            } elseif ($value !== $phpArray[$phpKey]) {
                $this->info("Updating key in php: $key");
                $changes[] = "Updated $key from  $value to {$phpArray[$phpKey]}";
                $jsonArray[$key] = $phpArray[$phpKey];
            }
        }

        $this->displayChanges($changes);

        if ($this->option('dry-run')) {
            return;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Apply changes?')) {
                return;
            }
        }

        ksort($mergedArray);
        $this->updatePhpFile($mergedArray);
        $this->updateJsonFile($jsonArray);
    }

    private function displayChanges($changes)
    {
        $this->info('Changes:');
        foreach ($changes as $change) {
            $this->info($change);
        }
    }

    private function updatePhpFile($phpArray)
    {
        $unflatPhpArray = Arr::undot($phpArray);
        $php = '<?php return '.var_export($unflatPhpArray, true).';';
        file_put_contents(lang_path('ja/ledger.php'), $php);
        $this->info('lang/ja/ledger.php updated successfully!');
    }

    private function updateJsonFile($phpArray)
    {
        $json = json_encode($phpArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(lang_path('ja.json'), $json);
        $this->info('lang/ja.json updated successfully!');
    }
}
