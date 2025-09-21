# 2025-09-21 マルチテナント環境における添付ファイル機能の包括的な問題解決の記録 (改訂版)

## 1. 概要

本ドキュメントは、当初「添付ファイルの保存パスがテナントごとに分離されていない」という単一の問題修正から始まった作業が、結果としてキュー処理の潜在的なバグや、Livewireコンポーネントにおけるテナントコンテキストの管理といった、より広範で深刻な問題を明らかにし、それらを体系的に解決するまでの全経緯を記録するものである。

**現在の状況:**
ファイルパスの分離、キュー処理の問題は解決済み。しかし、**台帳編集画面における添付ファイルのURL生成エラー**、および今回新たに発見された**サムネイルの保存パスの不整合**という2つの課題が未解決である。本ドキュメントの最終セクションに、これらの問題の根本原因と具体的な解決策を詳述する。

## 2. 課題1: 添付ファイルの保存パスがテナント分離されていない (解決済み)

*   **問題点:**
    当初の実装では、添付ファイルは `storage/app/public/Ledger/Attachments/` という単一のディレクトリに保存されており、テナントごとの分離がなされていなかった。これは、将来的なファイル管理の煩雑化や、テナント間のデータ分離の観点から問題があった。

*   **解決策:**
    1.  **`AttachedFilePathHelper` の導入:** ファイルパスの生成ロジックを一元管理する `app/Helpers/AttachedFilePathHelper.php` を導入。新しいパス構造を `storage/app/public/Ledger/Attachments/{ledger_define_id}/` と定義した。
    2.  **関連機能の修正:** ファイルのアップロード (`CreateColumn`/`ModifyColumn`)、ダウンロード (`AttachedFileDownloadController`)、非同期処理ジョブ (`ProcessAttachedFile`/`OcrAndOptimizeFile`) など、ファイルパスを扱う全ての箇所でこのヘルパーを利用するように修正し、パスの生成・解決ロジックを統一した。

*   **成果:**
    添付ファイルが台帳定義IDごとにディレクトリ分けして保存されるようになり、テナントごとの物理的なファイル分離の基礎が確立された。

## 3. 課題2: キュー処理のエラーと無限ループ (解決済み)

*   **問題点:**
    ファイルパスの修正後、ファイルのテキスト抽出やOCR処理を担うキューが正常に動作していないことが判明した。
    1.  **キューワーカーの停止:** 開発環境で `queue` コンテナが停止しており、ジョブが全く処理されていなかった。
    2.  **無限ループ:** `ProcessAttachedFile` ジョブが、OCR処理済みのファイル（`optimized` フラグが `true`）を再度OCRジョブに送ってしまい、処理が無限に繰り返されるバグが存在した。

*   **解決策:**
    1.  `./vendor/bin/sail up -d queue` コマンドでキューワーカーを起動し、ジョブ処理を再開させた。
    2.  `ProcessAttachedFile` ジョブのロジックを修正し、OCRジョブをディスパッチする前に、対象ファイルの `optimized` フラグが `true` でないことを確認する条件分岐を追加した。

*   **成果:**
    キューシステムが正常に稼働し、ファイルの非同期処理が意図通りに完了するようになった。無限ループが解消され、システムの安定性が向上した。

## 4. 課題3: Livewire編集画面のURL生成エラーとUIデグレード (一部解決・課題残存)

*   **問題点:**
    台帳編集画面 (`/ledgers/{ledgerId}/edit`) で `UrlGenerationException` が発生し、画面が正常に表示されない。この影響で、添付ファイルのサムネイル表示やダウンロードリンクも機能しない。

*   **原因分析の経緯:**
    1.  **Bladeビューの問題と特定:** 当初、ビュー内の `tenant()` ヘルパーが `null` を返すことが原因と推測し、複数の修正を試みたが解決しなかった。
    2.  **データモデルの誤解の特定:** ユーザーからの指摘により、`Ledger` モデルが `tenant_id` を直接持たず、`LedgerDefine` を経由してテナントに紐づくという、データモデルの理解に誤りがあったことを特定した。
    3.  **コンテキスト解決ロジックの欠落の特定:** `ModifyColumn`コンポーネントには、自身の`ledgerRecord`から`LedgerDefine`を経由してテナントIDを特定し、コンポーネントのプロパティ(`$tenantId`)にセットするロジックが欠けていた。

*   **実施済みの対策:**
    *   `app/Livewire/Ledger/ModifyColumn.php` の `mount()` メソッドを修正し、`Ledger` レコードの `define` リレーションをイーガーロードした上で、`$this->tenantId = $this->ledgerRecord->define->tenant_id;` を実行。これにより、コンポーネントは自身のテナントIDを確実に保持できるようになった。
    *   この対策により、`render()` メソッドで生成される削除ボタンのURL (`destroyUrl`) に関する `UrlGenerationException` は解消された。

*   **残された課題（最重要引き継ぎ事項）:**
    *   **現象:** 上記対策後も、テストは `it_correctly_prepares_initial_files_for_filepond` で失敗を続けている。手動での画面確認でも、サムネイルが表示されず（`src="#"` となる）、ダウンロードリンクが機能しない。
    *   **原因:** エラーの根本原因は、`ModifyColumn` コンポーネント内の **`prepareFilePondInitialFiles()` メソッド**にある。このメソッドは、FilePond（ファイルアップロードライブラリ）に渡すための初期ファイル情報（ダウンロードURLやサムネイルURLを含む）を生成するが、その内部で `route('file.download', ...)` を呼び出す際に、**修正済みの `$this->tenantId` プロパティをパラメータとして渡していない。** そのため、URL生成に失敗し、結果として `null` が返され、UIの不具合を引き起こしている。

## 5. 課題4: サムネイル保存パスの不整合 (新規課題)

*   **問題点:**
    *   添付ファイルのサムネイルが、台帳定義ごと (`.../Attachments/{ledger_define_id}/`) に分離されず、テナント内の単一の `thumbs` ディレクトリ (`.../Ledger/thumbs/`) にまとめて保存されている。
    *   これは、「課題1」で実施したファイルパス分離の対応が、サムネイルには適用されていないことが原因。

*   **原因分析:**
    *   調査の結果、サムネイル生成は `app/Jobs/Ledger/GenerateThumbnail.php` ジョブが担う設計であるものの、**現状どこからも初回生成の呼び出しが行われていない**ことが判明。
    *   サムネイルのパスを生成する `app/Helpers/AttachedFilePathHelper::getThumbnailStoragePath()` メソッドが、パス生成ロジックに `ledger_define_id` を含めていない。

*   **リスク:**
    *   現状ではサムネイルが一切生成されない。
    *   仮に生成されたとしても、異なる台帳定義間でファイル名が重複した場合、サムネイルが上書きされる。
    *   添付ファイル本体とサムネイルの保存ルールに一貫性がなく、管理が煩雑化する。

## 6. 今後の具体的な解決策（ネクストアクション）

以下の手順で、残された2つの問題を解決する。

### 6.1. 課題3 (Livewire編集画面) の解決策

1.  **対象ファイルの特定:**
    *   `app/Livewire/Ledger/ModifyColumn.php`

2.  **修正対象メソッドの特定:**
    *   `prepareFilePondInitialFiles()`

3.  **具体的な修正内容:**
    *   メソッド内で `route('file.download', ...)` を呼び出している箇所を全て探し、パラメータ配列に `'tenant' => $this->tenantId` を追加する。

4.  **検証方法:**
    *   **自動テスト:** `vendor/bin/sail test tests/Feature/Livewire/Ledger/ModifyColumnTest.php` を実行し、`it_correctly_prepares_initial_files_for_filepond` を含む全てのテストがパスすることを確認する。
    *   **手動テスト:** 台帳編集画面を開き、既存の添付ファイルのサムネイルが正しく表示され、ダウンロードリンクが正常に機能することをブラウザで確認する。

### 6.2. 課題4 (サムネイルパス) の解決策と最終方針

#### 6.2.1. 調査と方針決定の経緯

本課題の解決にあたり、いくつかの重要な技術的判断と調査を行った。後続の保守開発者のために、その経緯をここに記録する。

##### (1) サムネイル生成タイミングの特定

当初、サムネイル生成ジョブ `GenerateThumbnail` の呼び出し箇所がコードベース上に見当たらず、「初回生成ロジックが存在しない」と仮説を立てた。しかし、ユーザーからの「表示時に生成していたはず」との指摘を受け `AttachedFileDownloadController` を再調査した結果、以下の事実が判明した。

*   **既存のロジック:** コントローラーには、サムネイルが存在せず、かつファイルのステータスが `THUMBNAIL_FAILED` の場合に限り、`GenerateThumbnail` ジョブを**再ディスパッチ**する「再試行」ロジックが存在した。
*   **欠落していたロジック:** 一方で、サムネイルの「初回生成」を担う仕組みは実装されていなかった。

この調査結果に基づき、**初回生成は `ProcessAttachedFile` ジョブが担う**という方針を決定した。

##### (2) パス生成戦略の模索と最終方針の決定

サムネイルのパス構造をどう決定するかについて、複数の案を検討した。

1.  **ディレクトリ分離案:** 添付ファイル本体と同様に `.../Attachments/{ledger_define_id}/thumbs/` に保存する案。最も構造がクリーンだが、`ColumnHtmlService` など呼び出し側の修正が必要となる点が懸念された。
2.  **ファイル名ハッシュ化案:** ファイル名の衝突を避けるため、関連情報からユニークなハッシュを生成する案。DBスキーマ変更など、複雑性が増すため見送られた。
3.  **`hashedbasename` 流用案（最終方針）:** ユーザーからの「添付ファイル名は既に `hashedbasename` として一意になっているはず」という本質的な指摘を採用。`hashedbasename` をサムネイル名として流用し、テナント直下の `.../thumbs/` ディレクトリに保存する方針を最終決定した。

この最終方針は、`ColumnHtmlService` の修正が不要になるという大きなメリットがあり、かつファイル衝突も防げるため、最もシンプルでバランスの取れた解決策であると判断した。

#### 6.2.2. 最終的な修正計画

上記方針に基づき、以下の作業を実施する。

##### ステップ1: `AttachedFilePathHelper` の修正

*   **対象:** `app/Helpers/AttachedFilePathHelper.php`
*   **内容:** `getThumbnailStoragePath` メソッドのロジックを、テナント直下のサムネイル専用ディレクトリ (`tenants/{tenant_id}/Ledger/thumbs/`) に、引数の `hashedbasename` を使ってパスを生成するように修正する。（一度 `ledger_define_id` を追加した修正を元に戻す）

##### ステップ2: 関連コードの修正

`ledger_define_id` を渡すように変更した、以下のファイルの関連箇所をすべて元の形に戻す。
*   `app/Jobs/Ledger/GenerateThumbnail.php`
*   `app/Http/Controllers/AttachedFileDownloadController.php`

##### ステップ3: `ProcessAttachedFile` の修正

*   **対象:** `app/Jobs/Ledger/ProcessAttachedFile.php`
*   **内容:** `GenerateThumbnail` ジョブをディスパッチする処理を追加する。`ledger_define_id` は不要なため、`dispatch($this->attachedFile->id)` の形で呼び出す。

##### ステップ4: テストコードの修正

同様に、`ledger_define_id` を渡すように変更したテストコードをすべて元の形に戻す。
*   `tests/Feature/Helpers/AttachedFilePathHelperTest.php`
*   `tests/Unit/Jobs/GenerateThumbnailTest.php`

##### ステップ5: 検証

*   修正したテストがすべてパスすることを確認する。
*   手動で画像を添付し、`storage/tenants/{tenant_id}/Ledger/thumbs/` 配下にサムネイルが正しく生成・表示されることを確認する。

##### ステップ6: 関連ドキュメントの修正

*   今回の仕様変更に伴い、影響を受ける可能性のあるドキュメントを更新する。対象は `@docs` ディレクトリ配下の、添付ファイルのパス仕様やファイル処理フローに関する記載を含むすべてのドキュメントとする。

# 7. 課題5: OCRジョブの実行環境における問題の特定と解決

当初のファイルパス修正後、OCR処理を担う `OcrAndOptimizeFile` ジョブがキュー内で失敗し続けるという問題が新たに発生した。この問題の解決過程で、Dockerのビルドプロセス、コンテナの実行環境、ロギング、さらにはテスト手法に至るまで、複数の根深い問題が明らかになった。

### 7.1. 問題点

*   **現象:** `OcrAndOptimizeFile` ジョブがキューで処理されると、`ocr_failed` ステータスで失敗する。
*   **不可解なログの欠落:** ジョブの `catch` ブロックにはエラーログを記録する処理があるにもかかわらず、`storage/logs/queue.log` や `laravel.log` に一切のエラーが出力されず、デバッグが極めて困難な状況に陥っていた。

### 7.2. 原因分析と解決の経緯

#### (1) `docker exec` コマンドの失敗

最初の仮説は、ジョブが `docker exec` を使って `ocrmypdf` コンテナを呼び出す処理に失敗しているというものだった。

*   **調査:** `queue` コンテナ内で `which docker` を実行したところ、`docker` コマンドが存在しないことが判明。
*   **原因:** `docker-compose.yml` を調査した結果、`laravel`, `queue`, `scheduler` の3つのサービスがすべて同じイメージ名 `sail-8.4/app` を共有していた。これにより、後からビルドされた `laravel` や `scheduler` のイメージ（Docker CLIを含まない）が、Docker CLIをインストールするように設定された `queue` サービスのイメージを上書きしてしまい、結果として `queue` コンテナにDocker CLIがインストールされない、というビルドプロセスの根本的な欠陥が特定された。
*   **対策:**
    *   `docker-compose.yml` を修正し、`queue` サービスの `image` 名をユニークな `sail-8.4/app-queue` に変更。
    *   `docker/app/DockerfileQueue` がPHPのベース環境を含んでいなかったため、`vendor/laravel/sail/runtimes/8.4/Dockerfile` をベースに、Docker CLIをインストールする処理を追加する形で全面的に書き換えた。
    *   `sail build --no-cache` で全イメージを再ビルドし、問題を解決した。

#### (2) ログが一切出力されない問題

`docker exec` が利用可能になっても、ジョブは失敗し続け、依然としてログは出力されなかった。

*   **調査:**
    1.  `docker-compose.yml` の `queue` サービスに `LOG_CHANNEL: 'queue'` が設定されており、`config/logging.php` の `queue` チャネルが `storage/logs/queue.log` を指していることを確認。設定は正しかった。
    2.  `vendor/bin/sail exec laravel ls -ld storage/logs` を実行し、`storage/logs` ディレクトリのパーミッションを確認したところ、所有者が `root:root` になっていた。
*   **原因:** `queue` コンテナ内のプロセスは `sail` ユーザーで実行されているため、`root` が所有する `storage/logs` ディレクトリへの書き込み権限がなく、ログファイルを作成できずにサイレントフェイルしていた。
*   **対策:**
    *   **一時的対応:** `vendor/bin/sail exec laravel chown -R sail:sail storage/logs` を実行し、ディレクトリの所有者を `sail` ユーザーに変更することで、ログが出力されることを確認した。
    *   **恒久対応:** この問題の再発を防ぐため、`docker/app/start-container` スクリプトを修正。コンテナ起動時に `chown -R $WWWUSER:$WWWGROUP /var/www/html/storage/logs` が実行されるように処理を追加した。これにより、ホスト側のファイルパーミッションに依らず、コンテナは常にログディレクトリへの書き込み権限を確保できるようになった。

#### (3) `ocrmypdf` の `InputFileError`

ログが出力されるようになると、今度は `ocrmypdf` 自体が `InputFileError` を返していることが判明した。

*   **原因:** これは、テスト実行時にTinkerスクリプトで生成していたダミーのPDFファイルが、`ocrmypdf` が処理できない不正な形式だったためである。
*   **恒久対策としてのテストコード実装:** この問題を根本的に解決し、今後のリグレッションを防ぐため、Tinkerスクリプトによる手動テストではなく、恒久的な自動テストを実装する方針に切り替えた。

### 7.3. 解決策: OCRジョブのフィーチャーテスト実装

`OcrAndOptimizeFile` ジョブの複雑なロジックを堅牢にテストするため、`tests/Feature/Ledger/OcrAndOptimizeFileJobTest.php` を新規に作成した。

*   **テスト戦略:**
    *   **外部プロセスのモック化:** `Process::fake()` を使用して、`docker exec` の外部コマンド呼び出しをモック化した。これにより、Dockerデーモンが利用できないテスト環境でも、ジョブの内部ロジックを完全にテストできるようになった。
    *   **成功・失敗シナリオの網羅:** OCR処理が成功した場合と失敗した場合の両方のシナリオをテストする2つのテストケース (`it_successfully_processes_a_pdf_file_and_updates_status`, `it_handles_ocr_failure_and_updates_status`) を実装した。
    *   **ファイルシステムのシミュレーション:** `Storage::fake('public')` を使用してファイル操作をシミュレート。特に、成功ケースのテストでは、`Process::fake()` のクロージャ内で、`ocrmypdf` が出力するはずのファイルを擬似的に生成する処理を追加した。これにより、ジョブ内の `Storage::size()` 呼び出しが失敗する問題を回避し、より現実に近い形でのテストを実現した。

*   **成果:**
    *   一連のインフラ（Docker環境、パーミッション）の問題が修正された。
    *   `OcrAndOptimizeFile` ジョブの振る舞いを保証する、安定的かつ再現性の高い自動テストが整備された。
    *   これにより、今後の改修時に意図しない不具合（リグレッション）が発生することを防ぐセーフティネットが構築された。

