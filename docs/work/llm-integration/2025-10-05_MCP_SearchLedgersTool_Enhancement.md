# SearchLedgersTool ドキュメント改善実装

**作成日:** 2025年10月5日  
**ステータス:** 完了  
**関連ドキュメント:**
- [DEMO_STEP1_MINIMAL.md](./DEMO_STEP1_MINIMAL.md) - 実装検証結果
- [包括的MCP実装計画](./2025-09-29_Comprehensive_MCP_Implementation_Plan.md)

---

## 📝 背景

DEMO_STEP1_MINIMALの実装検証を通じて、以下の実用シナリオを実行しました:

### 検証したシナリオ
1. **担当者特定**: 「台帳でA社に関する担当者を知りたい」
   - 結果: `meta.users` フィールドから担当者情報を正常に取得
   
2. **他社比較**: 「他社と比較すると見込みはどうでしょうか」
   - 結果: 複数社の営業日報を検索・比較して適切な分析を提供

### 発見した課題

今回の検証で、SearchLedgersToolの説明文に以下の改善点が見つかりました:

1. **meta フィールドの活用法が不明確**
   - 担当者特定に使える `meta.users` の説明不足
   - meta.folders, meta.ledger_defines の存在が説明されていない

2. **実用的な検索パターンの欠如**
   - よく使う検索の具体例がない
   - パラメータの組み合わせ方が不明

3. **mode='count' の説明不足**
   - 存在チェックや統計用の高速モードが説明されていない

4. **日本語キーワードの具体例不足**
   - 完全一致と部分一致の違いが不明確

5. **パフォーマンスヒントの欠如**
   - 大量データでの検索最適化方法が説明されていない

---

## 🎯 実装内容

### 1. 説明文 (description) の改善

#### 追加した主要セクション

**A. meta フィールドの詳細説明**
```markdown
**Leveraging Metadata (メタデータの活用):**
When a search is successful, the `meta` field is automatically populated with:
- meta.users: Full user information for creators and modifiers (id, name)
- meta.folders: Folder information with paths
- meta.ledger_defines: Ledger definition details

**Best Practice for Identifying Responsible Persons:**
To find who is in charge, search for the ledger → check meta.users using creator_id
Example: "Who is in charge of Company A?" 
→ search_ledgers(q='Company A') 
→ Check ledger.creator_id in meta.users
```

**B. よく使う検索パターン集**
```markdown
**Common Search Patterns (よく使う検索パターン):**

1. Find all records for a specific company:
   search_ledgers(q='株式会社A商事', limit=50)

2. Get this week's activity records:
   search_ledgers(created_from='2025-10-01', created_to='2025-10-07')

3. Find important pending items:
   search_ledgers(tags='重要', exclude_tags='完了,見送り')

4. Check a user's activity history:
   search_ledgers(creator_id=3, limit=20)

5. Search within a specific folder:
   search_ledgers(folder_id=18, q='トラブル')

6. Quick count without loading data:
   search_ledgers(mode='count', tags='重要')

7. Get metadata only for quick browsing:
   search_ledgers(q='商談', include_content=false, limit=100)
```

**C. 日本語キーワードの扱い**
```markdown
**Japanese Keyword Handling (日本語キーワードの扱い):**
- Exact match: q='"株式会社A商事"' (use quotes)
- Partial match: q='A商事' (no quotes)
- Multiple keywords (AND): q='商事 提案' (space-separated)
- Note: Mroonga uses morphological analysis, word-based searches
```

**D. パフォーマンスヒント**
```markdown
**Performance Tips (パフォーマンスのヒント):**
- Broad searches without filters may be slow with large datasets
- Use 'ledger_define_id' or 'folder_id' to narrow search scope
- Use date ranges (created_from/created_to) to limit results
- Use mode='count' for existence checks (much faster)
```

### 2. スキーマ定義 (schema) の改善

各パラメータの説明を詳細化し、具体例を追加しました。

#### 主な改善点

**A. キーワード検索 (q パラメータ)**
```php
// Before
'Full-text search keyword. Supports Japanese and multi-byte characters.'

// After
'Full-text search keyword. Supports Japanese and multi-byte characters. 
Examples: "株式会社A商事", "営業日報", "検索機能". 
Use quotes for exact match: "株式会社A商事". 
Space-separated for AND: "商事 提案".'
```

**B. モード (mode パラメータ)**
```php
// Before
'The search mode.'

// After
'The search mode. "search" (default) returns full ledger data. 
"count" returns only the total number of matching ledgers 
(much faster for existence checks or statistics).'
```

**C. 作成者フィルタ (creator_id パラメータ)**
```php
// Before
'The ID of the user who created the ledger.'

// After
'The ID of the user who created the ledger. 
Use this to find all work by a specific person. 
You can get user IDs from meta.users in previous search results.'
```

**D. コンテンツ表示 (include_content パラメータ)**
```php
// Before
'Whether to include full content in summary format. 
If false, only a preview is included.'

// After
'Whether to include full ledger content in summary format. 
Set to false for quick browsing of many ledgers 
(only metadata and preview shown). Default: true.'
```

### 3. Search Parameters セクションの拡張

既存のパラメータリストに以下を追加:

```markdown
- 'mode': 'search' (default) returns full data, 'count' returns only count (faster)
- 'include_content': Set to false to get metadata only (useful for quick browsing)
- 'content_preview_length': Characters to preview from long text (default: 200)
```

---

## ✅ テスト結果

### 実行したテスト
```bash
./vendor/bin/sail test --filter=SearchLedgersToolTest
```

### 結果
```
PASS  Tests\Unit\Mcp\Tools\SearchLedgersToolTest
✓ it returns unauthorized if token is missing                  8.50s
✓ it returns unauthorized if token is invalid                  0.27s
✓ it returns raw format correctly                              0.26s
✓ it handles empty results for summary format                  0.26s
✓ it returns summary format without content                    0.27s
✓ it uses english keys in display fields                       0.27s
✓ it includes attachment info in summary format                0.26s
✓ it handles ledgers without attachments                       0.26s
✓ it formats file sizes correctly                              0.26s

Tests:    9 passed (58 assertions)
Duration: 10.97s
```

**全てのテストが通過** ✅

---

## 📊 改善効果の予測

### 1. LLMの理解度向上

**Before:** meta フィールドの存在は知っているが、活用方法が不明
```
User: "A社の担当者は誰ですか？"
LLM: search_ledgers(q='A社') を実行
     → 結果は得られるが、meta.users の使い方がわからず追加質問が必要
```

**After:** meta フィールドの活用法を理解し、一回で回答可能
```
User: "A社の担当者は誰ですか？"
LLM: search_ledgers(q='A社') を実行
     → ledger.creator_id = 3
     → meta.users[3] = {id: 3, name: "田中太郎"}
     → 「担当者は田中太郎さんです」と即座に回答
```

### 2. 検索効率の向上

**Before:** どのパラメータを使えばいいか手探り
```
試行1: search_ledgers(q='重要')  # 結果が多すぎる
試行2: search_ledgers(q='重要 案件')  # まだ多い
試行3: search_ledgers(q='重要', tags='新規')  # ようやく適切な結果
```

**After:** Common Search Patterns から適切なパターンを選択
```
試行1: search_ledgers(tags='重要', exclude_tags='完了,見送り')  # 一発で適切な結果
```

### 3. パフォーマンス最適化

**Before:** 常にフルデータを取得
```
search_ledgers(q='商談', limit=100)  
# → 100件の全データ + content を取得 (遅い、トークン消費大)
```

**After:** 目的に応じた最適化
```
# 存在確認のみ
search_ledgers(mode='count', q='商談')  # 高速

# 一覧表示
search_ledgers(q='商談', include_content=false, limit=100)  # 軽量
```

---

## 🎓 学んだこと

### 1. MCPツール説明の重要性

MCPツールの説明文は、LLMが正しくツールを使えるかどうかの決定的要因です。

**効果的な説明の要素:**
- ✅ 具体的な使用例（コード例）
- ✅ よく使うパターン集
- ✅ パラメータ間の関連性
- ✅ パフォーマンスヒント
- ✅ ベストプラクティス

### 2. meta フィールドの戦略的価値

今回の検証で、`meta` フィールドが単なる補助情報ではなく、**複数ツール呼び出しを削減する戦略的な機能**であることが判明しました。

**活用パターン:**
```
従来のアプローチ（2回のツール呼び出し）:
1. search_ledgers(q='A社') → ledger.creator_id = 3
2. get_user(id=3) → user.name = "田中太郎"

最適化されたアプローチ（1回のツール呼び出し）:
1. search_ledgers(q='A社') 
   → ledger.creator_id = 3
   → meta.users[3] = {id: 3, name: "田中太郎"}
```

### 3. 実装前の検証の価値

今回、実装完了後にデモ検証を行ったことで、実際の使用シナリオから改善点を発見できました。

**検証駆動開発のメリット:**
- ✅ 実際の使用感から改善点を発見
- ✅ ドキュメントの不足箇所を特定
- ✅ LLMの思考プロセスを理解

---

## 📝 今後の展開

### 1. 他のMCPツールへの適用

今回の改善パターンは、他のMCPツールにも適用可能です:

**優先度の高いツール:**
- ✅ CreateLedgerTool - 作成パターン集の追加
- ✅ GetPendingApprovalsTool - ワークフローパターンの説明強化
- ✅ GetActivityLogTool - フィルター組み合わせ例の追加

### 2. デモ環境の拡充

DEMO_STEP2 以降で以下を追加予定:
- より複雑な検索シナリオ（複数条件の組み合わせ）
- ワークフローとの統合シナリオ
- 統計情報との連携シナリオ

### 3. ドキュメントの継続的改善

ユーザーフィードバックとLLMの使用ログから、継続的に改善:
- よく使われるパターンの追加
- 誤解を招く表現の修正
- 新機能の追加に伴う説明の更新

---

## 📎 関連ファイル

### 変更したファイル
- `app/Mcp/Tools/SearchLedgersTool.php`
  - description の拡充（約70行追加）
  - schema の詳細化（全パラメータ）

### テストファイル
- `tests/Unit/Mcp/Tools/SearchLedgersToolTest.php`
  - 既存テスト9件すべて通過確認

### 関連ドキュメント
- `docs/work/DEMO_STEP1_MINIMAL.md` - 検証結果
- `docs/work/2025-09-29_Comprehensive_MCP_Implementation_Plan.md` - 全体計画

---

## ✅ 完了チェックリスト

- [x] description の改善
  - [x] meta フィールドの詳細説明
  - [x] Common Search Patterns 追加
  - [x] Japanese Keyword Handling 追加
  - [x] Performance Tips 追加
- [x] schema の改善
  - [x] 全パラメータの説明詳細化
  - [x] 具体例の追加
  - [x] ベストプラクティスの記載
- [x] テスト実行
  - [x] 全テスト通過確認
- [x] ドキュメント作成
  - [x] 実装内容の記録
  - [x] 改善効果の分析
  - [x] 学びの整理

---

**作成者**: AI Assistant  
**レビュー**: 実装完了・テスト通過・ドキュメント作成完了
