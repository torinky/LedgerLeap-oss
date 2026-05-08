# Storage Permission Fix Retrospective (2026-05-08)

**対象:** `/home/torinky/LedgerLeap/storage/framework/testing/disks/public/tenants`

## 良かったこと

- 先に `pwd` / `git rev-parse --show-toplevel` で実作業ルートを確認し、`/var/www/html` という推測パスを前提にしなかった。
- `namei -l` で親ディレクトリを含めて所有者と権限を確認し、どこで `root` 所有になっているかを特定できた。
- 修正は対象サブツリーに限定し、`chown -R` / `chmod -R u+rwX,go-rwx` を exact path にだけ適用した。
- 変更後に `touch` / `rm` で実際に書き込めることを確認できた。

## 悪かったこと

- 初回の想定パスに `/var/www/html` を使ったため、実際の WSL 作業ルートとの差を一度見直す必要があった。
- 権限問題は「広く chmod する」方向に流れやすいが、今回は runtime storage の狭い範囲に絞る必要があった。

## 上書き指示されたこと

- `public/` 全体を広げるのではなく、`storage/framework/testing/disks/public/tenants` のような **runtime storage subtree のみ** を修正する。
- まず所有者を直し、必要最小限の権限だけ付与する。
- 変更後は `namei -l` と write probe で検証する。

## 再利用可能な学び

1. **権限修正は exact path で行う**
   - `storage/framework/testing`、`storage/app/public`、`storage/logs` など、書き込み先を絞る。
   - `chmod 777` は恒久対策にしない。

2. **検証は 2 段階で行う**
   - `namei -l` で親ディレクトリを含む所有権を確認する。
   - `touch` / `rm` で実際の write path を確認する。

3. **WSL / Docker の混在では実ルート確認を先に行う**
   - `pwd` と `git rev-parse --show-toplevel` を先に実行する。
   - 推測パスではなく、実際の作業ツリーを基準に権限を直す。

## 参照

- `AGENTS.md`
- `.github/skills/sail-dev-workflow/SKILL.md`
- `docs/runbooks/bug-response-playbook.md`
