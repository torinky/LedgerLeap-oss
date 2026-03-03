# 関連案件タブ: テキスト列の識別番号による横断検索（パターンB対応）

**作成日:** 2026年3月2日  
**更新日:** 2026年3月2日  
**ステータス:** 🚧 実装中（Sprint A 完了）  
**目的:** 関連案件タブの識別番号検索を拡張し、`auto_number` 型列だけでなく、テキスト列に記載された識別番号でも関連レコードを探索できるようにする  
**関連Issue:** https://github.com/torinky/LedgerLeap/issues/76  
**前提Issue:** [Issue #54: 詳細画面に関連案件タブを追加](https://github.com/torinky/LedgerLeap/issues/54)

---

## 🎯 背景と問題

### Issue #54 での設計判断

Issue #54 では識別番号検索の対象を **「自レコードの `auto_number` 型カラムの値」のみ**（パターンA）と定義した。  
理由は「識別番号でヒット」を監査根拠として信頼できるよう厳密に保つためである。

しかし実業務では、テキスト列に他の台帳の識別番号を記載するケース（パターンB）が頻繁に発生する。  
AutoLink 機能がテキスト列の識別番号も自動リンク化することからも、このユーザー行動は想定済みであり  
関連案件タブもこれを拾えるようにすることで情報の探索性が向上する。

### 2つのパターンの整理

| パターン | 例 | Issue #54 での対応 | 本 Issue での対応 |
|---|---|---|---|
| **A. 自レコードの `auto_number` 型列** | 点検記録の「設備番号」列（auto_number型）に `EQ-042` | ✅ 識別番号検索（`🔖`） | 変更なし |
| **B. テキスト列に他台帳の識別番号が記載** | 作業日報の「作業内容」列（text型）に `「EQ-042 の修理」` と記述 | ⚠️ 検索対象外（意味検索で部分補完） | ✅ **新たに識別番号検索に追加** |

---

## 👥 ペルソナシナリオからの洞察

### 実務担当者（業務記録の参照 — UC2 の延長）

**シナリオ:**  
設備保守担当者が設備点検記録（「設備番号」`auto_number` 列 = `EQ-042`）を閲覧中、  
関連案件タブを開くと「作業日報」台帳の中に `EQ-042` と記載されたレコードを見たい。

**現状の問題:**  
- 作業日報の「作業内容」は `text` 型のため、パターンBとしてパターンAの識別番号検索には引っかからない
- 意味検索でヒットする可能性はあるが、RAGサービスが未起動の環境では完全に見えなくなる

**期待する動作:**  
- 「作業内容」欄に `EQ-042` が含まれる作業日報レコードが識別番号検索結果として表示される
- AutoLink のクリックで詳細画面を開いたときと同じ「番号で繋がっている」体験が関連案件タブでも再現される

### 現場リーダー（情報共有と確認 — UC2 の延長）

**シナリオ:**  
障害報告レコードを確認中に「過去の類似対応」を参照したい。  
過去の対応記録は別台帳（作業報告台帳）の「対応内容」列（text型）に障害番号が記載されていることが多い。

**期待する動作:**  
- テキスト列の障害番号でヒットしたレコードも関連案件タブに表示される
- ただし「どのカラムに記載されていたか」がツールチップで確認できるため、「`auto_number` 型列での明確な紐付け」との区別が可能

### 管理者（監査 — UC3 の延長）

**シナリオ:**  
識別番号 `WO-099` を持つ作業指示書を起点に、その番号が記載された全関連文書を横断確認したい。

**期待する動作:**  
- `auto_number` 型列での一致（パターンA）と、テキスト列での一致（パターンB）を区別して表示したい
- トグルフィルターで「テキスト記載のみの関連」を除外できると監査対象を絞り込める

---

## 📐 機能要件

### 1. 識別番号抽出の拡張（パターンB）

`extractAutoNumberValues()` を拡張し、以下の2段階で識別番号を抽出する：

**Step 1（パターンA、現行）:** 自レコードの `auto_number` 型カラムから値を取得  
**Step 2（パターンB、新規）:** 自テナント（およびシステム全体）の全 `auto_number` カラム定義からパターンを生成し、  
自レコードの全テキスト系列（`text`, `textarea`, `memo` 等）に正規表現でマッチングを実施

### 2. `matched_keys` の拡張

現行の `matched_keys` はヒットした識別番号値の配列だが、抽出元情報も付与する：

```php
// 拡張後の matched_keys エントリ
[
    'value'        => 'EQ-042',           // ヒットした識別番号値
    'source'       => 'auto_number',      // 'auto_number'（パターンA）| 'text_column'（パターンB）
    'source_column' => '設備番号',        // 抽出元カラム名（表示用）
]
```

### 3. 識別理由インジケーターの細分化（将来検討）

パターンAとパターンBを同じ `🔖 識別番号` として表示するか、別アイコンで区別するかは実装時に判断する。  
**第一候補:** 同じ `🔖` アイコンで統一し、ツールチップの `source_column` 情報で区別を示す  
**第二候補:** パターンBを `📝 テキスト記載` として別アイコンで表示し、3値フィルターに拡張する

### 4. トグルフィルターの対応

- パターンAとBを `🔖 識別番号` トグルで一括 ON/OFF する場合 → 実装コスト小
- パターンAとBを別トグルで制御する場合 → 実装コスト大、監査ユースケースに対しては有用

→ **第一候補は一括管理**。管理者フィードバックを受けて分離を検討する。

---

## 🏗 技術設計

### 既存資産の活用

`AutoLinkService` に既に以下の実装がある：

```php
// app/Services/AutoLinkService.php

private function getVirtualAutoNumberLinks(): \Illuminate\Support\Collection
{
    // 全テナントの LedgerDefine を取得し、auto_number カラムからパターンを生成
    // キャッシュ: Cache::tags(['auto_links'])->remember(60分)
}

private function generateAutoNumberPattern(object $options, bool $isUnique): string
{
    // prefix / digits / revision から正規表現を生成
    // 例: prefix='EQ-', digits=3 → '/(EQ-\d{3,})/u'
}
```

これらを `RelatedLedgers.php` から利用（または同等のロジックを抽出）することで  
正規表現生成を重複実装せずに済む。

### 変更対象ファイル

| ファイル | 変更内容 |
|---|---|
| `app/Livewire/Ledger/RelatedLedgers.php` | `extractAutoNumberValues()` にパターンBのロジック追加 |
| `app/Services/AutoLinkService.php` | `getVirtualAutoNumberLinks()` を `public` または抽出メソッドに変更（または同等サービス切り出し） |
| `resources/views/components/ledger/related-reason-badge.blade.php` | `source` 情報をツールチップに追加 |
| `tests/Feature/Livewire/Ledger/RelatedLedgersTest.php` | パターンB対応のテスト追加 |
| `lang/ja/ledger.php` | `related.identifier_source_auto_number`, `related.identifier_source_text_column` 翻訳キー追加 |

### データフロー（拡張後）

```
[extractAutoNumberValues($ledger)]
   ├── Step A: $ledger->define->column_define で auto_number 型列の値を収集
   │           → ['EQ-042', 'WO-099']（従来どおり）
   └── Step B: AutoLinkService::getVirtualAutoNumberLinks() でパターン取得
               → 自レコードの全テキスト列にパターンマッチング
               → 一致した値を収集（重複排除）
               → ['SPEC-001']（テキスト列からの抽出値）
              ↓
   $identifierKeys = [
       'EQ-042' => ['source' => 'auto_number', 'column' => '設備番号'],
       'WO-099' => ['source' => 'auto_number', 'column' => '案件番号'],
       'SPEC-001' => ['source' => 'text_column', 'column' => '作業内容'],
   ]

[searchByIdentifiers($identifierKeys)]
   → 各キーで横断検索（Mroonga MATCH() AGAINST()）
   → 結果に matched_keys（source情報付き）を付与
```

### パフォーマンス考慮事項

- `getVirtualAutoNumberLinks()` はキャッシュ済み（60分）のため追加コストは小さい
- テキスト列が多いレコードではパターンマッチング回数が増えるが、PHP側の正規表現処理であり DB アクセスは発生しない
- 抽出されるキーの数が増えることで `searchByIdentifiers()` の DB クエリ数が増加する可能性あり  
  → 上限（例: 最大10キー）を設けることを検討

### Mroonga 制約（継続適用）

- 複合インデックスは使用不可
- `MATCH() AGAINST()` は単一カラムに限定
- パターンBで抽出した値も既存の `SearchContext` + `scopeSearchContext` 経由で検索（変更なし）

---

## ⚠️ 技術的考慮事項

### AutoLinkService との結合

`getVirtualAutoNumberLinks()` は現在 `private` メソッドである。  
以下の選択肢がある：

| 選択肢 | メリット | デメリット |
|---|---|---|
| `protected` に変更して継承 | 変更最小 | 疎結合でない |
| `public` に変更 | シンプル | 外部公開範囲が広がる |
| パターン生成ロジックを `AutoNumberPatternService`（新サービス）に切り出す | 最も疎結合 | 新ファイル追加が必要 |
| `RelatedLedgers.php` 内で同等ロジックを再実装 | 依存なし | 重複コード |

**推奨:** `AutoNumberPatternService` への切り出し（または `AutoLinkService::getAutoNumberPatterns()` として `public` 化）

### テキスト列の対象範囲

パターンBのマッチング対象は以下のカラム型とする（初期案）：

- `text`, `textarea`, `memo` — テキスト系全般
- `select`, `radio` — 選択肢の値にも識別番号が入り得るが、通常は管理された値のため除外検討
- `files`, `auto_number`, `user_name`, `YMD`, `YMDHM`, `chk` — 除外

### ノイズの可能性

テキスト列に識別番号と同じパターンの文字列が偶然含まれる場合（例: 住所の番地、電話番号など）、  
意図しない検索結果が含まれる可能性がある。  
→ 現時点では許容し、ユーザーへの注記としてツールチップに「テキスト列に記載の番号」であることを明示する。

---

## 📋 WBS：スプリント計画（草案）

### ✅ Sprint A: AutoNumberPatternService の切り出し・テスト — 完了 (2026-03-03)

**エビデンス:** [46ab6bfc](https://github.com/torinky/LedgerLeap/commit/46ab6bfc)

- [x] `app/Services/AutoNumberPatternService.php` を新規作成
  - `getPatterns(): Collection` — 全テナントの `auto_number` カラム定義からパターンと列情報のコレクションを返す
  - `generatePattern(object $options, bool $isUnique): string` — 正規表現文字列を生成
- [x] `AutoLinkService` が `AutoNumberPatternService` を DI で利用するよう変更
- [x] `AutoNumberPatternServiceTest.php` を新規作成（パターン生成・キャッシュのテスト）
- [x] 既存の AutoLink テストがリグレッションしないことを確認
- [x] `./vendor/bin/sail test` パス確認（28 passed）・Pint 実行

---

### Sprint B: `extractAutoNumberValues()` の拡張

**目標:** `RelatedLedgers.php` の `extractAutoNumberValues()` にパターンBのロジックを追加する

- [ ] `extractAutoNumberValues()` を拡張
  - Step A（従来）: `auto_number` 型列の値を収集
  - Step B（新規）: `AutoNumberPatternService::getPatterns()` でパターン取得 → 全テキスト列にマッチング
  - 戻り値を `string[]` から `array<string, array{source: string, column: string}>` に変更
- [ ] `searchByIdentifiers()` に `source` 情報を渡せるよう引数を調整
- [ ] `mergeResults()` で `matched_keys` に `source` 情報を保持

---

### Sprint C: テスト整備

**目標:** パターンBの各ケースをテストでカバーする

- [ ] `RelatedLedgersTest.php` に以下のテストを追加
  - `it_extracts_identifier_from_text_column` — テキスト列から識別番号が抽出されることを確認
  - `it_does_not_extract_from_files_or_auto_number_column` — 除外列からは抽出されないことを確認
  - `it_searches_by_text_column_identifier` — 抽出した値で横断検索が実行されることを確認
  - `it_marks_source_as_text_column_in_matched_keys` — `source='text_column'` が `matched_keys` に記録されることを確認
  - `it_deduplicates_keys_across_pattern_a_and_b` — パターンAとBで同じ値が抽出された場合に重複排除されることを確認
- [ ] `./vendor/bin/sail test` 全テストパス確認

---

### Sprint D: ビュー対応

**目標:** ツールチップに `source` 情報（どのカラムから抽出したか）を表示する

- [ ] `related-reason-badge.blade.php` の `identifier` / `both` ツールチップに `source_column` を追加
  - パターンA: `識別番号: EQ-042（設備番号列）`
  - パターンB: `識別番号: EQ-042（テキスト記載）`
- [ ] `lang/ja/ledger.php` に翻訳キーを追加
  - `related.identifier_source_auto_number` → `識別番号列`
  - `related.identifier_source_text_column` → `テキスト記載`
- [ ] ブラウザ動作確認
- [ ] `laravel-boost` エラーチェック・Pint 実行

---

## 📊 工数・進捗サマリー

| スプリント | 内容 | 予想工数 | 状態 | 完了日 |
|---|---|---|---|---|
| **Sprint A** | AutoNumberPatternService 切り出し・テスト | 2〜3時間 | ✅ 完了 | 2026-03-03 |
| Sprint B | extractAutoNumberValues 拡張 | 1〜2時間 | 🔲 未着手 | — |
| Sprint C | テスト整備（5件） | 1〜2時間 | 🔲 未着手 | — |
| Sprint D | ビュー対応 | 1時間 | 🔲 未着手 | — |
| **合計** | | **5〜8時間** | | |

## 📝 実装結果

### Sprint A 完了 (2026-03-03)
- **コミット:** [46ab6bfc](https://github.com/torinky/LedgerLeap/commit/46ab6bfc)
- **新規ファイル:** `app/Services/AutoNumberPatternService.php`
- **変更ファイル:** `app/Services/AutoLinkService.php`（DI 追加・委譲）
- **新規テスト:** `tests/Feature/Services/AutoNumberPatternServiceTest.php`（6件）
- **テスト結果:** 28 passed（AutoNumberPatternServiceTest 6 + RelatedLedgersTest 22）

---

## 🔗 関連ドキュメント

- [Issue #54 関連案件タブ計画](./2026-03-01_issue-54-related-ledgers-tab-plan.md)
- [ペルソナ・ユースケースシナリオ](../../../../function/PersonaUseCaseScenario.md)
- [AutoLink 機能概要](../../../../function/AutoLink.md)
- [AutoLink サービス実装](../../../services/AutoLinkService.md)
- [自動ナンバリング値のクロスリファレンスリンク化改善計画](../auto-link/2025-10-13_auto-number-cross-reference-link-improvement.md)
- [検索機能概要](../../../../function/Search.md)

