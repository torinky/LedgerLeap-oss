# 2026-04-19 Issue 161 Breadcrumbs and Supporting Components Plan

## Goal

台帳詳細ページの補助コンポーネントについて、文言と役割の境界を整理する。

## Scope

### 主な対象

- `resources/views/components/ledger/livewire-breadcrumbs.blade.php`
- `resources/views/components/expandable-content.blade.php`
- `resources/views/livewire/ledger/show.blade.php`

### 関連翻訳

- `lang/ja/ledger/ui.php`
- `lang/ja/ledger/diff.php`
- `lang/ja/ledger/misc_components.php`

## Current work

- `livewire-breadcrumbs` のハードコードされた `Top` を翻訳キーへ移行
- `show.blade.php` で詳細ページのメタ情報と補助文言の役割を確認
- `expandable-content` の利用箇所を確認し、差分本文の補助に限定すべきかを判断

## Initial observations

- `Top` はパンくずの先頭導線として使われているが、直書きのままだと詳細ページ全体の翻訳方針とずれる
- `expandable-content` は差分表示以外にも使われており、いきなり用途を狭めるより先に利用実態を整理する必要がある
- 詳細ページのメタ情報は、版・更新者・更新日時のような主役情報と補助情報を分けて扱うのが適切

## Verification plan

- `ShowTest` を中心に関連 Feature test を実行する
- パンくずの表示と route 生成が壊れていないことを確認する
- 翻訳キー未使用の主要文言が残っていないことを確認する
