# 通知画面レイアウト改善の振り返り

- date: 2026-04-27
- scope: `resources/views/notifications/index.blade.php`
- related tests:
  - `tests/Feature/Http/Controllers/NotificationControllerTest.php`
  - `tests/Feature/Livewire/Notifications/NotificationListTest.php`
  - `tests/Feature/Livewire/Common/ActivityHistoryDisplayTest.php`
  - `tests/Feature/Livewire/Workflow/PendingListTest.php`
  - `tests/Feature/Livewire/Workflow/OtherRelatedTasksListTest.php`

## 何が良かったか

- 親ビューの状態源を `activeTab` に寄せたことで、タブ切替の責務が整理しやすかった。
- Mary UI の `x-mary-tabs` / `x-mary-tab` の `label` スロットを使い、タブラベルの文字サイズと件数バッジを分離できた。
- 画面幅の拡張は外側コンテナに限定し、内側の要素構造を大きく崩さずに済んだ。
- 変更後に HTTP テストと Livewire テストをまとめて回し、見た目変更でも挙動回帰を早めに確認できた。

## 何が悪かったか

- 序盤はヘッダー・上部サマリー・タブラベルに同じ件数表現が重なり、情報が冗長だった。
- 手書きタブ実装と Mary UI 実装を行き来したため、見た目の方針が一時的に散らばった。
- 幅調整とラベル調整を別々に進めた結果、最終確認の回数が少し増えた。

## 上書き指示されたこと

- タブ切替は手書き実装ではなく Mary UI のタブ実装に統一する。
- タブラベルの文字を詳細画面相当に大きくする。
- レイアウト幅は最大限活用するが、横長画面で要素が両端に寄って破綻しないようにする。
- 件数バッジは重複表示を避け、タブラベル側へ寄せる。

## これから標準化しなくてはいけないこと

- 横幅を広げるときは、まず外側コンテナだけを調整し、内側のカード/タブ/バッジ構造は維持する。
- 業務向けの一覧画面では、件数バッジは「ヘッダー」と「タブ」の両方に置かず、主表示箇所を1か所に固定する。
- Mary UI のタブは `x-mary-tabs` / `x-mary-tab` を基本とし、見出しの文字サイズや補助情報は `label` スロットで調整する。
- 横長画面の破綻防止は、両端分散を抑える `max-w-*` + `mx-auto` + 一貫した内側余白で先に解く。
- レイアウトの変更後は、見た目だけでなく親コントローラの初期タブ分岐と Livewire の件数イベントを必ず通す。

## 検証メモ

- `./vendor/bin/sail test tests/Feature/Http/Controllers/NotificationControllerTest.php`
- `./vendor/bin/sail test tests/Feature/Http/Controllers/NotificationControllerTest.php tests/Feature/Livewire/Notifications/NotificationListTest.php tests/Feature/Livewire/Common/ActivityHistoryDisplayTest.php tests/Feature/Livewire/Workflow/PendingListTest.php tests/Feature/Livewire/Workflow/OtherRelatedTasksListTest.php`

どちらも通過した。

