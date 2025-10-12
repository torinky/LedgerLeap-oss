# 権限システム修正完了レポート

**作成日:** 2025-10-11  
**問題:** Super Adminユーザーがフォルダ配下の台帳を閲覧できない  
**原因:** Spatieの権限システムが初期化されていなかった  
**解決:** RolesAndPermissionsSeederをDemoCompleteSeederに統合

---

## 🐛 問題の詳細

### 症状
- Super Adminユーザーでログイン後、フォルダに管理アイコンは表示される
- しかし、配下の台帳が閲覧できない
- フォルダ権限（RoleFolderPermission）は正しく設定されていた

### 根本原因
DemoCompleteSeederが以下の順序で実行されていた：

```
1. DemoMinimalSeeder
   └─ Super Adminロール作成（RoleFolderPermission設定）
2. DemoPhase1ExtensionSeeder
   └─ 追加データ作成
```

しかし、**Spatieの権限システム（Permission）が初期化されていなかった**ため、
以下の権限が付与されていませんでした：

- `view_ledgers` - 台帳の閲覧
- `create_ledgers` - 台帳の作成
- `update_ledgers` - 台帳の更新
- `delete_ledgers` - 台帳の削除
- `view_folders` - フォルダの閲覧
- 他46個の権限

---

## ✅ 解決方法

### 修正内容

#### 1. DemoCompleteSeederの修正

**変更前:**
```php
public function run(): void
{
    // Phase 1: 基盤データ
    $this->call(DemoMinimalSeeder::class);
    
    // Phase 2: 拡張データ
    $this->call(DemoPhase1ExtensionSeeder::class);
}
```

**変更後:**
```php
public function run(): void
{
    // Phase 0: 権限システムの初期化（追加！）
    $this->call(RolesAndPermissionsSeeder::class);
    
    // Phase 1: 基盤データ
    $this->call(DemoMinimalSeeder::class);
    
    // Phase 2: 拡張データ
    $this->call(DemoPhase1ExtensionSeeder::class);
}
```

#### 2. DatabaseSeederの修正

デモモード時にも権限システムを初期化するように修正：

```php
if ($isDemoMode) {
    // 権限システムを先に初期化（追加！）
    $this->call(RolesAndPermissionsSeeder::class);
    
    // その後デモデータを作成
    $this->call(DemoCompleteSeeder::class);
    return;
}
```

---

## 🔍 権限システムの仕組み

LedgerLeapでは2種類の権限システムが併用されています：

### 1. Spatie Permission（Laravel標準）

**用途:** アプリケーション全体の機能権限

**管理対象:**
- ユーザー管理（view_users, create_users, etc.）
- 組織管理（view_organizations, etc.）
- 台帳操作（view_ledgers, create_ledgers, etc.）
- フォルダ操作（view_folders, create_folders, etc.）

**設定:** `RolesAndPermissionsSeeder`で定義

**確認方法:**
```php
$user->can('view_ledgers') // true/false
```

### 2. RoleFolderPermission（独自実装）

**用途:** フォルダ単位のアクセス制御

**権限レベル:**
- `ADMIN` - 全ての操作
- `WRITE` - 作成・読取・更新
- `READ` - 読取のみ

**設定:** `DemoMinimalSeeder`, `DemoPhase1ExtensionSeeder`で設定

**確認方法:**
```php
RoleFolderPermission::where('role_id', $role->id)
    ->where('folder_id', $folder->id)
    ->first()
```

### 両方が必要な理由

**台帳を閲覧するには:**
1. ✅ Spatie Permission: `view_ledgers`権限が必要
2. ✅ RoleFolderPermission: フォルダへのREAD以上の権限が必要

**どちらか一方でも欠けていると、台帳が閲覧できません！**

---

## 📊 修正前後の比較

### 修正前

| チェック項目 | 結果 |
|------------|------|
| Super Adminロールが存在する | ✅ |
| ルートフォルダへのADMIN権限 | ✅ |
| view_ledgers権限 | ❌ **なし** |
| view_folders権限 | ❌ **なし** |
| 台帳閲覧 | ❌ **できない** |

### 修正後

| チェック項目 | 結果 |
|------------|------|
| Super Adminロールが存在する | ✅ |
| ルートフォルダへのADMIN権限 | ✅ |
| view_ledgers権限 | ✅ **あり** |
| view_folders権限 | ✅ **あり** |
| 台帳閲覧 | ✅ **できる** |

---

## 🚀 実行方法

### クリーンインストール

```bash
# データベースをリセット＋デモデータ投入
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail artisan db:seed --class=DemoCompleteSeeder
```

**実行順序:**
1. マイグレーション
2. RolesAndPermissionsSeeder（権限システム初期化）
3. DemoMinimalSeeder（基盤データ）
4. DemoPhase1ExtensionSeeder（拡張データ）

### 環境変数での実行

```bash
# .env
SEEDER_MODE=demo

# 実行
./vendor/bin/sail artisan migrate:fresh --seed
```

---

## ✅ 確認手順

### 1. Super Adminでログイン

```
メールアドレス: superadmin@example.com
パスワード:     demo1234
```

### 2. 権限の確認（Tinker）

```bash
./vendor/bin/sail artisan tinker
```

```php
$user = User::where('email', 'superadmin@example.com')->first();

// Spatie権限の確認
$user->can('view_ledgers');    // true
$user->can('view_folders');    // true
$user->can('create_ledgers');  // true

// フォルダ権限の確認
$rootFolder = Folder::whereNull('parent_id')->first();
$perm = RoleFolderPermission::where('role_id', $user->roles->first()->id)
    ->where('folder_id', $rootFolder->id)
    ->first();
echo $perm->permission->value; // "admin"
```

### 3. UIでの確認

#### ✅ 期待される動作
- フォルダ一覧が表示される
- フォルダをクリックすると台帳一覧が表示される
- 台帳をクリックすると詳細が表示される
- 新規作成・編集・削除が全て可能

#### ❌ 修正前の動作
- フォルダ一覧は表示される
- フォルダをクリックしても台帳が表示されない
- 「権限がありません」エラー

---

## 📝 今後の注意事項

### Seeder作成時の注意

新しいSeederを作成する際は、必ず以下の順序を守ること：

```php
class NewSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 権限システムの初期化（最優先！）
        $this->call(RolesAndPermissionsSeeder::class);
        
        // 2. データ作成
        // ...
    }
}
```

### テスト時の確認事項

新しいロールを追加した場合、必ず以下を確認：

1. **Spatieの権限が付与されているか**
   ```php
   $role->hasPermissionTo('view_ledgers');
   ```

2. **フォルダ権限が設定されているか**
   ```php
   RoleFolderPermission::where('role_id', $role->id)->count() > 0;
   ```

3. **ユーザーに正しく権限が付与されているか**
   ```php
   $user->can('view_ledgers');
   ```

---

## 🎯 まとめ

### 問題の本質
- フォルダベースの権限（RoleFolderPermission）だけでは不十分
- Spatieの機能権限（Permission）も同時に必要
- 両方が揃って初めて台帳の閲覧が可能になる

### 解決のポイント
- `RolesAndPermissionsSeeder`を最初に実行すること
- これにより、全てのロールに適切な権限が付与される
- フォルダ権限と機能権限が両方揃い、正常に動作する

### 影響範囲
- ✅ Super Adminユーザー: 全権限で動作
- ✅ 一般ユーザー: 部門別の権限で動作
- ✅ 全てのデモユーザー: 適切な権限で動作

---

**作成者:** AI Assistant  
**最終更新:** 2025-10-11  
**ステータス:** ✅ 修正完了・動作確認済み
