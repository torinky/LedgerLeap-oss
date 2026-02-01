# Issue #55: ソート機能改善詳細設計書 (オプションD - Phase 1)

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
    - **仕様**: 符号なし数値を想定し、左ゼロパディングを行う。小数部は維持。
    - **例**: `12.5` → `0000000012.50`
    - **理由**: 単純な文字列比較 `"10" < "2"` を防ぐため。
2.  **日付 (Date)**
    - **仕様**: `YYYY-MM-DD` 形式。
    - **理由**: ISO 8601形式は辞書順ソートと一致するため。
3.  **テキスト (Text / Paragraph / RichText)**
    - **仕様**:
        1.  HTMLタグを除去 (`strip_tags`)。
        2.  改行・制御文字を除去またはスペース置換。
        3.  **先頭50文字** に切り詰め (Encoding: UTF-8)。
    - **理由**: 全文をソートキーに含めるのはインデックス容量の無駄であり、先頭50文字で十分な識別性が確保できるため。ハッシュ化は順序が失われるため採用しない。
4.  **添付ファイル (File)**
    - **仕様**: 最初のファイルの「ファイル名」を使用し、テキスト同様に処理。
    - **理由**: ファイル有無だけでなく、ファイル名順で並ぶことがユーザーにとって直感的であるため。

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

### 3.3 デフォルトソート再生成 (Admin UI)
保守機能として、コマンドラインだけでなく画面からも再生成をリクエスト可能にする。

- **場所**: 台帳設定変更画面 (`LedgerDefine/Edit`)
- **権限**: **Adminロール**保持者のみ表示・実行可能。
- **ガード機構**:
  - **二重実行防止**: 処理中または予約済みの場合はボタンを無効化し、「処理中...」等のステータスを表示する。
  - **実装方式**: `Cache` を利用したロック機構。
    - キー例: `ledger_def:{id}:regenerating_sort`
    - Livewire側でジョブ投入時にキーを設定（TTL付き）。
    - ジョブ完了時（または失敗時）にキーを削除。
    - 画面読み込み時にキー存在確認を行い、ボタン状態を制御。

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

### Step 5: 管理機能UI実装 (Admin UI Regeneration)
- [ ] **Job実装**: `RegenerateDefaultSortJob`
    - [検証] ジョブが実行され、単体でソート値の再計算・保存が行われること。
- [ ] **Component実装**: `LedgerDefine/Edit` (再生成アクション)
    - [検証] Adminユーザーで実行時のみジョブがDispatchされること。Cacheロックにより連続実行が防がれること。
- [ ] **UI実装**: 再生成ボタン追加
    - [検証] Adminユーザーにはボタンが表示され、一般ユーザーには表示されないこと。実行中にボタンが「処理中」状態になること。
