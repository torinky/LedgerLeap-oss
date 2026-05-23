# Laravel 13 アップグレード振り返り

**status:** complete  
**last_updated_at:** 2026-04-04  
**related_memo:** `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md`  
**related_reports:** `docs/work/architecture/2026-04-04_laravel-13-sprint0-completion-report.md`, `docs/work/architecture/2026-04-04_laravel-13-sprint1-completion-report.md`, `docs/work/architecture/2026-04-04_laravel-13-sprint2-completion-report.md`, `docs/work/architecture/2026-04-04_laravel-13-sprint3-completion-report.md`

## 概要

LedgerLeap の Laravel 13 アップグレードは、`#129`〜`#132` の 4 スプリントで完了した。

- Sprint 0: 依存ブロッカーと影響範囲の確定
- Sprint 1: Composer / framework bump
- Sprint 2: CSRF / bootstrap / middleware の追従
- Sprint 3: 回帰テストとリリース判断

最終的に、主要導線の回帰確認まで通過し、アップグレード自体は完了扱いとした。

## 何がうまくいったか

### 1. 依存ブロッカーを先に固定したこと
最初に `darkaonline/l5-swagger` と `15web/filament-tree` をブロッカーとして切り分けたことで、
Composer 更新の前に「何が止めているか」を明確にできた。

この順番にしたことで、以降の作業が「どの依存をどう解決するか」に集中できた。

### 2. composer 更新と path package 修正を同じ流れで扱えたこと
`15web/filament-tree` は root の `laravel/framework` 更新を妨げる path package だったため、
root の制約変更だけでは完了せず、ローカル package 側の互換修正も必要だった。

Composer の再解決と path package の修正をセットで扱ったことで、`composer update --with-all-dependencies` が最終的に通った。

### 3. Laravel 13 の CSRF / middleware 変更を局所修正で吸収できたこと
`VerifyCsrfToken` の直接参照を `PreventRequestForgery` に寄せる修正は、
`app/Http/Kernel.php`、`app/Providers/Filament/AdminPanelProvider.php`、`app/Http/Middleware/VerifyCsrfToken.php`、`config/sanctum.php` の範囲で収まった。

bootstrap 全体を作り直す必要はなく、差分は限定的だった。

### 4. 回帰テストを代表導線に絞れたこと
Sprint 3 では、全体再実行ではなく、次の代表導線に絞って検証した。

- Filament dashboard / widget
- Livewire tenant switcher
- permission cache consistency
- MCP remote HTTP route
- bootstrap manifest API
- search / keyword / semantic / sorting

結果として、Laravel 13 移行後も壊れやすい導線を押さえられた。

### 5. issue と docs/work の役割分担を守れたこと
- `#129`〜`#132` は sprint の進行管理
- `docs/work/architecture/2026-04-04_laravel-13-upgrade-plan.md` は計画と完了結果の集約
- 各 Sprint 完了レポートは証跡
- この retrospective は学びの整理

役割を分けたことで、途中からでも追いやすい状態を保てた。

## 詰まった点

### 1. 依存の衝突は root だけ見ても解けなかった
`phpunit` / `pest` / `debugbar` / `phpcpd` などの開発依存も含めて調整しないと、
Laravel 13 の依存解決は途中で止まった。

特に `phpunit/phpunit ^12` と `sebastian/diff ^7`、`barryvdh/laravel-debugbar ^4.2.3` など、
一見 Laravel 本体とは離れて見えるパッケージも更新対象だった。

### 2. `wire:model.change` はカスタム input では期待通りに振る舞わないことがあった
台帳検索フォームでは、MaryUI の `x-mary-input` / `x-mary-toggle` に対して `wire:model.change` を使っていたが、
Livewire 4 の挙動と組み合わせるとイベントが期待通り拾えないケースがあった。

最終的には、`wire:model.live.debounce.300ms` と `wire:model.live` に寄せて安定化した。

### 3. 回帰確認の対象を広げすぎると、完了判断が遅くなる
検証対象を広げると安心感はあるが、完了判断が遅くなる。
今回は「壊れやすい導線の代表」を優先したことで、
完了条件を満たす最短経路に絞れた。

## 再利用できる学び

### アップグレードの進め方
1. 公式 upgrade guide を先に読む
2. 依存ブロッカーを棚卸しする
3. root 依存と path package を同時に見直す
4. 起動周辺の差分を局所修正する
5. 代表導線の回帰テストで締める

### テストの選び方
- まず tenant / Filament / Livewire / permission / search / MCP の基本導線を押さえる
- DB migrations 系や外部依存系は別枠で扱う
- UI 変更がない Sprint では frontend build を機械的に回さない

### UI の bind 方針
- `wire:model.change` は、カスタム Blade コンポーネントや MaryUI で相性確認が必要
- テキスト入力は `wire:model.live.debounce.*` に寄せると安定しやすい
- トグル類は `wire:model.live` で十分なことが多い

### 記録の残し方
- issue は進行管理用
- `docs/work` は判断理由と証跡用
- 完了レポートは Sprint ごとに分割
- 仕上げに retrospective を 1 本残すと、次回の移行で再利用しやすい

## この案件だけの記録

- 主ブロッカーは `darkaonline/l5-swagger` と `15web/filament-tree`
- 依存更新の完了点は `laravel/framework v13.3.0`
- 主要回帰テストは `49 passed (130 assertions)`
- `#129`〜`#132` は相互参照済み
- 追加で `#59` と `#127` にも検索フォーム修正の追跡を残した
- 既存の未整理変更として `app/Services/Embedding/KeywordEnhancedTextGenerator.php` と `app/Services/SynonymService.php` も別コミットで整理した

## 次回の注意点

- Laravel 13 のような大きめの移行は、Issue と docs の両輪で進める
- path package がある場合は、lock だけでなく package 側の互換も確認する
- カスタム input コンポーネントは、Livewire の modifier 挙動を必ず実機テストする
- 回帰確認は「全部」ではなく「壊れやすい導線」を先に固定する
- 再利用可能な学びが増えたら、`docs/work` だけでなく `.github` の不変条件も見直す

## 結論

Laravel 13 アップグレードは、
**ブロッカーの先出し → 依存更新 → 起動差分の局所修正 → 代表導線の回帰確認**
の順で進めると、LedgerLeap では無理なく完了できる。

今回の移行では、その流れを Sprint 分割で実証できた。

