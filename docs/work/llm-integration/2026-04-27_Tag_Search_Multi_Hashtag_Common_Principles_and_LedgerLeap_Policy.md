# 複数 `#タグ` 検索の一般的な挙動調査と LedgerLeap の判断整理

**作成日:** 2026年04月27日  
**種別:** 調査メモ / 仕様整理  
**関連:** `docs/function/PersonaUseCaseScenario.md`, `docs/function/Search.md`, `docs/function/Ledger.md`, `docs/work/llm-integration/2026-04-05_MCP_Search_Attachment_Feedback_Followup_Plan.md`, `docs/work/llm-integration/2026-04-05_Issue-136_Sprint_A3_Folder_Ledger_Fragment_Resolution_Plan.md`, `docs/work/llm-integration/2026-04-05_Issue-137_Synonym_Technical_Term_Selection_Policy_Memo.md`, `docs/work/llm-integration/2026-04-05_Issue-138_Search_Query_Trace_Explainability_Memo.md`, `docs/work/llm-integration/2026-04-05_Issue-139_Dialog_Query_Construction_Familiarity_Guidelines_Memo.md`

## 1. 目的

台帳リストの `#タグ` 複数指定について、インターネット上の一般的な検索・タグ実装の共通概念を確認し、LedgerLeap の現在判断とどこが一致し、どこが LedgerLeap 固有の判断かを整理する。

本メモは **実装修正の記録ではなく、仕様判断の根拠整理** を目的とする。

## 2. 調査対象

今回直接確認した代表例は次の 2 系統である。

1. **GitHub Code Search の検索構文**
   - 空白区切りの複数語は AND として扱われる
   - OR は明示が必要
   - つまり、複数語を並べた検索は「両方満たす」方向が基本

2. **`acts-as-taggable-on` の README**
   - `tagged_with(..., :match_all => true)` で全条件一致
   - `tagged_with(..., :any => true)` でいずれか一致
   - つまり、タグ条件は AND / OR を明示して使い分ける

> 補足: 今回の調査では、外部の代表例として GitHub Search 系とタグ管理ライブラリを確認し、一般論としての「検索」と「タグ絞り込み」の分離を整理した。個別サービスの厳密な UI 仕様は各製品ごとに差がある。

## 3. 一般的な共通概念として見える挙動

### 3.1 複数条件は AND が基本

多くの検索 UX では、複数の検索語を並べると **AND** として解釈される。

- すべての条件を満たすものを残す
- OR は明示的に指定する
- 「絞り込み」の感覚に近い

これは、ハッシュタグやラベルの複数指定でも自然に使われる考え方である。

### 3.2 OR は明示する

一般的な検索・タグ実装では、OR は暗黙ではなく **明示的に選ぶ** ことが多い。

- `any`
- `match any`
- `OR`
- `include any of these`

のように、利用者が意図を明確にできる形が好まれる。

### 3.3 タグ検索は「検索」より「facet / 絞り込み」に近い

タグは自由文検索というより、次のいずれかに近い扱いが多い。

- facet filter
- label filter
- taxonomy lookup
- autocomplete / candidate resolution

このため、タグ名は **完全一致の絞り込み** か、**候補解決の入口** として扱われやすい。

### 3.4 部分一致は lookup で吸収することが多い

タグ名を部分一致で直接検索できる UI もあるが、一般的には次のように役割分担されることが多い。

- **lookup / autocomplete**: 断片入力から候補を探す
- **filter / search**: 確定したタグで絞る

つまり、部分一致は「検索条件の本体」よりも「候補を見つける前段」で使われやすい。

## 4. LedgerLeap の現在判断との比較

### 4.1 一致している点

- **複数 `#タグ` を AND とする**  
  → 一般的な検索 UX と整合する
- **OR を明示しない限り、全部満たす方向で扱う**  
  → GitHub Code Search やタグライブラリの考え方と整合する
- **断片入力を lookup-first で救う**  
  → 一般的な候補解決の構造と整合する

### 4.2 LedgerLeap 固有の判断になっている点

- **各 `#タグ` を部分一致で扱う**  
  → 一般的なタグ filter の既定というより、LedgerLeap の「正式名を覚えていない前提」に合わせた製品判断
- **本文検索に混ぜず、タグ絞り込み専用にする**  
  → 検索式の可読性と説明性を重視した LedgerLeap 側の設計

## 5. 現在の判断との齟齬

### 5.1 齟齬がない点

- **複数 `#タグ` は AND**  
  → これは一般的な共通概念と一致している

### 5.2 齟齬がある点

- **複数 `#タグ` の各要素を部分一致で扱う**  
  → これは一般共通というより、LedgerLeap が「断片から探す」前提で採る拡張判断

したがって、**AND という結論は一般的** だが、**部分一致を既定にするかどうかは LedgerLeap 独自判断** である。

## 6. 推奨整理

仕様説明としては、次の 2 層に分けるのが最も分かりやすい。

### 6.1 一般則

- 複数条件は AND
- OR は明示
- タグは lookup / facet に近い

### 6.2 LedgerLeap の追加判断

- `#タグ` は断片入力を救うため、部分一致を許容する
- ただし本文検索には混ぜず、タグ条件としてのみ扱う
- 曖昧な場合は、勝手に OR に広げない

## 7. 結論

外部の一般的なサービス / 実装例と照らすと、LedgerLeap の現時点の結論は次のように整理できる。

- **複数 `#タグ` = AND** は一般的で、妥当
- **各 `#タグ` を部分一致にする** のは LedgerLeap 固有の判断
- したがって、**現在の判断は「大筋で一般的だが、部分一致の扱いだけが製品固有」** と言える

## 8. 参考

- GitHub Docs: `Understanding GitHub Code Search syntax`
- `acts-as-taggable-on` README
- `docs/function/PersonaUseCaseScenario.md` 2.5
- `docs/work/llm-integration/2026-04-05_Issue-136_Sprint_A3_Folder_Ledger_Fragment_Resolution_Plan.md`
- `docs/work/llm-integration/2026-04-05_Issue-137_Synonym_Technical_Term_Selection_Policy_Memo.md`
- `docs/work/llm-integration/2026-04-05_Issue-138_Search_Query_Trace_Explainability_Memo.md`
- `docs/work/llm-integration/2026-04-05_Issue-139_Dialog_Query_Construction_Familiarity_Guidelines_Memo.md`

## 9. 実地検証で確定した補足

- 営業日報の実データでは、タグは `営業活動` / `日次報告` のように別行で保持されていた
- `LedgerDefine::scopeSearchTags()` が複数タグを同一 `whereHas` 内で積んでいたため、別々のタグ行を AND で満たせず、一覧では 0 件になっていた
- `whereHas` をタグごとに分けたことで、`#日次 #営業` のような複数部分一致でも期待どおりにヒットすることを確認した
- 直接の Eloquent 検証だけでなく、`IndexManager` → `RecordsTable` の親子 Livewire 経路で `selectedFolderIds` / `selectedLedgerDefineIds` を含めて再現確認する必要がある

## 10. Freshness

- status: confirmed
- last_confirmed_at: 2026-04-27
- recheck_after: 台帳タグ検索の実装、`scopeSearchTags()`、または一覧画面の親子 Livewire 連携を変更したとき
- recheck_trigger: 直接クエリではヒットするのに一覧で 0 件になる、または複数 `#タグ` の部分一致 AND が再び崩れる

