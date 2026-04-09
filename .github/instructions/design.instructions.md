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
- **Cards**: `<x--marycard>` (Mary UI).
- **Titles**: Use `.ttl_3d5` for decorated headers defined in the project's `app.scss`.

## 6. Do / Don't

- **DO**: Prefer overriding Mary UI component appearances seamlessly using Tailwind classes when needed for alignment with project style.
- **DO**: Maintain consistent max-widths using Tailwind container classes (e.g., `max-w-7xl`, `mx-auto`).
- **DON'T**: Write inline style overrides sizes.
- **DON'T**: Ignore Dark Mode by hardcoding `bg-white` or `text-black`. Constantly fall back to `bg-base-100` and `text-base-content`.

## 7. Device & Responsiveness Strategy

現場（タブレット主体）および事務所（ノートPC主体）での利用シナリオを前提に、以下のレスポンシブ指針を遵守します：

- **操作性（タブレット想定）**: daisyUIのデフォルト要素サイズを維持し、最低44px程度のタッチターゲットを確保すること。ホバー（hover）のみでしか表示されない重要なアクションは配置しない。
- **情報密度（ノートPC想定）**: データ一覧やテーブル等では `lg:` （1024px以上）のブレークポイントを活用し、ノートPCの広い画面幅を最大限活かしたレイアウトを行うこと。
- **グリッドとフレックス**: `md:` (タブレット) と `lg:` (ノートPC) の間で適切に折り返されるよう、必ずTailwindのGrid/Flexレイアウトを使用してコンポーネントを組むこと。
- **画面下部アクションバー（Sticky Action Bar）**: 各種フォーム保存画面では、マルチウィンドウ（PC）と画面面積（タブレット）に配慮した以下のUIを採用する。
    - **全デバイス共通**: 画面下部（`fixed bottom-0`）へフローティング配置する。構造として `<x-mary-card>` は意図しない内部余白（Padding）を含むため使用せず、標準の `<div class="shadow-md bg-base-300 rounded-t-3xl overflow-hidden">` による専用ラップ要素を用いて実装すること。
    - **PC（ `lg:` 以上 ）**: 背後の文字（コンテンツ）がハッキリと読めるように、コンテナ全体の不透明度を一時的に下げるアプローチ（例：`opacity-[0.65]`）をとり、すりガラス等の「ぼかし（blur）」加工は用いない。マウスホバーした際は瞬時に `opacity-100` となり操作に集中できるようにする。
    - **タブレット・モバイル（ `lg:` 未満 ）**: 透過効果を用いず常に不透明（`opacity-100`）とするが、限られた表示面積を確保するため、**初期状態ではフッターの主要部分を下部に隠す（スライドダウン状態）**。上部をタップした場合に「競り上がる（スライドアップする）」構造（Pull Tab / Bottom Sheet）とすること。
        - スライドアップを引き起こす「タブ」部分は、カードのパディング内側に配置せず、左右の端まで到達するエッジツーエッジ（Edge-to-Edge）のデザインにする。
        - タブの最適高は `3.5rem` (`h-14`) 程度とし、`div.p-4` のコンテンツ枠とは完全に分離すること。
        - CSSによる縦位置調整は、不要な構文エラー（`translate-y-[calc(...)]`等）を避けるため `:style="'transform: translateY(calc(100% - 3.5rem));'"` のようにAlpine.jsのインラインスタイルバインディングを用いて確実に行うこと。
- **主要ボタンの階層化**: 状態を完全に変更・確定するアクション（「保存する」「変更する」等）は、他のボタン（閉じる、リンク生成、削除等）と比較して**明確に大きく、タップ面積の広いデザイン（例: `btn-lg px-8` 等）** とし、視覚的・操作的な強弱を明確につけること。
