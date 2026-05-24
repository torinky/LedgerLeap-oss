# Sprint 2-A1: ソースコード起点の公開ドキュメント候補棚卸し

## 概要
公開 doc リストを既存想定から起こすのではなく、routes / Livewire / Filament / API / MCP / tests / lang keys / existing docs から **source-derived に feature inventory を生成** し、公開候補・粒度差・coverage gap を整理します。

## 背景 / 目的
- 現行の #219 doc list は feature coverage を仮定しているが、source 側の実装面と 1:1 ではない
- import/export, file inspector, rollback, bootstrap manifest, admin announcement, notifications など、public candidate の粒度が混在している
- 先に source-derived inventory を作らないと、packet backlog 全体が仮説ベースのままになる

## 現状
- `docs/function/*.md` は developer-facing の機能説明として有用だが、公開 target list の正本ではない
- `routes/tenant.php` と `routes/api.php` だけでも public surface が多数あり、Getting Started / Features / API の境界がまだ揺れている
- `app/Livewire/*`, `app/Filament/*`, `tests/Feature/*` から見ると、現行公開 doc list には未反映または粗すぎる feature family がある

## 目標 / 完了状態
- source-derived feature inventory が作成されている
- current doc coverage gap が見える化されている
- #219 用 public doc target list v2 が作成されている
- packet 化の対象 feature family と comment anchor candidate が抽出されている

## スコープ / 非スコープ
### 対象
- route/controller scan
- Livewire / Filament / Blade scan
- API / MCP surface scan
- tests/Feature による observable behavior 補強
- existing docs との差分整理
- comment anchor candidate 抽出

### 対象外
- public doc 本文の大規模執筆
- skill / subagent 実装
- packet schema の最終確定

## 方針候補 / メモ
1. inventory は `route`, `ui`, `api/mcp`, `background`, `cross-cutting` の軸で起こす
2. feature family ごとに `audience`, `public/internal`, `doc_type candidate`, `existing doc coverage`, `comment anchor candidate` を付与する
3. まず漏れなく列挙し、その後に target list へ圧縮する

## スプリント分解
- [ ] source scan 対象ディレクトリと信号種別を確定する
- [ ] feature inventory テーブルを作成する
- [ ] current doc coverage gap を整理する
- [ ] revised public doc target list v2 を作成する
- [ ] comment anchor candidate list を作成する

## エビデンス / 参照先
- `routes/tenant.php`
- `routes/api.php`
- `app/Livewire/*`
- `app/Filament/*`
- `app/Mcp/*`
- `tests/Feature/*`
- `docs/function/*`
- `docs/api/*`
- `docs/architecture/*`
- Kubernetes API reference generation — https://kubernetes.io/docs/contribute/generate-ref-docs/kubernetes-api/
- Sphinx autodoc tutorial — https://www.sphinx-doc.org/en/master/tutorial/automatic-doc-generation.html

## 完了条件
- [ ] source-derived inventory が作られている
- [ ] public/internal 判定と doc type 候補が feature family ごとに整理されている
- [ ] #219 用 target doc list v2 が作られている
- [ ] comment anchor candidate が後続 sprint で使える形に整理されている
