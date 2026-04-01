# Issue #126 Dependency Inventory & Migration Targets

**関連 Issue**: [#126](https://github.com/torinky/LedgerLeap/issues/126)

## 1. 調査目的

LedgerLeap の Livewire 4 / Filament 4 移行について、
- 現在の依存関係のうち何が実際に v4 移行を阻害しているか
- 代替・移行先が存在するか
- いま migration を進めるべきか、それとも一時的にペンディングすべきか

を確認するための調査記録。

## 2. 現在の Filament / Livewire 系依存

### 2.1 ルート `composer.json` の require

- `filament/filament`: `^4`
- `livewire/livewire`: `^4`
- `15web/filament-tree`: `*`
- `codewithdennis/filament-select-tree`: `^3`

### 2.2 実際の composer 解決結果

- `filament/*` 系: `v3.3.49`
- `livewire/livewire`: `v3.7.11`
- `codewithdennis/filament-select-tree`: `v3.1.58`
- `15web/filament-tree`: `1.0.3`（ローカル path fork を参照）

## 3. 依存ごとの v4 対応可否

### 3.1 `15web/filament-tree`

- **現状**: repo 内に `packages/15web/filament-tree` を置き、path repository で参照可能にした。
- **移行先の有無**: あり。ローカル fork をそのまま移行先として使える。
- **補足**: fork 側 `composer.json` は `filament/support ^4.0` に寄せ済み。

### 3.2 `codewithdennis/filament-select-tree`

- **現状**: インストール済みは `v3.1.58`。
- **v4 対応版の有無**: あり。`v4.0.18` が存在し、`filament/forms ^4.0` を要求する。
- **ブロッカー性**: 現在の `v3.1.58` が `filament/forms ^3.0` を要求しているため、Filament 4 への更新を止めている。
- **判断**: ここは **置換・削除・fork のいずれかを Sprint 1/2 で決める必要がある**。

### 3.3 `althinect/filament-spatie-roles-permissions`

- **現状**: 現時点の root 依存からは外れている。
- **v4 対応版の有無**: あり。`v3.3.1` が `filament/filament ^4.0 || ^5.0` を要求する。
- **判断**: 現在の migration ブロッカーではないが、将来再導入する場合の候補としては v4 対応がある。

## 4. Composer の `why-not` で確認できた阻害チェーン

### `filament/support ^4`

- `codewithdennis/filament-select-tree v3.1.58` が `filament/forms ^3.0` を要求
- `filament/forms v3.3.49` が `filament/support v3.3.49` を要求
- `filament/support v3.3.49` が `livewire/livewire ^3.5` を要求

### `livewire/livewire ^4`

- `filament/support v3.3.49` が `livewire/livewire ^3.5` を要求

### `filament/forms ^4`

- `codewithdennis/filament-select-tree v3.1.58` が `filament/forms ^3.0` を要求
- そのため、Filament 4 系への更新は select-tree の現行版で止まる

### 追加確認: Filament 4 系は Livewire 4 ではなく Livewire 3 系を要求する

- `filament/support v4.9.3` は `livewire/livewire ^3.5` を要求している
- つまり、`filament/filament ^4` と `livewire/livewire ^4` は現行 upstream の組み合わせでは同時成立しない
- このため、`SelectTree` を `^4` に上げても、Livewire 4 への移行自体はまだ成立しない

## 5. 判断

### 結論

**Livewire 4 / Filament 4 への移行は、完全な意味ではまだペンディング推奨。**

ただし理由は「移行先が存在しない」からではなく、**現行の依存バージョンがまだ v3 に固定されているため**。

### 進められる部分

- `15web/filament-tree` の fork 化は進められる
- `codewithdennis/filament-select-tree` には v4 系が存在するため、代替・更新の道はある
- `althinect/filament-spatie-roles-permissions` も v4 対応版が存在するため、将来の再導入候補はある

### いま追加で判明した点

- `SelectTree` を更新しても、Filament 4 が Livewire 3 を要求するため、Livewire 4 まで含めた一括移行は upstream の制約上進められない
- そのため、`Issue #124` の本来テーマである「Livewire 4 への移行」は、少なくとも Filament 4 採用と同時には成立しない
- 参考として、`filament/support v5.0.0` は `livewire/livewire ^4.0` を要求しており、Livewire 4 を本当に使うなら Filament 5 系の再評価が必要になる

### まだ止めるべき部分

- ルート `composer.lock` の v3 固定解除
- `codewithdennis/filament-select-tree` の更新方針確定前の一気通貫な Filament 4 本体更新

## 6. 次アクション

1. `SelectTree` を **更新 / 代替 / fork / 削除** のどれにするか決める
2. その方針に沿って `composer.json` の constraint を v4 に寄せる
3. `composer update` を再実行し、`filament/*` と `livewire/livewire` を一括で解決する
4. その後に `Issue #126` の Sprint 1 完了判定を行う

### 補足

- もし Livewire 4 を優先するなら、Filament 4 ではなく Filament 5 系へ切り替える前提で再計画する方が整合的
- もし Filament 4 を優先するなら、Livewire 3 のまま進める判断が必要

## 8. 今回の SelectTree 更新試行結果

- `composer.json` の `codewithdennis/filament-select-tree` を `^4` に変更して更新を試行した
- しかし `filament/support v4.9.3` が `livewire/livewire ^3.5` を要求しており、`livewire/livewire ^4` と衝突した
- したがって、`SelectTree` 更新だけでは Sprint 1 は完了できない

## 7. 参照

- `docs/work/architecture/filament/2026-04-01_issue-126_filament-tree-fork-sprint1-plan.md`
- `Issue #126`
- `composer.json`
- `composer.lock`

