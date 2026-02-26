# Alpine.store 開閉状態同期機能の復旧

**作成日:** 2026-01-10
**対象:** LedgerLeap v12.0 / Branch: `feature/ledger-rollback`
**ステータス:** ✅ 完了・動作確認済み（第5回修正で解決）

## 0. 動作確認結果

**確認日時:** 2026-01-10
**結果:** ✅ すべての機能が正常に動作

### 最終的な解決方法

**問題の根本原因**: Livewireの`lazy`属性により、タブを切り替えてもコンポーネントが再マウントされないため、Alpine.jsの状態が更新されていなかった。

**解決策**: 各グループで200msごとにAlpine.storeの`states`オブジェクトを直接監視し、変更があれば自動的にUIを更新するポーリング方式を実装。

### 動作確認項目

- ✅ Alpine.store が正常に初期化される
- ✅ グループの開閉がlocalStorageに保存される
- ✅ 基本情報タブでの変更が更新履歴タブに反映される（200ms以内）
- ✅ 更新履歴タブでの変更が基本情報タブに反映される（200ms以内）
- ✅ 複数のグループを同時に操作しても正確に同期される
- ✅ ページリロード後も状態が保持される

## 1. 問題の概要

基本情報タブと更新履歴タブの間で、各ブロック（グループ）の開閉状態を同期する機能が失われていた。

### 症状
- 基本情報タブでグループを開閉しても、更新履歴タブには反映されない
- 更新履歴タブでグループを開閉しても、基本情報タブには反映されない
- タブを切り替えると、開閉状態がリセットされる

## 2. 原因分析

### 第1回〜第3回修正の問題点

1. **第1回**: レイアウトファイルへの配置 → 影響範囲が広すぎる
2. **第2回**: `@assets`ディレクティブの使用 → Livewire v3で正しく動作しない
3. **第3回**: `initialized`フラグの追加 → 保存は動作するが、タブ間同期が動作しない

### 第4回で発見した問題

**問題1: `@entangle`のシンタックスエラー**
```blade
x-data="{ previousTab: @entangle('selectedTab') }"
```
エラー: `Invalid character: '@'`

`@entangle`はBladeディレクティブなので、Alpine.jsの`x-data`内で直接使用できない。

**問題2: タブ変更イベントが発火しない**
上記のエラーにより、`tab-changed`イベントが全く発火せず、ストアのリロード処理が実行されない。

**問題3: 初期状態の読み込みは成功、変更の同期が失敗**
- ページを開いた時: localStorage から正しく読み込まれる ✅
- グループを開閉: localStorage に保存される ✅  
- タブを切り替え: イベントが発火せず、新しい状態が反映されない ❌

## 3. 修正内容（第3回）

### 3.1 第2回修正を踏襲（@assets ディレクティブ + ログ出力）

- Livewire v3 の `@assets` ディレクティブで Alpine.store を定義
- 詳細なログ出力を追加してデバッグを容易にする

### 3.2 初期化フラグによる $watch の制御

**根本的な問題**: $watch が初期化時に発火してしまう

**解決策**: `initialized` フラグを導入して、初期読み込み完了後のみ保存処理を実行する

```javascript
x-data="{ 
    isOpen: {{ $group['is_required_group'] ? 'true' : 'false' }},
    initialized: false  // ← 初期化フラグを追加
}"
x-init="
    // 1. まず、ストアから状態を読み込む
    if ($store.ledgerState && $store.ledgerState.currentLedgerId) {
        const stored = $store.ledgerState.isCollapsed('{{ $group['group_name'] }}', ...);
        isOpen = !stored;
    }
    
    // 2. 初期化完了フラグを立てる
    initialized = true;

    // 3. $watch を設定（初期化後のみ保存）
    $watch('isOpen', value => {
        if (!initialized) {
            console.log('[Group: {{ $group['group_name'] }}] Skipping save during initialization');
            return;  // ← 初期化中は保存しない
        }
        
        // 通常の保存処理
        if ($store.ledgerState && $store.ledgerState.currentLedgerId) {
            $store.ledgerState.states[$store.ledgerState.currentLedgerId]['{{ $group['group_name'] }}'] = !value;
            localStorage.setItem('ledger_collapsed_states', JSON.stringify($store.ledgerState.states));
        }
    });
"
```

### 3.3 処理フロー

1. **初期化開始**: `initialized = false`, `isOpen` はデフォルト値
2. **ストアから読み込み**: `isOpen` を更新（この時点では `$watch` 未設定）
3. **初期化完了**: `initialized = true`
4. **$watch 設定**: この後の変更のみが保存される
5. **ユーザー操作**: グループを開閉 → `initialized = true` なので保存される

### 3.4 変更ファイル

1. **`resources/views/livewire/ledger/show.blade.php`**（第2回修正を維持）
   - `@assets` セクションで Alpine.store を定義
   - 詳細なログ出力

2. **`resources/views/livewire/ledger/ledger-diff-viewer.blade.php`**（第3回で修正）
   - `initialized` フラグを追加
   - `$watch` の発火を制御
   - 初期化処理の順序を変更（読み込み → フラグ → $watch）

## 4. 動作確認手順（必須）

### 4.1 事前準備

1. **ブラウザ開発者ツールを開く**（F12 または Cmd+Option+I）
2. **Console タブを開く**
3. **Application → Local Storage → `ledger_collapsed_states` を削除**（初期状態から確認するため）

### 4.2 初期読み込みの確認

**目的**: 初期化時に状態が正しく読み込まれ、不要な保存が発生しないことを確認

1. **台帳詳細画面を開く**
2. **以下のログが表示されることを確認**：
   ```
   [LedgerState] Initializing Alpine.store...
   [LedgerState] Alpine.store registered successfully
   [LedgerDiffViewer] Initializing with ledgerId: XXX
   [LedgerDiffViewer] Alpine store found, calling init()
   [LedgerState] init() called with ledgerId: XXX
   [LedgerState] Created new state for ledgerId: XXX (初回のみ)
   ```

3. **各グループの初期化ログを確認**：
   ```
   [Group: グループ名] Initializing, isRequired: true/false
   [LedgerState] isCollapsed(グループ名) using default: true/false (isRequired=...)
   [Group: グループ名] Loaded from store, isOpen: true/false
   ```

4. **重要**: `Skipping save during initialization` のログが表示されることを確認
   - これが表示されない場合、初期化時に保存が発生していない（正常）
   - 複数回表示される場合、`$watch` が初期化中に発火している（異常）

### 4.3 基本情報タブでの操作確認

1. **基本情報タブで任意のグループを閉じる**
2. **以下のログが表示されることを確認**：
   ```
   [Group: グループ名] isOpen changed to: false
   [LedgerState] toggle(グループ名) to: true, Saved to localStorage
   [Group: グループ名] Saved to localStorage, collapsed: true
   ```

3. **Application → Local Storage で `ledger_collapsed_states` を確認**
   ```json
   {
     "123": {  // ledgerId
       "グループ名": true  // collapsed
     }
   }
   ```

### 4.4 タブ間同期の確認

1. **更新履歴タブに切り替える**
2. **同じグループの初期化ログを確認**：
   ```
   [Group: グループ名] Initializing, isRequired: ...
   [LedgerState] isCollapsed(グループ名): true
   [Group: グループ名] Loaded from store, isOpen: false
   ```
   - `isOpen: false` = 閉じた状態が正しく読み込まれている

3. **視覚的に確認**: 基本情報タブで閉じたグループが、更新履歴タブでも閉じていること

4. **更新履歴タブで別のグループを開く**
5. **基本情報タブに戻る**
6. **視覚的に確認**: 更新履歴タブで開いたグループが、基本情報タブでも開いていること

### 4.5 ページリロード後の確認

1. **ブラウザをリロード**（Cmd+R または F5）
2. **基本情報タブを確認**: 前回の開閉状態が保持されていること
3. **更新履歴タブを確認**: 同じ開閉状態が反映されていること

### 4.6 確認項目チェックリスト

- [ ] ブラウザコンソールに `[LedgerState]` ログが表示される
- [ ] 初期化時に `Skipping save during initialization` が表示されない（または表示されてもすぐに完了する）
- [ ] 基本情報タブでグループを開閉すると、localStorage に保存される
- [ ] 更新履歴タブでグループを開閉すると、localStorage に保存される
- [ ] タブを切り替えても、開閉状態が保持される
- [ ] 同じ台帳の異なるタブで開閉状態が同期される
- [ ] 必須グループはデフォルトで開く状態になる
- [ ] ページリロード後も開閉状態が保持される

### 4.7 エラーパターンの確認

以下のエラーが表示された場合の対処法：

**エラー: `[LedgerState] Alpine.js is not loaded!`**
- Alpine.js が読み込まれていない
- `resources/js/app.js` で Alpine.js がインポートされているか確認

**エラー: `[LedgerDiffViewer] Alpine.store(ledgerState) is not available!`**
- Alpine.store の初期化タイミングが遅い
- `@assets` が正しく配置されているか確認

**エラー: `[Group: XXX] Cannot save state - store not available`**
- グループの初期化時にストアがまだ準備できていない
- `x-init` の実行順序を確認

**問題: 開閉状態が保存されない**
- `initialized` フラグが正しく動作していない可能性
- ログで `Skipping save during initialization` の後に保存ログが表示されるか確認

**問題: タブを切り替えると状態がリセットされる**
- localStorage に保存されていない可能性
- Application → Local Storage で `ledger_collapsed_states` の内容を確認

## 5. 技術的な教訓

### 5.1 Livewire v3 での @push の制約

Livewire v3 では、コンポーネント内の `@push` ディレクティブが期待通りに動作しない場合がある。特に：

- 親コンポーネントの `@push` が子コンポーネントより後に実行される
- Alpine.js の初期化タイミングと競合する
- グローバルな状態（Alpine.store など）は、レイアウトファイルで定義すべき

### 5.2 Alpine.store の配置

Alpine.store のような**グローバルな状態管理**は、以下の理由でレイアウトファイルに配置すべき：

1. **早期実行**: すべてのコンポーネントの初期化前に確実に定義される
2. **可視性**: どのコンポーネントからもアクセス可能
3. **保守性**: 定義箇所が明確で、重複を避けられる

### 5.3 関連設計書

- [W3-1.2 表示状態引き継ぎ設計](2026-01-04_W3-1-2_display_state_sync.md)
- [W5-1.1 実施内容報告：Cycle 1 Featureテストの完了と課題解決](2026-01-05_W5-1-1_completion_report.md)

## 6. 今後の改善案

### 6.1 テストの追加

ブラウザテスト（Dusk など）で、開閉状態の同期が正しく動作することを自動テストする。

### 6.2 設定の永続化

現在は localStorage のみだが、将来的にはユーザー設定としてサーバー側にも保存することを検討する（複数デバイス間での同期）。

### 6.3 パフォーマンスモニタリング

localStorage への書き込み頻度が高い場合、デバウンス処理を追加することを検討する。

---

**修正完了:** 2026-01-10
**修正者:** GitHub Copilot

