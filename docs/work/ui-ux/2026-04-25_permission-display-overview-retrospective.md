# 2026-04-25 権限表示の概要先行化に関する振り返り

## 目的

権限表示を、ロール・組織・ユーザーの一覧から読むのではなく、「誰の、何に対する、どんなアクセスか」を先に把握できる構成へ寄せた。

## 何を変えたか

- 概要パネルに対象リソースと閲覧者を明示した。
- 現在のアクセスレベルと権限一覧を icon-bearing badge で見せるようにした。
- direct / inherited の状態マーカーを icon-only + tooltip + sr-only に統一した。
- 権限のラベルは Mary UI badge に寄せ、色と icon の役割を分けた。
- 翻訳キーの参照は、実際の `ledger.*` 集約の構造に合わせて見直した。
- 継承ラベルは、どのフォルダから引き継いでいるかが分かるようにフォルダパスを出すようにした。
- 継承ラベルは、フォルダアイコン付きの breadcrumb で表し、区切りを視覚的に分かるようにした。
- ロール名そのものは行の主表示として残し、状態表現はアイコンと補助ラベルへ分離した。
- 要求元フォルダは breadcrumb の各階層を個別リンクにし、各フォルダの edit 画面へ遷移できるようにした。



## 良かったこと

- 概要に subject と viewer を置いたことで、詳細表に入る前に文脈が分かるようになった。
- icon-only の状態マーカーに tooltip を足したことで、カード内の情報密度を落とさずに可読性を上げられた。
- 権限の実体は badge、状態は icon、説明は tooltip という分離ができ、同じ情報を二重に読ませなくて済んだ。
- Livewire の feature test で概要文言を固定できたので、次回の見た目調整で意図が壊れにくくなった。
- ユーザーが途中で継承ラベルの見せ方を上書きしたあとも、古い方針を残さず新しい意図へすぐ寄せ直せた。
- 役割行は部分修正を積み上げるより、見出し・ラベル・breadcrumb をひとまとまりで書き直した方が安全だった。

## 悪かったこと

- `ledger.misc_components.column.subject` のような参照を先に置いてしまい、実際の翻訳集約構造とずれたキーを一度使った。
- その結果、翻訳ファイルを読んでからでないと実在キーが分からず、軽い手戻りが出た。
- 直接付与 / 継承の説明を最初は文字ラベルで置こうとして、情報量に対して冗長になりやすかった。
- Blade の一部を手でつなぎ直したときに、PHP ブロックへ HTML が紛れ込んで syntax error を起こした。
- Blade のソースとコンパイル済みビューの不一致を疑う前に、ソース側だけを見て判断しそうになった。
- 継承ラベルの修正を狭く見すぎて、`@scope` の境界をまたぐ破損に気づくのが遅れた。

## 判断

- この作業の個別内容は `docs/work/ui-ux` に残す。
- 「権限表示のような compact な状態領域では、subject/viewer を概要に出し、状態マーカーは icon-only + tooltip + sr-only に寄せる」という学びは再利用可能なので、`.github/instructions/design.instructions.md` に昇格した。
- 「ユーザーが上書きした意図は、以前の案を残さず即座に新しい正規ルートへ戻す」「Blade の修正は崩れた branch を部分修正せず、近い semantic anchor からまとめて書き直す」という進め方は、`skill-maintenance` に戻せる再利用知識として扱う。

## 証拠

- UI: [resources/views/livewire/common/permission-display.blade.php](../../../resources/views/livewire/common/permission-display.blade.php)
- Enum: [app/Enums/FolderPermissionType.php](../../../app/Enums/FolderPermissionType.php)
- テスト: [tests/Feature/Livewire/Common/PermissionDisplayTest.php](../../../tests/Feature/Livewire/Common/PermissionDisplayTest.php)
- 計画: [docs/work/ui-ux/2026-04-25_permission-display-overview-first-plan.md](2026-04-25_permission-display-overview-first-plan.md)
- デザイン指針: [.github/instructions/design.instructions.md](../../../.github/instructions/design.instructions.md)

## 検証

- `./vendor/bin/sail test tests/Feature/Livewire/Common/PermissionDisplayTest.php`
- `./vendor/bin/sail test tests/Feature/Livewire/Common/PermissionDisplayTest.php` で 8 passed を確認したあと、要求元フォルダの各階層リンク化を追加で反映した。

## 次回のガードレール

- 権限やアクセスの要約を作るときは、最初に subject と viewer を出せるか確認する。
- direct / inherited のような長い状態は、ラベルで横幅を取るよりも icon と tooltip に寄せる。
- badge が icon-only になる場合は、tooltip と sr-only を必ずセットで考える。
- 翻訳キーを参照するときは、個別ファイル名ではなく `lang/ja/ledger.php` に集約される最終キーを確認する。
- 継承ラベルは、抽象的な文言よりも具体的なフォルダパスを優先する。
- 継承元フォルダのパスは、文字列1本より breadcrumb 表現に寄せた方が切れ目を追いやすい。
- 主表示（ロール名）と補助表示（継承元パス）を分けると、情報の重複が減って読み順が安定する。
- breadcrumb の階層は見た目だけでなくリンク先も揃えると、閲覧だけでなく移動にも使える。
- ユーザーが表示方針を上書きしたら、既存の案を並べて残さず、進捗コメントも新しい意図に言い直す。
- Blade の構造変更では、見た目だけでなく `@scope` や `@php` の境界も一緒に確認し、compiled view の症状が出たら一度 cache を疑う。


