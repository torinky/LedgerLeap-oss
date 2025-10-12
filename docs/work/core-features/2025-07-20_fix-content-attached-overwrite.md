# 添付ファイル content_attached 上書き問題のデバッグと修正計画

## 1. 概要

台帳レコードの添付ファイルカラムにおいて、`content_attached` カラムの内容が新規ファイル追加時にマージされず、既存のデータが上書きされてしまう不具合が発生しています。このドキュメントでは、この問題の根本原因を特定し、修正するための詳細な計画を記述します。

## 2. 問題の再確認と現状分析

### 2.1. 問題の現象

*   台帳レコードに複数のファイルを添付した場合、最初のファイルはTikaによるテキスト抽出とメタデータが `content_attached` に正しく反映されます。
*   しかし、2番目以降のファイルを追加すると、以前のファイルの `content_attached` データが失われ、新しく追加されたファイルのデータで上書きされてしまいます。
*   これにより、全文検索の対象となるべきテキスト情報が欠落し、検索機能に影響を与えています。

### 2.2. これまでの修正と残された課題

これまでに、`content_attached` の構造に関するいくつかの修正を行ってきました。

*   **`app/Livewire/Ledger/ModifyColumn.php` の `array_merge` エラー修正**: `content` が文字列になる問題を修正し、`array_merge` が常に配列を扱うようにしました。
*   **`app/Jobs/Ledger/ProcessAttachedFile.php` の `array_values()` 削除**: `content_attached` が数値インデックスの配列に変換され、キーが失われる問題を修正しました。
*   **`app/Jobs/Ledger/ProcessAttachedFile.php` の `optimized` ファイルのステータス修正**: OCR処理済みファイルがTikaでテキスト抽出できなかった場合に `TIKA_FAILED` になる問題を修正し、`COMPLETED` になるようにしました。
*   **`app/Jobs/Ledger/ProcessAttachedFile.php` の `meta` データ保持**: `ProcessAttachedFile` ジョブで `$result` を初期化する際に、既存の `meta` データを保持するように修正しました。
*   **`app/Livewire/Ledger/ModifyColumn.php` の `mergeFilesForSave()` 修正**: 新規ファイルの `content_attached` に `meta` 構造のプレースホルダーを含めるようにしました。
*   **`app/Models/AttachedFile.php` の `booted()` メソッド追加**: `AttachedFile` モデル作成時に `ProcessAttachedFile` ジョブがディスパッチされるようにしました。

これらの修正にもかかわらず、`content_attached` の上書き問題が継続していることから、まだ `content_attached` のデータフローのどこかに、既存のデータを正しくマージせず、上書きしてしまうロジックが残っていると考えられます。

## 3. 添付ファイルのデータフローと `content_attached` のライフサイクル再調査

`docs/work/2025-07-13_attachment-feature-enhancement.md` に記載されているアーキテクチャを再確認し、現在の実装との乖離がないか、特に `content_attached` の更新に関わる全ての箇所を詳細に追跡します。

### 3.1. `content_attached` の期待される構造

`content_attached` カラムは、台帳定義のカラムIDをキーとし、その中にファイルごとのハッシュ化されたファイル名をキーとする連想配列として保存されることを期待しています。各ファイルのエントリには、Tika/OCRによって抽出されたテキストを含む `meta` データが含まれます。

```json
{
    "column_id_1": {
        "hashed_filename_A.ext": {
            "meta": {
                "content": "extracted text A",
                "other_meta_key": "value"
            }
        },
        "hashed_filename_B.ext": {
            "meta": {
                "content": "extracted text B"
            }
        }
    },
    "column_id_2": {
        "hashed_filename_C.ext": {
            "meta": {
                "content": "extracted text C"
            }
        }
    }
}
```

### 3.2. `content_attached` の更新に関わる主要な箇所

1.  **Livewireコンポーネント (`app/Livewire/Ledger/CreateColumn.php`, `app/Livewire/Ledger/ModifyColumn.php`)**:
    *   **`mount()`**: 既存の `Ledger` レコードから `content_attached` をロードします。
    *   **`processFilesForSave()`**: ファイルアップロード後、一時ファイルを永続化し、`$this->newAttachedFiles` に情報を蓄積します。
    *   **`mergeFilesForSave()` (ModifyColumn)**: 新規アップロードされたファイルと、既存のファイル（削除されなかったもの）をマージし、`$this->content` と `$this->contentAttached` を更新します。
    *   **`saveDraft()` / `saveDirectly()` / `saveChangesAndReturnToDraft()`**: 最終的に `Ledger` モデルを保存する際に、`$this->content` と `$this->contentAttached` の内容がDBに書き込まれます。
2.  **キューのジョブ (`app/Jobs/Ledger/ProcessAttachedFile.php`)**:
    *   `AttachedFile` モデルが作成された後、`booted()` メソッドによってディスパッチされます。
    *   `handle()` メソッド内で、Tika/OCRによるテキスト抽出を行い、その結果を `Ledger` モデルの `content_attached` にマージして保存します。

## 4. 潜在的な原因の特定とデバッグ戦略

### 4.1. 潜在的な原因

*   **Livewireコンポーネントでの `content_attached` の不適切な初期化またはマージ**:
    *   `mount()` で `content_attached` をロードする際に、`$this->ledgerRecord->content_attached` が期待する構造になっていない場合。
    *   `mergeFilesForSave()` で `array_merge` を使用しているが、既存の `content_attached` の構造が複雑なため、意図せず上書きしてしまっている。特に、`$tmpContentAttached` の初期化や、`unset()` の挙動が影響している可能性。
*   **`ProcessAttachedFile` ジョブでの `content_attached` の不適切なマージ**:
    *   ジョブが `Ledger` モデルをロードする際に、最新の `content_attached` を取得できていない（競合状態）。
    *   `$workingContentAttached` の構築ロジックに不備があり、既存のファイルデータが正しく引き継がれていない。
    *   `$workingContentAttached[$this->attachedFile->column_id][$this->attachedFile->hashedbasename] = $result;` の行で、`$workingContentAttached[$this->attachedFile->column_id]` が文字列になっている問題は修正済みだが、他の箇所で同様の問題が発生している可能性。
*   **`AsColumnArrayJson` キャストの挙動**:
    *   `get()` や `set()` メソッドで、JSONのエンコード/デコード中にデータが破損したり、予期せぬ型変換が発生している。

### 4.2. 詳細なデバッグ戦略

データフローの各ポイントで `content_attached` の内容をログに出力し、その構造と値がどのように変化しているかを詳細に追跡します。

#### 4.2.1. ログ追加のタスク

以下のファイルに `Log::info()` を追加し、`json_encode()` を使用して配列の内容を明確に出力します。

1.  **`app/Livewire/Ledger/ModifyColumn.php`**:
    *   `mount()` メソッドの `if (!empty($this->ledgerRecord->content_attached))` ブロック内で、`Log::info('ModifyColumn: mount - initial contentAttached: ' . json_encode($this->contentAttached));` を追加。
    *   `mergeFilesForSave()` メソッドの `// 新規ファイルと残った既存ファイルをマージ` の直前で、`Log::info('ModifyColumn: mergeFilesForSave - before merge. addedFileContents: ' . json_encode($addedFileContents) . ', tmpContentAttached: ' . json_encode($tmpContentAttached));` を追加。
    *   `mergeFilesForSave()` メソッドの `// 新規ファイルと残った既存ファイルをマージ` の直後で、`Log::info('ModifyColumn: mergeFilesForSave - after merge. this->contentAttached: ' . json_encode($this->contentAttached));` を追加。
    *   `saveChangesAndReturnToDraft()` メソッドの `try` ブロック直前で、`Log::info('ModifyColumn: saveChangesAndReturnToDraft - contentAttached before save: ' . json_encode($this->contentAttached));` を追加。
    *   `saveDraft()` メソッドの `try` ブロック直前で、`Log::info('ModifyColumn: saveDraft - contentAttached before save: ' . json_encode($this->contentAttached));` を追加。
    *   `saveDirectly()` メソッドの `parent::saveDirectly();` の直前で、`Log::info('ModifyColumn: saveDirectly - contentAttached before save: ' . json_encode($this->contentAttached));` を追加。

2.  **`app/Jobs/Ledger/ProcessAttachedFile.php`**:
    *   `handle()` メソッドの `DB::transaction(function () {` の直後で、`Log::info('ProcessAttachedFile: handle - transaction start. Ledger ID: ' . $ledger->id . ', content_attached from DB: ' . json_encode($ledger->content_attached));` を追加。
    *   `$workingContentAttached` の構築ループの直後で、`Log::info('ProcessAttachedFile: handle - workingContentAttached after initialization: ' . json_encode($workingContentAttached));` を追加。
    *   `$workingContentAttached[$this->attachedFile->column_id][$this->attachedFile->hashedbasename] = $result;` の直後で、`Log::info('ProcessAttachedFile: handle - workingContentAttached after current file update: ' . json_encode($workingContentAttached));` を追加。
    *   `$ledger->save();` の直前で、`Log::info('ProcessAttachedFile: handle - content_attached before ledger save: ' . json_encode($ledger->content_attached));` を追加。

3.  **`app/Casts/AsColumnArrayJson.php`**:
    *   `get()` メソッドの `return is_string($value) ? json_decode($value, true) : $value;` の直前で、`Log::info('AsColumnArrayJson: get - value: ' . (is_string($value) ? $value : json_encode($value)));` を追加。
    *   `set()` メソッドの `return json_encode($value);` の直前で、`Log::info('AsColumnArrayJson: set - value: ' . json_encode($value));` を追加。

#### 4.2.2. 検証手順

1.  上記のログを追加します。
2.  `./vendor/bin/sail down && ./vendor/bin/sail up -d` でDockerコンテナを再起動し、変更を適用します。
3.  台帳レコードを新規作成し、添付ファイルカラムに**複数のファイル**を一度に追加して保存します。
4.  `storage/logs/laravel-YYYY-MM-DD.log` と `storage/logs/queue-YYYY-MM-DD.log` の両方のログファイルの内容を収集します。
5.  ログの内容を時系列で分析し、`content_attached` の構造と値がどのように変化しているかを追跡します。特に、ファイルが追加されるたびに既存のデータが保持されているか、上書きされているかを確認します。

## 5. 修正計画 (調査結果に基づく)

上記の詳細なログ分析の結果に基づいて、具体的な修正案を提示します。現時点での仮説に基づく修正案は以下の通りです。

### 5.1. `ProcessAttachedFile.php` の `workingContentAttached` 初期化の強化

`ProcessAttachedFile.php` の `handle()` メソッド内で、`$workingContentAttached` を初期化する際に、`$existingContentAttached[$columnId]` が `null` や空文字列の場合でも、`LedgerDefine` の `column_define` に基づいて `$workingContentAttached` を完全に初期化するようにします。これにより、既存の `content_attached` が不正な状態であっても、正しい構造から処理を開始できるようにします。

### 5.2. `ProcessAttachedFile.php` の `content_attached` 更新ロジックの厳密化

`$workingContentAttached[$this->attachedFile->column_id][$this->attachedFile->hashedbasename] = $result;` の行で、`$workingContentAttached[$this->attachedFile->column_id]` が確実に配列であることを再度確認し、必要であれば初期化します。

### 5.3. `ModifyColumn.php` の `mergeFilesForSave()` のマージロジックの再検証

`mergeFilesForSave()` メソッド内の `array_merge` の使用方法を再確認します。特に、`$tmpContentAttached` が正しく既存の `meta` データを保持しているか、そして `array_merge` が意図した通りに既存のデータを上書きせずに新しいデータを追加しているかを確認します。必要であれば、`array_merge` ではなく、手動でループしてマージするロジックを検討します。

## 6. ログ分析結果と新たな問題の特定

### 6.1. 初期のログ分析結果

最初のログ分析により、新規登録時の2ファイル添付では `ProcessAttachedFile` ジョブが正常に動作し、`content_attached` にデータが正しく追記されていることが確認されました。しかし、再編集時にファイルを追加すると、`ProcessAttachedFile` ジョブが開始される時点で、データベースから読み込まれた `content_attached` の既存データが、ファイル名を示す文字列で上書きされていることが判明しました。このことから、問題の根本原因は `ProcessAttachedFile` ジョブではなく、`app/Livewire/Ledger/ModifyColumn.php` コンポーネントにあると特定されました。

### 6.2. 最初の修正試行と新たな問題

`app/Livewire/Ledger/ModifyColumn.php` の `mergeContentFiles()` メソッド内の `$tmpContentAttached` の初期化ロジックが、既存の `content_attached` の `meta` データを失わせていることが原因であると仮説を立て、以下の修正を試みました。

*   `ModifyColumn.php` の `mergeContentFiles()` メソッド内で、`$tmpContentAttached` の初期化を `$this->ledgerRecord->content_attached[$column->id] ?? []` から行うように修正し、既存の `content_attached` の構造を正しく引き継ぐように変更。

この修正後、新たな問題として「添付ファイルが削除してもカラムから消えなくなった」という事象が発生しました。これは、`LedgerDiff` で過去のファイルを参照するため、ファイルの実体は削除しないが、UI上および `content` / `content_attached` からは削除したいという要件に反するものでした。

### 6.3. 新たな問題のデバッグと修正試行

「添付ファイルが削除してもカラムから消えない」問題に対し、以下のデバッグと修正を試みました。

1.  **`basename()` TypeError の解消**: 
    *   `app/Livewire/Ledger/ModifyColumn.php` の `mergeContentFiles()` メソッド内の `foreach ($this->deletedContent[$column->id] as $deletedFilePath)` ループで、`basename()` に配列が渡される `TypeError` が発生。
    *   原因は、`deletedContent` の構造が `deletedContent.${columnId}.${hashedBasename}` のように変更されたため、`$this->deletedContent[$column->id]` が連想配列になり、`$deletedFilePath` に値（`hashedBasename`）が直接入るようになったこと。
    *   修正として、`$deletedBaseFilenames = array_values($this->deletedContent[$column->id]);` を使用し、`deletedContent` の値のみを抽出するように変更。

2.  **UIからの削除情報の伝達修正**: 
    *   FilePond の `onremovefile` イベントでLivewireに渡される情報が不足していることが判明。`originalFilename` を渡していたが、`ModifyColumn.php` の `mergeContentFiles` では `hashedBasename` をキーとして削除処理を行っていたため、不一致が発生。
    *   `app/Livewire/Ledger/ModifyColumn.php` の `prepareFilePondInitialFiles()` メソッドで、`fileObject` の `metadata` に `hashedBasename` を追加。
    *   `resources/views/components/ledger/form/files.blade.php` の `onremovefile` コールバックを修正し、`deletedContent` に設定する値を `file.getMetadata('hashedBasename')` に変更。

3.  **LivewireプロパティとDBデータの同期問題**: 
    *   `ModifyColumn.php` の `mergeFilesForSave()` メソッド内で、データベースから再度 `Ledger` レコードを取得し、その古い `content` と `content_attached` で初期化しているため、Livewire コンポーネントの現在の状態（UIで削除されたファイルが反映された状態）が上書きされてしまっていた。
    *   修正として、`mergeFilesForSave()` メソッド内で `$tmpContent` と `$tmpContentAttached` を初期化する際に、Livewire コンポーネントの現在の `$this->content` と `$this->contentAttached` を使用するように変更。

4.  **AttachedFile レコードの削除処理のコメントアウト**: 
    *   ユーザーの要望により、`mergeFilesForSave()` メソッド内の `AttachedFile::where(...)->delete();` の行をコメントアウト。これにより、ファイルの実体は削除されないが、UI上からは消えることを目指した。

これらの修正を試みましたが、現状では問題は解決しておらず、ファイルがカラムから消えない状況が継続しています。

## 7. 今後の調査と修正計画

これまでの試行錯誤から、`content` および `content_attached` のデータフロー、特にLivewireのプロパティとデータベース間の同期、そしてUIからの削除イベントの伝達と処理に、まだ見落としがあると考えられます。

今後の調査では、以下の点に焦点を当てます。

*   **Livewire のライフサイクルとプロパティの更新タイミングの再確認**: `updated` メソッドや `prepareForValidation` メソッドなど、Livewireのライフサイクルイベントが `content` や `content_attached` にどのように影響しているかを詳細に追跡します。
*   **`$this->deletedContent` の最終的な状態の確認**: `saveChanges()` や `saveDraft()` が呼び出される直前で `$this->deletedContent` の内容をログに出力し、UIからの削除情報が正しく伝達されているかを確認します。
*   **`mergeFilesForSave()` メソッドの再精査**: 既存のファイルと新規ファイルの結合ロジック、および削除されたファイルの除外ロジックを、より厳密にステップバイステップで検証します。特に `array_merge` の挙動が期待通りであるか、手動でのマージが必要か検討します。
*   **`LedgerDefine::normalizeByColumnDefine()` の影響**: `processFilesForSave()` の最後で呼び出されている `normalizeByColumnDefine()` が、`content` や `content_attached` の構造に意図しない変更を加えていないかを確認します。

---
**注意**: ログの出力が大量になる可能性があります。デバッグが完了したら、これらのログは削除してください。