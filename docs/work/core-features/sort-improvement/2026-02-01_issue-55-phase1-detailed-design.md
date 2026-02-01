# Issue #55: ソート機能改善詳細設計書 (オプションD - Phase 1)

**作成日:** 2026年2月1日  
**状況:** 🔍 設計レビュー完了・実装待ち

**更新履歴:**
- 2026年2月1日: 初版作成
- 2026年2月1日 15:30: レビュー指摘を反映
  - 正規化ルールを詳細化（負数対応、AutoNumberプレフィックス対応、日付エラーハンドリング等）
  - ファイル情報の取得元を `content_attached` から `content` に修正
  - Admin UI手動再生成機能を削除し、`LedgerDefineObserver` 自動化のみに変更
  - Step 5（Admin UI実装）を削除、実装期間を4週間→3週間に短縮
- 2026年2月1日 16:00: AutoNumberの扱いを修正
  - `NumberingService` により保存時に既にゼロパディング済みのため、ソート時の追加処理不要と判断
  - AutoNumber正規化ルールを「そのまま使用」に変更

---

## 1. 概要 (Overview)
本設計書は、Issue #55「マルチ台帳リストにおけるソート機能の改善」に関する詳細仕様を定義する。

### 1.1 課題と解決策の選定理由
- **課題**: 現状のソート機能は、取得済みコレクション（current page）に対するPHPソートであるため、ページネーション実行時に「ページごとにソートされるが、全体としては整列していない」という不整合が発生する。また、異なる `LedgerDefine` をまたぐソートが考慮されていない。
- **採用案**: **Option D (Denormalized Column)**
  - `ledgers` テーブルに `default_sort_value` カラムを追加し、保存時に計算済みのソート用文字列を格納する。
- **採用理由**:
  - **ページネーション整合性**: DBレベルの `ORDER BY` が可能になり、全件に対する正確なソートとページ分割が保証される。
  - **パフォーマンス**: 読み取り時（リスト表示）の計算コストがゼロになり、インデックスによる高速な並び替えが可能。書き込み時の計算コストは微小であり、R/W比率の高い本システムに適している。

---

## 2. 技術設計 (Technical Design)

### 2.1 データベーススキーマ
`ledgers` テーブルを変更する。

| カラム名 | 型 | インデックス | 説明・設計意図 |
| :--- | :--- | :--- | :--- |
| `default_sort_value` | `VARCHAR(512)` (Nullable) | `[ledger_define_id, default_sort_value]` | **Nullableの理由**: マイグレーション直後の既存データはNULLとなるため。<br>**512文字の理由**: インデックスサイズ制約（InnoDBのキー長制限）と、ソートに必要な情報量のバランス。長文は切り詰めても実用上のソート順序への影響は軽微と判断。 |

### 2.2 ロジック: `generateDefaultSortValue()`
`App\Models\Ledger` に実装するコアロジック。

#### 設計方針: 文字列連結によるソート
異なる型（数値、日付、文字列）の値を一つの文字列カラムで扱うため、型ごとに「辞書順ソートで意図した順序になる形式」へ正規化を行う。

#### 正規化ルール (Normalization Rules)
1.  **数値 (Number / AutoNum)**
    - **仕様**: 符号付き数値対応。正数は`+`、負数は`-`プレフィックスを付加。整数部20桁、小数部10桁のゼロパディング。
    - **例**: `12.5` → `+00000000000000000012.5000000000`, `-10` → `-00000000000000000010.0000000000`
    - **理由**: 単純な文字列比較で正しいソート順を保証。負数・小数点桁数の違いに対応。
2.  **AutoNumber（自動採番）**
    - **仕様**: プレフィックス・サフィックスを含めた値をそのまま使用（追加のゼロパディング不要）。
    - **例**: `PROJ-0001` → `PROJ-0001`, `2025-A-0123` → `2025-A-0123`
    - **理由**: `NumberingService` により保存時に既にカラム設定の `digits` でゼロパディング済み。文字列として辞書順ソートで正しい順序になる。
3.  **日付 (Date / YMD)**
    - **仕様**: `YYYY-MM-DD` 形式に正規化。Carbon/DateTimeでパース、エラー時は`0000-00-00`。
    - **理由**: ISO 8601形式は辞書順ソートと一致。datetime形式・スラッシュ区切り等のバリエーションに対応。
4.  **テキスト (Text / Paragraph / RichText)**
    - **仕様**:
        1.  HTMLタグを除去 (`strip_tags`)。
        2.  Markdown記法を除去（リンク、装飾記号等）。
        3.  改行・タブを空白に置換、連続空白を1つに集約。
        4.  **先頭50文字** に切り詰め (UTF-8対応)。
    - **理由**: 全文をソートキーに含めるのはインデックス容量の無駄。Markdown記法除去で可読性向上。
5.  **添付ファイル (File)**
    - **仕様**: `content[column_id]` から最初のファイルのオリジナルファイル名を取得し、テキストと同様に正規化。
    - **データ構造**: `content[column_id]` は `{"hashed_filename.ext": "original_filename.ext"}` 形式の連想配列。
    - **取得方法**: 
      ```php
      $files = $content[$columnId]; // 連想配列
      $firstOriginalFilename = reset($files); // 最初の値（オリジナルファイル名）を取得
      ```
    - **注意**: `content_attached` にはテキスト抽出結果が格納されているが、ソートには使用しない（ファイル名のみ使用）。
    - **理由**: ファイル名順で並ぶことがユーザーにとって直感的。複数ファイルの場合は配列の先頭（実用上は最初にアップロードされたファイル）を使用。

#### 連結仕様
- `LedgerDefine` に紐づく `ColumnDefine` を `sort_index` 昇順で取得し、正規化後の値をセパレータ（`|` 推奨。各値に含まれにくい文字）で連結。
- 最終的な文字列長が512文字を超える場合は末尾を切り捨てる。

### 2.3 データの永続化
データの整合性を保つため、更新操作にフックして値を再計算する。

- **Observer (LedgerObserver)**
  - **イベント**: `saving`
  - **処理**: `generateDefaultSortValue()` を呼び出し、`default_sort_value` プロパティを更新。
  - **設計意図**: `creating` や `updating` ではなく `saving` にすることで、新規作成・更新の双方をカバーし、確実に値をセットする。

- **【重要】インポート処理への対応 (`App\Imports\LedgerImport`)**
  - **課題**: `Maatwebsite\Excel\Concerns\WithUpserts` を使用しているため、Eloquentのイベント（Observer）が発火しない。
  - **対応**: `model()` メソッド内で `Ledger` インスタンスを生成する際、明示的に `generateDefaultSortValue()` を呼び出す処理を追加する。

---

## 3. UI/UX 詳細設計

### 3.1 リストヘッダー (List Header)
ユーザーが「何順で並んでいるか」を直感的に理解できるようにする。特にデフォルトソートは**複数のカラム**（定義の `sort_index` 順）に基づいて行われるため、単一のカラムではなく「ソートに関与している全てのカラム」を明示する必要がある。

- **コンポーネント**: `resources/views/components/ledger/table-header.blade.php`
- **仕様**:
  - **個別ソート中 (`orderBy`指定あり)**:
    - 対象カラムのヘッダー背景を「濃い色」で強調。
  - **デフォルトソート中 (`orderBy === default`)**:
    - `sort_index` を持つ全てのカラムをハイライトするが、**優先順位が高いほど濃い色**にして視覚的な階層を表現する。
    - **実装イメージ**:
      - 第1キー (`sort_index=1`): 背景色 `bg-primary/20` (やや濃い)
      - 第2キー (`sort_index=2`): 背景色 `bg-primary/10` (薄い)
      - 第3キー以降: 背景色 `bg-primary/5` (ごく薄い)
    - **補足**: これにより、ユーザーはヘッダーを見るだけで「何が主キーで何が副キーか」を直感的に認識できる。ツールチップも補助的に維持する。

### 3.2 検索オプション
- **コンポーネント**: `resources/views/components/ledger/search.blade.php`
- **仕様**:
  - 「デフォルトソート」オプションを明示的に追加。
  - 内部的には `orderBy('default_sort_value', 'asc')` として扱う。

### 3.3 カラム定義変更時の自動再生成
カラム定義（特に `sort_index`）が変更された際、既存レコードのソート値を自動的に再生成する。

- **実装方式**: `LedgerDefineObserver` による自動検知とジョブキューイング
- **トリガー**: `LedgerDefine` の `column_define` カラム変更時
- **処理フロー**:
  1. `sort_index` の変更を検知
  2. `RegenerateDefaultSortJob` を5秒遅延でディスパッチ（連続変更対策）
  3. バックグラウンドでチャンク処理により既存レコードを更新
  4. ユーザーには「数分以内に反映」とフラッシュメッセージで通知

**設計意図:**
- ✅ ユーザー操作不要で自動的に整合性を維持
- ✅ UI実装コスト削減
- ✅ 誤操作リスク排除
- ✅ データ不整合時は Artisan コマンドで復旧可能

## 4. データ移行・運用 (Data Migration & Operations) (Updated)
以下のようなシナリオでデータ不整合（Stale Data）が発生するため、手動で値を再計算する手段を提供する。

1.  カラム定義（`ColumnDefine`）の `sort_index` が変更された場合。
2.  `generateDefaultSortValue` のロジックが変更された場合。

- **コマンド仕様**:
  ```bash
  php artisan ledger:regenerate-default-sort {ledger_define_id?}
  ```
  - 引数なし: 全件対象（確認プロンプトあり）。
  - 引数あり: 指定された `ledger_define_id` のレコードのみ対象。
  - **処理**: チャンクごとにレコードを取得し、`generateDefaultSortValue()` を実行して更新 (`update`) する。`timestamps` の更新は行わない (`timestamps = false` で一時的に無効化) ことを推奨（ソート順更新は「編集」ではないため）。

---

## 5. 既存リソースへの影響分析 (Impact Analysis)

本変更が影響を与える既存コード、テスト、ドキュメントの一覧。

### 5.1 コードベース
| ファイル | 変更内容 | 備考 |
| :--- | :--- | :--- |
| `App\Models\Ledger.php` | `generateDefaultSortValue` 追加、`$fillable` 追加。 | |
| `App\Observers\LedgerObserver.php` | `saving` イベントハンドラ追加。 | 新規作成または既存への追記。 |
| `App\Imports\LedgerImport.php` | `model()` メソッド内で値生成ロジックを追加。 | **要注意**: Observerバイパス対策。 |
| `App\Livewire\Ledger\IndexManager.php` | `orderBy` ロジック変更（`default` 時のクエリ構築委譲）。 | 複雑なPHPソートロジックの削除。 |
| `App\Livewire\Ledger\RecordsTable.php` | `render` クエリビルダ修正。`orderBy('default_sort_value')` 追加。 | |
| `resources/views/components/ledger/table-header.blade.php` | スタイルロジックの大幅変更（複数カラムハイライト）。 | |

### 5.2 テストコード
| ファイル | 影響・対応 |
| :--- | :--- |
| `Transactions/Feature/Ledger/LedgerCreationTest.php` | 作成後の `default_sort_value` が正しく入っているかアサーション追加を推奨。 |
| `Tests/Unit/Models/LedgerDefaultSortTest.php` | **【新規】** 正規化ロジックの網羅的テスト（境界値分析）。 |
| `Tests/Feature/Livewire/Ledger/MultiLedgerSortTest.php` | **【新規】** ページネーション整合性とグローバルソートの振る舞い検証。 |
| `Tests/Feature/Import/LedgerImportTest.php` | インポート後のレコードに `default_sort_value` があるか検証を追加。 |

### 5.3 ドキュメント
- **ER図**: `ledgers` テーブルへのカラム追加を反映する必要あり。
- **モデル定義書**: `Ledger` モデル定義書の更新。

---

## 6. WBS (Work Breakdown Structure) - Phase 1

### Step 1: データベースとモデルロジック実装
- [ ] **マイグレーション作成**: `add_default_sort_value_to_ledgers_table`
    - [検証] `migrate` 実行でエラーなくカラム・インデックスが追加されること。rollback可能であること。
- [ ] **モデルロジック実装**: `Ledger::generateDefaultSortValue`
    - **【詳細実装要件】**:
        - 数値: 10桁ゼロパディング。
        - テキスト: 改行削除、50文字切り詰め。
        - ファイル: ファイル有無チェック、ファイル名取得。
    - [検証] `tests/Unit/Models/LedgerDefaultSortTest.php` を作成し、各型（数値、日付、長文、ファイル）の入力に対して期待通りの正規化文字列が返るかテストする。

### Step 2: 自動化と永続化（Observer & Import）
- [ ] **Observer実装**: `LedgerObserver`
    - [検証] `tinker` で `Ledger::create` を実行し、DBに `default_sort_value` が保存されていること。
- [ ] **インポート処理修正**: `LedgerImport::model`
    - [検証] CSVファイルをインポートし、作成されたレコードの `default_sort_value` がNULLでないこと。
- [ ] **既存機能テスト実行**:
    - [検証] `php artisan test` で既存の登録系テストがFailしないこと（Observer追加による副作用がないこと）。

### Step 3: UIとクエリの統合
- [ ] **クエリビルダ修正**: `RecordsTable`
    - [検証] データを手動で書き換え、`orderBy=default` でソート順が変わることを確認。
- [ ] **UI実装**: 検索オプション修正
    - [検証] ブラウザで検索バーを開き、常に「デフォルト順」が表示されていること。
- [ ] **UI実装**: ヘッダーハイライト
    - [検証] デフォルトソート選択時、複数のターゲットカラムが薄い色でハイライトされ、ツールチップが表示されること。

### Step 4: 運用ツール実装
- [ ] **コマンド作成**: `ledger:regenerate-default-sort`
    - [検証] データを意図的にNULLまたは不正な値にした後、コマンド実行で正しい値に復元されること。
    
- [ ] **LedgerDefineObserver実装**: カラム定義変更時の自動再生成
    - `column_define` 変更検知（特に `sort_index` の変更）
    - `RegenerateDefaultSortJob` の自動キューイング（5秒遅延）
    - [検証] `sort_index` を変更して保存後、バックグラウンドでジョブが実行され、既存レコードのソート値が更新されること。

- [ ] **Job実装**: `RegenerateDefaultSortJob`
    - チャンク処理でのメモリ効率（1000件ずつ）
    - タイムアウト対策（大量データ対応）
    - 進捗状況のログ出力
    - エラーハンドリングとリトライ
    - [検証] 10万件規模のデータで実行時間・メモリ使用量を計測。

**UI上での手動再生成機能について:**
- ❌ **Admin UIでの再生成ボタンは実装しない**
- **理由**:
  1. `LedgerDefineObserver` によりカラム定義変更時に自動で再生成ジョブが投入される
  2. 手動再生成が必要なケースは極めて限定的（データ不整合時のみ）
  3. 誤操作による不要なジョブ実行のリスク回避
  4. 必要な場合は Artisan コマンドで十分対応可能（運用担当者のみ実行）
