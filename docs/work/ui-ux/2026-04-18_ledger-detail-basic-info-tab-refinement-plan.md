# 2026-04-18 Ledger Detail Basic Info Tab Refinement Plan

## Goal

台帳詳細ページの基本情報タブについて、添付ファイル以外の子孫コンポーネントも含めて表示品質を見直す。

狙いは次の3点。

1. `details` タブ内の表示を 1 つのまとまりとして見やすくする
2. 小さすぎる文字・アイコン・固定幅を減らし、PC でも読みやすくする
3. ローディング、差分表示、ワークフロー表示、折りたたみ説明の責務を整理する

## Scope

### 対象の主ビュー

- `resources/views/livewire/ledger/show.blade.php`
- `app/Livewire/Ledger/Show.php`

### 主な子孫コンポーネント

- `resources/views/livewire/ledger/ledger-diff-viewer.blade.php`
- `resources/views/livewire/ledger/workflow-status-card.blade.php`
- `resources/views/components/ledger/livewire-breadcrumbs.blade.php`
- `resources/views/components/element/loading-overlay.blade.php`
- `resources/views/components/expandable-content.blade.php`

### 今回の対象外

- `resources/views/components/attached-file/*`
- 添付ファイルそのものの UI 改修
- 基本情報タブ以外のタブの大規模再設計

## Current observations

### 1. `show.blade.php` に本体カードが重複している

`details` タブ内で、`x-mary-card` + `ledger-diff-viewer` + menu 構造が二重に描画されている。

- 1 つ目は `x-element.loading-overlay` の内側
- 2 つ目は `Actual content stays mounted` として、ほぼ同じ構造を再度描画

このままだと、ローディングは分離できても DOM の意図が読みづらく、保守時に誤更新しやすい。

### 2. `workflow-status-card.blade.php` が密度高めで固定サイズ寄り

- `style="width: 200px;"` が複数箇所の並びを制約している
- `text-xs` が進捗ラベル、件数、警告文に多い
- `w-4 h-4` の固定指定が多く、Mary UI の既定サイズを活かせていない箇所がある

状態表示として重要な領域なので、補助情報だけでなく主表示も少し伸ばしたい。

### 3. `ledger-diff-viewer.blade.php` は妥当だが、補助表示と行ホバーの見え方を整理したい

差分表としては密度が必要だが、次の要素は PC では小さく見えやすい。

- 比較状態ラベル
- 同一内容の案内
- omitted 表示の注記
- グループ見出しの補助アイコン
- 重要な比較行のホバーは、背景に埋もれない semantic color を使う

### 4. `livewire-breadcrumbs.blade.php` にハードコードされた文言が残っている

- `Top` が翻訳キーではない
- `isLivewire` 分岐と合わせて、文言の責務がやや薄い

詳細ページ全体のルールと合わせるなら、翻訳キー化の候補になる。

### 5. `expandable-content.blade.php` は補助的には妥当

差分表の本文開閉には使えるが、本文の主役表示に流用するならサイズ感を再検討した方がよい。

## Proposed improvement order

### Phase 1: 本体構造の重複を解消する

1. `show.blade.php` の `details` タブ内で、主カードを 1 つに整理する
2. ローディングはカードの上に重ねるだけにする
3. `ledger-diff-viewer` の重複描画を避ける
4. `displayLevel` / `showChanges` の menu は 1 か所に集約する

### Phase 2: 可読性とサイズの見直し

1. `workflow-status-card` の固定幅をやめる、またはレスポンシブ幅に変更する
2. `text-xs` を主役寄りのラベルでは `text-sm` 以上に上げる
3. `w-4 h-4` を乱用せず、意味が強いものだけを少し大きくし、十分読めるものは既定サイズを優先する
4. `ledger-diff-viewer` の補助テキストを、主役情報との優先度に応じて整理する

### Phase 3: 文言と補助コンポーネントの整理

1. `livewire-breadcrumbs` の `Top` を翻訳キー化する
2. `expandable-content` の用途を差分本文に限定するかを確認する
3. 説明文・警告文・状態表示の文言を translation skill に寄せる

## Skill mapping

### 既存スキルで判断するもの

- `ledger-detail-header`
  - ページ上部の統一ヘッダー、メタ情報、説明のまとまり
- `responsive-text-icon-sizing`
  - 小さすぎる文字・アイコンの見直し
- `livewire-loading-ui`
  - ローディングの重ね方、ターゲット分離、スケルトン/オーバーレイの使い分け
- `translation`
  - `Top` を含む文言、ボタン、ラベル、説明文
- `title-block`
  - 必要なら将来的に詳細ページ上部の主ブロックをさらに整理する際の参照

### 今回は直接対象外のもの

- `form-layout`
  - 今回の基本情報タブはフォームではなく閲覧中心
- `search-header-responsive-layout`
  - 検索ヘッダーではないため、今回は直接の対象外

## Detailed implementation plan

### 1. `show.blade.php` を 1 本の主カードに整理する

- `details` タブにある重複した `x-mary-card` 構造を 1 つにまとめる
- `x-element.loading-overlay` は同じ本体カードに重ねるだけにする
- `ledger-diff-viewer` は 1 回だけ描画する
- menu も 1 か所にまとめる

### 2. 基本情報タブの表示優先度を明確にする

- 上部に残すもの
  - タイトル
  - パンくず
  - 最低限のメタ情報
  - ワークフロー状態
- 中央に置くもの
  - 差分ビュー
  - 表示レベル切り替え
  - 差分表示切り替え
- 折りたたむもの
  - 説明文
  - 長い補足

### 3. サイズをデバイス基準に寄せる

- `text-xs` が主表示にかかっている箇所を抽出する
- `w-4 h-4` を主表示・補助表示で分ける。十分読めるものは既定サイズを残す
- PC で読ませたい情報は `md:` / `lg:` で少し上げる
- 小さくしてよい情報は badge / 補助メタ情報に限定する

### 4. 子孫コンポーネントを個別に見直す

- `workflow-status-card`
  - 固定幅を減らす
  - ラベルと進捗表示を整理する
- `ledger-diff-viewer`
  - 差分表の補助表示だけを小さく保つ
  - 必要なら見出しや状態ラベルを少し大きくする
  - 行ホバーは比較表として十分に見える semantic color を使う
- `livewire-breadcrumbs`
  - `Top` の翻訳キー化を検討する
- `expandable-content`
  - 本文の主役化を避け、補助開閉に限定する

## Verification plan

### Visual checks

- `details` タブで本体カードが 1 つに見えること
- PC で `workflow-status-card` の文字が読みやすいこと
- タブアイコンや補助アイコンが小さすぎないこと
- 差分表が密すぎず、でも一覧性は落ちていないこと

### Behavioral checks

- ローディングが本体カードの上にだけ重なること
- `displayLevel` / `showChanges` が意図したコンポーネントだけに作用すること
- `ledger-diff-viewer` の初期化回数が増えないこと
- 基本情報タブの切り替えで不要な再描画が起きないこと

### Skill checks

- `responsive-text-icon-sizing` の基準に沿っていること
- `livewire-loading-ui` の分離方針に沿っていること
- `translation` の対象は自然なキーに置き換えられること

## Recommendation

この件は**docs-only では終わらせず、計画書を起点に実装へ進むべき**。

理由は以下。

- すでに具体的な重複描画が見つかっている
- 固定サイズや `text-xs` が可読性に影響している
- 基本情報タブはページの第一印象に直結するため、先送りの影響が大きい

まずはこの計画書を基準に、`show.blade.php` から順に修正するのがよい。

## Sprint status

- Sprint 1: 完了
  - `show.blade.php` の details タブ本体構造を整理
  - `ledger-diff-viewer` の行ホバーを見やすい semantic color に戻す
  - 必須マーカー / 補助アイコンの固定サイズを見直す
- Sprint 2: 完了
  - `workflow-status-card` の固定幅を外し、主情報の文字とアイコンを読みやすく調整
  - `ledger-diff-viewer` の比較バナー、見出し、補助表示のサイズを再調整
  - `show.blade.php` のヘッダー帯と補助アイコンを拡張し、`workflow-action-buttons` の current version 表示を維持
  - 関連 Feature テストを実行し、`WorkflowStatusCardTest` / `LedgerDiffViewerTest` / `ShowTest` がすべて通過

