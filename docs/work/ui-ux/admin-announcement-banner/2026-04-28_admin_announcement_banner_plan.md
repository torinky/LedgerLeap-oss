# 管理者お知らせ機能 MVP 実装計画

- date: 2026-04-28
- target: 上部強制表示を主UIとする管理者お知らせ MVP
- goal: 登録管理 UI を別機能として見せつつ、通知センターへ裏側統合する

## 1. 目的

本計画は、管理者お知らせ機能の MVP を実装するための作業計画を定義します。
主UI はページ上部の強制表示とし、通知センターは同じ告知の再確認・履歴確認の統合先として扱います。

レビュー結果を踏まえ、以下を最初から設計に入れます。

- success は常駐バナーの表示レベルから外す
- バナーは overlay ではなく push を基本にする
- sticky は critical に限定し、info / warning は static を基本にする
- 閉じた状態は sessionStorage または cookie で保持する
- 管理者の登録・公開・停止は別 UI として分ける
- `status` か `is_active` を持たせ、日時とは別に手動停止できるようにする
- バナーは 1 行中心、長くても 2 行までに制限する
- アイコンを必ず併用し、色だけに依存しない

## 2. MVP の範囲

### 2.1 実装するもの

- ページ上部に管理者お知らせを強制表示する
- info / warning / critical の 3 レベルを持つ
- 1 件表示を基本とする
- 管理者が登録・公開・停止を行える管理画面を用意する
- 公開期間を starts_at / ends_at で管理する
- status か is_active で手動停止を表現する
- 通知センターへ同じ告知を統合表示する
- 閉じる操作を sessionStorage または cookie で維持する

### 2.2 後回しにするもの

- 複数件の積み上げ表示
- role / user 単位のセグメント配信
- 既読の DB 永続保存
- クリック率や既読率の分析
- 管理者プレビューの個別画面
- 国際化や多言語配信

## 3. UI 方針

### 3.1 配置方式

主UI は overlay ではなく push を基本にします。
固定ヘッダーやタブ列の上に重ねて操作を阻害するのではなく、ページ全体を下に押し下げて視認性を確保します。

- バナーは fixed のかぶせ表示にしない
- ヘッダーより前にコンテンツが始まる push レイアウトにする
- 常時表示が必要な場合でも、画面操作を塞がない

### 3.2 スクロール時の挙動

- critical: sticky にして常時視界に入るようにする
- warning: スクロールしたら static として画面外に流れる
- info: static を基本にする

- sticky は critical のみ
- info / warning は原則として追従させない
- 必要なら critical のみページ上部に留める

### 3.3 デザイン

- Mary UI の alert / card をベースにする
- DaisyUI の semantic class を使う
- `rounded-none` と `w-full` で上部バナーらしさを出す
- アイコンは `critical` / `warning` / `info` の意味を補強する
- 文量は 1 行、長くても 2 行に制限する
- 詳細は 1 つのリンクに集約する

- critical は警告三角形系アイコンと強い色で区別する
- warning はベル / 注意系アイコンを使う
- info は情報アイコンを使う
- バナー文言は短く、詳細はリンク先へ逃がす
- 管理者プレビューでは実際の上部表示に近い見た目を確認する

### 3.4 閉じる挙動

- 閉じた状態は sessionStorage か cookie に保存する
- DB 永続保存は MVP から外す
- ブラウザを閉じるまでは再表示しない
- ユーザー既読の永続管理は後回しにする

### 3.5 表示状態

- status か is_active を持たせる
- `draft` / `published` / `archived` などの状態で公開と停止を切り分ける
- 日時で満たされていても archived なら出さない
- 管理者の手動停止は終了時刻の書き換えではなく状態変更で扱えるようにする

### 3.6 通知センター統合

- 通知センターには同じ告知を裏側で同期する
- 通知センターは再確認と履歴の補助経路にする
- 上部表示で見た内容を後から確認できる導線だけを持たせる

## 4. データ設計

### 4.1 追加カラム案

- `title`: バナー見出し
- `body`: 本文
- `level`: `info` / `warning` / `critical`
- `status`: `draft` / `published` / `archived`
- `starts_at`: 表示開始日時
- `ends_at`: 表示終了日時
- `is_active`: 手動停止用のフラグ（status とどちらかを採用）
- `links`: JSON 配列の CTA 定義
- `display_scope`: `global` / `tenant`
- `target_tenant_id`: テナント絞り込み用
- `dismiss_storage_key`: ブラウザ保持用の識別子

### 4.2 リンク表現の再検討

リンク専用の別カラムは作らず、announcement 側に 1 つの JSON カラムを持たせます。
既存通知 DB は `notifications.data.payload.route` を中心にした単一リンク運搬が前提に近いので、announcement 側だけを柔軟にしておく方が変更範囲を最小化できます。

JSON カラムの中身は、少なくとも次の 2 つを持つ配列にします。

- `label`
- `url`

必要なら将来 `id` / `kind` / `sort` を足せるようにします。

判断基準は次の通りです。

- 検索条件や集計条件としてリンクそのものを使うか
- 通知センターと上部表示で同じリンクを再利用するか
- 複数導線が本当に必要か
- 将来の統計でリンククリックを独立集計したいか

MVP の方針は「本文に Markdown を埋め込まず、主導線は `links` JSON の先頭要素 1 件で持つ」です。
通知 DB 自体は増やさず、announcement 側の JSON にだけ CTA を持たせ、通知センターにはそのまま payload として同期します。
複数リンクは最初から JSON 配列で受けられるため、将来の拡張でカラム変更は発生しません。

### 4.3 設計の考え方

- 時間制御と手動停止は分ける
- `status` があるなら `draft` / `published` / `archived` を優先する
- 既存通知センターへ統合するため、通知データ側には同じ `announcement_id` を持たせる
- 既読よりも先に「表示したか」「閉じたか」を扱う
- リンク設計は `links` JSON 配列を主案とし、MVP では 1 件だけ入れる
- リンク順序が必要なため、JSON オブジェクトではなく配列で持つ

## 5. 画面設計

### 5.1 公開表示 UI

- ページ上部に常時表示する
- 固定ヘッダーより前面に来るが、操作を塞がない
- 表示中は本文を押し下げる
- 閉じるボタンは任意だが、critical は原則非表示にしない

### 5.2 登録管理 UI

- 新規作成、公開、停止を分けて操作する
- status と開始・終了日時を一画面で確認できる
- プレビュー領域で実際の上部表示に近い見え方を確認できる
- 通知センター統合の有無を設定できる

### 5.3 通知センター統合

- 通知センターでは同じ告知を履歴として再表示する
- 未読一覧に流すか、専用タブに流すかは実装時に選ぶ
- 上部表示を見逃した人が後で再確認できる導線にする

## 6. 主要な実装タスク

### Phase 1: データモデルと基盤

1. announcement 用のモデル / migration を作る
2. `status` または `is_active` を追加する
3. `starts_at` / `ends_at` / `level` / `display_scope` / `target_tenant_id` を定義する
4. リンクは単一カラム、Markdown 統合、JSON 配列のどれで持つかを決める
5. 既存通知 DB の payload との衝突がないか確認する
6. ブラウザ閉じるまでの dismiss 状態を保持するキー設計を決める

### Phase 2: 公開 UI

1. 上部表示コンポーネントを作る
2. push レイアウトでヘッダーを押し下げる
3. critical を sticky、info / warning を static にする
4. アイコン、見出し、本文、リンクを 1 ブロックにまとめる
5. 文字数を制限する
6. dismiss 状態を sessionStorage か cookie に保持する
7. overlay ではなく push であることをテストで固定する

### Phase 3: 管理画面

1. 登録フォームを作る
2. 公開 / 停止の状態遷移を持たせる
3. 管理者が期間、レベル、対象範囲を設定できるようにする
4. プレビューを付ける

### Phase 4: 通知センター統合

1. 同じ告知を通知データとして同期する
2. 通知センター側に一覧表示する
3. 再確認用のリンクを付ける
4. 既読処理は既存の通知基盤に寄せる

### Phase 5: UX 調整

1. sessionStorage / cookie の保持確認
2. レベルごとの表示時間、表示位置、非表示条件を調整
3. リンク数や本文量の上限を調整
4. スクロール時の見え方を確認

## 7. テスト方針

- 上部表示がページコンテンツを押し下げることを検証する
- critical が sticky、warning / info が static であることを確認する
- dismiss 後に sessionStorage または cookie で再表示されないことを確認する
- status 切替で公開 / 停止が変わることを確認する
- 通知センターに同じ告知が反映されることを確認する
- 文量とリンク数の制限を超えた場合の表示崩れを確認する

## 8. 未決定事項

- `status` と `is_active` のどちらを採用するか
- dismiss の保持先を sessionStorage にするか cookie にするか
- critical の sticky を全ページに適用するか対象ページに限定するか
- 通知センターに未読として流すか、別タブで統合するか
- 1 件運用で足りるか、将来の複数件に備えて枠を作るか

### GitHub 追跡

- Epic: #180
- Sprint 1: #181
- Sprint 2: #182
- Sprint 3: #183
- Sprint 4: #184

### Sprint 2 完了メモ

- 初度の見た目として合意済み。
- バナー本体は単一の alert surface にし、背景は画面ローディングのように横方向へ流れる帯で表現する。
- 日付ラベル、CTA ボタン、閉じるボタンは右端のアクションエリアに寄せる。
- リンクは text link ではなくボタンとして見せ、操作対象であることを明確にする。
- 閉じる操作も leave transition を付け、表示 / 非表示の切り替えをアニメーション化する。
- Evidence: [resources/views/components/admin/announcement-banner.blade.php](../../../../resources/views/components/admin/announcement-banner.blade.php)
- Evidence: [resources/sass/app.scss](../../../../resources/sass/app.scss)
- Evidence: `./vendor/bin/sail npm run build` ✅
- Evidence: browser preview で warning / info の見た目を確認済み。

### 上書きされた指示

- 途中で入っていた nested card / inner wrapper の見せ方は、単一 surface の alert 表現に上書きした。
- 本文中に置いていた日付ラベルと close ボタンは、右端のアクションエリアに上書きした。
- 文字リンクで見せる案は、btn-soft の CTA ボタンに上書きした。
- 単発の薄いグラデーション案は、横に流れる帯のアニメーションに上書きした。
- 閉じる時の即時消去は、leave transition を伴う閉じ方に上書きした。

## 9. 受け入れ条件

- ユーザーはページ上部の告知を必ず視認できる
- 閉じても次回アクセスまで再表示されない
- 管理者は登録、公開、停止を分けて操作できる
- 通知センターで同じ告知を再確認できる
- info / warning / critical の差異が画面上で判別できる
- バナーは overlay ではなく push で配置されている
- critical のみ sticky で、info / warning は static になっている
- 1 行中心で、長くても 2 行までに収まる
- アイコンと色でレベルが判別できる

## 10. 参照

- [最終スコープメモ](2026-04-28_admin_announcement_banner_scope.md)
- [概要メモ](2026-04-28_admin_announcement_banner_overview.md)
- [調査メモ](2026-04-28_admin_announcement_banner_research.md)
