# context-mode Health Check & Recovery Runbook

**対象:** `ctx_fetch_and_index` / `ctx_index` / `ctx_search` が `disk I/O error` を返す障害
**最終更新:** 2026-06-20
**エビデンス:** 2026-06-20 セッション (better-sqlite3 バインディング欠落 + OpenCode adapter 移行)

## 障害パターン

| 症状 | 原因 | 修復 |
|------|------|------|
| `ctx_fetch_and_index` → `disk I/O error` | better-sqlite3 ネイティブバインディング未ビルド | `npm rebuild` |
| `ctx_index` → `disk I/O error` | 同上 | 同上 |
| `ctx_search` → `disk I/O error` | 同上 | 同上 |
| Content DB が空 / 未作成 | adapter 移行時に content DB 未移行 | DB 自動作成（修復後） |
| `ctx_stats` は正常、`tokens saved = 0` | content/index 系のみ故障 | 本 runbook 適用 |

## 診断手順

### 1. ctx_doctor で基本状態確認

```
ctx doctor
```

以下が PASS であることを確認:
- `Storage content: PASS`
- `FTS5 / SQLite: PASS`
- `Plugin registration: PASS`

### 2. better-sqlite3 バインディング確認

```bash
find ~/.cache/opencode/packages/context-mode@latest/node_modules/better-sqlite3 -name "*.node" -type f
```

出力が空 → バインディング欠落。**修復手順 A へ。**

### 3. content DB 実体確認

```bash
ls -la ~/.config/opencode/context-mode/content/*.db
```

`.db` ファイルが存在しない → content DB 未作成。better-sqlite3 修復後に自動生成される。

## 修復手順 A: better-sqlite3 再ビルド

```bash
npm rebuild better-sqlite3
```

作業ディレクトリ: `~/.cache/opencode/packages/context-mode@latest/node_modules`

### 確認

```bash
ls -la ~/.cache/opencode/packages/context-mode@latest/node_modules/better-sqlite3/build/Release/better_sqlite3.node
```

ファイルが存在すれば成功。**opencode セッションを再起動する。**

## 修復手順 B: plugin 登録確認

`opencode.json` に以下が含まれていることを確認:

```json
"plugin": ["context-mode"]
```

存在しない場合は `ctx upgrade` を実行して自動追加。

## 修復手順 C: content DB 手動作成（最終手段）

上記 A, B を実施しても DB が自動生成されない場合:

```bash
node -e "
const path = require('path');
const Database = require(require('path').join(
  process.env.HOME,
  '.cache/opencode/packages/context-mode@latest/node_modules/better-sqlite3'
));
const dbPath = path.join(
  process.env.HOME,
  '.config/opencode/context-mode/content',
  require('crypto').createHash('sha256')
    .update(process.cwd().replace(/\\\\/g, '/').replace(/\\/+$/, '').toLowerCase())
    .digest('hex').slice(0, 16) + '.db'
);
const db = new Database(dbPath);
db.pragma('journal_mode = WAL');
db.close();
console.log('Created:', dbPath);
"
```

## 再発防止チェックリスト

`ctx upgrade` 実行後は必ず以下を確認:

- [ ] `ctx_doctor` で全項目 PASS
- [ ] `ctx_fetch_and_index` テスト呼び出し成功
- [ ] `ctx_search` で既存 index 検索可能
- [ ] `ctx_stats` で `tokens saved > 0`
