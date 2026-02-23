# 深い階層フォルダ検証用デモデータ拡充計画 (2026-02-23)

**作成日:** 2026年2月23日  
**ステータス:** 📝 計画段階  
**関連Issue:** #73 フォルダツリーのスクロール追従・深い階層対応  
**関連ドキュメント:**
- [Seeder統合と使い方ガイド](../../../development/database-seeding-guide.md)
- [デモ・統合テストデータ マスタープラン](../../../development/test-data-design.md)
- [フォルダツリー固定表示・深い階層対応 改善提案](./2026-02-23_folder-tree-sticky-improvement-plan.md)

---

## 1. 目的

現行のデモデータ（`DemoCompleteSeeder`）はフォルダ階層が最大 3 段（ルート → 部門 → 台帳種別）と浅い。  
医療・製造現場の実態に即した **5〜6 段の深い階層** を持つデモデータを整備し、以下を可能にする。

| 検証項目 | 説明 |
| :--- | :--- |
| **Sticky ツリーの動作確認** | Issue #73 Sprint 1 の CSS 変更がスクロール時に正しく追従するか |
| **アコーディオン UI の操作感** | Sprint 3 で実装するアコーディオン開閉がストレスなく動作するか |
| **N+1 クエリ最適化の効果測定** | Sprint 4 の `descendants` クエリ最適化によるパフォーマンス改善を実測する |
| **選択ノード自動スクロールの挙動** | Sprint 2 の `scrollIntoView` が深い階層でも正しく動作するか |

---

## 2. 設計方針（マスタープランとの整合）

[test-data-design.md](../../../development/test-data-design.md) の基本原則に従う。

1. **既存テストへの影響回避**  
   - `[DEMO-DEEP]` プレフィックスで命名し、既存データと明確に区別する。  
   - 独立した Seeder クラス `DemoDeepHierarchySeeder` として分離し、個別実行可能にする。

2. **実務的なシナリオ**  
   - 対医療ユースケース（病院 → 診療科 → 病棟 → チーム → 担当）を採用する。  
   - ペルソナ 1.3「現場リーダー/作業班長」の操作シナリオを模擬する。

3. **段階的拡張**  
   - 本計画は `DemoCompleteSeeder` への呼び出し追加ではなく、**オプション Seeder** として提供する。  
   - `DemoCompleteSeeder` を実行済みの環境に追加投入できる設計とする。

---

## 3. フォルダ階層の設計

### 3.1. 採用シナリオ：総合病院（5〜6 段）

```
[DEMO-DEEP] 総合病院（ルート）                 [1段目]
├── 内科部門                                    [2段目]
│   ├── 外来診療科                              [3段目]
│   │   ├── 第一外来病棟                        [4段目]
│   │   │   ├── 朝番チーム                      [5段目]
│   │   │   │   └── 申し送り記録（台帳定義あり） [6段目相当]
│   │   │   └── 夜番チーム                      [5段目]
│   │   └── 第二外来病棟                        [4段目]
│   └── 入院診療科                              [3段目]
│       ├── 第一病棟                             [4段目]
│       └── 第二病棟                             [4段目]
├── 外科部門                                    [2段目]
│   ├── 一般外科                                [3段目]
│   │   └── 手術室                              [4段目]
│   └── 整形外科                                [3段目]
│       └── リハビリ病棟                         [4段目]
└── 管理部門                                    [2段目]
    ├── 診療録管理                               [3段目]
    └── 医療安全管理                             [3段目]
        └── インシデント管理                     [4段目]
```

**総ノード数:** 約 20 フォルダ + 3〜5 台帳定義

### 3.2. 台帳定義（深い階層に配置）

| 台帳定義 | 配置フォルダ | 主な InputType |
| :--- | :--- | :--- |
| `[DEMO-DEEP] 申し送り記録` | 朝番チーム / 夜番チーム | Text, Textarea, Date, Select |
| `[DEMO-DEEP] 手術記録` | 手術室 | Text, Date, Textarea, Files |
| `[DEMO-DEEP] インシデント報告` | インシデント管理 | Text, Textarea, Date, Select, Checkbox |

### 3.3. 台帳レコード（ツリー視認性向上のための件数分散）

| フォルダ | 台帳件数 |
| :--- | ---: |
| 朝番チーム | 5 件 |
| 夜番チーム | 3 件 |
| 手術室 | 4 件 |
| インシデント管理 | 2 件 |

---

## 4. 実装計画

### Sprint 6: 深い階層デモデータの整備（工数 0.5 日）

**作業内容:**

1. `database/seeders/DemoDeepHierarchySeeder.php` を新規作成  
   - 上記フォルダ構造（約 20 ノード）を `Folder::create()` / `FixTree()` で構築  
   - 台帳定義 3 種と台帳レコード 14 件を投入  
   - `demo@example.com`（Admin）に全フォルダの `ADMIN` 権限を付与

2. `docs/development/database-seeding-guide.md` に `DemoDeepHierarchySeeder` のセクションを追記

3. 動作確認コマンド例:
   ```bash
   ./vendor/bin/sail artisan db:seed --class=DemoDeepHierarchySeeder
   ```

**テスト:**
- [ ] Seeder 実行後、`Folder::count()` が期待値（既存 + 20 前後）になること
- [ ] `Folder::whereDescendantOf($rootFolder)` でネスト構造が正しく取得できること
- [ ] `TreeTest` の N+1 クエリテスト（20 クエリ未満）が深い階層でも通過すること

---

## 5. 使用上の注意

- 本 Seeder は `DemoCompleteSeeder` が**実行済みの環境**に追加投入することを想定している。  
  単体実行の場合は `DemoMinimalSeeder` を先に実行すること。
- `DEMO-DEEP` プレフィックス付きデータは、`DemoMinimalSeeder` や `DemoPhase1ExtensionSeeder` と独立しており、削除も単独で可能な設計とする。

---

## 6. 関連ファイル

| ファイル | 役割 |
| :--- | :--- |
| `database/seeders/DemoDeepHierarchySeeder.php` | 新規作成（本計画の成果物） |
| `database/seeders/DeepHierarchyFolderSeeder.php` | 既存（空ファイル → 本計画で内容を実装） |
| `docs/development/database-seeding-guide.md` | Seeder ガイドに追記 |
| `tests/Feature/Livewire/Folder/TreeTest.php` | Sprint 4 の N+1 クエリ回帰テスト強化で使用 |

