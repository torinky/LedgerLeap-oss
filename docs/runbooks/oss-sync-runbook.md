# OSS Sync Runbook

**対象:** `sync-to-public.yml` による LedgerLeap private → LedgerLeap-oss の同期運用

---

## 1. 除外リストに新しいパスを追加する手順

### Step 1: `.github/sync-excludes.txt` を更新

```bash
# 対象パスを末尾に追加
echo "path/to/exclude" >> .github/sync-excludes.txt

# または docs/work/2026-05-23_oss-publication-plan.md §4.3 と同期して編集
```

### Step 2: OSS repo に該当ファイルが既存かどうか確認

```bash
# ファイルの場合
gh api repos/torinky/LedgerLeap-oss/contents/path/to/file --jq '.name' 2>&1

# ディレクトリの場合
gh api repos/torinky/LedgerLeap-oss/contents/path/to/dir --jq '[.[].name]' 2>&1
```

### Step 3: 存在する場合は手動削除（⚠️ rsync は自動削除しない）

**なぜ手動削除が必要か:**  
rsync の `--delete` フラグは `--exclude-from` で除外したファイルを**コピー先から削除しない**。  
`--delete-excluded` を使えば削除できるが、LedgerLeap の sync-to-public.yml には意図的に付けていない（公開側独自ファイルを誤削除するリスク回避のため）。

```bash
# ファイルの SHA を取得
SHA=$(gh api repos/torinky/LedgerLeap-oss/contents/path/to/file --jq '.sha')

# 削除
gh api repos/torinky/LedgerLeap-oss/contents/path/to/file \
  -X DELETE \
  -f message="chore: remove path/to/file (excluded from sync)" \
  -f sha="$SHA"
```

### Step 4: private に commit して push

```bash
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git add .github/sync-excludes.txt && git commit -m 'docs(.github): add path/to/file to sync excludes' && git push"
```

### Step 5: sync ワークフローの成功を確認

```bash
gh run list --repo torinky/LedgerLeap --workflow=sync-to-public.yml --limit=3
```

### Step 6: OSS repo からファイルが消えていることを確認

```bash
gh api repos/torinky/LedgerLeap-oss/contents/path/to/file 2>&1
# "Not Found" なら OK
```

---

## 2. sync-excludes.txt に関する注意事項

### sync-excludes.txt 自身は同期されない

`sync-excludes.txt` の11行目には `.github/sync-excludes.txt` が除外パターンとして記載されている。  
→ OSS repo のコピーは常に **初回 bootstrap 時点のバージョン** が残る。  
→ CI が参照するのはプライベート側 (`$GITHUB_WORKSPACE/.github/sync-excludes.txt`) のため問題なし。  
→ OSS repo の古いコピーを見て「反映されていない」と誤認しないよう注意。

### docs/work/2026-05-23_oss-publication-plan.md §4.3 との同期

除外パターンを変更する際は、`sync-excludes.txt` と **publication plan の §4.3 を同時に更新する**。

---

## 3. PUBLIC_SYNC_ENABLED ゲートの確認

`sync-to-public.yml` の sync job は `vars.PUBLIC_SYNC_ENABLED == 'true'` の場合のみ実行される。

```bash
# 現在の設定を確認
gh api repos/torinky/LedgerLeap/actions/variables/PUBLIC_SYNC_ENABLED --jq '.value' 2>&1
```

---

## 4. 外部コントリビュータ向けワークフローの扱い

`external-tests.yml` と `parallel-canary.yml` は OSS repo に同期されている。  
両ワークフローは `workflow_dispatch` 専用（自動実行なし）で、`DB_PASSWORD` secret が必要。  
→ fork した人が使うには `Settings > Secrets and variables > Actions > DB_PASSWORD` の設定が必要。  
→ 両ファイルにインラインコメントで説明済み（commit `e0f2ed40`）。

---

## 参照

- [.github/sync-excludes.txt](../../.github/sync-excludes.txt)
- [.github/workflows/sync-to-public.yml](../../.github/workflows/sync-to-public.yml)
- [docs/work/2026-05-23_oss-publication-plan.md §4.3](../work/2026-05-23_oss-publication-plan.md)
- [docs/work/2026-05-25_issue-223-retrospective.md](../work/2026-05-25_issue-223-retrospective.md)
