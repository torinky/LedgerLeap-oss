# Issue #134 Sprint 2 Retro: RelatedLedgers の displayLevel loading 境界

**作成日**: 2026-04-04  
**関連 Issue**: #134  
**状態**: proven / feature-local  
**last_confirmed_at**: 2026-04-04  
**recheck_after**: 2026-05-04  
**recheck_trigger**: `RelatedLedgers` の表示レベル更新経路や Livewire の loading target ルールを再設計するとき

## 要点

- `RelatedLedgers` の表示レベル切り替えは、`$parent` 直結よりも **child-local の request を起点** にした方が loading 開始が早く、ボタン押下後の待ち時間が短く見えた。
- `placeholder()` は初回表示専用、`wire:loading` / `wire:target` は更新専用に分けると、初回 skeleton と更新時 loading の責務が混ざりにくい。
- `wire:loading.delay` は、短い更新での loading 点滅を避けるのに有効だった。
- `displayLevelUpdated` の既存イベント同期は残しつつ、`relatedDisplayLevelRequested` を child から送ることで、親子の責務を分離できた。

## 実装メモ

### 変更したファイル
- `app/Livewire/Ledger/RelatedLedgers.php`
- `app/Livewire/Ledger/Show.php`
- `resources/views/livewire/ledger/related-ledgers.blade.php`
- `resources/views/livewire/ledger/show.blade.php`
- `resources/views/livewire/ledger/related-ledgers-placeholder.blade.php`
- `tests/Feature/Livewire/Ledger/RelatedLedgersTest.php`
- `tests/Feature/Livewire/Ledger/ShowAdditionalTest.php`

### 検証
- `./vendor/bin/sail test tests/Feature/Livewire/Ledger/RelatedLedgersTest.php --filter='it_targets_update_display_level_in_loading_overlay|it_updates_display_level_from_event'`
- `./vendor/bin/sail test tests/Feature/Livewire/Ledger/ShowAdditionalTest.php --filter='it_updates_display_level_on_event|it_syncs_display_level_requested_from_related_ledgers'`
- `./vendor/bin/sail test tests/Feature/Livewire/Ledger/RelatedLedgersTabTest.php`

## 判断

- **docs/work に残す**: `RelatedLedgers` 固有の loading 境界と同期順序は feature-local で、まだ 1 つの実装実績に留まる。
- **.github に昇格候補**: 「初回表示は placeholder、更新は wire:loading/target、親子同期は request 境界を揃える」は、同種の tab UI で再利用できるため、今後複数箇所で再発するなら instructions/skill へ移行候補。

## 参考

- `/.github/skills/livewire-loading-ui/SKILL.md`
- `/.github/skills/livewire-tenant-context/SKILL.md`
- `docs/work/ui-ux/2026-03-21_issue-116-sprint2-result.md`
- `docs/work/ui-ux/2026-03-28_issue-120-tab-switch-performance-note.md`

