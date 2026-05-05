# Issue #204 Sprint 1 完了レポート

## 完了日
2026-05-05

## 対象
- Issue: [#204](https://github.com/torinky/LedgerLeap/issues/204)
- HAR: `localhost3.har`, `localhost4.har`

---

## 発見事項

### ❌ non-lazy バンドルが発生する最短操作手順

**「一度訪れたフォルダに戻る」ことで、#[Lazy] が解除される。**

localhost4.har のフォルダ切替シーケンス：

| # | currentFolderId | lazy | time | 備考 |
|---|-----------------|------|------|------|
| 1 | 3 | ✅ | 848ms | 初回選択 |
| 2 | 7 | ✅ | 628ms | 別フォルダへ切替 |
| 3 | 10 | ✅ | 924ms | 別フォルダへ切替 |
| 4 | **3** | ❌ | **9558ms** | **フォルダ3に戻る** |
| 5 | **3** | ❌ | **7073ms** | **同じフォルダ3** |
| 6 | **3** | ❌ | **2362ms** | **同じフォルダ3** |

**パターン:**
- 新しいフォルダへ切替 → lazy ✅（新規コンポーネントが lazy ロードされる）
- **一度訪れたフォルダに戻る** → lazy ❌（既存コンポーネントが再利用され、親と同じバッチになる）

### localhost3.har で lazy%=100% だった理由

localhost3.har では2回のフォルダ切替しか行っておらず、両方とも「新しいフォルダ」への切替だった：
1. フォルダA → フォルダB（新規）
2. フォルダB → フォルダC（新規）

「一度訪れたフォルダに戻る」操作がなかったため、lazy%=100% が維持された。

---

## 根本原因

### 1. `wire:key` がフォルダIDに基づいている

`resources/views/livewire/ledger/index-manager.blade.php` (line 344):
```blade
wire:key="ledger-records-table-folder-{{ $currentFolderId }}"
```

同じフォルダに戻ると、Livewire は既存の RecordsTable コンポーネントを再利用する。

### 2. Livewire の `#[Lazy]` ライフサイクル

`vendor/livewire/livewire/src/Features/SupportLazyLoading/SupportLazyLoading.php` を分析した結果：

- **初回マウント時**: `lazyLoaded: false` が memo に設定され、placeholder が表示される
- **`__lazyLoad` 呼び出し後**: `lazyLoaded: true` が memo に設定される
- **その後の更新**: `lazyLoaded: true` なので通常コンポーネントとして動作し、親の更新に追随する

つまり、`#[Lazy]` は「初回マウント時の遅延ロード」のみを制御し、**一度マウントされた後は通常コンポーネントと同じ動作**になる。

### 3. `isolate: false` の影響

`RecordsTable` と `RecordsTableRow` は `#[Lazy(isolate: false)]` を持っている：

```php
#[Lazy(isolate: false)]
class RecordsTable extends BaseLivewireComponent { ... }

#[Lazy(isolate: false)]
class RecordsTableRow extends BaseLivewireComponent { ... }
```

`isolate: false` は「複数の lazy コンポーネントを1つのネットワークリクエストにバンドルする」（= `bundle: true`）という意味だが、これは**初回 lazy ロード時**の動作のみを制御する。

一度マウントされた後は、親 (IndexManager) が更新されると、子 (RecordsTable, RecordsTableRow) も同じ `/livewire/update` バッチに含まれる。

---

## 再現手順（最小）

1. ページを開く
2. フォルダAを選択（RecordsTable が lazy ロードされる）
3. フォルダBを選択（RecordsTable が lazy ロードされる）
4. **フォルダAに戻る**（RecordsTable が既存コンポーネントとして再利用され、IndexManager と同じバッチでレンダリングされる）
5. HAR で `IM + RT + Row` が1つのリクエストにバンドルされていることを確認

---

## 証拠

### HAR 分析結果

```
localhost4.har:
  [ 35] IM currentFolderId=3  (✅ lazy, 848ms)
  [ 44] IM currentFolderId=7  (✅ lazy, 628ms)
  [ 71] IM currentFolderId=10 (✅ lazy, 924ms)
  [ 94] IM currentFolderId=3  (❌ non-lazy, 9558ms) ← フォルダ3に戻る
  [136] IM currentFolderId=3  (❌ non-lazy, 7073ms) ← 同じフォルダ
  [158] IM currentFolderId=3  (❌ non-lazy, 2362ms) ← 同じフォルダ
```

### ❌ non-lazy バンドルの内容

```
[ 94] 9558ms  640KB  req:[ledger.index-manager, ledger.records-table, ledger.records-table-row, ...]
[136] 7073ms  581KB  req:[ledger.index-manager, ledger.records-table, ledger.records-table-row, ...]
[158] 2362ms  442KB  req:[ledger.index-manager, ledger.records-table, ledger.records-table-row, ...]
```

すべて `IM + RT + N×Row` が同一バッチ。

---

## 次のステップ（Sprint 2）

### 調査項目

1. **修正方針の検討**:
   - 方針A: `wire:key` にタイムスタンプやランダム値を追加し、毎回新規コンポーネントとして扱う
   - 方針B: `isolate: true` に変更し、RecordsTableRow を独立したリクエストに分離する
   - 方針C: RecordsTable を `#[Lazy]` から外し、IndexManager の render で非同期ロードを手動実装する

2. **各修正方針の影響評価**:
   - 方針A: 同じフォルダに戻った場合、レコードの状態がリセットされる（スクロール位置など）
   - 方針B: リクエスト数が増加するが、lazy 分離は維持される
   - 方針C: 実装コストが高い

3. **推奨方針**:
   - 方針B（`isolate: true`）が最もシンプルで、期待される lazy 動作に近い
   - ただし、RecordsTableRow の数が多い場合、リクエスト数が増加するため、パフォーマンス影響を測定する必要がある

---

## 関連ファイル

- `app/Livewire/Ledger/RecordsTable.php`
- `app/Livewire/Ledger/RecordsTableRow.php`
- `app/Livewire/Ledger/IndexManager.php`
- `resources/views/livewire/ledger/index-manager.blade.php`
- `resources/views/components/ledger/table-row.blade.php`
- `vendor/livewire/livewire/src/Features/SupportLazyLoading/SupportLazyLoading.php`
