<?php

namespace App\Console\Commands\Ledger;

use App\Models\Ledger;
use Illuminate\Console\Command;

class RepairJsonColumns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:repair-json-columns {--tenant= : 特定のテナントのみを対象にする} {--dry-run : 実際の更新を行わずに対象を確認する}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '二重エンコードされた台帳レコードの content カラムを修復します。';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        if ($tenantId) {
            $tenants = [\App\Models\Tenant::find($tenantId)];
        } else {
            $tenants = \App\Models\Tenant::all();
        }

        foreach ($tenants as $tenant) {
            if (! $tenant) {
                continue;
            }

            $this->info("テナント [{$tenant->id}] を処理中...");
            tenancy()->initialize($tenant);

            $ledgers = Ledger::all();
            $repairedCount = 0;

            foreach ($ledgers as $ledger) {
                $content = $ledger->content;
                $contentAttached = $ledger->content_attached;
                $needsUpdate = false;

                if ($this->repairArray($content)) {
                    $needsUpdate = true;
                }

                if ($this->repairArray($contentAttached)) {
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $repairedCount++;
                    if (! $dryRun) {
                        // キャストを介さず保存すると整合性が壊れる可能性があるため、
                        // モデル経由で保存し、AsColumnArrayJson が正しく処理するようにする。
                        $ledger->content = $content;
                        $ledger->content_attached = $contentAttached;
                        $ledger->save();
                    }
                    $this->line("  ID: {$ledger->id} を修復対象として検知しました。");
                }
            }

            $this->info("テナント [{$tenant->id}]: {$repairedCount} 件のレコードを".($dryRun ? '検知' : '修復').'しました。');
            tenancy()->end();
        }

        return 0;
    }

    /**
     * 配列内の要素がJSON文字列化されている場合、デコードして修復を試みる。
     *
     * @param  array  &$array
     * @return bool 変更があったかどうか
     */
    private function repairArray(&$array): bool
    {
        if (! is_array($array)) {
            return false;
        }

        $changed = false;
        foreach ($array as $key => $value) {
            // 文字列であり、JSON配列またはオブジェクトのように見える場合
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                try {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    // デコード結果が配列であれば、それは二重エンコードされていたとみなす
                    if (is_array($decoded)) {
                        $array[$key] = $decoded;
                        $changed = true;
                    }
                } catch (\JsonException $e) {
                    // JSONではない場合はスキップ
                }
            }
        }

        return $changed;
    }
}
