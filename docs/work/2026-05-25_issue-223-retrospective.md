# Issue #223 Retrospective — private staging baseline と setup 完走確認 (Phase 1 & 2)

**作成日:** 2026-05-25  
**対象:** Phase 1 (ベースライン同期確認) / Phase 2 (除外リストの追加監査)

---

## 良かったこと

### 技術要素
- `gh api repos/torinky/LedgerLeap-oss/contents/` による OSS repo 内容確認は一撃で確実だった。
- sync-to-public.yml run ID を evidence として issue チェックボックスに貼ることで、後から検証可能な証跡になった。

### 作業の進め方
- Phase 2 のチェックボックスを確認する前に、rsync の動作を調査してから削除の方針を決めた。事前調査が無駄な push を防いだ。

---

## 悪かったこと / 新規発見トラップ

### 🔴 rsync `--delete` + `--exclude-from` の落とし穴（新規発見）

**事象:** `bin/sync-ai-instructions.sh` を `.github/sync-excludes.txt` に追加したが、OSS repo に残存していた。

**原因:** rsync の `--delete` フラグは「コピー元にないファイルをコピー先から削除する」が、**`--exclude-from` で除外されたファイルは対象外**。除外ファイルはコピー先から削除されない。  
`--delete-excluded` を付けると除外ファイルも削除されるが、LedgerLeap の `sync-to-public.yml` には付いていない（意図的 — 公開側にのみ存在するファイルを消してしまうリスクがあるため）。

**修正:** GitHub API で直接削除 → OSS commit `d202d976`

**教訓 → 手順:**  
除外リストに新しいパスを追加するときは、OSS repo にそのファイルが既存かどうかを確認し、存在する場合は手動削除が必要。  
→ 詳細手順は `docs/runbooks/oss-sync-runbook.md` を参照。

### sync-excludes.txt 自身は OSS repo に同期されない

`sync-excludes.txt` の11行目には `.github/sync-excludes.txt` が除外パターンとして記載されており、OSS repo は常に古い（初回 bootstrap 時の）バージョンを保持する。CI が使うのはプライベート側のバージョン。混乱しないよう留意が必要。

---

## 再利用判断

| 学習 | 分類 | 記録先 |
|------|------|--------|
| rsync `--delete` は除外ファイルを宛先から消さない | **reusable** | `docs/runbooks/oss-sync-runbook.md`, OSS publication plan §4.3 |
| 除外追加時の手動削除手順 | **reusable** | `docs/runbooks/oss-sync-runbook.md` |
| `sync-excludes.txt` の自己除外 | local（既知） | `sync-excludes.txt` ヘッダコメントに記載済み |
| GitHub API で OSS ファイルを直接削除できる | reusable | runbook に記載 |

---

## 参照

- [docs/work/2026-05-23_oss-publication-plan.md](./2026-05-23_oss-publication-plan.md)
- [docs/work/2026-05-24_issue-218_public-sync-retrospective.md](./2026-05-24_issue-218_public-sync-retrospective.md)
- [docs/runbooks/oss-sync-runbook.md](../runbooks/oss-sync-runbook.md)
- [.github/workflows/sync-to-public.yml](../../.github/workflows/sync-to-public.yml)
- OSS 手動削除コミット: `d202d976d81b68783e3dcfebbb92bc05c7bde474` (LedgerLeap-oss)
- 除外追加コミット: `4ab59eb1` (LedgerLeap private)
