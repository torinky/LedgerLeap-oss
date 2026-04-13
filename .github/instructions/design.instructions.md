# LedgerLeap Design & UI Generation Guidelines

This file contains the UI rules for the AI agent to follow when generating or modifying any views or front-end components.

## 1. Core Principles

- **Vibe**: Clean, corporate administrative interface. Information density should be managed nicely to ensure maximum readability and operability.
- **Tech Stack**: daisyUI v5 + Tailwind CSS v4.
- **Hardcoding Ban**:
    - NEVER use hardcoded Hex colors (e.g. `text-[#00b900]`), arbitrary pixel values (e.g. `p-[10px]`), or custom CSS components unless absolutely necessary.
    - **NEVER use hardcoded natural language text** (Japanese, English, etc.) for UI labels, button texts, or error messages. Use translation keys (`__('ledger.xxx')`) and manage them via the translation skill workflow.

## 2. Color System

Use daisyUI semantic colors exclusively to ensure compatibility across themes (`corporate` for Light and `coffee` for Dark):

- `primary`: Main brand color, primary actions.
- `secondary`, `accent`: Secondary and accent actions/highlights.
- `info`, `success`, `warning`, `error`: Contextual alerts and states.
- `base-100`, `base-200`, `base-300`: Layered backgrounds and surfaces.
- `base-content`: Default text color.

## 3. Mary UI Precedence

- **IMPORTANT**: If a UI component exists in Mary UI (the Laravel Livewire UI library we use), **you MUST use the Mary UI Blade component** (e.g. `<x-mary-card>`) instead of raw HTML with daisyUI classes (e.g. `<div class="card">`).
- When using Mary UI, you must instruct the AI (or write the code yourself) to adjust Mary UI's styling to visually match the project's overall daisyUI style guide above. Do this by utilizing Mary UI's component attributes or passing standard Tailwind utility classes.

## 4. Typography & Spacing

- Rely on Tailwind's default typography classes (`text-sm`, `text-lg`, `font-bold`).
- Rely on Tailwind's spacing scale (`p-4`, `gap-2`, `m-4`). Do not create custom scales via arbitrary values (`gap-[13px]`).

## 5. UI Component Guidelines

- **Buttons**: `<x-mary-button class="btn-primary">` (Mary UI). Use `btn-ghost` or `btn-outline` for secondary actions.
- **Inputs**: `<x-mary-input>` (Mary UI). Add `icon-input` class if an icon prepends it.
- **Cards**: `<x-mary-card>` (Mary UI).
- **Titles**: Use `.ttl_3d5` for decorated headers defined in the project's `app.scss`.

## 6. Do / Don't

- **DO**: Prefer overriding Mary UI component appearances seamlessly using Tailwind classes when needed for alignment with project style.
- **DO**: Maintain consistent max-widths using Tailwind container classes (e.g., `max-w-7xl`, `mx-auto`).
- **DON'T**: Write inline style overrides sizes.
- **DON'T**: Ignore Dark Mode by hardcoding `bg-white` or `text-black`. Constantly fall back to `bg-base-100` and `text-base-content`.

## 7. Device & Responsiveness Strategy

現場（タブレット主体）および事務所（ノートPC主体）での利用シナリオを前提に、以下のレスポンシブ指針を守る。

- **操作性（タブレット想定）**: daisyUI のデフォルト要素サイズを維持し、最低 44px 程度のタッチターゲットを確保する。hover のみでしか表示されない重要なアクションは置かない。
- **情報密度（ノートPC想定）**: 一覧やテーブルでは `lg:`（1024px以上）を活用し、広い画面幅を最大限使う。
- **グリッドとフレックス**: `md:`（タブレット）と `lg:`（ノートPC）の間で適切に折り返すよう、Tailwind の Grid / Flex レイアウトで組む。
- **画面下部アクションバー（Sticky Action Bar）**:
  - 全デバイス共通: 画面下部（`fixed bottom-0`）へフローティング配置する。`<x-mary-card>` は意図しない内部余白を含むため使わず、`<div class="shadow-md bg-base-300 rounded-t-3xl overflow-hidden">` のような専用ラッパーで実装する。
  - PC（`lg:` 以上）: 背後の文字が読めるように、コンテナ全体の不透明度を一時的に下げる（例: `opacity-[0.65]`）。ぼかし（blur）は使わず、hover 時に `opacity-100` へ戻す。
  - タブレット・モバイル（`lg:` 未満）: 常に不透明（`opacity-100`）にしつつ、表示面積を確保するため初期状態ではフッターの主要部分を下部に隠す。上部のタブをタップするとスライドアップする構造にする。
  - 主要ラベルやボタン文言は翻訳キーで管理し、`ledger.action_bar_open` / `ledger.action_bar_close` のようなキーを使う。

## 8. Icon Usage and Rotation

1. **優先順位**: FontAwesome (`<i>`) > Heroicons (`<x-mary-icon>`)。
2. **基本ルール**: `up/down` 等のバリエーションがあるアイコンは、切り替えて使うことを優先する。
3. **回転アニメーション**: クラスによる回転は、必ず **ラッパー `<span>`** に対して適用する。
4. **Hybrid Icon Pattern**: Mary UI (Heroicons) と FontAwesome を併用する場合、Enum に `heroicon()` (Mary UI 用) および `icon()` (FontAwesome 用) の両方のメソッドを実装し、呼び出し側で使い分ける。これにより SvgNotFound 例外を回避する。

```blade
<span class="inline-flex transition-transform duration-300 ease-in-out" :class="expanded ? 'rotate-180' : 'rotate-0'">
    <i class="fa-solid fa-chevron-up"></i>
</span>
```

## 9. Text Switching (Alpine.js)

`x-text` よりも `x-show` を用いた 2 要素分割方式を推奨する。

```blade
<span x-show="expanded" x-cloak style="display:none">{{ __('ledger.action_bar_close') }}</span>
<span x-show="!expanded">{{ __('ledger.action_bar_open') }}</span>
```

## 10. Text Writing Principles

1. **Buttons** は、できるだけ **動詞を含む短い行動文** にする。ボタンを押したあとに何が起きるかが一目で分かる文言を優先する。
   - 例: `保存する` / `変更を保存する` / `送信する` / `一覧に戻る` / `詳細を表示する`
   - 破壊的・不可逆な操作は、対象と結果が分かるようにする。
   - 例: `台帳を削除する` / `変更を破棄して閉じる`
2. **Labels** は、原則として **名詞または名詞句** にする。入力項目や対象そのものを示し、文章にしない。
   - 例: `台帳名` / `承認者` / `検索条件` / `ステータス`
   - **Important**: 入れ子構造にする場合、親ラベルと重複する単語を避ける（例: 「ステータス」パネル内では「承認ステータス」や「状態」など）。
3. **説明文・補足文** は、ユーザーが次に何をすべきか、何ができるかを示す **短い平易な文** にする。
   - 可能な限り主語・動作を明確にし、冗長な修飾を避ける。
   - 1 文で伝わる内容を優先し、長文はヘルプや tooltip に逃がす。
4. **エラーメッセージ** は、`何が起きたか` / `必要なら原因` / `次にどうするか` を含める。
   - 例: `保存できませんでした。入力内容を確認して再試行してください。`
   - 責める言い方や抽象的な言い方は避ける。
5. **状態表示・件数・短いメタ情報** は、前節のとおり badge-first で見直す。
6. 文言を迷ったときは、`button = action` / `label = noun` / `description = guidance` / `error = problem + next step` を基準にする。
7. 日本語のボタン動詞は、`保存する` / `更新する` / `作成する` / `登録する` / `編集する` / `変更する` / `反映する` を文脈で使い分ける。詳細は `docs/work/ui-ux/2026-04-11_text-writing-guidance.md` を参照する。

参考: `docs/work/ui-ux/2026-04-11_text-writing-guidance.md` / `docs/work/ui-ux/2026-04-11_status-badge-pattern-guidance.md`

## 11. Status Badges and Tooltips

1. `x-mary-badge` を使い、ツールチップが必要な場合は daisyUI `tooltip` ラッパーで包む。
2. 省スペース化が必要な場所では `badge-sm` を使う。
3. フッター、アクションバー、一覧ヘッダー、サマリーなどの **短い数値 / 状態 / メタ情報** は、単なるテキストのままにせず、**まず badge 化できないかを検討する**。
4. 目安として、以下に当てはまる場合は `text` より `badge` を優先する。
   - 1〜数語で読める短い状態名・件数・ラベルである
   - 一目で認識できることが重要で、補助説明は tooltip に逃がせる
   - 非操作で、選択 / 解除 / 破棄などのインタラクションを持たない
   - 色やアイコンで意味を補強できる
5. 逆に、以下は badge 化せず text や別コンポーネントを維持する。
   - 長い文章、エラーメッセージ、操作案内、説明文
   - 選択 / フィルタ / 解除のような操作が主目的のラベル
   - 句として読む必要があり、アイコン化しても意味が薄くなるもの
6. フィルタ・選択・解除が主目的のラベルは badge ではなく、必要に応じて chip / tag 相当のインタラクティブ表現を検討する。
7. 判断に迷う場合は、`badge = 状態を読む` / `chip = 状態を操作する` / `text = 説明を読む` を基準にする。
8. **Summary Badge Pattern**: 折りたたみパネル（検索オプション等）のヘッダーには、パネルが閉じている状態でも現在の適用内容（ソート順、フィルタ等）が把握できるよう、サマリーバッジを配置する。

```blade
<div class="tooltip tooltip-top" data-tip="{{ __('ledger.workflow.tooltip.current_status_desc') }}">
    <x-mary-badge :value="$status->label()" :icon="$icon" class="badge-sm font-bold shadow-sm" />
</div>
```

参考: `docs/work/ui-ux/2026-04-11_status-badge-pattern-guidance.md`

```php
$icon = match ($status) {
    WorkflowStatus::DRAFT => 'o-pencil-square',
    WorkflowStatus::PENDING_INSPECTION => 'o-magnifying-glass',
    WorkflowStatus::PENDING_APPROVAL => 'o-clock',
    WorkflowStatus::APPROVED => 'o-check-badge',
    default => 'o-document-text',
};
```

## 12. daisyUI Swap Component Constraints

daisyUI `swap` は `<input type="checkbox">` の checked 状態でしか安定して動作しない。Alpine.js の `expanded` 変数を外から `swap-active` へ反映する設計は避ける。

```blade
{{-- ❌ 誤用例: Alpine.js 変数で swap-active を外部制御 --}}
<label class="swap swap-rotate" :class="{ 'swap-active': expanded }">
    <input type="checkbox" class="hidden" />
    ...
</label>
```

## 13. UI Change Verification

- Blade のタグ構造を変えたら、見た目だけでなく変数スコープや `route()` の動的解決が壊れていないか確認する。
- ビュー修正だけでも、関連する Feature テストやレンダリング確認を行う。
- 変更後の UI が正常に動き、既存テストを通過したことを報告に含める。

## 14. Animations & UI State Persistence

1. **x-collapse**: 展開/折りたたみ（Accordion）パネルの実装には、ネイティブの `<details>` よりも Alpine.js の `x-collapse` を優先する。これにより、スムーズなスライドアニメーションを提供し、操作感をプレミアムにする。
2. **UI State Persistence**: 検索オプションの開閉状態など、ユーザーが頻繁に操作する UI の状態はリロード時に維持されるべきである。Alpine.js のプラグイン `$persist` が利用できない場合は、`localStorage` を手動で使用して状態を永続化する。

```blade
<div x-data="{ open: localStorage.getItem('search_panel_open') === 'true' }"
     x-init="$watch('open', value => localStorage.setItem('search_panel_open', value))">
    <button @click="open = !open">Toggle</button>
    <div x-show="open" x-collapse x-cloak>...</div>
</div>
```
