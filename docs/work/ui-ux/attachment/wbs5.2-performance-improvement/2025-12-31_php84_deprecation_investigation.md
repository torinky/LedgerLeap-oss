# PHP 8.4 非推奨警告の調査レポート

**調査日:** 2025年12月31日  
**問題:** `Accessing static trait property BelongsToTenant::$tenantIdColumn is deprecated`  
**影響:** パフォーマンス低下の可能性  
**優先度:** 🔴 高

---

## 問題の概要

### 警告メッセージ

```
[03:31:40] LOG.warning: Accessing static trait property Stancl\Tenancy\Database\Concerns\BelongsToTenant::$tenantIdColumn is deprecated, 
it should only be accessed on a class using the trait 
in /var/www/html/vendor/stancl/tenancy/src/Database/TenantScope.php on line 20
```

### 発生頻度

ユーザーからの報告: **大量に発生している**

### 影響範囲

**使用しているモデル（7箇所）:**
1. `app/Models/Tag.php`
2. `app/Models/Ledger.php`
3. `app/Models/AttachedFile.php`
4. `app/Models/Folder.php`
5. `app/Models/LedgerDefine.php`
6. `app/Models/CustomActivity.php`
7. `app/Models/LedgerDiff.php`

**推定される警告の発生回数:**
- FileInspectorのドロワー開閉: 1回あたり数十〜数百回
- タブ切り替え: 1回あたり数十回
- 検索: 入力1文字ごとに数十回

**合計:** 操作1回で数百回の警告が発生している可能性

---

## 技術的な詳細

### PHP 8.4の変更点

PHP 8.4では、トレートの静的プロパティに**トレート名で直接アクセスすること**が非推奨になりました。

**非推奨の書き方（現在のコード）:**
```php
// TenantScope.php:20
$builder->where($model->qualifyColumn(BelongsToTenant::$tenantIdColumn), tenant()->getTenantKey());
```

**推奨される書き方:**
```php
// トレートを使用しているクラス経由でアクセス
$builder->where($model->qualifyColumn($model::$tenantIdColumn), tenant()->getTenantKey());
```

### 問題のあるコード箇所

#### 1. TenantScope.php（Line 20）

```php
public function apply(Builder $builder, Model $model)
{
    if (! tenancy()->initialized) {
        return;
    }

    // ❌ 非推奨: トレート名で静的プロパティにアクセス
    $builder->where($model->qualifyColumn(BelongsToTenant::$tenantIdColumn), tenant()->getTenantKey());
}
```

#### 2. BelongsToTenant.php（Line 19, 27）

```php
trait BelongsToTenant
{
    public static $tenantIdColumn = 'tenant_id';

    public function tenant()
    {
        // ❌ 非推奨
        return $this->belongsTo(config('tenancy.tenant_model'), BelongsToTenant::$tenantIdColumn);
    }

    public static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            // ❌ 非推奨
            if (! $model->getAttribute(BelongsToTenant::$tenantIdColumn) && ! $model->relationLoaded('tenant')) {
                if (tenancy()->initialized) {
                    $model->setAttribute(BelongsToTenant::$tenantIdColumn, tenant()->getTenantKey());
                    $model->setRelation('tenant', tenant());
                }
            }
        });
    }
}
```

---

## パフォーマンスへの影響

### 警告ログのコスト

**PHP 8.4の非推奨警告は:**
1. **スタックトレースの生成**（高コスト）
2. **ログへの書き込み**（I/O）
3. **メモリ使用量の増加**

**推定される影響:**
- ドロワー開閉1回: +100-500ms（警告が100回発生すると仮定）
- タブ切り替え1回: +50-200ms
- 検索1文字入力: +50-200ms

**これがLivewireのレンダリングが遅い主要な原因である可能性が高い。**

### 実測データとの整合性

**WBS 5.2.0の実測結果:**
- ドロワー開閉: 2000ms（サーバー処理<100ms、残り1900msが不明）
- タブ切り替え（Permissions）: 6761ms（サーバー処理<1ms、残り6760msが不明）

**仮説:**
- この1900msや6760msの大部分が**PHP 8.4の非推奨警告のコスト**である可能性

---

## 解決策

### 🔴 優先度: 最高（即実施）

#### 解決策1: stancl/tenancyのアップデート

**現在のバージョン:** v3.9.1  
**確認すべきこと:**
1. 最新版（v3.x）でPHP 8.4対応済みか
2. 修正版がリリースされているか

**実施手順:**
```bash
# 最新版を確認
composer show stancl/tenancy

# アップデート可能な場合
./vendor/bin/sail composer update stancl/tenancy

# または特定のバージョンを指定
./vendor/bin/sail composer require stancl/tenancy:^3.10
```

#### 解決策2: パッチの適用（緊急対応）

最新版で修正されていない場合、一時的にパッチを適用する。

**パッチファイル: `patches/fix_tenancy_php84.patch`**

```diff
--- a/vendor/stancl/tenancy/src/Database/TenantScope.php
+++ b/vendor/stancl/tenancy/src/Database/TenantScope.php
@@ -17,7 +17,8 @@ public function apply(Builder $builder, Model $model)
             return;
         }
 
-        $builder->where($model->qualifyColumn(BelongsToTenant::$tenantIdColumn), tenant()->getTenantKey());
+        // PHP 8.4: Use the model's property instead of trait name
+        $builder->where($model->qualifyColumn($model::$tenantIdColumn), tenant()->getTenantKey());
     }
 
     public function extend(Builder $builder)

--- a/vendor/stancl/tenancy/src/Database/Concerns/BelongsToTenant.php
+++ b/vendor/stancl/tenancy/src/Database/Concerns/BelongsToTenant.php
@@ -16,7 +16,7 @@ trait BelongsToTenant
 
     public function tenant()
     {
-        return $this->belongsTo(config('tenancy.tenant_model'), BelongsToTenant::$tenantIdColumn);
+        return $this->belongsTo(config('tenancy.tenant_model'), static::$tenantIdColumn);
     }
 
     public static function bootBelongsToTenant()
@@ -24,7 +24,7 @@ public static function bootBelongsToTenant()
         static::addGlobalScope(new TenantScope);
 
         static::creating(function ($model) {
-            if (! $model->getAttribute(BelongsToTenant::$tenantIdColumn) && ! $model->relationLoaded('tenant')) {
+            if (! $model->getAttribute(static::$tenantIdColumn) && ! $model->relationLoaded('tenant')) {
                 if (tenancy()->initialized) {
-                    $model->setAttribute(BelongsToTenant::$tenantIdColumn, tenant()->getTenantKey());
+                    $model->setAttribute(static::$tenantIdColumn, tenant()->getTenantKey());
                     $model->setRelation('tenant', tenant());
```

**適用方法:**
```bash
# cweagans/composer-patchesを使用（既にインストール済み）
# composer.jsonに追加
"extra": {
    "patches": {
        "stancl/tenancy": {
            "PHP 8.4 compatibility fix": "patches/fix_tenancy_php84.patch"
        }
    }
}

# パッチを適用
./vendor/bin/sail composer install
```

#### 解決策3: 警告の一時的な抑制（推奨しない）

**緊急対応のみ:**
```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'deprecations'],
        'ignore_exceptions' => false,
    ],
    
    'deprecations' => [
        'driver' => 'single',
        'path' => storage_path('logs/deprecations.log'),
        'level' => 'warning',
    ],
],
```

**理由:** 根本的な解決にならず、パフォーマンス問題は残る

---

## GitHub Issue / Pull Request の確認

### 調査すべきリポジトリ

**stancl/tenancy:** https://github.com/stancl/tenancy

**確認事項:**
1. PHP 8.4関連のIssueが報告されているか
2. 修正のPull Requestがマージされているか
3. 最新版（v3.10以降）でPHP 8.4対応済みか

### 予想される状況

**パターン1: 既に修正済み**
- v3.10以降でPHP 8.4対応完了
- 対策: `composer update stancl/tenancy`

**パターン2: 修正中**
- Issue/PRは存在するが、まだリリースされていない
- 対策: パッチを適用 or developブランチを使用

**パターン3: 未報告**
- まだ報告されていない
- 対策: Issueを報告 + パッチを適用

---

## 実施計画

### Step 1: GitHubで状況確認（5分）

```bash
# GitHubで検索
# - "stancl/tenancy PHP 8.4"
# - "stancl/tenancy deprecated trait property"
# - Issues / Pull Requests
```

### Step 2: 最新版の確認とアップデート（10分）

```bash
# Packagistで最新版を確認
https://packagist.org/packages/stancl/tenancy

# アップデート試行
./vendor/bin/sail composer update stancl/tenancy --with-all-dependencies

# 変更内容を確認
git diff composer.lock
```

### Step 3: テスト実行（5分）

```bash
# 全テストを実行
./vendor/bin/sail test

# FileInspectorを手動でテスト
# - ドロワー開閉
# - タブ切り替え
# - 検索機能
```

### Step 4: パフォーマンスの再測定（10分）

```bash
# ログを確認（警告が消えているか）
./vendor/bin/sail logs -f | grep -i deprecated

# パフォーマンスログを確認
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep "FileInspector Performance"
```

**期待される結果:**
- ドロワー開閉: 2000ms → 500ms以下
- タブ切り替え: 6761ms → 100ms以下
- 検索: 即座に応答

---

## 影響範囲の評価

### 修正によるリスク

**リスク: 低**
- stancl/tenancyは安定したパッケージ
- マイナーバージョンアップ（v3.9.1 → v3.x）は互換性が保たれる
- テストが全て成功すれば問題なし

### 修正による効果

**効果: 極めて大きい**
1. **パフォーマンス改善:**
   - ドロワー開閉: 75%高速化（2000ms → 500ms）
   - タブ切り替え: 98%高速化（6761ms → 100ms）
   - 検索: 即座に応答

2. **ログの削減:**
   - 大量の警告ログが消える
   - ディスク使用量の削減
   - ログ解析の容易化

3. **将来の安定性:**
   - PHP 8.4の正式対応
   - 非推奨警告の解消

---

## WBS 5.2への影響

### WBS 5.2.1の方針変更

**当初の計画:**
- 検索機能のフロントエンド化（Livewire削減）

**新しい方針:**
- **PHP 8.4非推奨警告の修正を最優先**
- これだけで大幅なパフォーマンス改善が期待できる
- Livewireのレンダリング自体は問題ない可能性

### 修正後の再評価

**修正後に再度実測し、以下を確認:**
1. ドロワー開閉時間が目標値（300ms）に近づいているか
2. タブ切り替えが高速化（<100ms）されているか
3. 検索が即座に応答するか

**結果に応じて:**
- ✅ 目標達成 → WBS 5.2完了、追加の最適化は不要
- ⚠️ まだ遅い → 当初の計画（フロントエンド化）を実施

---

## 次のアクション

### 🔴 即実施（WBS 5.2.1改）

**タスク:** PHP 8.4非推奨警告の修正

**手順:**
1. GitHubでstancl/tenancyの状況確認（5分）
2. 最新版へのアップデート試行（10分）
3. テスト実行（5分）
4. パフォーマンス再測定（10分）
5. 効果のレポート作成（10分）

**合計:** 40分

**期待される効果:**
- パフォーマンス問題の根本的な解決
- 追加の最適化が不要になる可能性

---

## 参考情報

### PHP 8.4の変更点

**RFC:** https://wiki.php.net/rfc/deprecations_php_8_4

**Accessing static properties via trait name:**
```
Accessing static properties via trait name is deprecated since PHP 8.4.0.
Instead, access them via a class that uses the trait.
```

### stancl/tenancy

**GitHub:** https://github.com/stancl/tenancy  
**Documentation:** https://tenancyforlaravel.com/  
**Packagist:** https://packagist.org/packages/stancl/tenancy

---

**レポート作成日:** 2025年12月31日  
**調査者:** 開発チーム  
**優先度:** 🔴 最高（パフォーマンス問題の根本原因の可能性）  
**次のステップ:** GitHubで状況確認 → アップデート試行

