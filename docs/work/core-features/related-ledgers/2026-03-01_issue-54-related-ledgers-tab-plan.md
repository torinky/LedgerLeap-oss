# Issue #54: 詳細画面に関連案件タブを追加

**作成日:** 2026年3月1日  
**更新日:** 2026年3月1日  
**ステータス:** 🚧 Sprint 1 完了 / Sprint 2 進行中  
**目的:** 台帳レコード詳細画面に「関連案件」タブを追加し、識別番号検索・意味検索の2軸で関連レコードを探索できるようにする  
**関連Issue:** https://github.com/torinky/LedgerLeap/issues/54

---

## 🎯 背景と要件

### 背景

現在の台帳詳細画面（`Show`）には「基本情報」「更新履歴」「活動履歴」「アクセスと権限」の4タブがある。  
しかし、あるレコードに関連する他のレコード（例：同じ設備番号を持つ保守記録・点検記録）を横断的に確認する手段が存在しない。  
ユーザーは都度リスト画面に戻り、手動で検索し直す必要があり、業務上のコンテキストスイッチが多発している。

### ペルソナシナリオからの洞察

[`docs/function/PersonaUseCaseScenario.md`](../../../../function/PersonaUseCaseScenario.md) の各ペルソナに対して以下のシナリオが想定される。

#### 実務担当者
- **UC2「過去の業務記録の検索と参照」の延長:** 特定の案件（レコード）を閲覧中に「この案件と似たケースを過去に扱ったか」をその場で確認したい
- 例: 設備点検記録を見ながら「同じ設備番号 EQ-042 に関する過去の保守依頼を確認したい」

#### 現場リーダー / 作業班長
- **UC2「チーム内の情報共有と確認」の延長:** 障害報告レコードを確認中に「類似の過去インシデント」を即座に参照し、対応方針の参考にしたい
- 例: クレーム対応票を開きながら「同様のクレーム事例を意味検索で発掘したい」

#### 管理者
- **UC3「活動状況の監査」の延長:** 特定の台帳レコードを起点に、識別番号で紐づく全関連文書を一覧確認し監査の網羅性を担保したい

---

## 📐 機能要件

### 1. 新タブ「関連案件」の追加

- `show.blade.php` の `x-mary-tabs` に新タブを追加
- 既存タブ（details/history/activity/permissions）と同列に並ぶ
- URL パラメータ `?tab=related` でディープリンク可能

### 2. 識別番号検索（サブタブ①）

- 現在のレコードに含まれる **`auto_number` タイプのカラム値** を抽出し、`AutoLinkService` の `createAutoNumberLink()` が生成する lookup パスと同じキーで全台帳を横断検索する
- 検索対象: `Ledger::where('content', 'LIKE', '%{識別番号}%')` ではなく、`/ledgers/lookup/{query}` のバックエンドロジックを Service として直接呼び出す
- 現在表示中のレコード自身は結果から除外する
- 複数の `auto_number` カラムがある場合はそれぞれ検索してマージ（重複排除）

### 3. 意味検索（サブタブ②）

- 現在のレコードのコンテンツを検索クエリとして `RagSearchService::searchLedgers()` を呼び出す
- クエリ生成: `content` の各カラム値をテキストとして結合（`files` タイプは除く）
- 上位 N 件（デフォルト20件）を表示
- 現在表示中のレコード自身は結果から除外する
- RAGサービス未起動時は「意味検索は現在利用できません」メッセージを表示（グレースフルデグラデーション）

### 4. タブバッジ（件数表示）

- 「関連案件」タブのラベル横に `badge` で総件数を表示
- 件数は識別番号検索 + 意味検索の **重複排除後の合計**（または各検索の件数を別バッジで表示）
- 件数が0の場合はバッジ非表示（タブ自体は表示する）
- 件数は `lazy` ロード後に非同期で更新

### 5. 権限フィルター

- 表示結果はログインユーザーが閲覧可能なレコードのみ
- 既存の `PermissionService` / `WritableFolderRepository` による権限フィルターを通す

---

## 🏗 アーキテクチャ設計

### コンポーネント構成

```
Show.php（親）
└── [新タブ] related
    └── livewire:ledger.related-ledgers（新規 Livewire コンポーネント）
        ├── 識別番号検索結果リスト
        └── 意味検索結果リスト
```

### 新規ファイル

| ファイル | 種別 | 目的 |
|---|---|---|
| `app/Livewire/Ledger/RelatedLedgers.php` | Livewire | 関連案件タブのコントローラー |
| `resources/views/livewire/ledger/related-ledgers.blade.php` | Blade | 関連案件タブのビュー |

### 変更ファイル

| ファイル | 変更内容 |
|---|---|
| `resources/views/livewire/ledger/show.blade.php` | 新タブの追加、バッジ表示 |
| `app/Livewire/Ledger/Show.php` | `$relatedCount` プロパティ追加（任意） |

### 既存資産の流用

| 既存資産 | 流用方法 |
|---|---|
| `RagSearchService::searchLedgers()` | 意味検索に直接利用 |
| `AutoLinkService` + lookup ロジック | 識別番号検索のバックエンド |
| `InitializesTenantContext` trait | テナント初期化 |
| `loading-overlay` コンポーネント | ローディング表示 |
| `ledger-diff-viewer` の `lazy` パターン | 遅延ロードでパフォーマンス確保 |

### データフロー

```
[RelatedLedgers::mount(Ledger $ledger)]
   ↓
   1. auto_number カラム値の抽出 → identifierKeys[]
   2. ユーザー閲覧可能フォルダ ID 取得
   ↓
[render()]
   ├── 識別番号検索: foreach $identifierKeys → LedgerLookupService 呼び出し
   │     → 結果をマージ・重複排除・自身を除外
   └── 意味検索: コンテンツをクエリ化 → RagSearchService::searchLedgers()
         → 自身を除外
```

---

## ⚠️ 技術的考慮事項

### パフォーマンス

- タブは `lazy` 属性で遅延ロード（初期表示コストゼロ）
- 意味検索は RAG ベクトル検索のため重い → スピナー表示を忘れずに
- 識別番号検索: `auto_number` カラムが複数ある場合でも N+1 クエリを防ぐ（IN 句でまとめて取得）

### エッジケース

| ケース | 対応 |
|---|---|
| `auto_number` カラムが存在しない | 識別番号検索セクションを非表示 |
| RAGサービス未起動 | 意味検索セクションに「利用不可」メッセージ |
| 両方0件 | 「関連案件が見つかりませんでした」のプレースホルダー表示 |
| 自身のレコードが検索結果に含まれる | `where('id', '!=', $this->ledgerId)` で除外 |

### Mroonga 制約（Critical）

- 識別番号の全文検索に Mroonga の複合インデックスは使用不可
- `MATCH() AGAINST()` は単一カラムに限定、複数カラムは `OR` で結合

---

## 📋 WBS：スプリント計画

---

### 🏁 Sprint 1: バックエンド基盤（識別番号検索）

**目標:** 識別番号検索の Service ロジックを実装し、テストで動作検証する  
**確認ポイント:** テストがパスし、識別番号で関連レコードが正しく取得されること

#### Block 1.1: 識別番号抽出ロジック

- [x] **Task 1.1.1**: `auto_number` カラム値の抽出メソッドを `RelatedLedgers.php` に実装
  - 対象: `$ledger->content` から `auto_number` タイプのカラムを取得
  - 出力: `['SPEC-001', 'EQ-042']` のような文字列配列
  - 依存: なし

#### Block 1.2: 識別番号による横断検索

- [x] **Task 1.2.1**: `LedgerLookupController` のバックエンドロジックを調査し、`SearchContext` + `scopeSearchContext` を直接利用する方針に決定
  - `AutoLinkService` は HTML 生成担当のため不流用。`LedgerLookupController::handle()` と同一ロジックを `RelatedLedgers` に実装
  - 依存: Task 1.1.1

- [x] **Task 1.2.2**: 識別番号検索メソッドを実装
  - 複数キーを OR で検索、自身を除外、権限フィルター適用
  - 依存: Task 1.2.1

#### Block 1.3: テスト実装（識別番号検索）

- [x] **Task 1.3.1**: `tests/Feature/Livewire/Ledger/RelatedLedgersTest.php` を新規作成
  - `it_finds_related_ledgers_by_identifier` ✅
  - `it_excludes_self_from_identifier_search` ✅
  - `it_returns_empty_when_no_auto_number_columns` ✅
  - `it_returns_empty_collection_when_no_identifiers_given` ✅ (追加)
  - `it_filters_out_results_from_inaccessible_folders` ✅ (追加)

- [x] **Task 1.3.2**: テスト実行・パス確認 — **8 passed, 1 skipped** (2026-03-01)

**✅ Sprint 1 完了条件**
- 識別番号検索テストが全てパスする ✅
- Pint 実行済み ✅

---

### 🏁 Sprint 2: バックエンド基盤（意味検索）

**目標:** 意味検索の Service ロジックを実装し、テストで動作検証する  
**確認ポイント:** テストがパスし、RAGサービス未起動時もエラーにならないこと

#### Block 2.1: 意味検索クエリ生成

- [ ] **Task 2.1.1**: `RelatedLedgers.php` にコンテンツをクエリ文字列へ変換するメソッドを実装
  - `files` タイプのカラムは除外
  - テキスト長の上限設定（RAG への入力トークン制限対策）
  - 依存: なし

#### Block 2.2: 意味検索の実行

- [ ] **Task 2.2.1**: `RagSearchService::searchLedgers()` を呼び出す意味検索メソッドを実装
  - 結果から自身を除外
  - 権限フィルター適用
  - RagSearchService が利用不可な場合の例外キャッチ
  - 依存: Task 2.1.1

#### Block 2.3: テスト実装（意味検索）

- [ ] **Task 2.3.1**: `RelatedLedgersTest.php` に意味検索テストを追加
  - `it_finds_related_ledgers_by_semantic_search` : 意味検索で関連レコードを取得（RAG モック）
  - `it_excludes_self_from_semantic_search` : 自身が結果から除外される
  - `it_handles_rag_service_unavailable_gracefully` : RAGサービス例外時は空配列を返す

- [ ] **Task 2.3.2**: テスト実行・パス確認

**✅ Sprint 2 完了条件**
- 意味検索テストが全てパスする
- RAGサービス未起動時もエラーにならない
- Pint 実行済み

---

### 🏁 Sprint 3: Livewire コンポーネントとビュー

**目標:** `RelatedLedgers` Livewire コンポーネントと Blade ビューを実装し、動作確認する  
**確認ポイント:** ブラウザで詳細画面に「関連案件」タブが表示され、検索結果がリスト表示されること

#### Block 3.1: Livewire コンポーネント

- [ ] **Task 3.1.1**: `app/Livewire/Ledger/RelatedLedgers.php` を作成
  - `mount(int $ledgerId)` で `$ledgerRecord` をロード
  - `InitializesTenantContext` trait を使用
  - 識別番号検索結果・意味検索結果をプロパティとして保持
  - `$identifierCount` / `$semanticCount` プロパティを公開

- [ ] **Task 3.1.2**: Lazy ロード対応
  - `#[Lazy]` 属性またはタブ内での `wire:init` による遅延ロードを実装

#### Block 3.2: Blade ビュー

- [ ] **Task 3.2.1**: `resources/views/livewire/ledger/related-ledgers.blade.php` を作成
  - 2 セクション構成: 「識別番号で見つかった案件」「意味的に近い案件」
  - 各セクションにローディングオーバーレイ（Tier 2）
  - 各レコードのカード表示: タイトル、台帳定義名、更新日、詳細へのリンク
  - 0 件時のプレースホルダーメッセージ
  - RAGサービス利用不可時のアラート表示

#### Block 3.3: show.blade.php へのタブ追加

- [ ] **Task 3.3.1**: `show.blade.php` に「関連案件」タブを追加
  - `<x-mary-tab name="related" label="{{ __('ledger.tab.related') }}"` として追加
  - 既存の4タブの後に配置
  - `lazy` 遅延ロードを適用

- [ ] **Task 3.3.2**: 翻訳キー追加
  - `lang/ja/ledger.php` に以下を追加:
    ```php
    'tab' => [
        // ...existing keys...
        'related' => '関連案件',
    ],
    'related' => [
        'identifier_section_title' => '識別番号で見つかった案件',
        'semantic_section_title' => '意味的に近い案件',
        'empty' => '関連案件が見つかりませんでした',
        'rag_unavailable' => '意味検索は現在利用できません',
        'count_badge' => ':count 件',
    ],
    ```

#### Block 3.4: 動作確認

- [ ] **Task 3.4.1**: ブラウザでの手動確認
  - auto_number カラムを持つ台帳のレコード詳細画面で関連案件タブを表示
  - 識別番号検索・意味検索の結果が表示されること
  - RAGサービス停止時のフォールバック表示確認

**✅ Sprint 3 完了条件**
- ブラウザで関連案件タブが正常に表示される
- 識別番号検索・意味検索が動作する
- エラーログが出ていない（`laravel-boost` で確認）

---

### 🏁 Sprint 4: タブバッジ・UI 仕上げ・テスト整備

**目標:** タブバッジ表示・件数更新・UI 最終調整を行い、Feature テストを整備して完成させる  
**確認ポイント:** タブに件数バッジが表示され、全テストがパスすること

#### Block 4.1: タブバッジの実装

- [ ] **Task 4.1.1**: `RelatedLedgers.php` に `$totalCount` プロパティ（識別番号 + 意味検索の重複排除合計）を実装
  - dispatch イベントで親（`Show.php`）またはタブラベルにカウントを反映

- [ ] **Task 4.1.2**: `show.blade.php` のタブラベルにバッジを追加
  - 件数0の場合はバッジ非表示
  - `badge badge-info badge-sm` スタイルを適用
  - `loading-overlay` 中はバッジを `-` 表示

#### Block 4.2: UI 最終調整

- [ ] **Task 4.2.1**: スケルトンローダーの追加（タブ切り替え時）
  - 既存タブ（activity/permissions）と同パターンで実装

- [ ] **Task 4.2.2**: レスポンシブ対応確認（モバイル表示）

#### Block 4.3: Feature テスト整備

- [ ] **Task 4.3.1**: `RelatedLedgersTest.php` にタブバッジ・UI テストを追加
  - `it_renders_related_tab_with_badge_count` : バッジ件数が正しく表示される
  - `it_shows_empty_placeholder_when_no_related_found` : 0 件時のプレースホルダー

- [ ] **Task 4.3.2**: 全テスト実行・リグレッション確認
  - コマンド: `./vendor/bin/sail test`
  - 既存テストへの影響がないこと

#### Block 4.4: コード品質

- [ ] **Task 4.4.1**: Pint 実行
  - コマンド: `./vendor/bin/sail pint`

- [ ] **Task 4.4.2**: エラーチェック
  - `laravel-boost` MCP の `last-error` / `browser-logs` で確認

**✅ Sprint 4 完了条件**
- タブバッジが正しく表示される
- `./vendor/bin/sail test` 全テストパス
- Pint 実行済み・エラーなし

---

## 📊 総予想工数

| スプリント | 内容 | 予想工数 |
|---|---|---|
| Sprint 1 | 識別番号検索バックエンド + テスト | 2〜3時間 |
| Sprint 2 | 意味検索バックエンド + テスト | 1〜2時間 |
| Sprint 3 | Livewire + Blade + show.blade.php 統合 | 2〜3時間 |
| Sprint 4 | タブバッジ・UI 仕上げ・全テスト整備 | 1〜2時間 |
| **合計** | | **6〜10時間** |

---

## 🔗 関連ドキュメント

- [ペルソナ・ユースケースシナリオ](../../../../function/PersonaUseCaseScenario.md)
- [検索機能概要](../../../../function/Search.md)
- [AutoLink機能概要](../../../../function/AutoLink.md)
- [Auto-number クロスリファレンス改善計画](../auto-link/2025-10-13_auto-number-cross-reference-link-improvement.md)
- [台帳複製機能設計](../2025-12-11_ledger_duplicate_feature_design.md)
- [GitHub Issue #54](https://github.com/torinky/LedgerLeap/issues/54)

---

## 実装結果（Sprint 完了後に記入）

### Sprint 1 完了日時
2026-03-01 — テスト 8 passed / 1 skipped (Lazy placeholder は Sprint 3 で対応)

### Sprint 2 完了日時
_未完了_

### Sprint 3 完了日時
_未完了_

### Sprint 4 完了日時
_未完了_

