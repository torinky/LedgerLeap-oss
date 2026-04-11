# 2026-04-11 Text Writing Guidance for Buttons, Labels, and Descriptions

## Goal

LedgerLeap の UI 文言を、ボタン・ラベル・説明・エラーで一貫した書き方に揃える。

## Evidence record

```yaml
claim: Buttons should read like actions, labels should read like nouns, descriptions should be short guidance, and errors should include the problem plus next step
status: confirmed-official
last_confirmed_at: 2026-04-11
recheck_after: 90d
recheck_trigger:
  - upstream writing / button guidance changes
  - same UI copy area is edited again
sources:
  - type: official-doc
    url: https://www.gov.uk/guidance/content-design/writing-for-gov-uk
  - type: official-doc
    url: https://docs.developer.apple.com/design/human-interface-guidelines/buttons
  - type: official-doc
    url: https://carbondesignsystem.com/components/tag/usage/
  - type: repo-proof
    path: .github/instructions/design.instructions.md
  - type: repo-proof
    path: resources/views/components/ledger/sticky-action-bar.blade.php
notes: GOV.UK emphasizes active voice, short sentences, simple vocabulary, and being specific/clear; Apple states a button initiates an instantaneous action; Carbon tags should be concise and disclose overflow via tooltip.
```

## Working decision

- **button = action**: 動詞を含む短い行動文にする。
- **label = noun**: 項目名・対象名を示す名詞句にする。
- **description = guidance**: ユーザーの理解や次の操作を助ける短い補足文にする。
- **error = problem + next step**: 何が起きたかと、次にどうするかを短く示す。

## Japanese verb selection guide

日本語では、似た動詞でも UX 上の意味が少しずつ違う。LedgerLeap では次の基準で使い分ける。

| 動詞 | 使う場面 | 例 | 備考 |
|---|---|---|---|
| 保存する | 入力内容をそのまま保持する | `保存する` | 最も一般的な確定操作。画面内編集の標準ボタンに向く。 |
| 更新する | 既存データを上書き・再取得・同期する | `更新する` / `変更を更新する` | バックエンドや一覧の再反映と相性がよい。単なる編集確定には使いすぎない。 |
| 作成する | 新しいレコードや設定を新規に作る | `新規作成する` / `台帳を作成する` | 新規リソース生成のときに使う。 |
| 登録する | 業務上の正式な登録・提出・受付に近い | `申請を登録する` | 単なる UI 編集よりも、制度的・業務的な確定感が強い。 |
| 編集する | 既存内容を編集する画面・状態に入る | `編集する` / `内容を編集する` | ボタンより見出し・モード切替・リンクに向くことが多い。 |
| 変更する | 設定や条件を変える | `設定を変更する` / `通知条件を変更する` | 設定系・選択肢系との相性がよい。 |
| 反映する | 設定や変更内容をシステムへ適用する | `変更を反映する` | 直接の保存よりも、適用・同期・再計算の意味合いが強い。 |

### Selection rules

1. **新規か既存か** でまず分ける。新規なら `作成する`、既存なら `保存する` / `更新する` / `変更する` を検討する。
2. **業務上の確定か、単なる画面編集か** で `登録する` と `保存する` を分ける。
3. **設定変更か、データ編集か** で `変更する` と `編集する` を分ける。
4. **再計算・同期・適用** のニュアンスが必要なら `反映する` を使う。
5. 迷ったら、ボタンは「押したあと何が起きるか」が最も自然に伝わる動詞を選ぶ。

## Heuristics

1. ボタン文言は「押したあと何が起きるか」が分かることを優先する。
2. 単なる動詞だけで意味が弱い場合は、対象や結果を足して明確にする。
3. ラベルは文章にしない。名詞や短い名詞句に寄せる。
4. 説明文は短文にし、長い案内は tooltip / help / docs に逃がす。
5. エラーは責任追及ではなく、原因と次の一手を伝える。
6. 画面上の短い状態や件数は badge-first で見直す。

## External references checked on 2026-04-11

### GOV.UK content design

- URL: https://www.gov.uk/guidance/content-design/writing-for-gov-uk
- Extracted points:
  - Use the active rather than passive voice.
  - Use short sentences, sub-headed sections, and simple vocabulary.
  - Be specific, informative, clear, and to the point.
  - Use an active verb for titles when the page is about doing the thing.

### Context7 check (GOV.UK Content Design Manual)

- Library ID: `/websites/gov_uk_guidance_content-design`
- Context7 extraction:
  - Know your audience and use their vocabulary where possible.
  - Write in plain English and make content accessible to everyone.
  - Use the active rather than passive voice to make writing concise and clear.
  - Use an active verb for titles when the page is about doing the thing.
  - Write for the web: specific, informative, clear, and to the point.

### Apple Human Interface Guidelines — Buttons

- URL: https://developer.apple.com/design/human-interface-guidelines/buttons
- Extracted point:
  - A button initiates an instantaneous action.
- LedgerLeap interpretation:
  - Buttons should read like actions, not labels.

### Context7 check (Apple Human Interface Guidelines)

- Library ID: `/websites/developer_apple_design_human-interface-guidelines`
- Context7 extraction:
  - Button content should clearly communicate the action the button performs.
  - Labels should be concise and unambiguous.
  - Button titles should be succinct, logical, and typically one or two words.
  - Use verbs or verb phrases that directly relate to the action.
  - Standard navigation buttons like Cancel / Done / Back help clarify task state and exit behavior.

### Carbon Design System — Tag usage

- URL: https://carbondesignsystem.com/components/tag/usage/
- Extracted points:
  - Tags are used to label, categorize, or organize items.
  - Titles should be concise and informative.
  - Overflow content should disclose the full title in a tooltip.
- LedgerLeap interpretation:
  - Short metadata may fit badge-like display; longer content should stay descriptive and may need tooltip disclosure.

### Context7 check (Carbon Design System)

- Library ID: `/carbon-design-system/carbon`
- Context7 extraction:
  - Tags are for categorizing items and should use short labels for easy scanning.
  - Read-only tags are for labeling/categorizing and do not have interactive functionality.
  - Selectable tags are for selecting/unselecting items or filtering by label.
  - Dismissible tags are for removing filters.
  - Operational tags are for disclosing related items, not for external navigation.

## LedgerLeap examples

### Good button copy

- `保存する`
- `変更を保存する`
- `送信する`
- `一覧に戻る`
- `詳細を表示する`
- `変更を破棄して閉じる`
- `台帳を作成する`
- `設定を変更する`
- `変更を反映する`

### Good labels

- `台帳名`
- `承認者`
- `検索条件`
- `ステータス`

### Good description / helper text

- `この台帳に登録・更新する際に承認フローを必須にします。`
- `複数のロールを選択できます。`
- `入力内容を確認して再試行してください。`

### Bad patterns to avoid

- 抽象的な動詞だけのボタン: `実行`, `処理`
- 文章になっているラベル: `この項目の名前を入力してください`
- 長すぎる説明を badge や label に詰め込む
- エラーだけで次の行動が分からない文言

## Evidence links

- Design rule: `/.github/instructions/design.instructions.md`
- Badge guidance: `docs/work/ui-ux/2026-04-11_status-badge-pattern-guidance.md`
- Sticky action bar implementation: `resources/views/components/ledger/sticky-action-bar.blade.php`

