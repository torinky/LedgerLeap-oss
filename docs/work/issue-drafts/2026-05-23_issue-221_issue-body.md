# Sprint 4: OSS コミュニティ整備と最終検証

## 概要
公開リポジトリに必要なコミュニティ基盤を整え、外部コントリビュータが迷わず参加できる状態に仕上げます。

## 作成タイミング
- 作成日時: 2026-05-23T08:08:30Z
- 位置づけ: Epic #216 の Sprint 4 として、AI 資産の分離と公開ドキュメントの方向性が固まった後に起票
- 参照タイミング: 公開直前にコミュニティ向けの入口と検証を締める段階で作成

## このスプリントで作るもの
- `README.md` の OSS 向け入口
- `CONTRIBUTING.md` の貢献ガイド
- `CODE_OF_CONDUCT.md` の行動規範
- `SECURITY.md` の公開窓口と対応方針
- `.github/ISSUE_TEMPLATE/` の公開運用向けテンプレート

このスプリントで作るのは、公開リポジトリのコミュニティ入口一式。Sprint 2 で作った docs 群を、ここでは root から案内できる状態に整える。

## 背景 / 目的
- 公開後に最初に目に入るのは README、CONTRIBUTING、Issue テンプレート
- セキュリティ窓口や行動規範がないと、OSS としての受け皿が弱い
- 最終的に、公開側の導線と検証を一通り揃えておきたい

## 現状
- `README.md` は現状内部文書へのリンクが多い
- `CONTRIBUTING.md` と `CODE_OF_CONDUCT.md` は未整備
- `SECURITY.md` はテンプレート状態
- Issue テンプレートはあるが、公開後の運用に合わせた見直しが必要

## 目標 / 完了状態
- 公開側の入口が分かりやすくなる
- 貢献ルールと安全窓口が整う
- Issue / PR の運用ルールが明確になる
- 公開開始後に、迷わず次の作業へ進める

## スコープ / 非スコープ
### 対象
- `README.md` の OSS 向け再構成
- `CONTRIBUTING.md` の新規作成
- `CODE_OF_CONDUCT.md` の新規作成
- `SECURITY.md` の実態反映
- `.github/ISSUE_TEMPLATE/` の汎用化
- リリース前の README / docs / テンプレート整合確認

### 対象外
- コードの新機能実装そのもの
- AI 資産の追加変更
- 公開リポジトリ bootstrap の再実施

## 方針候補 / メモ
1. 利用者向けは短く、コントリビュータ向けは手順中心にする
2. Issue テンプレートはバグ/通常イシューの 2 系統に寄せる
3. 内部向けリンクは公開版から削る

## Sprint 内で検討すべき詳細事項
- README の最初の導線
- CONTRIBUTING に書くべき最低限の開発フロー
- SECURITY で公開する連絡先の表記
- Issue テンプレートの粒度
- 最終公開前のリンク切れ確認方法

## スプリント分解
- [ ] `README.md` の公開向け構成を確定
- [ ] `CONTRIBUTING.md` を確定
- [ ] `CODE_OF_CONDUCT.md` を確定
- [ ] `SECURITY.md` を確定
- [ ] Issue テンプレートを公開運用向けに確定

## エビデンス / 参照先
- `docs/work/2026-05-23_oss-publication-plan.md`
- `.github/ISSUE_TEMPLATE/issue_request.yml`
- `.github/ISSUE_TEMPLATE/bug_report.yml`
- `README.md`
- `SECURITY.md`

## 完了条件
- [ ] 公開側の入口文書が整っている
- [ ] コントリビュータ向け手順が整っている
- [ ] セキュリティ窓口が整っている
- [ ] Issue 運用が公開向けに整っている
- [ ] 最終検証の結果をもって親 Issue を閉じられる
