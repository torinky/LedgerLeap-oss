# 管理者お知らせ機能の調査メモ

- date: 2026-04-28
- scope: 上部強制表示と通知センター統合の調査
- goal: 既存デザインシステムの知見を、別機能として見える構造に反映する

## 0. 既存機能の観察結果

### 0.1 通知センターがすでにある

LedgerLeap には、notifications.index の通知センター画面があり、通知・タスク・アクティビティをタブで統合しています。
NotificationList は未読一覧、既読、全既読、ページネーション、件数反映まで持っており、管理者お知らせを通知センターに統合する器として十分に使えます。

### 0.2 通知データ基盤がすでにある

NotificationType、GenericNotification、WorkflowSummaryNotification、NotificationService が揃っており、database 通知の生成・配信・既読管理まで一通りあります。
つまり「新しい通知の運搬方法」を作る必要は薄く、上部表示と通知センターの両方に同じ告知を流す裏側の統合基盤として使えます。

### 0.3 テナント付きの導線がある

通知ページは tenant 付きルートで運用されており、ワークフロー pending から通知ページへの遷移も既存です。
そのため、上部表示で着意させたあと、通知センターに集める導線もそのまま設計できます。

## 1. 既存の類似実装

### 1.1 GOV.UK Notification Banner

参考: https://design-system.service.gov.uk/components/notification-banner/

- ページ内容に直接関係しないが、サービス全体やその人に必要な情報を伝える用途に向く
- 1ページに複数出しすぎない
- エラー表示の代替にはしない
- 成功通知は成功ページや通常フローの終点に寄せる

### 1.2 Fluent UI Message bar

参考: https://fluent2.microsoft.design/components/web/react/core/messagebar/usage

- サーフェス全体の状態を伝える用途に向く
- severity を info / success / warning / error で分ける
- 複数件を重ねられる
- dismissible だが、解決していない重要メッセージは再表示されうる

### 1.3 Fluent UI Toast

参考: https://fluent2.microsoft.design/components/web/react/core/toast/usage

- 一時的な通知向き
- 画面端に短く出す用途が中心
- 必須アクションを促す用途には向かない

### 1.4 Apple Human Interface Guidelines

参考: https://developer.apple.com/design/human-interface-guidelines/alerts

- アラートは作業を中断させる
- 単なる情報提供には使いにくい
- アプリ起動時に毎回出すのは避けるべき

## 2. ライブラリ候補の考え方

### 2.1 第一候補: 既存の Mary UI + Tailwind + DaisyUI で自前実装

- LedgerLeap の現行技術スタックに合う
- 上部表示と管理画面を一体で作りやすい
- Livewire と相性がよい
- 既存のカード、アラート、バッジ、ボタンの組み合わせで十分組める可能性が高い

### 2.2 参考候補: Fluent UI の Message bar / Toast

- 設計思想の参考としては有用
- ただし Laravel / Livewire への直接導入は前提が違う
- React 前提のコンポーネントは、このプロジェクトの主実装候補にはしにくい

### 2.3 参考候補: トースト系ライブラリ

- notyf
- toastify-js
- react-hot-toast

これらは一時通知には使えるものの、今回のような上部強制表示と統合通知の二重運用には用途がずれます。
したがって、主案にはしない方がよいです。

## 3. 要件に落とし込む観点

- 表示はページ上部の強制表示を主経路にする
- 通知センターは同じ告知の統合・履歴経路にする
- 表示レベルは info / warning / critical を中心にする
- starts_at / ends_at / priority / scope / audience を持つ
- 閉じる条件はレベル別に分ける
- ユーザー既読とセッション非表示を分けて考える
- 詳細導線は通知センターか外部ヘルプに逃がす

## 4. 調査の結論

今回の用途は、上部強制表示を主に据え、通知センターをその統合先にするのが妥当です。
新しい UI を別機能として見せることで、既存通知との差異を保ちつつ、裏側で統合できます。

## 5. 参照

- [概要メモ](2026-04-28_admin_announcement_banner_overview.md)
- [最終スコープメモ](2026-04-28_admin_announcement_banner_scope.md)
