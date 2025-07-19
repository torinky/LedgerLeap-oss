# 添付ファイルパスのリファクタリング計画

## 1. 目的

添付ファイルの保存先ファイルパスに台帳定義IDの階層を追加し、システム全体で普遍的に利用できるようにする。これにより、ファイル管理の構造化とスケーラビリティを向上させる。

## 2. 現状の課題

*   現在の添付ファイルパスは `storage/app/public/Ledger/Attachments/` の下に直接ファイルが保存されており、台帳定義IDによる階層化がされていない。
*   これにより、ファイル数が増加した場合の管理が煩雑になる可能性がある。
*   特定の台帳定義に紐づくファイルを効率的に管理・検索することが難しい。

## 3. 変更の方向性

*   ファイルパスに `ledger_define_id` を含めることで、`storage/app/public/Ledger/Attachments/{ledger_define_id}/` のような階層構造にする。
*   OCR処理後のオリジナルファイルも同様に `storage/app/public/Ledger/Attachments/{ledger_define_id}/Originals/` のような階層にする。
*   ファイルパスの生成・解決ロジックを一元化し、システム全体で普遍的に利用できるようにする。

## 4. 影響範囲

*   ファイルのアップロード処理 (`Livewire` コンポーネント)
*   ファイルのダウンロード処理 (`AttachedFileDownloadController`)
*   OCR処理関連ジョブ (`ProcessAttachedFile`, `OcrAndOptimizeFile`)
*   ファイルパスを直接参照している可能性のあるビュー (`Blade` ファイル)
*   ファイルパスを生成・検証しているテストコード
*   ファイルパスに関するドキュメント

## 5. 実装計画 (ステップ・バイ・ステップ)

### ステップ 1: ファイルパス生成ヘルパーの作成

*   **目的:** ファイルパスの生成ロジックを一元化し、再利用可能なヘルパーとして定義する。
*   **タスク:**
    1.  `app/Helpers/AttachedFilePathHelper.php` を新規作成する。
    2.  以下の静的メソッドを定義する。
        *   `getAttachmentPath(int $ledgerDefineId, string $hashedBasename): string`
            *   例: `public/Ledger/Attachments/{ledger_define_id}/{hashedBasename}` を返す。
        *   `getOriginalAttachmentPath(int $ledgerDefineId, string $hashedBasename): string`
            *   例: `public/Ledger/Attachments/{ledger_define_id}/Originals/{hashedBasename}` を返す。
        *   `getThumbnailPath(int $attachedFileId): string`
            *   これは既存の `AttachedFileDownloadController` を経由するため、ダウンロードルートを返す。
        *   `getThumbnailStoragePath(string $hashedBasename): string`
            *   例: `public/Ledger/thumbs/{hashedBasename}` を返す。
*   **成果物:** ファイルパス生成ロジックをカプセル化したヘルパークラス。
*   **状態:** 完了

### ステップ 2: ファイルアップロード処理の修正

*   **目的:** ファイルアップロード時に、新しいパス構造でファイルを保存するように変更する。
*   **対象ファイル:**
    *   `app/Livewire/Ledger/CreateColumn.php`
    *   `app/Livewire/Ledger/ModifyColumn.php`
*   **タスク:**
    1.  `store('public/Ledger/Attachments')` のような箇所を、`AttachedFilePathHelper::getAttachmentPath($this->ledgerDefineId, $hashedBasename)` を使用するように変更する。
    2.  `ModifyColumn.php` の `processFilesForSave()` メソッド内で、既存ファイルのパスを再構築する際に、新しいパス構造を考慮するように修正する。
*   **成果物:** 新しいパス構造でファイルが保存されるようになる。
*   **状態:** 完了

### ステップ 3: ファイルダウンロード処理の修正

*   **目的:** 新しいパス構造からファイルを正しく取得してダウンロードできるように変更する。
*   **対象ファイル:**
    *   `app/Http/Controllers/AttachedFileDownloadController.php`
*   **タスク:**
    1.  `download` メソッド内で、`AttachedFile` モデルの `path` および `original_file_path` を使用してファイルを読み込む際に、新しいパス構造を考慮するように修正する。
    2.  `AttachedFilePathHelper::getThumbnailPath()` を使用している箇所があれば、そのロジックが新しいパス構造に対応していることを確認する。
*   **成果物:** 新しいパス構造のファイルが正しくダウンロードできるようになる。
*   **状態:** 完了

### ステップ 4: OCR処理関連ジョブの修正

*   **目的:** OCR処理時に、新しいパス構造でファイルを移動・保存するように変更する。
*   **対象ファイル:**
    *   `app/Jobs/Ledger/ProcessAttachedFile.php`
    *   `app/Jobs/Ledger/OcrAndOptimizeFile.php`
*   **タスク:**
    1.  `ProcessAttachedFile.php` で、オリジナルファイルを `Originals` ディレクトリに移動する際に、`AttachedFilePathHelper::getOriginalAttachmentPath()` を使用するように変更する。
    2.  `OcrAndOptimizeFile.php` で、OCR処理後のファイルを保存する際に、`AttachedFilePathHelper::getAttachmentPath()` を使用するように変更する。
*   **成果物:** OCR処理が新しいパス構造に対応する。
*   **状態:** 完了

### ステップ 5: ファイルパスを直接参照しているビューの修正

*   **目的:** ハードコードされたファイルパスを、新しいパス構造に対応させる。
*   **対象ファイル:**
    *   `resources/views/components/ledger/form/files.blade.php`
    *   `resources/views/components/ledger/detail/table.blade.php` (もしあれば)
    *   その他、`asset('storage/...')` のような形式でファイルパスを直接参照している Blade ファイル
*   **タスク:**
    1.  `asset('storage/...')` のような形式でファイルパスを直接参照している箇所を特定する。
    2.  これらの箇所を、`AttachedFileDownloadController` を経由するルート (`route('file.download', ...)`) に変更するか、または `Storage::url(AttachedFilePathHelper::getAttachmentPath(...))` のようにヘルパー関数を使用するように変更する。
*   **成果物:** ビューでのファイル表示が新しいパス構造に対応する。
*   **状態:** 完了

### ステップ 6: テストコードの修正

*   **目的:** ファイルパスの変更に伴い、関連するテストを更新する。
*   **対象ファイル:**
    *   `tests/Unit/Jobs/OcrAndOptimizeFileTest.php`
*   **タスク:**
    1.  テスト内でハードコードされているファイルパスを、新しいパス構造に合わせる。
    2.  `AttachedFilePathHelper` を使用してパスを生成するようにテストコードを修正する。
    3.  `ColumnDefine` がEloquentモデルではないため、ファクトリではなく直接インスタンス化するように修正する。
    4.  `AttachedFile::factory()->create()` の呼び出しで、`ledger_id`, `creator_id`, `modifier_id`, `filename`, `hashedbasename`, `contain_content`, `optimized` など、`NOT NULL` 制約のあるフィールドをすべて提供するように修正する。
    5.  `Bus::fake()` の動作に関するテストの不安定性を解消するため、`Bus::spy()` を使用し、`Process` モックがファイルを作成するように設定する。
*   **成果物:** テストが新しいファイルパス構造に対応し、正しく動作する。
*   **状態:** 完了

### ステップ 7: ドキュメントの更新

*   **目的:** 変更されたファイルパスの構造をドキュメントに反映する。
*   **対象ファイル:**
    *   `docs/models/AttachedFile.md`
    *   `docs/work/2025-07-13_attachment-feature-enhancement.md`
    *   その他、ファイルパスに関する記述があるドキュメント
*   **タスク:**
    1.  新しいファイルパスの構造 (`storage/app/public/Ledger/Attachments/{ledger_define_id}/...`) を明記する。
    2.  `AttachedFilePathHelper` の利用について記述する。
*   **成果物:** ドキュメントが最新のファイルパス構造を反映する。
*   **状態:** 完了

## 6. 反省点 / 学び

今回の添付ファイルパスのリファクタリングとテスト修正において、いくつかの重要な反省点と学びがありました。

1.  **`replace` ツールの厳密性:**
    *   `replace` ツールは `old_string` と `new_string` の完全一致を要求するため、空白、改行、コメント、インデントのわずかな違いでも失敗しました。特に複数行にわたるコードブロックの置換では、この厳密性がデバッグを困難にしました。
    *   **学び:** 今後は、`replace` ツールを使用する際は、`read_file` で取得した内容をそのまま `old_string` に使用し、変更箇所のみを `new_string` に反映させるように、より慎重に扱う必要があります。また、複雑な変更の場合は、より小さな単位での置換を試みるか、`write_file` でファイル全体を書き換えるアプローチも検討すべきです。

2.  **テスト環境と本番環境の差異:**
    *   `after()` メソッドがマイグレーションでSQL構文エラーを引き起こした問題は、テスト環境（SQLiteまたは特定のMySQLバージョン）と開発環境（SailのMySQL）の差異に起因する可能性がありました。Laravelのマイグレーションはデータベースに依存しないように設計されていますが、特定のDB固有の機能（`after()` など）を使用する際には注意が必要です。
    *   **学び:** データベーススキーマの変更を伴うマイグレーションでは、`after()` のようなDB固有の機能の使用を避け、カラムの順序はコードの可読性を優先し、物理的な順序に固執しない方が安全です。

3.  **EloquentモデルとプレーンなPHPクラスの混同:**
    *   `ColumnDefine` がEloquentモデルではないにもかかわらず、ファクトリを使用しようとしたことで、多くのテストエラーが発生しました。これは、クラスの性質を正確に理解していなかった私の誤りです。
    *   **学び:** クラスの責務とフレームワークにおける役割（Eloquentモデルか、単なるデータオブジェクトかなど）を明確に把握することが重要です。テストコードを書く前に、対象クラスの設計を再確認する習慣を強化します。

4.  **テストデータの完全性:**
    *   `AttachedFile::factory()->create()` で `ledger_id`, `creator_id`, `modifier_id`, `filename`, `hashedbasename`, `contain_content`, `optimized` といった `NOT NULL` 制約のあるフィールドが不足していたために、テストが失敗しました。
    *   **学び:** ファクトリを使用する際は、モデルのマイグレーションファイルや `$fillable` プロパティを確認し、必須フィールドがすべてテストデータとして提供されていることを徹底する必要があります。

5.  **ジョブのディスパッチとテストのモック:**
    *   `Bus::fake()` や `Bus::spy()` を使用したジョブのディスパッチのテストで、期待通りに動作しない問題に直面しました。これは、`Bus::fake()` が特定の条件下で外部プロセスからのファイル書き込みを認識しないことや、`dispatchNow` と `dispatch` の違い、そしてテスト環境でのモックの挙動に関する理解不足が原因でした。
    *   **学び:** Laravelのテストヘルパー（`Bus::fake()` など）の挙動を深く理解し、外部プロセスとの連携や非同期処理のテストにおいては、より詳細なデバッグ手法（一時ファイルの作成、ログの確認など）を積極的に活用する必要があります。また、テストの目的（ジョブがキューに入れられたか、ジョブが実行された結果が正しいか）を明確にし、それに応じた適切なアサーションを選択することが重要です。

これらの反省点を踏まえ、今後のソフトウェア開発タスクにおいて、より正確かつ効率的なアプローチを心がけます。

6.  **Enum キャストの重要性:**
    *   **問題:** `AttachedFile` モデルの `status` プロパティが Enum キャストされていなかったため、Blade テンプレートで `->value` にアクセスしようとした際に「Attempt to read property "value" on string」エラーが発生しました。
    *   **学び:** モデルのプロパティが Enum である場合、必ず `$casts` 配列で Enum キャストを明示する必要があります。これにより、型安全性が確保され、予期せぬエラーを防ぐことができます。

7.  **Docker コンテナ間のファイルパス解決:**
    *   **問題:** `ProcessAttachedFile` ジョブ内で Tika にファイルを渡す際、`Storage::path()` がデフォルトディスクの絶対パスを返すため、`public` ディスクのファイルパスが正しく解決されず、Tika がファイルを開けないエラーが発生しました。また、`OcrAndOptimizeFile` ジョブで `ocrmypdf` コマンドに渡すパスも、コンテナ内のパスに変換する必要がありました。
    *   **学び:** Laravel の `Storage` ファサードを使用する際、特に複数のディスクや Docker コンテナ間でファイルを扱う場合は、`Storage::disk('disk_name')->path('relative/path')` のようにディスクを明示し、コンテナ内のパス構造を正確に理解してパスを構築することが不可欠です。

8.  **クラス名のタイプミス:**
    *   **問題:** `OcrAndOptimizeFile` クラスの定義でタイプミスがあり、`OcrAnodOptimizeFile` となっていたため、「Cannot redeclare class」エラーが発生しました。
    *   **学び:** クラス名やファイル名、変数名などの命名には細心の注意を払い、タイプミスがないか複数回確認する習慣をつけるべきです。特に、自動生成されたコードやリファクタリングの際には、このような単純なミスが大きな問題に繋がることがあります。

