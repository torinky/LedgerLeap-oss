<?php

namespace Database\Seeders;

use App\Enums\AttachedFileStatus;
use App\Helpers\AttachedFilePathHelper;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 2.6 効果測定用デモデータシーダー
 *
 * 以下の測定項目を実現するためのデータを作成:
 * 1. 初回検索可能時間（Tika完了からベクトル化まで）
 * 2. ファイルタイプ別の処理品質
 * 3. 段階的品質向上の確認
 */
class Phase26DemoSeeder extends Seeder
{
    private Tenant $tenant;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    public function run(): void
    {
        $this->command->info('=== Phase 2.6 Demo Data Seeder ===');

        // 既存のデモデータをクリーンアップ
        $this->cleanupDemoData();

        // 基本データ作成
        $this->createBaseData();

        // 測定用データ作成
        $this->createMeasurementData();

        $this->command->info('✓ Phase 2.6 demo data created successfully!');
        $this->command->info('');
        $this->command->info('測定方法:');
        $this->command->info('1. キュー処理: ./vendor/bin/sail artisan queue:work --queue=default,vlm,ocr');
        $this->command->info('2. ログ監視: ./vendor/bin/sail logs -f laravel.test');
        $this->command->info('3. DB確認: SELECT id, status, finalized_source, tika_processed_at, ocr_processed_at, vlm_processed_at FROM attached_files;');
    }

    private function cleanupDemoData(): void
    {
        $this->command->info('Cleaning up existing demo data...');

        // Phase26Demo tenantを削除
        $tenants = Tenant::where('id', 'phase26demo')->get();

        if ($tenants->isEmpty()) {
            $this->command->info('  No existing demo data found');

            return;
        }

        $this->command->info('  Found '.count($tenants).' tenant(s) to clean up...');

        foreach ($tenants as $tenant) {
            // テナントコンテキストで削除
            tenancy()->initialize($tenant);

            // 外部キー制約を考慮した順序で削除
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            DB::table('ledger_chunks')->delete();
            DB::table('attached_files')->delete();
            DB::table('ledgers')->delete();
            DB::table('role_folder_permissions')->delete();
            DB::table('ledger_defines')->delete();
            DB::table('folders')->delete();
            // user_organizationsは後でクリーンアップ
            DB::table('users')->delete();

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            tenancy()->end();

            // Organization自体を削除（Observerを無効化）
            $tenant->deleteQuietly();
        }

        $this->command->info('✓ Cleanup completed');
    }

    private function createBaseData(): void
    {
        $this->command->info('Creating base data...');

        // Tenant
        $this->tenant = Tenant::firstOrCreate(
            ['id' => 'phase26demo'],
            ['name' => 'Phase26Demo測定用テナント']
        );

        // テナント初期化
        tenancy()->initialize($this->tenant);

        // テナントデータベースのマイグレーション確認
        $connection = \DB::connection('mysql');
        $tablesExist = $connection->getSchemaBuilder()->hasTable('folders');

        if (! $tablesExist) {
            \Artisan::call('tenants:migrate', [
                '--tenants' => [$this->tenant->id],
            ]);
        }

        // User
        $this->user = User::firstOrCreate(
            ['email' => 'phase26@demo.test'],
            [
                'name' => 'Phase26測定ユーザー',
                'password' => bcrypt('password'),
            ]
        );

        // Folder
        $this->folder = Folder::create([
            'title' => 'Phase2.6測定フォルダ',
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        // LedgerDefine
        $this->ledgerDefine = LedgerDefine::create([
            'title' => 'Phase2.6測定台帳',
            'folder_id' => $this->folder->id,
            'column_define' => [
                [
                    'id' => 0,
                    'name' => 'ファイル添付',
                    'typeIdentifier' => 'files',
                    'order' => 0,
                    'required' => false,
                    'unique' => false,
                    'sortBy' => false,
                    'hint' => '',
                    'options' => [],
                    'file' => [],
                    'display_level' => 3,
                ],
                [
                    'id' => 1,
                    'name' => '備考',
                    'typeIdentifier' => 'text',
                    'order' => 1,
                    'required' => false,
                    'unique' => false,
                    'sortBy' => false,
                    'hint' => '',
                    'options' => [],
                    'display_level' => 3,
                ],
            ],
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $this->command->info('✓ Base data created');
    }

    private function createMeasurementData(): void
    {
        $this->command->info('Creating measurement data...');

        // 測定シナリオ1: オフィスファイル（Tikaのみで完了すべき）
        $this->createScenario('オフィスファイル測定', [
            ['type' => 'word', 'name' => 'report.docx', 'content' => $this->generateWordContent()],
            ['type' => 'excel', 'name' => 'data.xlsx', 'content' => $this->generateExcelContent()],
            ['type' => 'ppt', 'name' => 'presentation.pptx', 'content' => $this->generatePptContent()],
        ]);

        // 測定シナリオ2: 画像ファイル（段階的品質向上）
        $this->createScenario('画像ファイル測定', [
            ['type' => 'image', 'name' => 'invoice001.jpg', 'content' => $this->generateImageContent('請求書')],
            ['type' => 'image', 'name' => 'contract001.png', 'content' => $this->generateImageContent('契約書')],
            ['type' => 'image', 'name' => 'receipt001.jpg', 'content' => $this->generateImageContent('領収書')],
        ]);

        // 測定シナリオ3: PDFファイル（混合）
        $this->createScenario('PDFファイル測定', [
            ['type' => 'pdf', 'name' => 'text_pdf.pdf', 'content' => $this->generateTextPdfContent()],
            ['type' => 'pdf', 'name' => 'scanned_pdf.pdf', 'content' => $this->generateScannedPdfContent()],
        ]);

        // 測定シナリオ4: 大量ファイル（パフォーマンス測定）
        $this->createBulkScenario('パフォーマンス測定', 20);

        $this->command->info('✓ Measurement data created');

        // ジョブをディスパッチ
        $this->dispatchJobs();
    }

    private function dispatchJobs(): void
    {
        $this->command->info('Dispatching jobs...');

        $files = AttachedFile::where('ledger_define_id', $this->ledgerDefine->id)
            ->where('status', AttachedFileStatus::PENDING_INITIAL_PROCESSING)
            ->get();

        foreach ($files as $file) {
            ProcessAttachedFile::dispatch($file);
        }

        $this->command->info("✓ Dispatched {$files->count()} ProcessAttachedFile jobs");
    }

    private function createScenario(string $ledgerName, array $files): void
    {
        $ledger = Ledger::create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'content' => [
                1 => $ledgerName,
            ],
            'content_attached' => [],
        ]);

        foreach ($files as $fileData) {
            $this->createAttachedFile($ledger, $fileData);
        }

        $this->command->info("  ✓ Scenario: {$ledgerName} (".count($files).' files)');
    }

    private function createBulkScenario(string $ledgerName, int $count): void
    {
        $this->command->info("  Creating bulk scenario: {$ledgerName} ({$count} files)...");

        $files = [];
        $types = ['word', 'excel', 'image', 'pdf'];

        for ($i = 1; $i <= $count; $i++) {
            $type = $types[array_rand($types)];
            $files[] = [
                'type' => $type,
                'name' => "bulk_{$type}_{$i}.".$this->getExtension($type),
                'content' => $this->generateContentByType($type, $i),
            ];
        }

        $this->createScenario($ledgerName, $files);
    }

    private function createAttachedFile(Ledger $ledger, array $fileData): void
    {
        $mime = $this->getMimeType($fileData['type']);
        $hashedName = hash('sha256', $fileData['name'].microtime());
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $hashedBasename = $hashedName.'.'.$extension;

        // AttachedFilePathHelperを使って正しいパスを生成
        $path = AttachedFilePathHelper::getAttachmentPath(
            $this->ledgerDefine->id,
            $hashedBasename
        );
        Storage::disk('public')->put($path, $fileData['content']);

        AttachedFile::create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'column_id' => 0,
            'filename' => $fileData['name'],
            'hashedbasename' => $hashedBasename,
            'path' => $path,
            'mime' => $mime,
            'size' => strlen($fileData['content']),
            'status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING,
            'contain_content' => false,
            'optimized' => false,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);
    }

    private function getMimeType(string $type): string
    {
        return match ($type) {
            'word' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function getExtension(string $type): string
    {
        return match ($type) {
            'word' => 'docx',
            'excel' => 'xlsx',
            'ppt' => 'pptx',
            'image' => 'jpg',
            'pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function generateContentByType(string $type, int $index): string
    {
        return match ($type) {
            'word' => $this->generateWordContent($index),
            'excel' => $this->generateExcelContent($index),
            'image' => $this->generateImageContent("文書{$index}"),
            'pdf' => $this->generateTextPdfContent($index),
            default => "Dummy content {$index}",
        };
    }

    // ダミーコンテンツ生成メソッド

    private function generateWordContent(int $index = 1): string
    {
        return <<<EOT
=== 報告書 #{$index} ===

株式会社サンプル商事　御中

件名: 2025年度第{$index}四半期業績報告

1. 売上概況
   - 前年比: 120%
   - 製品番号ABC-{$index}が好調
   - 新規顧客獲得: 50社

2. 課題
   - 在庫管理の最適化
   - 物流コストの削減

以上
EOT;
    }

    private function generateExcelContent(int $index = 1): string
    {
        return <<<EOT
製品番号,製品名,単価,在庫数
ABC-{$index},高性能センサー,50000,100
DEF-{$index},制御ユニット,80000,50
GHI-{$index},ケーブルセット,5000,500
EOT;
    }

    private function generatePptContent(int $index = 1): string
    {
        return <<<EOT
=== プレゼンテーション #{$index} ===

スライド1: タイトル
2025年度事業計画

スライド2: 目標
売上目標: 前年比130%
新規顧客: 100社

スライド3: 戦略
製品番号ABC-{$index}の拡販
海外展開の強化
EOT;
    }

    private function generateImageContent(string $documentType): string
    {
        return <<<EOT
=== {$documentType} ===

株式会社サンプル商事
〒100-0001 東京都千代田区千代田1-1-1

お客様名: 株式会社テスト工業
注文番号: ORD-2025-001
製品番号: ABC-12345

金額: ¥500,000
消費税: ¥50,000
合計: ¥550,000

支払期限: 2025年12月31日
EOT;
    }

    private function generateTextPdfContent(int $index = 1): string
    {
        return <<<EOT
=== PDF文書 #{$index} ===

契約書

甲: 株式会社サンプル商事
乙: 株式会社テスト工業

第1条（目的）
本契約は製品番号ABC-{$index}の供給について定める。

第2条（納期）
2025年12月31日までに納品する。

第3条（金額）
金500,000円（税別）
EOT;
    }

    private function generateScannedPdfContent(): string
    {
        // スキャンPDFを模擬（実際はOCRが必要な画像データ）
        return $this->generateImageContent('スキャン契約書');
    }
}
