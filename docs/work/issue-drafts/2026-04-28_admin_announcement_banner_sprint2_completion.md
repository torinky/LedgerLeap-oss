# [Issue]: 管理者お知らせバナー Sprint 2 完了報告

## イシュー種別
改善

## 概要
初度の見た目として合意した管理者お知らせバナーの状態を、Sprint 2 の完了結果として固定する。

## 背景 / 目的
- 管理者お知らせは、上部強制表示を主UIにして着意させたい。
- Sprint 2 では、見た目の合意と操作導線の整理を優先した。
- 途中で変わった表示方針も、そのまま後続の Sprint で参照できるように残しておきたい。

## Sprint 2 で確定した内容
- バナー本体は単一の alert surface にする。
- 背景は横に流れる帯のアニメーションで見せる。
- 日付ラベル、CTA、閉じるボタンは右端のアクションエリアにまとめる。
- リンクは text link ではなくボタンとして扱う。
- 閉じる操作も leave transition を付けてアニメーション化する。

## 上書きされた指示
- nested card / inner wrapper の見せ方は、単一 surface の alert 表現に上書きした。
- 本文中に置いていた日付ラベルと close ボタンは、右端のアクションエリアに上書きした。
- 文字リンクで見せる案は、btn-soft の CTA ボタンに上書きした。
- 単発の薄いグラデーション案は、横に流れる帯のアニメーションに上書きした。
- 閉じる時の即時消去は、leave transition を伴う閉じ方に上書きした。

## エビデンス
- [resources/views/components/admin/announcement-banner.blade.php](../../../resources/views/components/admin/announcement-banner.blade.php)
- [resources/sass/app.scss](../../../resources/sass/app.scss)
- `./vendor/bin/sail npm run build` ✅
- browser preview で warning / info の見た目を確認済み

## 完了条件
- [x] 初度の見た目として合意した
  - Evidence: browser preview で warning / info の配置と密度を確認済み
- [x] アクションエリアが右端に寄っている
  - Evidence: 日付ラベル / CTA / close を右端グループにまとめた
- [x] 背景が横方向に流れる帯として見える
  - Evidence: `resources/sass/app.scss` の `admin-announcement-banner-gradient`
- [x] 閉じる時もアニメーションしている
  - Evidence: leave transition と `transitionend` で offset を更新する実装
- [x] ビルドが通る
  - Evidence: `./vendor/bin/sail npm run build` ✅

## GitHub 追跡
- Epic: `#180`
- Sprint 2: `#182`

## 関連リンク
- [実装計画](../ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_plan.md)
- [概要メモ](../ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_overview.md)
- [最終スコープメモ](../ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_scope.md)
- [調査メモ](../ui-ux/admin-announcement-banner/2026-04-28_admin_announcement_banner_research.md)

## 確認事項
- [x] 改善イシューであることを確認した
- [x] Sprint 2 の結果としてまとめた
- [x] 上書きされた指示も記録した
- [x] エビデンスを添付した
