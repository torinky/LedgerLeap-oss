# テストカバレッジ改善アクションプラン

作成日: 2026-02-15  
更新日: 2026-02-15  
作成者: GitHub Copilot (カバレッジレポート分析結果より)

## 📊 現状サマリー

**カバレッジ計測日**: 2026-02-15 03:21:34 UTC  
**測定環境**: PHP 8.4.17 + PHPUnit 11.5.33 + PCOV

| 指標 | 現状値 | 目標値 (3ヶ月後) | 状態 |
|:---|---:|---:|:---:|
| 行カバレッジ | 49.25% | 70% | ❌ |
| メソッドカバレッジ | 34.77% | 60% | ❌ |
| クラスカバレッジ | 14.41% | 50% | ❌ |

### 重大な発見事項

1. **致命的リスク領域** (データ破損・セキュリティ)
   - `app/Casts/AsColumnArrayJson.php` - クラスカバレッジ 0% ⚠️
   - `app/Services/PermissionService.php` - 34.54% (権限管理の核心)
   - `app/Rules/` - クラスカバレッジ 0% (4クラス全て未テスト)

2. **完全未カバー領域** (機能欠落リスク)
   - `app/Exports/` - 0% (38行)
   - `app/Mail/` - 0% (151行)
   - `app/Notifications/` - 0% (147行)
   - `app/Modules/ImageUpload/` - 0% (9行)
   - `app/QueryFilters/` - 0% (9行、Mroonga全文検索含む)

3. **優秀な領域** (維持・強化対象)
   - `app/Services/NumberingService.php` - 100%
   - `app/Services/Scoring/` - 100%
   - `app/Services/LedgerService.php` - 97.39%
   - `app/Services/AnalyticsService.php` - 94.59%
   - `app/Services/AutoLinkService.php` - 93.75%
   - `app/Facades/` - 100%

## 🎯 アクションプラン（4フェーズ構成）

戦略ドキュメント「[2026-02-11_test-quality-and-duplication-strategy.md](./2026-02-11_test-quality-and-duplication-strategy.md)」に準拠した優先順位で実施します。

---

## 📍 Phase 1: 致命的リスクの即時解消（Priority: 🔴 CRITICAL）

**期間**: Week 1-2 (2026-02-15 ～ 2026-02-28)  
**目標**: データ破損防止・セキュリティ強化

### 1.1 Casts のテスト整備 ⚠️ **最優先**

**対象ファイル**:
- `app/Casts/AsColumnArrayJson.php` (二重エンコード厳禁ルール)
- `app/Casts/AsColumnDefinesArrayJson.php`
- `app/Casts/AsEncrypted.php`

**実施内容**:
```bash
# 1. 既存テストの確認
find tests -name "*Cast*" -o -name "*AsColumnArrayJson*"

# 2. テストが存在しない場合、新規作成
./vendor/bin/sail artisan make:test Casts/AsColumnArrayJsonTest --unit

# 3. テストケース実装後、カバレッジ確認
./vendor/bin/sail composer test:coverage -- --filter=Casts

# 4. Mutation Testing で品質検証
./vendor/bin/sail composer test:mutation -- \
  --filter=app/Casts/AsColumnArrayJson.php \
  --test-framework-options="--filter=AsColumnArrayJson" \
  --map-source-class-to-test
```

**必須テストケース**:
- ✅ 正常なJSON配列のシリアライズ/デシリアライズ
- ✅ 既にJSON文字列化されたデータ（二重エンコード）の検知
- ✅ `null` 値の扱い
- ✅ 空配列 `[]` の扱い
- ✅ 不正なJSONの扱い
- ✅ ネストされた配列構造の保持

**成功基準**:
- 行カバレッジ: 79.07% → **100%**
- クラスカバレッジ: 0% → **100%** (3/3クラス)
- Mutation Score Indicator (MSI): **80%以上**

---

### 1.2 Rules のテスト追加・Mutation Testing

**対象ファイル**:
- `app/Rules/UniqueAutoNumber.php` (採番の一意性)
- `app/Rules/UniqueColumnValue.php` (カラム値の一意性)
- `app/Rules/ValidAutoLinkPattern.php` (自動リンクパターン検証)
- `app/Rules/RequiredCheckbox.php` (必須チェックボックス)

**実施内容**:
```bash
# 1. テストファイル作成（存在しない場合）
./vendor/bin/sail artisan make:test Rules/UniqueAutoNumberTest --unit
./vendor/bin/sail artisan make:test Rules/UniqueColumnValueTest --unit
./vendor/bin/sail artisan make:test Rules/ValidAutoLinkPatternTest --unit
./vendor/bin/sail artisan make:test Rules/RequiredCheckboxTest --unit

# 2. カバレッジ確認
./vendor/bin/sail composer test:coverage -- --filter=Rules

# 3. Mutation Testing (各ファイル個別実行)
./vendor/bin/sail composer test:mutation -- \
  --filter=app/Rules/UniqueAutoNumber.php \
  --test-framework-options="--filter=UniqueAutoNumber" \
  --map-source-class-to-test
```

**成功基準**:
- 行カバレッジ: 83.64% → **95%以上**
- クラスカバレッジ: 0% → **100%** (4/4クラス)
- MSI: **85%以上** (バリデーションロジックは高精度が必須)

---

### 1.3 PermissionService のテスト強化

**対象ファイル**: `app/Services/PermissionService.php`

**現状**: 34.54% (86/249行)  
**問題点**: 権限管理の核心ロジックがほぼ未テスト

**実施内容**:
```bash
# 1. 既存テストの確認と拡充
./vendor/bin/sail composer test -- --filter=PermissionService

# 2. 未カバー部分の特定
./vendor/bin/sail composer test:coverage -- --filter=Services/PermissionService

# 3. テストケース追加後、Mutation Testing
./vendor/bin/sail composer test:mutation -- \
  --filter=app/Services/PermissionService.php \
  --test-framework-options="--filter=Permission" \
  --map-source-class-to-test
```

**必須テストケース**:
- ✅ フォルダベース権限の階層検証
- ✅ ロール・組織・ユーザー間の権限継承
- ✅ キャッシュ無効化ロジック (`flushAllUserPermissionsCache`)
- ✅ テナント分離の確認
- ✅ 権限チェックのエッジケース（親フォルダなし、循環参照等）

**成功基準**:
- 行カバレッジ: 34.54% → **80%以上**
- メソッドカバレッジ: 12.50% → **70%以上**
- MSI: **75%以上**

---

### 1.4 NotificationService のテスト追加

**対象ファイル**: `app/Services/NotificationService.php`

**現状**: 16.38% (29/177行)  
**問題点**: 通知機能が完全に未検証

**実施内容**:
```bash
# 1. テストケース作成
./vendor/bin/sail artisan make:test Services/NotificationServiceTest --unit

# 2. 統合テストも追加（メール送信・データベース通知）
./vendor/bin/sail artisan make:test Feature/Notifications/NotificationFlowTest

# 3. カバレッジ確認
./vendor/bin/sail composer test:coverage -- --filter=NotificationService
```

**必須テストケース**:
- ✅ 通知タイプ別の配信ロジック
- ✅ ユーザー・ロールへの通知送信
- ✅ メール通知とデータベース通知の同期
- ✅ 通知設定の尊重（OFF設定時は送信しない）
- ✅ ワークフローサマリー通知の集約ロジック

**成功基準**:
- 行カバレッジ: 16.38% → **70%以上**
- メソッドカバレッジ: 25.00% → **60%以上**

---

## 📍 Phase 2: クラスカバレッジの底上げ（Priority: 🟡 HIGH）

**期間**: Week 3-5 (2026-03-01 ～ 2026-03-21)  
**目標**: 未テストクラスへの基本テスト追加

### 2.1 Services の未カバークラス (22/30クラス)

**戦略**: 各サービスに対して最低限の「スモークテスト」を追加

**対象クラス** (優先度順):
1. `EmbeddingService` (55.22% → 80%)
2. `JpDatetimeService` (57.14% → 80%)
3. `RagSearchService` (未確認 → 70%)
4. `WorkflowService` (未確認 → 75%)
5. その他の Services

**実施内容**:
```bash
# 各サービスごとにテストファイル作成
for service in EmbeddingService JpDatetimeService RagSearchService WorkflowService; do
  ./vendor/bin/sail artisan make:test "Services/${service}Test" --unit
done

# 全体カバレッジ再計測
./vendor/bin/sail composer test:coverage
```

**成功基準**:
- Services クラスカバレッジ: 26.67% → **70%以上** (21/30クラス)

---

### 2.2 Models の未カバークラス (29/34クラス)

**問題点**: リレーション、スコープ、Observer との連携が未検証

**対象モデル** (優先度高):
- `Ledger` (複雑な `scopeSearch`)
- `LedgerDefine` (カラム定義の動的生成)
- `Folder` (階層構造、権限チェック)
- `Organization` (階層構造)
- `WorkflowTask` (ステート管理)
- `Tag`, `RoleTag`, `UserOrganization` 等の中間テーブル

**実施内容**:
```bash
# 既存のモデルテストを確認
find tests -name "*ModelTest.php" -o -name "*Test.php" | grep -i model

# 不足しているモデルのテスト作成
./vendor/bin/sail artisan make:test Models/LedgerTest --unit
./vendor/bin/sail artisan make:test Models/FolderTest --unit

# カバレッジ確認
./vendor/bin/sail composer test:coverage -- --filter=Models
```

**必須テストケース**:
- ✅ リレーションの動作確認 (`hasMany`, `belongsTo` 等)
- ✅ スコープの正確性 (`scopeSearch`, `scopeVisibleTo` 等)
- ✅ 属性キャスト (`AsColumnArrayJson` 等)
- ✅ Observer との連携 (作成・更新・削除時の副作用)
- ✅ テナント分離

**成功基準**:
- Models クラスカバレッジ: 14.71% → **60%以上** (20/34クラス)

---

### 2.3 完全未カバー領域の基本テスト追加

#### 2.3.1 Mail クラス (0% → 60%)

**対象**:
- `app/Mail/TaskClaimedMail.php`
- `app/Mail/WorkflowActionMail.php`
- `app/Mail/WorkflowSummaryMail.php`

**実施内容**:
```bash
# メールテスト作成
./vendor/bin/sail artisan make:test Mail/WorkflowActionMailTest --unit

# Mailable のレンダリング確認
# tests/Unit/Mail/WorkflowActionMailTest.php 内で:
# - メールの件名・本文が正しくレンダリングされるか
# - 添付ファイルの有無
# - 送信先アドレスの正確性
```

**成功基準**: 行カバレッジ 60%以上

---

#### 2.3.2 Notifications クラス (0% → 60%)

**対象**:
- `app/Notifications/GenericNotification.php`
- `app/Notifications/WorkflowSummaryNotification.php`

**実施内容**:
```bash
./vendor/bin/sail artisan make:test Notifications/GenericNotificationTest --unit
```

**成功基準**: 行カバレッジ 60%以上

---

#### 2.3.3 Exports クラス (0% → 50%)

**対象**: `app/Exports/LedgerExport.php`

**実施内容**:
```bash
./vendor/bin/sail artisan make:test Exports/LedgerExportTest --unit

# Excel エクスポートの統合テスト
./vendor/bin/sail artisan make:test Feature/Ledger/ExportFlowTest
```

**必須テストケース**:
- ✅ エクスポートデータの正確性
- ✅ カラム定義に応じた動的な列生成
- ✅ 権限フィルタの適用

**成功基準**: 行カバレッジ 50%以上

---

#### 2.3.4 QueryFilters (0% → 70%)

**対象**: `app/QueryFilters/MroongaFullTextFilter.php`

**重要性**: ⚠️ **高** - Mroonga全文検索の正確性はシステムの要

**実施内容**:
```bash
./vendor/bin/sail artisan make:test QueryFilters/MroongaFullTextFilterTest --unit
```

**必須テストケース**:
- ✅ 単独インデックスに対する `MATCH() AGAINST()` の生成
- ✅ 複数カラムの `OR` 結合
- ✅ 複合インデックスが使用されないことの確認（制約違反検知）
- ✅ 日本語キーワードの扱い
- ✅ 空文字列・null の扱い

**成功基準**: 行カバレッジ 70%以上、MSI 80%以上

---

## 📍 Phase 3: 統合テストとエンドツーエンド検証（Priority: 🟢 MEDIUM）

**期間**: Week 6-8 (2026-03-22 ～ 2026-04-11)  
**目標**: 機能全体のフロー検証

### 3.1 Livewire コンポーネントの主要フロー

**現状**: 51.51% (行) / 6.25% (クラス)  
**戦略ドキュメント判定**: 「対象外」だが、結合テストとしての価値を認める

**対象コンポーネント** (主要画面のみ):
- `app/Livewire/Ledger/Create.php`
- `app/Livewire/Ledger/Edit.php`
- `app/Livewire/Ledger/Search.php`
- `app/Livewire/Workflow/PendingList.php`
- `app/Livewire/Workflow/WorkflowAssigneeSelect.php`

**実施内容**:
```bash
# Livewire テストは時間がかかるため、並列実行を活用
./vendor/bin/sail test --filter=Livewire --parallel --processes=4
```

**必須テストケース**:
- ✅ 台帳の作成・編集フロー（バリデーション含む）
- ✅ 全文検索の動作
- ✅ ワークフロー承認・差し戻しの動作
- ✅ ファイルアップロードの動作

**成功基準**:
- 行カバレッジ: 51.51% → **60%** (目標は控えめ)
- クラスカバレッジ: 6.25% → **30%** (主要8コンポーネント)

---

### 3.2 Filament 管理画面のスモークテスト

**現状**: 9.62% (290/3,016行)  
**問題点**: 管理画面が壊れても気づけない

**対象リソース** (最重要のみ):
- `app/Filament/Resources/TenantResource.php`
- `app/Filament/Resources/UserResource.php`
- `app/Filament/Resources/RoleResource.php`
- `app/Filament/Resources/FolderResource.php`
- `app/Filament/Resources/OrganizationResource.php`

**実施内容**:
```bash
# Filament のテストは Livewire テストと同様
./vendor/bin/sail artisan make:test Feature/Filament/TenantResourceTest
```

**必須テストケース**:
- ✅ リソース一覧画面のレンダリング
- ✅ 作成・編集フォームの表示
- ✅ バリデーションエラーの表示
- ✅ 保存・削除の動作

**成功基準**:
- 行カバレッジ: 9.62% → **40%** (基本動作の確認のみ)

---

### 3.3 エクスポート・インポートの往復テスト

**対象**:
- `app/Exports/LedgerExport.php` ↔ `app/Imports/LedgerImport.php`

**実施内容**:
```bash
./vendor/bin/sail artisan make:test Feature/Ledger/ExportImportRoundTripTest
```

**必須テストケース**:
- ✅ エクスポート → インポート でデータが完全に復元されるか
- ✅ 特殊文字・日本語の扱い
- ✅ 添付ファイルの扱い
- ✅ リレーションの保持

**成功基準**: テストが成功すること（カバレッジ目標なし）

---

## 📍 Phase 4: 保守性向上と継続的改善（Priority: 🔵 LOW）

**期間**: Week 9以降 (2026-04-12 ～)  
**目標**: CI/CDへの統合と日常的な品質維持

### 4.1 重複コードの解消

**現状**: PHPCPD で検出された重複（PoC報告書参照）

**実施内容**:
```bash
# 重複検出
./vendor/bin/sail composer test:duplication

# 検出された重複を解消（リファクタリング）
# - IndexManager vs RecordsTable
# - その他の重複箇所
```

**成功基準**: 重複率 5%未満

---

### 4.2 CI/CD パイプラインへの統合

**対象**: `.github/workflows/tests.yml`

**追加する検証項目**:
```yaml
- name: Run Coverage Report
  run: ./vendor/bin/sail composer test:coverage

- name: Check Code Duplication
  run: ./vendor/bin/sail composer test:duplication

- name: Run Mutation Testing (Critical Modules Only)
  run: |
    ./vendor/bin/sail composer test:mutation -- \
      --filter=app/Casts/AsColumnArrayJson.php \
      --test-framework-options="--filter=AsColumnArrayJson" \
      --map-source-class-to-test
```

**成功基準**: 全チェックが通ること

---

### 4.3 Console コマンドのテスト

**現状**: 26.46% (281/1,062行)  
**優先度**: 低（必要に応じて）

**対象コマンド** (重要度が高いもののみ):
- `app/Console/Commands/SendWorkflowSummaryNotification.php`
- `app/Console/Commands/RagChunkExistingLedgersCommand.php`

**実施内容**:
```bash
./vendor/bin/sail artisan make:test Console/Commands/SendWorkflowSummaryNotificationTest
```

**成功基準**: 行カバレッジ 50%以上（基本動作の確認のみ）

---

## 📊 マイルストーン（3ヶ月計画）

| マイルストーン | 期間 | 目標 | 完了条件 |
|:---|:---|:---|:---|
| **M1: 致命的リスク解消** | Week 1-2 | Casts, Rules, PermissionService | 全て MSI 80%以上 |
| **M2: クラスカバレッジ30%達成** | Week 3-4 | Services, Models の基本テスト | クラスカバレッジ 30% |
| **M3: クラスカバレッジ50%達成** | Week 5-6 | Mail, Notifications, Exports | クラスカバレッジ 50% |
| **M4: 統合テスト完了** | Week 7-8 | Livewire, Filament | 主要フロー検証完了 |
| **M5: CI/CD統合** | Week 9 | GitHub Actions 設定 | 自動テスト稼働 |

---

## 🔄 日常的な運用ルール

### 新機能開発時
1. 実装前にテストを書く（TDD推奨）
2. PR作成前に以下を実行:
   ```bash
   ./vendor/bin/sail composer test:coverage
   ./vendor/bin/sail composer test:duplication
   ```
3. カバレッジが下がっていないか確認

### PR レビュー時
- 新規コードのカバレッジが **80%以上** であることを確認
- 重複コードが追加されていないことを確認

### リファクタリング時
- リファクタリング前に Mutation Testing を実行
- MSI が維持されているか確認

---

## 📚 参考資料

- [テスト戦略ドキュメント (2026-02-11)](./2026-02-11_test-quality-and-duplication-strategy.md)
- [PoC レポート (2026-02-11)](./2026-02-11_poc_report.md)
- [カバレッジレポート (2026-02-15)](../../coverage/index.html)
- [Testing Best Practices](../../development/Testing-Best-Practices.md)

---

## 🚀 実行開始コマンド（Phase 1 即時開始）

```bash
# 1. 現状確認
open coverage/index.html

# 2. Casts のテスト作成（最優先）
./vendor/bin/sail artisan make:test Casts/AsColumnArrayJsonTest --unit

# 3. Rules のテスト作成
./vendor/bin/sail artisan make:test Rules/UniqueAutoNumberTest --unit
./vendor/bin/sail artisan make:test Rules/UniqueColumnValueTest --unit
./vendor/bin/sail artisan make:test Rules/ValidAutoLinkPatternTest --unit

# 4. 既存の PermissionService テストを確認
./vendor/bin/sail composer test -- --filter=PermissionService

# 5. 実装後、カバレッジ再計測
./vendor/bin/sail composer test:coverage
```

---

**次のアクション**: GitHub Issue の作成（進捗管理用）

