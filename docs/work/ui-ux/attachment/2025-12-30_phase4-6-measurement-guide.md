# Phase 4.6 パフォーマンス実測定ガイド

**作成日:** 2025年12月30日  
**対象:** FileInspector パフォーマンス測定  
**目的:** Phase 4.6.5 完了基準の実測定

---

## 1. 測定機能の実装状況

### ✅ 実装完了
- フロントエンド測定機能（Performance API）
- バックエンドログ収集機能
- 統計データ蓄積機能（JSON）

### 📝 実装内容

**ファイル1:** `resources/views/livewire/attached-file/file-inspector.blade.php`
- `performance.now()` による時間測定
- ドロワー開閉時間の記録
- タブ切り替え時間の記録
- コンソールログ出力
- バックエンドへのデータ送信

**ファイル2:** `app/Livewire/AttachedFile/FileInspector.php`
- `logPerformance()` メソッド追加
- Laravel標準ログへの記録
- JSON統計ファイルへの蓄積（ローカル環境のみ）

---

## 2. 測定の前提条件

### ⚠️ 重要な制約

**Livewireテストでは測定不可:**
- Livewireのテスト環境では実際のJavaScriptが実行されない
- Alpine.jsのコードが実行されないため、パフォーマンスログが生成されない
- **実際のブラウザでの操作が必須**

### ✅ 測定可能な環境

1. **実ブラウザ（Chrome推奨）**
2. **開発環境（Laravel Sail起動済み）**
3. **実データまたはSeeder実行済み**

---

## 3. 測定手順

### Step 1: 環境準備

```bash
# 開発環境起動
cd /Users/kazutaka/PhpstormProjects/LedgerLeap
./vendor/bin/sail up -d

# ログファイルをクリア（任意）
docker exec ledgerleap-laravel-1 rm -f storage/logs/performance_stats.json
docker exec ledgerleap-laravel-1 sh -c 'echo "" > storage/logs/laravel-$(date +%Y-%m-%d).log'

# テストデータ準備（未実施の場合）
./vendor/bin/sail artisan db:seed
```

### Step 2: ブラウザでアクセス

1. **Chromeを開く**: http://localhost
2. **ログイン**: テストユーザーでログイン
3. **Chrome DevToolsを開く**: `F12`キーまたは右クリック → 検証
4. **Consoleタブを開く**

### Step 3: ドロワー開閉時間の測定

1. **台帳一覧を開く**: 任意のフォルダ内の台帳一覧
2. **添付ファイルがある台帳を開く**: 台帳詳細画面
3. **添付ファイルのアイコンをクリック**: FileInspectorドロワーが開く
4. **コンソールを確認**:

```
[FileInspector Performance] Drawer open duration: 287.45 ms
```

5. **3回測定を繰り返す**（ドロワーを閉じて再度開く）
6. **測定値を記録**

### Step 4: タブ切り替え時間の測定

1. **FileInspectorを開いた状態で**
2. **各タブを順番にクリック**: Content → Details → History → Permissions
3. **コンソールを確認**:

```
[FileInspector Performance] Tab switch: content -> details 42.30 ms
[FileInspector Performance] Tab switch: details -> history 58.15 ms
[FileInspector Performance] Tab switch: history -> permissions 35.20 ms
```

4. **複数回測定を繰り返す**
5. **測定値を記録**

### Step 5: データ収集

**方法1: コンソールから手動コピー**
- コンソールログをコピー＆ペースト

**方法2: パフォーマンス統計ファイル**
```bash
docker exec ledgerleap-laravel-1 cat storage/logs/performance_stats.json | jq '.'
```

**方法3: Laravelログ**
```bash
docker exec ledgerleap-laravel-1 grep "FileInspector Performance" storage/logs/laravel-$(date +%Y-%m-%d).log
```

---

## 4. クエリ数の測定

### Option 1: Chrome DevTools Network タブ

1. **DevTools → Networkタブを開く**
2. **XHR/Fetchフィルターを有効化**
3. **FileInspectorを開く**
4. **`openInspector` リクエストを確認**
5. **レスポンスの内容を確認**

### Option 2: Laravel Telescope（推奨）

```bash
# Telescopeインストール（未インストールの場合）
./vendor/bin/sail composer require laravel/telescope --dev
./vendor/bin/sail artisan telescope:install
./vendor/bin/sail artisan migrate

# http://localhost/telescope にアクセス
# Queriesタブでクエリ数を確認
```

### Option 3: クエリログ有効化

**`config/database.php` を一時的に編集:**

```php
'connections' => [
    'mysql' => [
        // ...
        'options' => [
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
],
```

**`app/Providers/AppServiceProvider.php` の `boot()` に追加:**

```php
if (app()->environment('local')) {
    \DB::listen(function ($query) {
        \Log::info('Query', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time . 'ms',
        ]);
    });
}
```

**測定実施:**
```bash
# ログをクリア
docker exec ledgerleap-laravel-1 sh -c 'echo "" > storage/logs/laravel-$(date +%Y-%m-%d).log'

# ブラウザでFileInspectorを開く

# クエリ数を確認
docker exec ledgerleap-laravel-1 grep "Query" storage/logs/laravel-$(date +%Y-%m-%d).log | wc -l
```

---

## 5. 測定結果の記録

### テンプレート

#### ドロワー開閉時間

| 測定回 | 初回（ms） | 2回目（ms） | 3回目（ms） | 平均（ms） |
|-------|----------|-----------|-----------|----------|
| 1     |          |           |           |          |
| 2     |          |           |           |          |
| 3     |          |           |           |          |
| **平均** |          |           |           |          |

#### タブ切り替え時間

| タブ切り替え | 測定1（ms） | 測定2（ms） | 測定3（ms） | 平均（ms） |
|------------|-----------|-----------|-----------|----------|
| Content → Details |  |  |  |  |
| Details → History |  |  |  |  |
| History → Permissions |  |  |  |  |
| Permissions → Content |  |  |  |  |

#### クエリ数

| リレーション | 実測クエリ数 |
|------------|------------|
| AttachedFile本体 |  |
| ledger |  |
| ledger.define |  |
| ledger.define.folder |  |
| creator |  |
| modifier |  |
| activities.causer |  |
| **合計** |  |

---

## 6. 評価基準

### 成功基準

| 項目 | 目標 | 判定 |
|-----|------|------|
| クエリ数 | 5回以内 | 平均 ____ 回 → _____ |
| ドロワー開閉（初回） | 300ms以内 | 平均 ____ ms → _____ |
| ドロワー開閉（2回目以降） | 300ms以内 | 平均 ____ ms → _____ |
| タブ切り替え | 100ms以内 | 平均 ____ ms → _____ |

---

## 7. トラブルシューティング

### コンソールログが表示されない

**原因:**
- JavaScriptが無効化されている
- ブラウザのキャッシュ問題

**解決策:**
```bash
# アセットを再ビルド
./vendor/bin/sail npm run build

# ブラウザのキャッシュをクリア
Ctrl + Shift + Delete
```

### パフォーマンス統計ファイルが生成されない

**原因:**
- `config/app.php` の `'env' => 'local'` 設定が必要

**確認:**
```bash
docker exec ledgerleap-laravel-1 php artisan config:show app.env
```

### ログが記録されない

**確認:**
```bash
# ログディレクトリの権限確認
docker exec ledgerleap-laravel-1 ls -la storage/logs/

# 書き込み権限がない場合
docker exec ledgerleap-laravel-1 chmod -R 777 storage/logs/
```

---

## 8. 測定完了後の作業

1. **測定結果をレポートに記入**:
   - `docs/work/ui-ux/attachment/2025-12-30_phase4-6-5_performance_report.md`
   - セクション「5. 測定結果」を更新

2. **評価を実施**:
   - 成功基準と比較
   - Phase 4.6.5 の完了判定

3. **改善提案の検討**（必要に応じて）:
   - 目標未達の場合の改善策
   - Phase 5 での最適化計画

---

**作成者:** 開発チーム  
**最終更新:** 2025年12月30日

