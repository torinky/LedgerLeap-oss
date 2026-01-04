# W3-1.2 表示状態引き継ぎ設計

**最終更新:** 2026-01-04
**対象:** LedgerLeap v12.0 / Branch: `feature/ledger-rollback`
**ステータス:** Draft

## 1. 目的

詳細画面（`Show.php`）で設定された `displayLevel`（表示レベル）や、ユーザーが操作したグループ開閉状態を、更新履歴タブ内の `LedgerHistoryManager` および `LedgerDiffViewer` に**矛盾なく引き継ぐ**ための方針を定義する。Phase 1 / Cycle 1 の範囲では、以下を満たすことを優先する。

- 基本情報タブと更新履歴タブで、「おおよそ同じ見え方」を維持できること
- Livewire の制約（単一配列での状態管理）およびパフォーマンスを尊重すること
- 将来の Cycle 2 以降で、検索フィルタや任意2バージョン比較の状態同期に拡張できること

> [!NOTE]
> このドキュメントは **Phase 1 / Cycle 1** における方針を定義する。Cycle 2 以降で双方向同期やフィルタ状態の共有を拡張する前提で、「壊さずに伸ばせる設計」を意識する。

---

## 2. 表示レベル (`displayLevel`) の同期

### 2.1 データフロー（Phase 1 / Cycle 1）

`Show` (Parent) → `LedgerHistoryManager` (履歴タブの Livewire Root) → `LedgerDiffViewer` (差分表示コンポーネント)

- `Show` が唯一の **Single Source of Truth** として `displayLevel` を持つ。
- 履歴タブ・差分コンポーネントは、`Show` から渡された値を参照する **読み取り専用** の立場とする。

### 2.2 実装方針（単方向同期）

#### 2.2.1 Parent (`Show.php`)
- プロパティ:
  - `public int $displayLevel = 2; // 1=最小, 2=標準, 3=詳細`
- 基本情報タブ上の UI 操作で `$displayLevel` を更新する。
- 更新履歴タブの Livewire コンポーネント呼び出し時に、常に最新の `$displayLevel` を渡す。

**Show.blade.php（イメージ）**
```blade
<livewire:ledger.ledger-history-manager
    :ledgerId="$ledgerRecord->id"
    :displayLevel="$displayLevel"
    wire:key="history-manager-{{ $ledgerRecord->id }}"
/>
```

#### 2.2.2 Child (`LedgerHistoryManager.php`)
- 公開プロパティ:
  - `public int $displayLevel;`
- マウント時に `displayLevel` を受け取り、その値を自身および子コンポーネントへ伝搬する。
- Phase 1 / Cycle 1 では、**Show → History の単方向同期** とし、履歴タブ側から `displayLevel` を変更しない。

**LedgerHistoryManager.php（イメージ）**
```php
class LedgerHistoryManager extends Component
{
    public int $ledgerId;
    public int $displayLevel;

    public function mount(int $ledgerId, int $displayLevel): void
    {
        $this->ledgerId = $ledgerId;
        $this->displayLevel = $displayLevel;
    }

    public function render()
    {
        return view('livewire.ledger.history-manager', [
            'diffs' => $this->loadDiffs(),
        ]);
    }
}
```

#### 2.2.3 GrandChild (`LedgerDiffViewer.php`)
- `LedgerHistoryManager` から `displayLevel` を受け取り、表示カラム・詳細部分の出し分けに利用する。
- 自身から `displayLevel` を変更したり、親へイベントで戻す処理は Phase 1 / Cycle 1 では**実装しない**。

**LedgerHistoryManager.blade.php（イメージ）**
```blade
<livewire:ledger.diff-viewer
    :ledgerId="$ledgerId"
    :baseDiffId="$selectedDiffId"
    :displayLevel="$displayLevel"
    :collapsedStates="$collapsedStates"
    wire:key="ledger-diff-viewer-{{ $ledgerId }}-{{ $selectedDiffId }}"
/>
```

### 2.3 今後の拡張余地（メモ）

- Livewire v3 の `#[Reactive]` 属性を使うと、親から子への自動追従がしやすくなるが、Phase 1 / Cycle 1 では「イベント + 明示的プロパティ渡し」を基本にし、安定運用を優先する。
- Cycle 2 以降に、`displayLevel` を含む「表示状態DTO」（単一配列）を導入しても壊れないよう、プロパティ名・責務をシンプルに保つ。

---

## 3. グループ開閉状態（collapsedStates）の扱い

### 3.1 Phase 1 / Cycle 1 の方針

- `collapsedStates` は、**主に Show 側の UX を担保するための状態** と定義する。
- 履歴タブ・差分ビューでは、**Show 側の「初期状態」を参考にしつつ、各ビュー内でローカルに完結させる**。
- サーバー往復やシリアライズコストを増やさないため、Phase 1 / Cycle 1 ではタブ間のリアルタイム完全同期は行わない。

### 3.2 データ構造

- 型: `array`
- 構造: `['group_key' => bool]`
  - `group_key`: `basic_info`, `workflow`, `attachments` など、column_define_id やセクション識別子に基づく安定キー。
  - 値: `true` = 折りたたみ中, `false` = 展開中。

### 3.3 データフロー

1. `Show` コンポーネント
   - `public array $collapsedStates = [];`
   - 基本情報タブの開閉UIで `$collapsedStates[$groupKey]` を更新。

2. `LedgerHistoryManager` コンポーネント
   - `mount` 時に `collapsedStates` を受け取り、自身のプロパティにコピー。
   - `LedgerDiffViewer` に初期値として渡す。

3. `LedgerDiffViewer` コンポーネント
   - 受け取った `collapsedStates` を用いて**初期描画時の開閉状態のみを決定**する。
   - その後の開閉は Alpine.js 側のローカル状態で管理し、Phase 1 / Cycle 1 では Livewire プロパティを更新しない。

---

## 4. Alpine.js / LocalStorage の利用方針

Phase 1 / Cycle 1 では、以下のような「軽量なローカル状態管理」を導入する。ただし、**本番採用は PM 判断** とし、少なくとも履歴タブ側では適用できるようにしておく。

### 4.1 Alpine.store + LocalStorage パターン（候補案）

```javascript
document.addEventListener('alpine:init', () => {
    Alpine.store('ledgerState', {
        collapsed: {},
        ledgerId: null,

        init(ledgerId) {
            this.ledgerId = ledgerId;
            const key = `ledger_collapsed_${this.ledgerId}`;
            const stored = localStorage.getItem(key);
            if (stored) {
                this.collapsed = JSON.parse(stored);
            }
        },

        toggle(groupName) {
            this.collapsed[groupName] = !this.collapsed[groupName];
            const key = `ledger_collapsed_${this.ledgerId}`;
            localStorage.setItem(key, JSON.stringify(this.collapsed));
        },

        isCollapsed(groupName) {
            return !!this.collapsed[groupName];
        }
    })
})
```

### 4.2 履歴タブでの利用例（イメージ）

```html
<div x-data x-init="$store.ledgerState.init({{ $ledgerId }})">
    <!-- Group Loop -->
    <div class="collapse"
         :class="{ 'collapse-open': !$store.ledgerState.isCollapsed('{{ $groupName }}'), 'collapse-close': $store.ledgerState.isCollapsed('{{ $groupName }}') }"
         :aria-expanded="!$store.ledgerState.isCollapsed('{{ $groupName }}')">
        <div class="collapse-title" @click="$store.ledgerState.toggle('{{ $groupName }}')" role="button" tabindex="0">
            {{ $groupName }}
        </div>
        <!-- ... -->
    </div>
</div>
```

> [!NOTE]
> - Phase 1 / Cycle 1 では、最低限「履歴タブ (`LedgerHistoryManager` / `LedgerDiffViewer`) 側」での採用を優先する。
> - `Show` 側の開閉UIを同ストアに揃えるリファクタリングは**推奨**だが、必須ではない（開発コストとリスクを見て判断）。

### 4.3 構造差異（Structural Mismatch）への対応

- 過去/現在でカラム構成が異なり、グループ名が一致しないケースを想定する。
- 方針:
  - 開閉状態は「グループ名（文字列キー）」に対するアドバイザリ状態（Advisory State）とみなし、存在しないグループには適用しない。
- 挙動:
  - 現在も過去も存在するグループ: ストアの状態に従う。
  - 現在のみ存在するグループ: 過去ビューには出ないため影響なし。
  - 過去のみ存在するグループ: ストアにキーが無いため、デフォルトの開閉状態に従う。

---

## 5. `ShowDiff`（専用履歴画面）への適用

- 同じ `ledgerId` を用いることで、`Show` → 更新履歴タブ → `ShowDiff`（専用履歴画面）へと、**一貫した開閉状態**を引き継げる余地を残す。
- Phase 1 / Cycle 1 では、まず更新履歴タブへの適用を優先し、`ShowDiff` への適用は後追いでも良い。

---

## 6. PM 判断が必要な事項

### 6.1 `displayLevel` の同期方向

> [!NOTE]
> **実装方針:** Phase 1 / Cycle 1 では、まず「案B: 初期値はサーバー値、操作中の変更はフロント内で完結」を採用し、UIテスト・パフォーマンステストの結果に応じて「案A: 完全にShow主導の単方向同期」へ撤退できるように実装を分離する。

- 案A: 親（Show）→ 子（履歴タブ/差分ビュー）の単方向同期
  - 想定される実装:
    - `Show` から `displayLevel` を prop で渡すのみ。
    - 履歴タブ側では `displayLevel` を**表示専用**として扱い、変更UIは持たない（もしくは持ってもフロントローカルに閉じる）。
  - メリット:
    - 実装がシンプルで、`Show` を単一の真の状態源として扱える。
    - 予期せぬイベントループやちらつきのリスクが小さい。
  - デメリット:
    - 履歴タブ側から表示レベルを柔軟に切り替えることができない。

- 案B: 初期値はサーバー値、操作中の変更はフロントエンド内で完結（**Phase 1 / Cycle 1 の初期採用案**）
  - 想定される実装:
    - `LedgerHistoryManager` に `displayLevelLocal` を追加する。
      ```php
      public int $displayLevel;      // サーバーからの初期値
      public int $displayLevelLocal; // 画面内で利用するローカル値

      public function mount(int $ledgerId, int $displayLevel): void
      {
          $this->ledgerId = $ledgerId;
          $this->displayLevel = $displayLevel;
          $this->displayLevelLocal = $displayLevel;
      }

      public function setDisplayLevelLocal(int $level): void
      {
          $this->displayLevelLocal = $level;
      }
      ```
    - Blade では `displayLevelLocal` を UI バインドおよび `LedgerDiffViewer` への入力に使用する。
      ```blade
      <select wire:model="displayLevelLocal">
          <option value="1">最小</option>
          <option value="2">標準</option>
          <option value="3">詳細</option>
      </select>

      <livewire:ledger.diff-viewer
          :displayLevel="$displayLevelLocal"
          ...
      />
      ```
    - `Show` 側へイベントは送らず、画面表示中の変更は履歴タブ内で完結させる。
  - メリット:
    - 初期状態は `Show` と揃えつつ、履歴タブ内で柔軟に表示レベルを切り替えられる。
    - 差分ビューの描画は常に `displayLevelLocal` を見るだけでよく、シンプル。
  - デメリット:
    - 基本情報タブと履歴タブで表示レベルが一時的にズレることを許容する前提になる。

#### 6.1.1 撤退戦略（B→A のロールバックパス）

- 実装分離の方針:
  - `displayLevelLocal` とそれを操作するメソッド（例: `setDisplayLevelLocal()`）を1か所に集約し、フラグで有効/無効を切り替えられるようにする。
  - Blade 側も `displayLevelLocal` を使うUI部分をコンポーネント・セクションにまとめ、案Aに戻す際はこのUIを無効化または非表示にするだけで済むようにする。
- コンフィグ/フラグ:
  - `config/ledgerleap.php` などに `ledger_diff.display_level_local_control` （bool）を用意し、履歴タブ内でのローカル制御の ON/OFF を切り替え可能にする。
  - フラグOFF時は `displayLevelLocal` を廃止し、常に `$displayLevel`（Show 由来）だけを使う実装に切り替える。
- テスト計画:
  - UIテストで以下を確認:
    - ローカル制御ON時: 履歴タブ内の表示レベル切り替えが差分ビューに即反映されるが、基本情報タブの表示レベルは変わらないこと。
    - ローカル制御OFF時: 基本情報タブでの設定がそのまま履歴タブにも反映され、履歴タブ側の切り替えUIは無効/非表示になっていること。

### 6.2 `collapsedStates` の扱いレベル

> [!NOTE]
> **実装方針:** Phase 1 / Cycle 1 では、「案B: 初期値はサーバー値、操作中の変更はフロント内（Alpine.store/LocalStorage）で完結」を採用し、必要に応じて「案A: 初期値共有のみ」に戻せるように実装を分離する。

- 案A: 初期値共有のみ
  - 想定される実装:
    - `Show` → `LedgerHistoryManager` → `LedgerDiffViewer` への初期 `collapsedStates` 渡しのみ。
    - その後の開閉操作は、各ビュー内の Alpine ローカル状態で完結させ、Livewire プロパティは更新しない。
  - メリット:
    - Livewire のシリアライズ負荷を抑えつつ、「おおよその見え方」を履歴タブにも引き継げる。
    - 実装負荷が低く、既存コードへの影響も小さい。
  - デメリット:
    - タブ間で折りたたみ状態を完全には同期できない（同一ブラウザ内でさえ）。

- 案B: 初期値はサーバー値、開閉操作はフロント内で共有（Alpine.store/LocalStorage）
  - 想定される実装:
    - 初期表示時に `Show` から受け取った `collapsedStates` をもとに、Alpine.store (`ledgerState.collapsed`) の初期値を構成する（必要に応じてマージ）。
    - 以降の開閉操作はすべて `ledgerState.toggle(groupName)` を通じて行い、その状態を LocalStorage に保存する（4.1〜4.2 参照）。
    - 履歴タブや `ShowDiff` など、同一ブラウザ・同一 `ledgerId` のコンポーネントは、`ledgerState.init(ledgerId)` を通じて同じ状態を再利用する。
    - 開閉状態の変更はサーバーに送信せず、`Show` 側の `$collapsedStates` は画面表示中には更新しない（サーバーの「正」は持たない）。
  - メリット:
    - 同一ブラウザ内では、基本情報タブ→履歴タブ→専用履歴画面の間で、自然な開閉状態の引き継ぎが実現できる。
    - サーバーイベントが発生しないため、パフォーマンス面のリスクが小さい。
  - デメリット:
    - 別ブラウザ・別端末間では開閉状態が同期されない（LocalStorageベースのため）。
    - サーバー側に「正」の開閉状態を持たない前提になる。

#### 6.2.1 撤退戦略（B→A のロールバックパス）

- 実装分離の方針:
  - Alpine.store/LocalStorage を利用するコード（`ledgerState.init`/`toggle`/`isCollapsed`）を明確に切り出し、Blade 側での使用箇所を限定する。
  - 案Aに戻す際は、Alpine.store を利用せず、`collapsedStates` をそのままローカル x-data にコピーして使うシンプルな実装に差し替えられるようにしておく。
- コンフィグ/フラグ:
  - `config/ledgerleap.php` 等に `ledger_diff.collapsed_states_frontend_store` （bool）を追加し、Alpine.store/LocalStorage を使うかどうかを切り替えられるようにする。
  - フラグOFF時は、4.1/4.2 のストア初期化をスキップし、各ビューが単純な `x-data="{ collapsed: @js($collapsedStates) }"` のようなローカル状態だけを持つようにする。
- テスト計画:
  - UIテストで確認するポイント:
    - フロントストアON時: 同一ブラウザ内でタブ/画面を行き来しても、開閉状態が引き継がれること。
    - フロントストアOFF時: 初回表示時にはShowの状態が反映されるが、以降は各ビューごとに独立していること。
  - パフォーマンス・負荷テスト:
    - LocalStorage 読み書きがパフォーマンスに与える影響を測定し、問題があれば案A固定に戻す判断材料とする。

---

## 7. まとめ（Phase 1 / Cycle 1 の前提）

| 状態 | 同期元 | 同期先 | 手法 | 備考 |
|---|---|---|---|---|
| `displayLevel` | `Show` (Parent) | `LedgerHistoryManager` → `LedgerDiffViewer` | 初期値: Prop（単方向） / 操作中: 履歴タブ内のローカル値 (`displayLevelLocal`) | フラグでローカル制御のON/OFFを切り替え可能。 |
| `collapsedStates` | `Show` (初期状態) | `LedgerHistoryManager` → `LedgerDiffViewer` | 初期値: Prop / 操作中: Alpine.store + LocalStorage でフロント内共有（ON時） | フラグでAlpine.store利用のON/OFFを切り替え、単純なローカル状態に戻せる。 |

この設計により、Phase 1 / Cycle 1 では **「初期値はサーバー値」「操作中の変更はフロント内で完結」** という案Bを試しつつ、問題があれば即座に案A（完全にShow主導の単方向同期/初期値共有のみ）へ切り替えられる。Cycle 2 以降は、実際の利用状況とテスト結果に基づき、どちらを正式採用するかを再評価する。
