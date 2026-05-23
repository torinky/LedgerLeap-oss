# Issue #217 Sprint 0 完了レポート

**status:** complete
**last_updated_at:** 2026-05-23
**related_issue:** `#217`
**related_epic:** `#216`
**related_memo:** `docs/work/2026-05-23_oss-publication-plan.md`

## 概要

Sprint 0 では、公開前に決めるべき境界条件を確認した。
その結果、公開用ドキュメントは既存の `docs/work/` や `docs/development/` をそのまま流用せず、
外部読者向けに新規作成する前提で進めるべきだと判断した。

あわせて、公開直前の確認で機械的な一括変換だけでは足りず、
1 ファイルごとの調査・整理・書き直しが必要だと確認した。

## 実施した調査

### 1. 既存の公開移行計画の確認
- `docs/work/2026-05-23_oss-publication-plan.md` を確認
- 公開対象と非公開対象の分離方針を確認
- `docs/work/` は内部の意思決定・実装記録として残す前提を再確認

### 2. 外部 OSS のドキュメント構成の比較
- `laravel/laravel`
  - README で概要、Contributing、Code of Conduct、Security を簡潔に分離
  - 詳細な技術説明は README ではなく別導線に逃がしている
- `filamentphp/filament`
  - README は導入入口として短く保ち、詳細は `docs/` に分離
  - docs は `Introduction` / `Resources` / `Global search` / `Upgrade guide` のように機能別に整理
- `spatie/laravel-permission`
  - README と docs を分け、docs 側で `Introduction` / `Basic usage` / `Best practices` / `Questions and issues` を役割別に整理
  - security の連絡先を専用セクションで明示

### 3. 公開リスクの軽い走査
- 既知の秘密文字列パターンを軽く走査した
- その結果、[`docs/development/demo-environment-setup.md`](../development/demo-environment-setup.md) に実トークン形式の出力例が残っていることを確認した
- これは公開前に必ず置き換える必要がある

## テンプレート設計の参照元

今回追加した public doc テンプレートは、次の公開 OSS の構成を参照している。

- `laravel/laravel` の README / SECURITY 分離: root README を短い入口に保ち、セキュリティ窓口を独立させる構成を参考にした。
- `filamentphp/filament` の docs 分割: 概要と詳細を別ページへ分ける「短い入口 + 詳細 docs」の形を参考にした。
- `spatie/laravel-permission` の docs 分割: 基本概念、実践、FAQ、セキュリティを役割別に分ける整理を参考にした。
- `vercel/next.js` の docs contribution guide: docs を読者別・機能別に分ける考え方、file-system routing、MDX frontmatter、shared content / generated page の扱いを参考にした。
- `facebook/react` の compiler docs: developer-facing docs の定型として、Purpose / Input Invariants / Output Guarantees / Algorithm / Edge Cases / Example を参考にした。
- `microsoft/vscode` の root README / SECURITY / CONTRIBUTING: root entry point は短く、security は専用ファイル、貢献案内は別導線にする分離方針を参考にした。

この追加確認で、setup 系ページには `Troubleshooting` が必要、開発者向けページには `Edge cases` と `Validation` の明示が必要だと分かったため、テンプレート側にも反映した。

また、`SECURITY.md` については、major OSS の共通項として次の方針を確定した。

- `Supported Versions` を明示する
- 公開 issue ではなく private channel で報告を受ける
- 受領確認と進捗共有の流れを記載する
- レポートには影響・再現手順・関連ファイルを含めてもらう
- 秘密情報は報告本文に載せないよう注意を促す

## 判断

公開ドキュメントは、次の 4 つを満たす必要がある。

1. **ユーザー向けの観測可能な機能**を先に書く
2. **実装経緯や work 系の内部記録**を混ぜない
3. **機能ごとに 1 ファイル単位で整理**する
4. **公開前に秘密情報・デモ値・内部参照を除去**する

このため、公開文書の変換は機械的コピーではなく、
「ソース文書の目的判定 → 対象読者の確定 → コード・テストの根拠確認 → 公開文書への再構成」を
1 ファイルずつ行う運用が必要だと結論づけた。

## 専用 skill が必要な理由

既存のドキュメントは、以下が混在していて単純な置換では安全に整えられない。

- 公開情報にすべき機能説明
- `docs/work/` への内部参照
- 実装の紆余曲折
- デモ値や認証情報の例
- コード・テスト・設定への根拠リンク

そのため、次スプリント以降で使うための専用 skill を作成し、
「1 ファイルごとの公開化手順」と「公開前の除去チェック」を定型化する必要がある。

## Sprint 0 の結論

- 公開範囲の基本方針は固まった
- 公開ドキュメントは新規作成方針で進める
- 変換作業は 1 ファイル単位の手作業前提に切り替える
- 公開前に除去すべき具体例として、`docs/development/demo-environment-setup.md` のトークン例が見つかった

## スプリント分解の進捗

- [x] シークレット検出と公開禁止候補の列挙
  - Evidence: 軽量走査で `docs/development/demo-environment-setup.md` のトークン形式出力例を確認し、`docs/work/2026-05-23_oss-publication-plan.md` §4.3 / §5.3 で除外候補を確認済み
- [x] `docs/` の公開/非公開方針を確定
  - Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §3.1-§3.3 と §5.2-§5.3、加えて `laravel/laravel` / `filamentphp/filament` / `spatie/laravel-permission` の公開構成比較を実施済み
- [ ] `SECURITY.md` の改訂ポイントを確定
  - Evidence: `SECURITY.md` はまだテンプレート状態で、公開向けの報告先・対応方針の再記述が未完了
- [x] 公開対象から除外するファイル一覧を固める
  - Evidence: `docs/work/2026-05-23_oss-publication-plan.md` §4.3 / §5.3 に除外リストを整理済み

## 次スプリントの前提条件

- 専用 skill を作成する
- 公開文書のファイルごとのテンプレートを固定する
- `SECURITY.md` の報告先と対応方針を公開向けに整える
- デモ環境ドキュメントのトークン例を公開向けに匿名化する
- 必要なら trufflehog 相当の正式な秘密情報スキャンを実施する

## GitHub 追跡

- Epic: `#216`
- Sprint 0: `#217`

## 参照

- `docs/work/2026-05-23_oss-publication-plan.md`
- `docs/development/environment-setup.md`
- `docs/development/demo-credentials.md`
- `docs/development/demo-environment-setup.md`
- `SECURITY.md`
- `docs/README.md`
- `docs/runbooks/README.md`
