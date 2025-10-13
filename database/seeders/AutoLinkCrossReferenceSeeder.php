<?php

namespace Database\Seeders;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * AutoLink Cross Reference Seeder
 *
 * 自動リンククロスリファレンス機能のデモ用データセット
 *
 * 目的:
 * - 全ての台帳定義に自動ナンバリングカラムを追加
 * - 台帳間で他の台帳の文書番号を参照するデータを作成
 * - 自動リンク機能の動作確認を可能にする
 *
 * 前提条件:
 * - DemoMinimalSeederが実行済みであること
 * - DemoPhase1ExtensionSeederが実行済みであること
 */
class AutoLinkCrossReferenceSeeder extends Seeder
{
    private $tenant;

    private array $ledgerDefines = [];

    private array $ledgers = [];

    public function run(): void
    {
        $this->command->info('🚀 Starting AutoLink Cross Reference Seeder...');

        $this->command->info('🏢 Step 1: Initialize tenant...');
        $this->initializeTenant();

        $this->command->info('📝 Step 2: Adding auto_number columns to existing ledger defines...');
        $this->addAutoNumberColumns();

        $this->command->info('🔢 Step 2.5: Assigning dummy auto_numbers to existing ledgers...');
        $this->assignDummyAutoNumbersToExistingLedgers();

        $this->command->info('📊 Step 3: Creating ledgers with cross-references...');
        $this->createCrossReferenceLedgers();

        $this->command->info('✅ AutoLink Cross Reference Seeder completed successfully!');
        $this->displayUsage();
    }

    private function initializeTenant(): void
    {
        $this->tenant = \App\Models\Tenant::where('id', 'demo-tenant')->first();

        if (! $this->tenant) {
            $this->command->error('   ✗ Tenant "demo-tenant" not found. Please run DemoCompleteSeeder first.');
            exit(1);
        }

        tenancy()->initialize($this->tenant);
        $this->command->info('   ✓ Tenant initialized: '.$this->tenant->id);
    }

    private function addAutoNumberColumns(): void
    {
        // 営業日報に自動ナンバリングを追加
        $this->updateSalesDailyDefine();

        // 設備点検表に自動ナンバリングを追加
        $this->updateFacilityInspectionDefine();

        // 週報に自動ナンバリングを追加
        $this->updateWeeklyReportDefine();

        // 経費申請は既に auto_number があるので確認のみ
        $this->verifyExpenseApplicationDefine();
    }

    private function updateSalesDailyDefine(): void
    {
        $define = LedgerDefine::where('title', '[DEMO] 営業日報')->first();

        if (! $define) {
            $this->command->warn('   ⚠ [DEMO] 営業日報 not found. Skipping.');

            return;
        }

        $columns = collect($define->column_define);

        // 既にauto_numberカラムが存在する場合はスキップ
        if ($columns->contains(fn ($c) => $c->type === 'auto_number')) {
            $this->command->info('   ✓ [DEMO] 営業日報 already has auto_number. Skipping modification.');
            $this->ledgerDefines['営業日報'] = $define;

            return;
        }

        // 1. 新しいカラムIDを安全に採番（既存の最大ID + 1）
        $newId = $columns->max('id') + 1;

        // 2. 既存カラムのorderを+1する
        $updatedColumns = $columns->map(function ($column) {
            $column->order++;

            return $column;
        })->all();

        // 3. 新しいauto_numberカラムを作成し、配列の先頭に追加
        $newColumn = new ColumnDefine(
            $newId, '日報番号', 'auto_number', 0, // orderを0に設定して先頭に
            ['prefix' => 'DAILY-', 'digits' => 4, 'revision' => ''],
            false, true, true, '自動採番', [],
            1, '基本情報'
        );
        array_unshift($updatedColumns, $newColumn);

        $define->column_define = $updatedColumns;
        $define->save();

        $this->ledgerDefines['営業日報'] = $define;
        $this->command->info('   ✓ Added auto_number to: [DEMO] 営業日報 (DAILY-XXXX)');
    }

    private function updateFacilityInspectionDefine(): void
    {
        $define = LedgerDefine::where('title', '[DEMO] 設備点検表')->first();

        if (! $define) {
            $this->command->warn('   ⚠ [DEMO] 設備点検表 not found. Skipping.');

            return;
        }

        $columns = collect($define->column_define);

        // 既にauto_numberカラムが存在する場合はスキップ
        if ($columns->contains(fn ($c) => $c->type === 'auto_number')) {
            $this->command->info('   ✓ [DEMO] 設備点検表 already has auto_number. Skipping modification.');
            $this->ledgerDefines['設備点検表'] = $define;

            return;
        }

        // 1. 新しいカラムIDを安全に採番（既存の最大ID + 1）
        $newId = $columns->max('id') + 1;

        // 2. 既存カラムのorderを+1する
        $updatedColumns = $columns->map(function ($column) {
            $column->order++;

            return $column;
        })->all();

        // 3. 新しいauto_numberカラムを作成し、配列の先頭に追加
        $newColumn = new ColumnDefine(
            $newId, '点検番号', 'auto_number', 0, // orderを0に設定して先頭に
            ['prefix' => 'INSP-', 'digits' => 4, 'revision' => ''],
            false, true, true, '自動採番', [],
            1, '基本情報'
        );
        array_unshift($updatedColumns, $newColumn);

        $define->column_define = $updatedColumns;
        $define->save();

        $this->ledgerDefines['設備点検表'] = $define;
        $this->command->info('   ✓ Added auto_number to: [DEMO] 設備点検表 (INSP-XXXX)');
    }

    private function updateWeeklyReportDefine(): void
    {
        $define = LedgerDefine::where('title', '[DEMO] 週報')->first();

        if (! $define) {
            $this->command->warn('   ⚠ [DEMO] 週報 not found. Skipping.');

            return;
        }

        $columns = collect($define->column_define);

        // 既にauto_numberカラムが存在する場合はスキップ
        if ($columns->contains(fn ($c) => $c->type === 'auto_number')) {
            $this->command->info('   ✓ [DEMO] 週報 already has auto_number. Skipping modification.');
            $this->ledgerDefines['週報'] = $define;

            return;
        }

        // 1. 新しいカラムIDを安全に採番（既存の最大ID + 1）
        $newId = $columns->max('id') + 1;

        // 2. 既存カラムのorderを+1する
        $updatedColumns = $columns->map(function ($column) {
            $column->order++;

            return $column;
        })->all();

        // 3. 新しいauto_numberカラムを作成し、配列の先頭に追加
        $newColumn = new ColumnDefine(
            $newId, '週報番号', 'auto_number', 0, // orderを0に設定して先頭に
            ['prefix' => 'WR-', 'digits' => 4, 'revision' => ''],
            false, true, true, '自動採番', [],
            1, '基本情報'
        );
        array_unshift($updatedColumns, $newColumn);

        $define->column_define = $updatedColumns;
        $define->save();

        $this->ledgerDefines['週報'] = $define;
        $this->command->info('   ✓ Added auto_number to: [DEMO] 週報 (WR-XXXX)');
    }

    private function verifyExpenseApplicationDefine(): void
    {
        $define = LedgerDefine::where('title', '[DEMO] 経費申請')->first();

        if (! $define) {
            $this->command->warn('   ⚠ [DEMO] 経費申請 not found. Skipping.');

            return;
        }

        // 既に auto_number カラムがあるはず
        $hasAutoNumber = false;
        foreach ($define->column_define as $column) {
            if ($column->type === 'auto_number') {
                $hasAutoNumber = true;
                break;
            }
        }

        $this->ledgerDefines['経費申請'] = $define;

        if ($hasAutoNumber) {
            $this->command->info('   ✓ Verified: [DEMO] 経費申請 already has auto_number (EXP-XXXX)');
        } else {
            $this->command->warn('   ⚠ [DEMO] 経費申請 does not have auto_number. Please check the seeder.');
        }
    }

    private function assignDummyAutoNumbersToExistingLedgers(): void
    {
        $this->command->info('   🔍 Assigning dummy auto_numbers to existing ledgers...');

        foreach ($this->ledgerDefines as $defineName => $define) {
            $autoNumberColumn = collect($define->column_define)
                ->first(fn ($c) => $c->type === 'auto_number');

            if (! $autoNumberColumn) {
                $this->command->info("     ⚠ {$defineName} has no auto_number column. Skipping.");
                continue;
            }

            $ledgers = Ledger::where('ledger_define_id', $define->id)->get();
            $prefix = $autoNumberColumn->options['prefix'] ?? '';
            $digits = $autoNumberColumn->options['digits'] ?? 4;
            $autoNumberIndex = $autoNumberColumn->id; // auto_numberカラムのインデックス

            $count = 1;
            foreach ($ledgers as $ledger) {
                $content = $ledger->content;

                // auto_numberカラムのインデックスに値が設定されていない場合のみ更新
                if (! isset($content[$autoNumberIndex]) || empty($content[$autoNumberIndex])) {
                    $dummyNumber = $prefix.str_pad((string) $count, $digits, '0', STR_PAD_LEFT);
                    $revision = $autoNumberColumn->options['revision'] ?? '';
                    if (! empty($revision)) {
                        $dummyNumber .= $revision;
                    }
                    $content[$autoNumberIndex] = $dummyNumber;
                    $ledger->content = $content;
                    $ledger->save();
                    $this->command->info("     ✓ Assigned dummy auto_number {$dummyNumber} to {$defineName} Ledger ID: {$ledger->id}");
                }
                $count++;
            }
        }
    }

    private function createCrossReferenceLedgers(): void
    {
        $this->command->info('   📝 Creating demo ledgers with cross-references...');

        // 既存の台帳を取得して番号を記録
        $this->collectExistingLedgers();

        // 新しい台帳を作成（クロスリファレンス付き）
        $this->createCrossReferencedSalesDaily();
        $this->createCrossReferencedExpenseApplication();
        $this->createCrossReferencedWeeklyReport();
        $this->createCrossReferencedFacilityInspection();
    }

    private function collectExistingLedgers(): void
    {
        $this->command->info('   🔍 Collecting existing ledgers for reference...');

        // 各台帳定義の最新の台帳を取得
        foreach ($this->ledgerDefines as $key => $define) {
            $ledger = Ledger::where('ledger_define_id', $define->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($ledger && isset($ledger->content[0])) {
                $this->ledgers[$key] = $ledger->content[0];
                $this->command->info("     ✓ Found {$key}: {$ledger->content[0]}");
            }
        }
    }

    private function createCrossReferencedSalesDaily(): void
    {
        if (! isset($this->ledgerDefines['営業日報'])) {
            return;
        }

        $define = $this->ledgerDefines['営業日報'];
        $demoUser = User::where('email', 'demo@example.com')->first();

        if (! $demoUser) {
            $this->command->warn('   ⚠ Demo user not found.');

            return;
        }

        // 経費申請番号と週報番号を参照する営業日報を作成
        $expNumber = $this->ledgers['経費申請'] ?? 'EXP-0001';
        $wrNumber = $this->ledgers['週報'] ?? 'WR-0001';

        $content = [
            now()->format('Y-m-d'), // 日付
            '株式会社F商事', // 顧客名
            'システム導入提案とフォローアップ', // 訪問目的
            '提案中', // 商談ステータス
            '高', // 優先度
            <<<MARKDOWN
## 面談概要

本日、**株式会社F商事** の情報システム部長と打ち合わせを実施しました。

### 商談内容

前回提出した提案書（週報{$wrNumber}参照）に基づき、詳細なデモンストレーションを実施。
特に全文検索機能とワークフロー機能に強い関心を示されました。

### 経費精算について

今回の訪問に関する経費申請は **{$expNumber}** で提出済みです。
- 交通費: 電車往復
- 会議費: ランチミーティング費用

### 次回アクション

来週、技術担当者を交えた詳細説明会を実施予定。
点検報告書（INSP-0001）の運用フローも紹介する予定。

### 関連文書

- 週報: {$wrNumber}
- 経費申請: {$expNumber}
- 提案書: PROP-2025-001（未作成）
MARKDOWN,
            7 => <<<MARKDOWN
### 評価 ⭐⭐⭐⭐⭐

非常に好感触。特に既存システムの課題を詳しくヒアリングできたことが大きな成果。

#### 成功要因
- 事前準備（{$wrNumber}での計画）が功を奏した
- 経費精算（{$expNumber}）もスムーズに完了
- デモ環境の品質が高く評価された
MARKDOWN,
            '来週火曜日に再訪問。技術担当者向けの詳細説明会を実施。', // 次回アクション
            'DAILY-'.str_pad((string) (Ledger::where('ledger_define_id', $define->id)->count() + 1), 4, '0', STR_PAD_LEFT),
        ];

        Ledger::create([
            'ledger_define_id' => $define->id,
            'creator_id' => $demoUser->id,
            'modifier_id' => $demoUser->id,
            'status' => 'none',
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("   ✓ Created: 営業日報 with references to {$expNumber} and {$wrNumber}");
    }

    private function createCrossReferencedExpenseApplication(): void
    {
        if (! isset($this->ledgerDefines['経費申請'])) {
            return;
        }

        $define = $this->ledgerDefines['経費申請'];
        $demoUser = User::where('email', 'demo@example.com')->first();

        if (! $demoUser) {
            return;
        }

        $dailyNumber = $this->ledgers['営業日報'] ?? 'DAILY-0001';
        $inspNumber = $this->ledgers['設備点検表'] ?? 'INSP-0001';

        $nextNumber = Ledger::where('ledger_define_id', $define->id)->count() + 1;

        $content = [
            now()->format('Y-m-d'), // 申請日
            '交通費', // 経費区分
            5000, // 金額
            <<<MARKDOWN
## 経費の詳細

### 業務内容
営業活動に関する交通費（日報番号: **{$dailyNumber}** 参照）

### 訪問先
株式会社F商事への商談訪問

### 交通手段
- 電車往復: 1,200円
- タクシー（駅〜得意先）: 3,800円

### 関連文書
- 営業日報: {$dailyNumber}
- 設備点検: {$inspNumber}（同日に実施）

### 承認者への連絡事項
この商談は今期の重点案件のため、速やかな承認をお願いいたします。
MARKDOWN,
            [], // 領収書（添付ファイル）
            'EXP-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT),
        ];

        Ledger::create([
            'ledger_define_id' => $define->id,
            'creator_id' => $demoUser->id,
            'modifier_id' => $demoUser->id,
            'status' => 'draft',
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("   ✓ Created: 経費申請 with references to {$dailyNumber} and {$inspNumber}");
    }

    private function createCrossReferencedWeeklyReport(): void
    {
        if (! isset($this->ledgerDefines['週報'])) {
            return;
        }

        $define = $this->ledgerDefines['週報'];
        $demoUser = User::where('email', 'dev1@example.com')->first();

        if (! $demoUser) {
            $demoUser = User::where('email', 'demo@example.com')->first();
        }

        if (! $demoUser) {
            return;
        }

        $dailyNumber = $this->ledgers['営業日報'] ?? 'DAILY-0001';
        $expNumber = $this->ledgers['経費申請'] ?? 'EXP-0001';
        $inspNumber = $this->ledgers['設備点検表'] ?? 'INSP-0001';

        $nextNumber = Ledger::where('ledger_define_id', $define->id)->count() + 1;

        $content = [
            now()->startOfWeek()->format('Y-m-d'), // 週開始日
            <<<MARKDOWN
## 今週の主な成果

### 1. 自動リンク機能の実装完了 ✅

#### クロスリファレンス機能
- 自動ナンバリング値の相互参照が可能に
- 例: 営業日報（{$dailyNumber}）から経費申請（{$expNumber}）へのリンク
- 例: 経費申請（{$expNumber}）から点検報告（{$inspNumber}）へのリンク

#### 技術的な詳細
自動ナンバリングのパターン生成と仮想リンクの実装により、
台帳間のクロスリファレンスが実現しました。

### 2. デモデータの充実

以下の台帳間でクロスリファレンスが可能になりました:
- 営業日報 ↔ 経費申請
- 経費申請 ↔ 設備点検
- 週報 ↔ 全ての台帳

### 3. テストの実施

| テスト種別 | 件数 | 結果 |
|-----------|------|------|
| ユニットテスト | 4件 | ✅ 全てパス |
| フィーチャーテスト | 3件 | ✅ 全てパス |
| 手動テスト | 10件 | ✅ 全てパス |

### 成果物
- PRリンク: #789
- ドキュメント: /docs/work/core-features/auto-link/2025-10-13_auto-number-cross-reference-link-improvement.md

### 関連文書
- 営業日報: {$dailyNumber}
- 経費申請: {$expNumber}
- 点検報告: {$inspNumber}
MARKDOWN,
            3 => <<<MARKDOWN
## 来週の予定

### 最優先 🔴
1. **ユーザーマニュアルの作成**
   - 自動リンク機能の使い方
   - クロスリファレンスの活用例
   - ベストプラクティスの紹介

2. **パフォーマンステスト**
   - 大量データでの動作確認
   - キャッシュ効率の測定

### 通常 🟡
3. **フィードバック収集**
   - ユーザーテストの実施
   - 改善点の洗い出し

4. **ドキュメント整備**
   - API仕様書の更新
   - テストケースの追加

### 関連
営業日報（{$dailyNumber}）、経費申請（{$expNumber}）、点検報告（{$inspNumber}）の
クロスリファレンスパターンをさらに充実させる予定。
MARKDOWN,
            '予定通り', // 進捗状況
            'WR-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT),
        ];

        Ledger::create([
            'ledger_define_id' => $define->id,
            'creator_id' => $demoUser->id,
            'modifier_id' => $demoUser->id,
            'status' => 'draft',
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("   ✓ Created: 週報 with references to {$dailyNumber}, {$expNumber}, and {$inspNumber}");
    }

    private function createCrossReferencedFacilityInspection(): void
    {
        if (! isset($this->ledgerDefines['設備点検表'])) {
            return;
        }

        $define = $this->ledgerDefines['設備点検表'];
        $inspector = User::where('email', 'inspector1@example.com')->first();

        if (! $inspector) {
            $inspector = User::where('email', 'demo@example.com')->first();
        }

        if (! $inspector) {
            return;
        }

        $expNumber = $this->ledgers['経費申請'] ?? 'EXP-0001';
        $wrNumber = $this->ledgers['週報'] ?? 'WR-0001';

        $nextNumber = Ledger::where('ledger_define_id', $define->id)->count() + 1;

        $content = [
            now()->format('Y-m-d'), // 点検日
            'サーバールーム空調設備', // 設備名
            '月次点検', // 点検区分
            ['外観異常なし', '動作正常', '異音なし', '温度正常', '清掃実施'], // 点検項目
            <<<MARKDOWN
## 点検結果

### 総合評価
✅ **正常** - 全項目で異常なし

### 詳細確認項目
- [x] 外観に損傷・劣化なし
- [x] 動作音が正常範囲内
- [x] 温度・湿度が適正（20℃、55%）
- [x] 清掃完了（フィルター・外装）

### 修理・メンテナンス履歴
前回（先月）の点検で指摘した軽微な振動問題は完全に解消されています。

### 経費精算
フィルター交換部品の購入費用を経費申請（{$expNumber}）で申請予定。

### 関連文書
- 週報: {$wrNumber}（今週の活動として報告済み）
- 経費申請: {$expNumber}（部品購入費）
- 前回点検: INSP-0001

### 次回点検予定
来月同日（定期点検）

### 備考
この空調設備は重要インフラのため、異常を発見した場合は
即座に設備管理部門（内線1234）に連絡してください。
MARKDOWN,
            'INSP-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT),
        ];

        Ledger::create([
            'ledger_define_id' => $define->id,
            'creator_id' => $inspector->id,
            'modifier_id' => $inspector->id,
            'status' => 'approved',
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("   ✓ Created: 設備点検表 with references to {$expNumber} and {$wrNumber}");
    }

    private function displayUsage(): void
    {
        $this->command->info('');
        $this->command->info('📚 Usage Guide:');
        $this->command->info('');
        $this->command->info('🔗 Auto Number Formats:');
        $this->command->info('   営業日報:   DAILY-XXXX');
        $this->command->info('   経費申請:   EXP-XXXX');
        $this->command->info('   週報:       WR-XXXX');
        $this->command->info('   設備点検表: INSP-XXXX');
        $this->command->info('');
        $this->command->info('🎯 Cross-Reference Examples:');
        $this->command->info('   営業日報 → 経費申請番号、週報番号を参照');
        $this->command->info('   経費申請 → 営業日報番号、設備点検番号を参照');
        $this->command->info('   週報     → 全ての台帳番号を参照');
        $this->command->info('   設備点検 → 経費申請番号、週報番号を参照');
        $this->command->info('');
        $this->command->info('✨ How to Test:');
        $this->command->info('   1. 台帳詳細画面を開く');
        $this->command->info('   2. 他の台帳の番号（例: EXP-0001）が自動的にリンク化されている');
        $this->command->info('   3. リンクをクリックすると、該当する台帳に遷移');
        $this->command->info('   4. 台帳一覧画面でも同様にリンク化される');
        $this->command->info('');
    }
}
