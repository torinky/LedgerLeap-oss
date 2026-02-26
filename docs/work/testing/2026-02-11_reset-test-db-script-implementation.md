# テストデータベースリセットスクリプトの実装

**実装日:** 2026年2月11日  
**担当者:** システム  
**関連Issue:** マイグレーション実行時のMySQLモニタ問題

## 1. 概要

テスト環境でのマイグレーションリセット時に発生していた問題を解決するため、専用スクリプト `bin/reset-test-db.sh` を実装しました。

## 2. 問題の背景

### 2.1. 発生していた問題

`./vendor/bin/sail artisan migrate:fresh --env=testing` コマンドを実行すると、以下の問題が発生していました：

1. **MySQLモニタに入ってしまう**
   - Sailのmysqlコマンドがインタラクティブモードで起動
   - スクリプトの自動実行が中断される

2. **テーブルが残る**
   - `DROP DATABASE` が正しく実行されない
   - テーブルが既に存在するエラー (`Table already exists`) が発生

3. **環境によって動作が不安定**
   - `migrate:fresh` が機能しない場合がある
   - `migrate:refresh` はデッドロックリスクがある

### 2.2. 根本原因

- `./vendor/bin/sail mysql` コマンドは内部で `docker exec -it` を使用
- `-i` (インタラクティブ) オプションにより標準入力が有効
- パイプやheredocで標準入力を渡すとモニタモードに入る

## 3. 実装した解決策

### 3.1. スクリプトの特徴

**ファイル:** `bin/reset-test-db.sh`

**主要な改善点:**

1. **docker exec を直接使用**
   - Sailのラッパーを経由せず、直接MySQLコンテナにコマンド実行
   - `-e` オプションでバッチモードを強制

2. **確実なデータベース削除**
   - 2回の削除試行で既存接続の影響を回避
   - データベース作成の成功を確認

3. **詳細なデバッグ情報**
   - 各ステップでステータスを表示
   - テーブル数を確認してクリーンな状態を検証

### 3.2. スクリプトの構造

```bash
# Step 1: データベース削除・再作成
docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -e "DROP DATABASE IF EXISTS ledgerleap_test;"
# (2回試行で確実性を向上)

docker exec ledgerleap-mysql-1 mysql -uroot -ppassword -e "CREATE DATABASE ledgerleap_test ..."

# Step 2: 設定キャッシュクリア
./vendor/bin/sail artisan config:clear

# Step 3: テーブル数確認（デバッグ用）
docker exec ledgerleap-mysql-1 mysql ... # COUNT(*)

# Step 4: マイグレーション実行
./vendor/bin/sail artisan migrate --env=testing
```

## 4. 動作確認結果

### 4.1. 実行結果

```
=== テストデータベースリセット開始 ===

[1/4] データベースを削除・再作成中...
  ✓ データベース作成成功
  ✓ データベース再作成完了

[2/4] 設定キャッシュをクリア中...
  ✓ キャッシュクリア完了

[3/4] データベースの状態を確認中...
  ✓ 現在のテーブル数: 0

[4/4] マイグレーション実行中...
  ...（全マイグレーション成功）

=== テストデータベースリセット完了 ===
```

### 4.2. 成功ポイント

- ✅ MySQLモニタに入らない
- ✅ テーブルが0の状態から開始
- ✅ 全マイグレーションが正常に完了
- ✅ エラーなく完走

## 5. ドキュメント更新

以下のドキュメントを更新しました：

### 5.1. `docs/database/schema.md`

**変更箇所:** セクション 6.1「テスト環境での推奨コマンド」

**変更内容:**
- `migrate:fresh` から `./bin/reset-test-db.sh` に変更
- 動作しない可能性のあるコマンドを明記
- 手動実行の方法を追加

### 5.2. `docs/development/Testing-Best-Practices.md`

**変更箇所:** セクション「マイグレーション管理とトラブルシューティング」

**変更内容:**
- スクリプトの動作説明を追加
- 手動実行のコマンドを明記
- 重複セクションを削除

## 6. 今後の展開

### 6.1. 開発環境での利用

開発環境でも同様の問題が発生する可能性があるため、必要に応じて以下を検討：

```bash
# 開発環境用スクリプト（未実装）
./bin/reset-dev-db.sh
```

### 6.2. CI/CD統合

GitHub ActionsなどのCI環境では、このスクリプトを使用することで安定したテスト実行が可能になります。

## 7. 注意事項

### 7.1. パスワードのハードコーディング

現在、MySQLのrootパスワードがスクリプト内にハードコードされています (`-ppassword`)。

**対策:**
- 開発・テスト環境のみで使用
- 本番環境では別の認証方法を使用

### 7.2. コンテナ名の依存

スクリプトは `ledgerleap-mysql-1` というコンテナ名に依存しています。

**対策:**
- `docker-compose.yml` でコンテナ名が変更された場合はスクリプトも更新
- または環境変数で設定可能にする（将来の改善）

## 8. 関連ドキュメント

- **実装:** `bin/reset-test-db.sh`
- **マイグレーション:** `docs/development/Testing-Best-Practices.md`
- **スキーマ:** `docs/database/schema.md`
- **トラブルシューティング:** `docs/work/testing/2026-02-11_migration-deadlock-troubleshooting.md`

---

**実装完了日:** 2026年2月11日  
**ステータス:** ✅ 完了・動作確認済み

