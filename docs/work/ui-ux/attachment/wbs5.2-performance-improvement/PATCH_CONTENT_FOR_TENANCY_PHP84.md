# PHP 8.4対応パッチ for stancl/tenancy

このパッチは、PHP 8.4で非推奨となったトレート静的プロパティへの直接アクセスを修正します。

## パッチ内容

以下の内容を `patches/fix_tenancy_php84_trait_property.patch` として保存してください：

```patch
--- a/src/Database/TenantScope.php
+++ b/src/Database/TenantScope.php
@@ -17,7 +17,7 @@ class TenantScope implements Scope
             return;
         }
 
-        $builder->where($model->qualifyColumn(BelongsToTenant::$tenantIdColumn), tenant()->getTenantKey());
+        $builder->where($model->qualifyColumn($model::$tenantIdColumn), tenant()->getTenantKey());
     }
 
     public function extend(Builder $builder)
--- a/src/Database/Concerns/BelongsToTenant.php
+++ b/src/Database/Concerns/BelongsToTenant.php
@@ -16,7 +16,7 @@ trait BelongsToTenant
 
     public function tenant()
     {
-        return $this->belongsTo(config('tenancy.tenant_model'), BelongsToTenant::$tenantIdColumn);
+        return $this->belongsTo(config('tenancy.tenant_model'), static::$tenantIdColumn);
     }
 
     public static function bootBelongsToTenant()
@@ -24,9 +24,9 @@ trait BelongsToTenant
         static::addGlobalScope(new TenantScope);
 
         static::creating(function ($model) {
-            if (! $model->getAttribute(BelongsToTenant::$tenantIdColumn) && ! $model->relationLoaded('tenant')) {
+            if (! $model->getAttribute(static::$tenantIdColumn) && ! $model->relationLoaded('tenant')) {
                 if (tenancy()->initialized) {
-                    $model->setAttribute(BelongsToTenant::$tenantIdColumn, tenant()->getTenantKey());
+                    $model->setAttribute(static::$tenantIdColumn, tenant()->getTenantKey());
                     $model->setRelation('tenant', tenant());
                 }
             }
```

## パッチの適用方法

### 方法1: composer-patchesを使用（推奨）

1. パッチファイルを作成：
```bash
# 上記のパッチ内容をコピーして保存
cat > patches/fix_tenancy_php84_trait_property.patch << 'EOF'
[パッチ内容をここに貼り付け]
EOF
```

2. composer.jsonに設定を追加（既に追加済み）：
```json
{
  "extra": {
    "patches": {
      "stancl/tenancy": {
        "PHP 8.4 compatibility - Fix deprecated trait static property access": "patches/fix_tenancy_php84_trait_property.patch"
      }
    }
  }
}
```

3. パッチを適用：
```bash
./vendor/bin/sail composer install
```

### 方法2: 手動適用（composer-patchesが動作しない場合）

```bash
# TenantScope.php の修正
sed -i '' 's/BelongsToTenant::\$tenantIdColumn/$model::\$tenantIdColumn/' \
  vendor/stancl/tenancy/src/Database/TenantScope.php

# BelongsToTenant.php の修正（3箇所）
sed -i '' 's/BelongsToTenant::\$tenantIdColumn/static::\$tenantIdColumn/g' \
  vendor/stancl/tenancy/src/Database/Concerns/BelongsToTenant.php
```

## 修正内容の詳細

### 修正箇所1: TenantScope.php (Line 20)

**変更理由:** PHP 8.4ではトレート名で静的プロパティにアクセスすることが非推奨

**Before:**
```php
$builder->where($model->qualifyColumn(BelongsToTenant::$tenantIdColumn), tenant()->getTenantKey());
```

**After:**
```php
$builder->where($model->qualifyColumn($model::$tenantIdColumn), tenant()->getTenantKey());
```

### 修正箇所2-4: BelongsToTenant.php (Lines 19, 27, 29)

**変更理由:** トレート内では `static::` を使用すべき

**Before:**
```php
BelongsToTenant::$tenantIdColumn
```

**After:**
```php
static::$tenantIdColumn
```

## 確認方法

### 1. ブラウザコンソールで警告が消えていることを確認
```
F12 → Console タブ
警告: "Accessing static trait property ... is deprecated" が表示されないこと
```

### 2. テストを実行
```bash
./vendor/bin/sail test tests/Feature/Livewire/AttachedFile/FileInspectorTest.php
```

### 3. パフォーマンスの改善を確認
- ドロワー開閉速度
- タブ切り替え速度
- 検索応答速度

## 注意事項

- このパッチは stancl/tenancy v3.9.1 向けです
- composer update 時にパッチが自動適用されます
- 将来のバージョンでは公式に修正される可能性があります

## 参考情報

- **PHP RFC:** https://wiki.php.net/rfc/deprecations_php_8_4
- **stancl/tenancy GitHub:** https://github.com/archtechx/tenancy
- **Issue報告:** このパッチ内容を元にIssueを報告することを推奨

---

**作成日:** 2025年12月31日  
**対象バージョン:** stancl/tenancy v3.9.1  
**PHP バージョン:** 8.4+

