# W3-1.3 承認履歴テーブルUI設計

**最終更新:** 2026-01-04
**対象:** LedgerLeap v12.0 / Branch: `feature/ledger-rollback`
**ステータス:** Draft

## 1. デザインコンセプト

### "Clean Timeline, Clear Context"
従来の「カード形式」から、情報のスキャン性と一覧性に優れた「Modern Table UI」へ刷新する。
実務担当者が「いつ、誰が、何をしたか」を瞬時に把握でき、リーダーや管理者が「どのバージョンと比較するか」を迷わず選択できるUIを目指す。

#### キーワード
- **Aesthetic**: Tailwind / DaisyUI ベースのフラットでクリーンなデザイン。余白（Whitespace）を適切に取り、窮屈さを排除する。
- **Interactive**: 行全体がクリッカブル（詳細表示）。ホバー時のさりげないフィードバック。
- **Contextual**: 現在表示中のバージョン、比較対象のバージョンを明確なインジケータで示す。

---

## 2. レイアウト構造

### 2.1 コンテナ (`LedgerHistoryManager`)
- **Sticky Header**: スクロールしても常に上部に固定され、操作（比較モード切替など）が可能なヘッダー。
- **Infinite Scroll List**: `IntersectionObserver` を用いた無限スクロールリスト。
- **Split Pane (Optional)**: PC画面では、左側にリスト、右側に詳細（DiffViewer）を表示する2ペイン構成も検討（Phase 1では上下配置またはモーダル/別領域表示でシンプルに開始）。

### 2.2 テーブルカラム構成

| カラム | 内容 | UIパターン | 幅 |
|---|---|---|---|
| **Indicator** | 選択状態/比較対象表示 | `Left-border` highlight, `Icon` (Base/Target) | 4px+Icon |
| **Version** | バージョン番号 | `Badge` (Solid/Outline) | Fixed |
| **Status** | ワークフロー状態 | `Dot` + `Label` (Success/Warning etc.) | Fixed |
| **User** | 更新者情報 | `Avatar` + `Name` (Tooltip/Popoverあり) | Auto |
| **Date** | 更新日時 | `Relative Time` (Hoverで絶対日時) | Fixed |
| **Comment** | コメント | `Truncated Text` (1行表示, Tooltipあり) | 1fr |
| **Actions** | 操作ボタン | `Dropdown` or `Inline Buttons` (View, Compare) | Fixed |

### 2.3 UIコンポーネント詳細

#### A. バージョン選択（Interaction）
- **通常モード**:
    - 行クリック: そのバージョンのスナップショットを表示（`selectedDiffId` 更新）。
    - 視覚効果: 選択中の行は背景色変化（`bg-primary/10`）。
- **比較モード（Comparison Mode）**:
    - ヘッダーの「比較モード」スイッチで有効化。
    - 各行に「基準(A)」「対象(B)」を選択するラジオボタン/チェックボックスが出現、または行クリックで順次選択。
    - **推奨インタラクション**:
        - メインの「表示中バージョン」は常に選択状態（Base）。
        - 他の行に「これと比較 (Correct with this)」ボタン（またはアイコン）を表示。
        - 選択すると、その行が比較対象（Target）となり、DiffViewerが更新される。

#### B. ユーザー情報（Pop-over）
- アバターまたは名前にホバー/クリックで `Pop-up Card` を表示。
- **内容**:
    - フルネーム、所属組織、メールアドレス。
    - アクション: 「アドレスをコピー」「Chatで連絡」（Phase 1はリンクのみ）。

#### C. ステータスバッジ
- `daisyUI` の `badge` コンポーネントを使用。
- `APPROVED` (Green), `REJECTED` (Red), `APPLIED` (Blue) 等、直感的な配色。

---

## 3. 実装イメージ (Blade/Tailwind)

### 3.1 Sticky Header Area
```html
<div class="sticky top-0 z-10 bg-base-100/95 backdrop-blur border-b border-base-300 p-4 flex justify-between items-center">
    <h3 class="font-bold text-lg flex items-center gap-2">
        <x-mary-icon name="o-clock" /> 更新履歴
    </h3>
    <div class="flex gap-2">
        <!-- Compare Mode Toggle -->
        <label class="cursor-pointer label">
            <span class="label-text mr-2 text-xs font-mono uppercase tracking-wide opacity-70">Compare Mode</span>
            <input type="checkbox" class="toggle toggle-primary toggle-sm" wire:model.live="isCompareMode" />
        </label>
        
        <!-- Quick Actions (Phase 1) -->
        <button class="btn btn-xs btn-ghost" wire:click="compareLatest">最新と比較</button>
    </div>
</div>
```

### 3.2 List Row Item
```html
<!-- Loop -->
<div 
    class="group relative flex items-center gap-4 p-3 border-b border-base-200 hover:bg-base-200/50 transition-colors cursor-pointer {{ $isSelected ? 'bg-primary/5 border-l-4 border-l-primary' : '' }}"
    wire:click="selectVersion({{ $diff->id }})"
>
    <!-- Version Badge -->
    <div class="flex-none w-16 text-center">
        <span class="badge badge-neutral font-mono shadow-sm">Ver. {{ $diff->version }}</span>
    </div>

    <!-- Status -->
    <div class="flex-none w-24">
        <div class="flex items-center gap-1.5 text-xs font-semibold {{ $statusColor }}">
            <div class="w-2 h-2 rounded-full {{ $statusBg }}"></div>
            {{ $statusLabel }}
        </div>
    </div>

    <!-- User -->
    <div class="flex-none flex items-center gap-2 w-40">
        <x-mary-avatar :image="$diff->modifier->avatar_url" class="w-6 h-6" />
        <span class="text-sm truncate font-medium">{{ $diff->modifier->name }}</span>
    </div>

    <!-- Comment -->
    <div class="flex-1 min-w-0">
        <p class="text-sm text-base-content/70 truncate">{{ $diff->comment }}</p>
    </div>

    <!-- Date -->
    <div class="flex-none w-32 text-right text-xs text-base-content/50 font-mono">
        {{ $diff->created_at->diffForHumans() }}
    </div>

    <!-- Actions (Hover visible) -->
    <div class="flex-none w-24 flex justify-end opacity-0 group-hover:opacity-100 transition-opacity">
        @if($isCompareMode)
            <button class="btn btn-xs btn-outline btn-primary" xx-click.stop="setComparison">比較</button>
        @else
            <x-mary-icon name="o-chevron-right" class="w-4 h-4 text-base-content/30" />
        @endif
    </div>
</div>
```

---

## 4. 考慮事項

### 4.1 レスポンシブ対応
- **Mobile**:
    - Comment, Status, Actions の一部を非表示または別行に配置（Card Layoutにフォールバック）。
    - アバターと日時、バージョンを優先表示。
- **Desktop**:
    - フルカラム表示。2ペイン構成の余地を残す。

### 4.2 ダークモード
- `base-100`, `base-200` 等のセマンティックカラーを使用し、自動対応。
- ハイライト色は不透明度（`bg-primary/10`）で制御し、背景色に馴染ませる。

### 4.3 アクセシビリティ
- 各行は `role="button"` または `tabindex="0"`。
- キーボードナビゲーション（`ArrowUp`, `ArrowDown`）で選択変更（W2要件）。
- ARIAラベルで「Ver. 2を選択中」「Ver. 1と比較」等の状態を読み上げ。

---

## 5. 比較UXの決定事項

W2-1.3のシナリオに基づき、以下の挙動とする。
1.  **デフォルト**: リストをクリックすると、上部のDiffViewerにそのバージョンの**スナップショット（単独表示）**を表示。
2.  **比較アクション**:
    -   行右側の「比較」ボタン（あるいはモードON時の行クリック）で、**現在プレビューしているバージョン**と**クリックしたバージョン**を比較する。
    -   DiffViewerは「比較モード（Split or Diff）」に切り替わる。
    -   解除ボタンでスナップショットに戻る。


---

## 6. 柔軟性への配慮 (Flexibility Considerations)

「実装後の画面イメージが合わない場合に、構成を容易に変更したい」という要件に応えるため、以下の実装方針を採用する。

### 6.1 Slot-based Component Design
各要素（ヘッダー、行、アクション）をBladeコンポーネントの**スロット (Slots)** として定義し、親コンポーネント側でレイアウトを自由に組み替えられるようにする。これにより、例えば「バージョン番号を右端に移動したい」「アバターを非表示にしたい」といった変更が、HTML構造（Blade）の差し替えのみで完結し、ロジック修正を不要にする。

```html
<!-- 親コンポーネント (Usage Example) -->
<x-ledger.history-table :diffs="$history">
    <x-slot:header>
        <!-- ヘッダー構成をここで定義 -->
        <x-ledger.history-header-item>Version</x-ledger.history-header-item>
        <x-ledger.history-header-item>Status</x-ledger.history-header-item>
    </x-slot:header>

    <x-slot:row :diff="$diff">
        <!-- 行の中身の並び順もここで制御 -->
        <x-ledger.history-cell-version :version="$diff->version" />
        <x-ledger.history-cell-status :status="$diff->status" />
        <x-ledger.history-cell-user :modifier="$diff->modifier" />
        <!-- ... -->
    </x-slot:row>
</x-ledger.history-table>
```

### 6.2 Atomic CSS Classes (Tailwind)
スタイルはすべて Tailwind CSS のユーティリティクラスで完結させ、独自CSSクラス（`.custom-history-table` 等）への依存を排除する。これにより、レイアウト変更時にCSSファイルを行き来することなく、Bladeファイル上だけで見た目の調整が完結する。

### 6.3 Features Toggle (Configurable Props)
表示要素（アバター、コメント、日時など）のON/OFFをプロパティで制御できるようにする。
- `:showAvatar="true"`
- `:compactMode="false"`
- `:layout="'table' | 'cards'"` (将来的なレイアウト切替への布石)

