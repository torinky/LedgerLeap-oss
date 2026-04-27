# マイポータル情報設計 Sprint 1 完了報告

**日付:** 2026-04-27  
**対象:** issue 178 相当の Sprint 1  
**関連:** issue 178, 179 とその周辺で実施した通知 / ポータル修正

## 完了した内容

- マイポータルで残す情報を、所属・役割・主要権限・担当フォルダに整理した。
- アイコン、badge、tooltip、CTA の役割を分け、主情報を見出しと本文で読める構成に寄せた。
- フォルダツリーは台帳一覧への入口として扱い、ポータルでは `clickNavigatesToLedgerList` を前提にした。
- 役割 / 所属の表示は stats ベースに再構成し、説明文と補助情報を近接させた。
- 詳細フォルダツリーの hover ちらつきは、portal では DaisyUI tooltip を使わず静的な title 表示へ寄せて抑えた。

## 変更箇所

- [app/Livewire/MyPortal.php](../../../../app/Livewire/MyPortal.php)
- [resources/views/livewire/my-portal.blade.php](../../../../resources/views/livewire/my-portal.blade.php)
- [resources/views/components/folder/tree.blade.php](../../../../resources/views/components/folder/tree.blade.php)
- [tests/Feature/Livewire/MyPortalTest.php](../../../../tests/Feature/Livewire/MyPortalTest.php)

## 理由

- ポータルを台帳一覧の代替ブラウザにせず、状況把握と次の行動の入口に役割分担するため。
- 権限や担当範囲の読み取りに必要な情報を、カード内で迷わず追えるようにするため。
- hover に依存する tooltip は、狭いツリー / アコーディオン内でちらつきや再描画感を生みやすいため。

## 検証

- `./vendor/bin/sail test tests/Feature/Livewire/MyPortalTest.php`

## 結果

- issue 178 相当の Sprint 1 は完了。
- 以降の Sprint 2 / 3 で扱う遷移・state handoff の土台は、この情報設計に揃えた。