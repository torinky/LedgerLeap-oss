# テスト網羅率（Coverage）とテスト重複（冗長）検知：実施方針（LedgerLeap 向けまとめ）

作成日: 2026-02-11
更新日: 2026-02-11 (Sail環境対応・PoC完了版)
作成者: 自動生成

## 目的
- LedgerLeap リポジトリ（**Laravel Sail 環境**）における「どの程度テストがコードをカバーしているか」を把握する。
- 冗長（重複）テストやテストの弱点を効率的に発見／改善するための方針と、実行可能な手順を示す。

## 短い結論（推奨順）
1. **総合 coverage**（PCOV推奨、`sail composer test:coverage` で即座に可視化）
2. **静的重複検出**（PHPCPD on Sail、`sail composer test:duplication` で実行）
3. **並列実行による高速化**（Paratest on Sail、`sail test --parallel`）
4. **フレーク／実行順依存チェック**（ランダム実行）
5. **Mutation Testing**（Infection、`sail composer test:mutation` で重要モジュール限定実行）

## 設計方針（LedgerLeap 特有ルールの遵守）

1.  **テナント初期化の厳格化**: Unit/Feature テストの `setUp()` では必ず `tenancy()->initialize($tenant)` を行うこと。
    -   *禁止事項*: テストメソッド内で別のテナントで再初期化しない（状態不整合の原因）。
2.  **Mroonga とデータベース**: 全文検索を含むテスト（Mroonga利用）は `RefreshDatabase` ではなく **`DatabaseMigrations`** を使用する。
3.  **モデルイベントの信頼性**: Sail 環境では `$model->touch()` がイベントを発火しない場合があるため、必ず **`$model->update(['col' => 'val'])`** を使用する。
4.  **Livewire の型制約**: Public プロパティは「連想配列」のみとし、Eloquent モデルを直接保持しない（Hydration エラー回避）。

## 1. 利用できる手法（Sail 環境での考慮点）

- **総合コードカバレッジ**
    -   **ツール**: Pest + **PCOV** (推奨)
    -   **Sailでの注意**: PCOV は Xdebug よりも圧倒的に高速です。`sail php -i | grep pcov` で有効化を確認してください。
    -   **PoC結果**: `tests/Unit/Services` に対して高速にHTMLレポート生成を確認済み。

- **静的重複検出（PHPCPD）**
    -   **ツール**: **`systemsdk/phpcpd`** (重要: `sebastian/phpcpd` は Sail 環境で互換性問題あり)
    -   **実行**: Sail コンテナ内で実行。
    -   **利点**: 既存の重複コードを即座に可視化。

- **並列テスト（Paratest）**
    -   **実行**: `sail test --parallel`
    -   **利点**: テスト実行時間の短縮（全体実行時に必須）。

- **Mutation Testing（Infection）**
    -   **ツール**: `infection/infection` (Adapter: `phpunit` with custom path `vendor/bin/pest`)
    -   **注意**: 実行時間が長いため、モジュール単位（`--filter`）での実行を推奨。

## 2. 推奨ツール一覧（Laravel Sail 向け）
-   **Pest (3.x)**: Laravel 標準テストフレームワーク
-   **PCOV**: カバレッジ計測用ドライバ（高速）
-   **systemsdk/phpcpd**: コード重複検出（Sail互換フォーク）
-   **brianium/paratest**: 並列実行（Laravel `sail test --parallel` で利用）
-   **infection/infection**: Mutation testing

## 3. 主要コマンド例（Composer Scripts 利用）

PoC を経て、以下のショートカットコマンドを `composer.json` に定義済みです。

### 前提確認
```bash
# Sail の起動
./vendor/bin/sail up -d
```

### A. 総合 coverage（HTMLレポート生成）
```bash
# HTML レポート出力 (coverage/ ディレクトリに生成)
./vendor/bin/sail composer test:coverage
```
*ブラウザで `coverage/index.html` を開いて確認します。*

### B. 静的重複検出（PHPCPD）
```bash
# app ディレクトリ全体を対象に実行
./vendor/bin/sail composer test:duplication
```

### C. Mutation Testing（Infection）
```bash
# 全体実行（非常に時間がかかります）
./vendor/bin/sail composer test:mutation

# [推奨] 特定のファイル/ディレクトリに限定して実行
# [推奨] 特定のファイルに限定して実行（高速・部分的）
# 例: app/Services/LedgerService.php のみ
./vendor/bin/sail composer test:mutation -- --filter=app/Services/LedgerService.php
# [さらに最適化] 初期テストの実行範囲も絞る場合（全体テストが遅い場合）
# --test-framework-options を使用して Pest にフィルタを渡す
./vendor/bin/sail composer test:mutation -- --filter=app/Services/LedgerService.php --test-framework-options="--filter=Ledger"

```

### D. 並列実行（高速化）
```bash
# Pest/Paratest を使用した並列実行
./vendor/bin/sail test --parallel --processes=4
```

## 4. LedgerLeap 向け PoC（完了）

**対象**: `app/Services` 以下のビジネスロジック
**結果**: [PoC Report (2026-02-11)](./2026-02-11_poc_report.md) 参照

### 実施結果まとめ
-   **環境**: Sail + PCOV での高速なカバレッジ計測を確認。
-   **重複検知**: `LedgerService` の重複を検出し、リファクタリング（`buildMetaData` 共通化）で解消済み。
-   **Mutation**: Infection の動作を確認。Pest との連携には `infection.json5` で `phpUnit.customPath` の設定が必要（設定済み）。

## 5. 次のアクション

1.  **日々の開発**: 
    -   PR 作成前に `sail composer test:duplication` で重複チェック。
    -   新機能実装時に `sail composer test:coverage` でカバレッジ確認。
2.  **CI/CD 統合**:
    -   今回整備したコマンド (`composer test:*`) を GitHub Actions ワークフローに組み込む。
3.  **既存コードの改善**:
    -   PHPCPD で検出されている他の重複（`IndexManager` vs `RecordsTable` 等）の解消。

---
## 6. Mutation Testing のトラブルシューティング
### A. 「No source code was executed」と表示される場合
初期テストの実行結果が 0 件、または対象ファイルがカバーされていないと判定されています。
- **原因**: `--test-framework-options="--filter=..."` で指定した名前が、実際のテストクラス名やメソッド名と一致していない可能性があります。
- **対策**: `sail test --filter=...` 単体で実行し、期待したテストが走るか確認してください。
### B. 「Mutants required more time than configured」と表示される場合
各変異の検証（テスト実行）がタイムアウトしています。
- **原因**: LedgerLeap の多くのテストはデータベースを利用する「重い」テストであるため、デフォルトのタイムアウトでは不足します。
- **対策**: `--timeout=300` （秒）などの大きな値を指定して実行してください。
### C. カバレッジが 0% になる場合
Infection がテストの実行によるソースコードへの接触を検知できていません。
- **原因**: PCOV が正しく認識されていないか、フィルター設定が不適切です。
- **対策**: まず `sail composer test:coverage` でカバレッジが取れているか確認してください。カバレッジが取れていれば、Infection でも取れるはずです。
---
## 付録: 部分実行用コマンドリスト（Service別・最適化版）
タイムアウトを回避し、実行速度を劇的に向上させるため、以下のオプションを組み合わせたコマンドの使用を強く推奨します。
**実行速度向上のためのポイント**:
- `--filter`: **変異（Mutation）させるソースファイル**を限定します。
- `--test-framework-options="..."`: **実行するテストケース**を限定します。タイムアウト防止に不可欠です。
- `--timeout=300`: 個別テストの遅延を許容します。
- `--map-source-class-to-test`: 修正に関連するテストのみを動的に検出しようとします。
### 各 Service 用・最適化済みコマンド一覧
```bash
# AdSyncService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/AdSyncService.php --test-framework-options="--filter=AdSync" --map-source-class-to-test
# AnalyticsService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/AnalyticsService.php --test-framework-options="--filter=Analytics" --map-source-class-to-test
# AutoLinkService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/AutoLinkService.php --test-framework-options="--filter=AutoLink" --map-source-class-to-test
# EmbeddingService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/EmbeddingService.php --test-framework-options="--filter=Embedding" --map-source-class-to-test
# LedgerService (重要)
./vendor/bin/sail composer test:mutation -- --filter=app/Services/LedgerService.php --test-framework-options="--filter=Ledger" --map-source-class-to-test
# NotificationService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/NotificationService.php --test-framework-options="--filter=Notification" --map-source-class-to-test
# NumberingService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/NumberingService.php --test-framework-options="--filter=Numbering" --map-source-class-to-test
# PermissionService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/PermissionService.php --test-framework-options="--filter=Permission" --map-source-class-to-test
# RagSearchService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/RagSearchService.php --test-framework-options="--filter=RagSearch" --map-source-class-to-test
# SynonymService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/SynonymService.php --test-framework-options="--filter=Synonym" --map-source-class-to-test
# TenantAccessService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/TenantAccessService.php --test-framework-options="--filter=TenantAccess" --map-source-class-to-test
# UserService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/UserService.php --test-framework-options="--filter=User" --map-source-class-to-test
# VlmClientService
./vendor/bin/sail composer test:mutation -- --filter=app/Services/VlmClientService.php --test-framework-options="--filter=VlmClient" --map-source-class-to-test
# WorkflowService (要増加タイムアウト)
./vendor/bin/sail composer test:mutation -- --filter=app/Services/WorkflowService.php --test-framework-options="--filter=Workflow" --map-source-class-to-test
```
---
## 付録: 参考リンク
- [Infection PHP Guide (Filtering)](https://infection.github.io/guide/usage.html#Filtering-mutated-files)
- [Pest/PHPUnit Filters](https://pestphp.com/docs/filtering-tests)
- [PCOV drivers](https://github.com/krakjoe/pcov)
