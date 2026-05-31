# 添付ファイル hashedbasename 移行手順書

**対象 Issue:** #233 (Epic)  
**最終更新:** 2026-05-31

---

## 概要

`attached_files` テーブルの `hashedbasename` カラムに UNIQUE 制約を追加し、
ファイル名 + 容量 + 最終更新日時 に基づく衝突耐性の高いハッシュ生成方式に移行するための手順です。

## 前提

- `migrate:fresh` を本番DBで実行する場合、**ストレージファイルは削除されません**。
- 過去の添付ファイルがストレージに残留している可能性があります。
- 本番DBの再構築前に、必ずバックアップを取得してください。

---

## 移行手順

### Step 1: 重複データの検出

```bash
# 全テナントの重複を検出
./vendor/bin/sail artisan attached-files:detect-duplicates

# 特定テナントのみ
./vendor/bin/sail artisan attached-files:detect-duplicates --tenant=1

# プレビューモード（--dry-run）
./vendor/bin/sail artisan attached-files:detect-duplicates --fix --dry-run
```

**期待結果:** 重複が0件であること。重複がある場合は Step 1-A を実行。

### Step 1-A: 重複データの修復

```bash
# 重複レコードの hashedbasename を再生成、ストレージファイルも移動
./vendor/bin/sail artisan attached-files:detect-duplicates --fix
```

### Step 2: ストレージ整合性チェック

```bash
# DBレコードとストレージファイルの整合性を確認
./vendor/bin/sail artisan attached-files:check-storage-consistency

# 全テナントの孤立ファイルを検出・削除
./vendor/bin/sail artisan attached-files:check-storage-consistency --clean

# プレビューしてから削除
./vendor/bin/sail artisan attached-files:check-storage-consistency --clean --dry-run
```

**確認項目:**
- orphan records（DBレコードはあるが実ファイル不在）が 0 件
- orphan files（実ファイルはあるがDBレコード不在）が削除済み

### Step 3: `migrate:fresh` 前のサムネイルクリーンアップ

```php
// Tinker で実行
$deleted = App\Helpers\AttachedFilePathHelper::cleanupOrphanedThumbnails(1);
echo "{$deleted} 件の孤立サムネイルを削除しました。";
```

### Step 4: データベース再構築

```bash
# 本番DB再構築（要バックアップ確認）
./vendor/bin/sail artisan migrate:fresh --force

# デモデータ投入
./vendor/bin/sail artisan db:seed --class=DemoCompleteSeeder
```

### Step 5: 再構築後の検証

```bash
# 重複検出
./vendor/bin/sail artisan attached-files:detect-duplicates

# ストレージ整合性
./vendor/bin/sail artisan attached-files:check-storage-consistency

# テスト実行
./vendor/bin/sail test tests/Feature/Livewire/Ledger/CreateColumnFileCollisionTest.php
```

---

## ロールバック手順

問題が発生した場合:

1. **DBのリストア**: `migrate:fresh` 前のバックアップから復元
2. **ストレージのリストア**: `storage/app/public/tenants/` をバックアップから復元
3. **ロールバック確認**: 添付ファイルの表示・ダウンロードが正常に動作することをブラウザで確認

---

## 検証リスト

- [ ] `attached-files:detect-duplicates` で重複 0 件を確認
- [ ] `attached-files:check-storage-consistency` で orphan 0 件を確認
- [ ] ファイルインスペクターで拡大表示ボタンが正しいファイルを表示することを確認
- [ ] サムネイルが正しいファイルのサムネイルであることを確認
- [ ] OCR/Tika 結果が正しいファイルのものであることを確認
- [ ] 異なる台帳定義間でファイルが混在していないことを確認
