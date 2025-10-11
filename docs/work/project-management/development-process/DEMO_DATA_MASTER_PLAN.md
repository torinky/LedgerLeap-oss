# デモ・統合テストデータ マスタープラン

**作成日:** 2025年10月4日  
**目的:** MCPツール・UI機能を包括的に検証・デモできるデータセットの設計  
**アプローチ:** MCPツールを軸に、必要なデータを逆算して設計

---

## 📋 全体方針と設計原則

### 基本原則

#### 1. 既存テストへの影響回避
- ✅ **独立したSeeder**: `DemoSeeder`として完全分離
- ✅ **独立した実行**: `php artisan db:seed --class=DemoSeeder`で個別実行
- ✅ **環境分離**: `APP_ENV=demo`で制御可能
- ✅ **命名規則**: デモデータは識別可能な名前（例: `[DEMO]`プレフィックス）
- ✅ **テストDB非干渉**: `RefreshDatabaseWithTenant`トレイトのテストに影響しない

#### 2. 機能網羅性の確保

##### カラムタイプの完全網羅
```
利用可能な10種類のInputTypeを全て使用:
1. TextType          - テキスト（短文）
2. TextareaType      - テキストエリア（長文）
3. NumberType        - 数値
4. DateType          - 日付
5. SelectType        - セレクトボックス
6. CheckboxType      - チェックボックス
7. PhoneNumberType   - 電話番号
8. FilesType         - ファイル（複数）
9. AutoNumberType    - 自動採番
10. (その他拡張型)   - 必要に応じて追加
```

##### ワークフロー状態の完全網羅
```
5つの全ステータスをカバー:
- NONE (ワークフロー非適用)
- DRAFT (作成中)
- PENDING_INSPECTION (点検待ち)
- PENDING_APPROVAL (承認待ち)
- APPROVED (承認済み)
```

##### 権限パターンの網羅
```
全ての権限レベルをカバー:
- READ (閲覧のみ)
- WRITE (書き込み)
- ADMIN (管理)
- 点検権限
- 承認権限
- フォルダ階層での権限継承
```

#### 3. 段階的拡張アプローチ
- **Phase 1**: MCPツール最小動作セット（優先度: 🔴 高）
- **Phase 2**: ユースケース網羅（優先度: 🟡 中）
- **Phase 3**: フル機能デモ（優先度: 🟢 低）

#### 4. 実務的データ
- 実際のビジネスシナリオに基づく
- 自然な日本語コンテンツ
- 日時データは現実的な分布

---

## 🎯 MCPツールマトリックス

### Phase 1: 最小動作セット

| # | MCPツール | 検証項目 | 必要データ | 優先度 |
|---|----------|---------|-----------|--------|
| 1 | **SearchLedgersTool** | 検索、フィルタ、ソート、添付ファイル | 台帳30件、タグ20個、添付10件 | 🔴 |
| 2 | **CreateLedgerTool** | 作成、権限チェック | 台帳定義8種、フォルダ10個 | 🔴 |
| 3 | **GetLedgerDefinesTool** | 定義取得、権限フィルタ | 台帳定義8種（全InputType網羅） | 🔴 |
| 4 | **GetPendingApprovalsTool** | タスク取得、翻訳 | 承認待ち10件、点検待ち8件 | 🔴 |
| 5 | **ExecuteApprovalTool** | 承認/差戻 | 承認待ち5件（差戻可能） | 🔴 |
| 6 | **GetWorkflowHistoryTool** | 履歴取得 | ワークフロー履歴20件 | 🟡 |
| 7 | **ClaimWorkflowTaskTool** | タスク割当 | 未割当タスク5件 | 🟡 |
| 8 | **GetActivityLogTool** | ログ取得、フィルタ | アクティビティ100件 | 🟡 |
| 9 | **GetLedgerStatsTool** | 期間別統計 | 過去3ヶ月の台帳分布 | 🟢 |
| 10 | **GetUserActivityStatsTool** | 活動統計 | ユーザー活動データ | 🟢 |
| 11 | **GetFolderStatsTool** | フォルダ統計 | フォルダ階層、台帳分布 | 🟢 |

---

## 📊 データ依存関係と最小セット

### Phase 1の最小データセット

```yaml
# 基盤データ
テナント: 1個
  name: "demo-tenant"
  
組織: 3個
  - 本社
  - 営業部
  - 技術部

ユーザー: 12名（ペルソナ別）
  管理者: 2名（全権限）
  実務担当者: 6名（営業3名、技術3名）
  点検者: 2名
  承認者: 2名

ロール: 5個
  - システム管理者
  - 一般ユーザー（営業）
  - 一般ユーザー（技術）
  - 点検者
  - 承認者

# フォルダ構造（10個）
フォルダ階層:
  / (ルート)
  ├── 営業部/
  │   ├── 日報/
  │   └── 商談記録/
  ├── 技術部/
  │   ├── 開発日報/
  │   └── 障害報告/
  └── 全社共通/
      ├── 申請書/
      ├── 報告書/
      └── 議事録/

# 台帳定義（8種類）- 全InputType網羅
台帳定義:
  1. 営業日報（ワークフロー有）
     - TextType, DateType, TextareaType, SelectType
  
  2. 開発日報（ワークフロー有）
     - TextType, DateType, NumberType, TextareaType
  
  3. 商談記録（ワークフローなし）
     - TextType, DateType, NumberType, PhoneNumberType
  
  4. 障害報告（ワークフロー有）
     - TextType, SelectType, DateType, TextareaType, CheckboxType
  
  5. 経費申請（ワークフロー有）
     - AutoNumberType, DateType, SelectType, NumberType, TextareaType
  
  6. 設備点検表（ワークフロー有）
     - TextType, DateType, SelectType, CheckboxType, TextareaType
  
  7. 議事録（ワークフローなし）
     - TextType, DateType, TextareaType, FilesType
  
  8. 週報（ワークフロー有）
     - DateType, TextareaType, SelectType

# 台帳データ（60件）- ステータス分散
台帳分布:
  DRAFT: 8件（13%）
  PENDING_INSPECTION: 12件（20%）
  PENDING_APPROVAL: 10件（17%）
  APPROVED: 25件（42%）
  NONE: 5件（8%）

定義別分布:
  営業日報: 10件（過去2週間）
  開発日報: 10件（過去2週間）
  商談記録: 8件（NONE、様々な日付）
  障害報告: 8件（様々なステータス）
  経費申請: 10件（様々なステータス）
  設備点検表: 6件（月次）
  議事録: 4件（NONE、過去1ヶ月）
  週報: 4件（過去1ヶ月）

# その他のデータ
タグ: 25個
  カテゴリ別: 営業×5, 技術×5, 全社×5, プロジェクト×5, その他×5

添付ファイル: 15件
  PDF: 5件
  Excel: 3件
  Word: 3件
  画像: 4件

ワークフロータスク: 22件
  未割当: 5件
  点検待ち: 7件
  承認待ち: 10件

アクティビティログ: 120件（自動生成）
  created: 60件
  updated: 30件
  file_attached: 15件
  status_changed: 15件
```

---

## 🔍 機能網羅性チェックリスト

### カラムタイプ網羅（Phase 1で必須）
- [x] TextType - 営業日報、商談記録等
- [x] TextareaType - 全台帳定義
- [x] NumberType - 開発日報（進捗率）、経費申請（金額）
- [x] DateType - 全台帳定義
- [x] SelectType - 営業日報（結果）、障害報告（重要度）
- [x] CheckboxType - 障害報告、設備点検表
- [x] PhoneNumberType - 商談記録（連絡先）
- [x] FilesType - 議事録（添付資料）
- [x] AutoNumberType - 経費申請（申請番号）
- [ ] その他拡張型 - Phase 2で追加検討

### ワークフロー状態網羅（Phase 1で必須）
- [x] NONE - 商談記録、議事録
- [x] DRAFT - 各定義2件ずつ
- [x] PENDING_INSPECTION - 各定義2件ずつ
- [x] PENDING_APPROVAL - 各定義2件ずつ
- [x] APPROVED - 各定義4件ずつ

### 権限パターン網羅（Phase 1で必須）
- [x] 閲覧のみ - 監査ロール
- [x] 書き込み - 一般ユーザー
- [x] 管理権限 - 管理者
- [x] 点検権限 - 点検者ロール
- [x] 承認権限 - 承認者ロール
- [x] 複数フォルダへの権限 - クロス部門ロール
- [x] フォルダ階層での継承 - 親フォルダの権限

### 検索・フィルタパターン網羅（Phase 1で必須）
- [x] キーワード検索 - 台帳content
- [x] タグ検索 - 複数タグ
- [x] 期間検索 - created_at範囲
- [x] ステータスフィルタ - 全ステータス
- [x] 作成者フィルタ - 複数ユーザー
- [x] フォルダフィルタ - 階層構造
- [x] 添付ファイル検索 - content_attached

---

## 📝 ドキュメント構成

```
docs/work/
├── DEMO_DATA_MASTER_PLAN.md              # 本ファイル（全体計画）
├── demo_plans/
│   ├── Phase1_Overview.md                 # Phase 1概要
│   ├── 01_SearchLedgersTool_Plan.md      # ツール1詳細計画 ⭐ 次に作成
│   ├── 02_CreateLedgerTool_Plan.md       # ツール2詳細計画
│   ├── 03_GetLedgerDefinesTool_Plan.md   # ツール3詳細計画
│   ├── 04_GetPendingApprovalsTool_Plan.md
│   ├── 05_ExecuteApprovalTool_Plan.md
│   ├── 06_GetWorkflowHistoryTool_Plan.md
│   ├── 07_ClaimWorkflowTaskTool_Plan.md
│   ├── 08_GetActivityLogTool_Plan.md
│   ├── 09_GetLedgerStatsTool_Plan.md
│   ├── 10_GetUserActivityStatsTool_Plan.md
│   └── 11_GetFolderStatsTool_Plan.md
└── DEMO_SEEDER_IMPLEMENTATION.md         # Seeder実装ガイド
```

---

## 🚀 実装ロードマップ

### Phase 1: MCPツール最小動作セット（優先度: 🔴）
**期間:** 5-7日  
**目標:** 全11MCPツールが動作確認できる最小データセット

#### Week 1
- **Day 1**: 基盤データ（テナント、組織、ユーザー、ロール、フォルダ）
- **Day 2-3**: 台帳定義8種（全InputType網羅）
- **Day 4-5**: 台帳データ60件（ステータス分散）
- **Day 6**: ワークフロータスク、タグ、添付ファイル
- **Day 7**: 動作確認、調整

### Phase 2: ユースケース網羅（優先度: 🟡）
**期間:** 3-4日  
**目標:** 主要ペルソナのユースケースをカバー

### Phase 3: フル機能デモ（優先度: 🟢）
**期間:** 2-3日  
**目標:** 複雑なシナリオ、エッジケース

---

## 📋 詳細計画テンプレート

各MCPツールごとに以下の形式で詳細計画を作成：

### MCPツール詳細計画: {ツール名}

#### 1. 目的
このツールで何を確認・デモするか

#### 2. 動作確認シナリオ（3-5個）
- **シナリオ1**: 基本動作
- **シナリオ2**: 権限制御
- **シナリオ3**: フィルタリング
- **シナリオ4**: エラーハンドリング
- **シナリオ5**: エッジケース

#### 3. 必要なデータ詳細
```yaml
ユーザー:
  - ペルソナ: 実務担当者
  - 人数: 3名
  - 権限: 営業部フォルダ書き込み

台帳:
  - 種類: 営業日報
  - 件数: 10件
  - ステータス:
    - DRAFT: 2件
    - PENDING_INSPECTION: 2件
    - APPROVED: 6件
  - 期間: 過去2週間
  - タグ: 5個（重要、顧客A、新規、フォローアップ、完了）
  - 添付: 3件（PDF、Excel、画像）
```

#### 4. 期待される動作
##### format=summary
```json
{
  "__display_fields__": {
    "期間": "過去2週間",
    "総件数": "10件"
  },
  "__summary__": "過去2週間に10件の営業日報が...",
  "ledgers": [...]
}
```

##### format=raw
```json
{
  "ledgers": [...],
  "total": 10,
  "meta": {...}
}
```

#### 5. データ作成コード例
```php
// DemoSeeder内での実装例
$salesDailyReport = LedgerDefine::create([...]);
$ledgers = collect();
for ($i = 0; $i < 10; $i++) {
    $ledgers->push(Ledger::factory()->create([...]));
}
```

#### 6. テスト確認コマンド
```bash
# MCPツールのテスト
php artisan mcp:test SearchLedgersTool --demo

# 手動確認
curl -X POST http://localhost/mcp \
  -H "Authorization: Bearer {token}" \
  -d '{"tool": "SearchLedgersTool", "format": "summary"}'
```

---

## ✅ 承認チェックリスト

### 全体方針
- [ ] 既存テストへの影響回避策は適切か
- [ ] 機能網羅性の基準は明確か
- [ ] 段階的拡張のアプローチは妥当か

### データ設計
- [ ] 最小データセット（60件）は適切か
- [ ] 全InputTypeがカバーされているか
- [ ] 全ワークフロー状態がカバーされているか
- [ ] 全権限パターンがカバーされているか

### 実装計画
- [ ] 実装期間（5-7日）は現実的か
- [ ] ドキュメント構成は適切か
- [ ] 次のアクション（SearchLedgersTool詳細計画）は明確か

---

## 🎯 次のアクション

1. ✅ 本全体計画の承認
2. 📝 **SearchLedgersTool詳細計画の作成**（最優先）
3. 🔄 詳細計画のレビュー・承認
4. 💻 DemoSeeder実装開始

---

**作成者**: AI Assistant  
**レビュー**: User  
**ステータス**: 承認待ち
