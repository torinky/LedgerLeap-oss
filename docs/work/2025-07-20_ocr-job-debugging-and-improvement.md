# OCRジョブのデバッグと改善に関する留意事項

## 1. OCR処理の無限ループ問題とその解決

### 問題の概要
`ProcessAttachedFile`ジョブが、OCR処理後のファイルに対してTikaでテキスト抽出が失敗した場合に、`optimized`フラグをチェックせずに`OcrAndOptimizeFile`ジョブを再ディスパッチしていたため、無限ループが発生していました。これにより、`jobs`テーブルにジョブが継続的に登録され続ける問題が発生していました。

### 解決策
`ProcessAttachedFile.php`において、Tikaでのテキスト抽出が失敗した場合、またはTikaサービスでエラーが発生した場合に`OcrAndOptimizeFile`ジョブをディスパッチする前に、`$this->attachedFile->optimized`が`true`でないことを確認する条件を追加しました。これにより、既にOCR処理が完了しているファイルに対しては、再度OCRジョブがディスパッチされないように修正し、無限ループを解消しました。

## 2. `jobs`テーブルへのジョブ継続登録問題とその解決

### 問題の概要
`queue`コンテナが停止していたため、ジョブが処理されずに`jobs`テーブルに蓄積されていました。また、OCR処理の無限ループもこの問題に拍車をかけていました。

### 解決策
`./vendor/bin/sail up -d queue`コマンドで`queue`コンテナを起動することで、ジョブが処理されるようになりました。さらに、OCR処理の無限ループが解消されたことで、不要なジョブの再登録もなくなりました。

## 3. Dockerコンテナ間のパス指定の重要性

### 問題の概要
`OcrAndOptimizeFile.php`内で`ocrmypdf`コマンドを実行する際、ホストの物理パスをそのまま渡していたため、`ocrmypdf`コンテナ内から正しくファイルにアクセスできていませんでした。

### 解決策
`OcrAndOptimizeFile.php`において、`Storage::disk('public')->path('')`を利用してLaravelのストレージルートの物理パスを取得し、それを空文字列に置換することで、ホストの絶対パスから`storage/app/public/`以下の相対パスを取得し、それに`/var/www/html/storage/app/public/`を付加することで、コンテナ内の絶対パスを生成するように修正しました。これにより、`ocrmypdf`コンテナが正しくファイルにアクセスできるようになりました。

## 4. ログ出力の管理

### 留意事項
ログ出力はデバッグや監視に不可欠ですが、詳細すぎるログはディスク容量の圧迫や可読性の低下を招きます。今後、ログ出力を有効にする可能性がある場合でも、コメントアウトされたログを削除せず、必要に応じて有効化できるようにしておくべきです。また、ログの量は意味がわかる程度に制限し、冗長な情報は避けるべきです。

### 対応
`ProcessAttachedFile.php`のログ出力を、ファイルIDとステータスのみに制限するよう簡潔に修正しました。

## 5. テスト用ファイルのクリーンアップ

### 留意事項
テスト中に作成された一時ファイルや生成されたファイルは、テスト完了後に必ず削除し、プロジェクトのクリーンな状態を維持すべきです。これにより、ディスク容量の無駄遣いを防ぎ、将来的なテストや開発作業に影響を与えないようにします。

### 対応
テストのために作成された以下の不要なファイルを削除しました。
*   `/Users/kazutaka/PhpstormProjects/LedgerLeap/public/test_ocr.png`
*   `/Users/kazutaka/PhpstormProjects/LedgerLeap/public/output.pdf`
*   `/Users/kazutaka/PhpstormProjects/LedgerLeap/storage/app/public/output.pdf`
*   `/Users/kazutaka/PhpstormProjects/LedgerLeap/storage/app/tika_test.php`

## 6. 変更適用時のDockerコンテナ再起動の必要性

### 留意事項
Laravel Sail環境下でコードの変更（特にDockerイメージやコンテナのボリュームマウントに関連する変更、またはPHPコードの変更）を反映させるためには、関連するDockerコンテナの再起動が不可欠です。変更が反映されない場合、問題の特定が困難になる可能性があります。

### 対応
今回のデバッグ作業中、コード修正のたびに`./vendor/bin/sail down && ./vendor/bin/sail up -d`コマンドを使用してDockerコンテナを再起動し、変更が確実に適用されるようにしました。