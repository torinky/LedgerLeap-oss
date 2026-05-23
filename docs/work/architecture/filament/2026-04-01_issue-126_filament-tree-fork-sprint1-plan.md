# Issue #126 Sprint 1 Plan: `15web/filament-tree` fork 基盤化

**関連 Issue**: [#126](https://github.com/torinky/LedgerLeap/issues/126)

## 1. 背景

`Issue #126` では、LedgerLeap の tree 機能を `15web/filament-tree` から **fork / 強制組み込み** へ切り替える方針を採っています。

Sprint 1 は、その移行を実装に入るための **fork 基盤作成** に限定します。ここでは tree の見た目や個別モデルの挙動はまだ大きく変えず、まずは「LedgerLeap 管理下のローカル package として参照できる状態」を作ります。

## 2. Sprint 1 の目的

- `15web/filament-tree` を repo 内のローカル fork に移す
- Composer から fork 版 package を参照できるようにする
- Filament 4 / Livewire 4 移行を前提に、package 側の受け口を開く
- 以降の Sprint 2 で API / 実装差分を詰められる土台を作る

## 3. Sprint 1 の作業分解

### 3.1 ローカル fork の配置作成

- `vendor/15web/filament-tree` 相当の package を repo 内へコピーする
- 配置先は `packages/15web/filament-tree` を採用する
- package の `composer.json` が保持されることを確認する

**完了条件**: ローカル fork の実体が repo 内にあり、以降の Composer 参照先として使える状態になっている。

### 3.2 Composer 参照先の切り替え

- ルート `composer.json` に path repository を追加する
- 既存の `require` にある `15web/filament-tree` をそのままローカル fork に向ける
- path repository の優先順位が期待どおりであることを確認する

**完了条件**: Composer が `packages/15web/filament-tree` を優先参照できる。

### 3.3 package メタデータの最小更新

- fork 側 `composer.json` の依存制約を現行の Laravel / Filament / Livewire 事情に合わせる
- package 名 / namespace / auto-discovery が root から見て破綻しないことを確認する
- 今後の Sprint 2 でコード互換修正を入れても、引き続き Composer 解決できる状態にする

**完了条件**: package のメタデータが root の依存解決を阻害しない。

### 3.4 起動確認と最小スモークチェック

- `composer install` / `composer update` のどちらで反映するかを決める
- Laravel の package discovery が落ちないことを確認する
- `TreePage` / `InteractsWithTree` を使う既存コードが即時に壊れていないか確認する

**完了条件**: 既存アプリが fork 版 package を読み込んだ状態で起動できる。

## 4. Sprint 1 の実施順序

1. ローカル fork ディレクトリを作る
2. package 一式をコピーする
3. root `composer.json` を path repository 化する
4. fork 側 `composer.json` を最小更新する
5. Composer 解決と package discovery を確認する

## 5. 事前チェックポイント

- `Issue #124` は凍結済みで、tree 移行の主系は `Issue #126` にある
- 現行の tree 見た目は変えず、まずは参照先だけを内製化する
- Sprint 1 では UI 改修や `Folder` / `Organization` 個別調整には入らない
- Filament 4 / Livewire 4 への実装差分は Sprint 2 に送る

## 6. 受け入れ条件

- [ ] `packages/15web/filament-tree` に fork 実体がある
- [ ] ルート `composer.json` が path repository を参照している
- [ ] `composer install` または `composer update` で fork 版 package を解決できる
- [ ] package discovery が成功する
- [ ] 既存の tree 関連ページが起動時に致命的エラーにならない

## 7. Issue 連携メモ

- 進捗報告は `Issue #126` に集約する
- Sprint 1 完了時には、この文書と `Issue #126` の両方に完了報告を残す
- 旧方針側の `Issue #124` は凍結コメントのみ残し、作業は進めない

## 8. Sprint 1 着手時に確認したブロッカー

- `composer update 15web/filament-tree --with-all-dependencies` を試行したところ、`codewithdennis/filament-select-tree` が `filament/forms ^3` を要求しており、Filament 4 系への解決が止まった。
- そのため、`15web/filament-tree` の fork だけでは Composer lock の更新まで完走できない。
- Sprint 1 を完了条件まで進めるには、`SelectTree` 側の対応方針（削除・代替・fork）の別整理が必要。
- 現時点では、fork 基盤の配置と path repository の準備までは進めたが、lock の更新は保留として扱う。

