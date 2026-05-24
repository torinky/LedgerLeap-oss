# Issue #223 Phase 3-5: OSS セットアップ完走確認 — 詳細スプリント計画

**作成日:** 2026-05-24  
**対象イシュー:** #223 Sprint 1-A  
**前提:** Phase 1 (sync 確認) / Phase 2 (除外リスト) は完了済み

---

## 概要

現在の開発環境（`/Users/kazutaka/PhpstormProjects/LedgerLeap`）は 11 コンテナが稼働中。  
OSS repo (`torinky/LedgerLeap-oss`) を **別ディレクトリ** にクローンして `./bin/setup.sh` の完走を確認する。

ポート競合を避けるため、**現開発環境を一旦停止 → OSS 検証 → 開発環境を復旧** の逐次方式をとる。

---

## 発見済みリスク・技術的課題

### ⚠️ R-1: vendor/ ブートストラップ問題（最重要）

`bin/setup.sh` は序盤で `./vendor/bin/sail` を呼ぶが、`vendor/` は `.gitignore` に含まれる。  
fresh clone ではそのまま実行すると `No such file or directory` で即クラッシュ。

**対策（setup.sh 実行前に実施）:**
```bash
# Docker を使って composer install だけ先に実行
docker run --rm -v $(pwd):/app -w /app composer:latest install --ignore-platform-reqs --no-scripts
```

またはホストに composer があれば:
```bash
composer install --ignore-platform-reqs --no-scripts
```

→ この問題は **setup.sh のバグ** として別途 fix issue を起票する。

### ⚠️ R-2: ポート競合

現 dev env が占有するポート: `80, 5173, 3306, 6379, 1025, 8025, 7700, 9998, 8001`  
OSS の `docker-compose.yml` は同じポートを使う。  
→ **必ず dev env を `sail down` してから OSS を起動する。**  
→ Tika の 9998 は `docker-compose.yml` 内でハードコードのため .env では変更不可。

### ⚠️ R-3: AI モデルのダウンロード

`VLM_ENABLED=true` / `RAG_ENABLED=true` がデフォルト。  
embedding サービスが起動時に HuggingFace から `cl-nagoya/ruri-v3-310m` 等を Pull する。  
ネットワーク制限 or タイムアウトが発生しうる。

**対策:** `.env` で `VLM_ENABLED=false` / `RAG_ENABLED=false` に設定して初回検証を軽量化する。  
モデル込みのフル検証は Phase 5 で実施。

### ⚠️ R-4: ディスク容量

Docker イメージのフル再ビルドには 20-30 GB 程度必要。  
事前に `docker system df` で空き容量を確認する。

---

## スプリント分解

### Sprint 3-A: 事前確認・環境チェック

```bash
# ディスク確認
docker system df
df -h /

# 現 dev env のポート確認（記録用）
docker compose ps

# ホスト Composer 確認
which composer && composer --version
```

**完了条件:**
- [ ] ディスク空き 30 GB 以上を確認
- [ ] 現 dev env の稼働コンテナ一覧を記録
- [ ] bootstrap 方法（host composer or Docker composer）を決定

---

### Sprint 3-B: 現開発環境の停止

```bash
cd /Users/kazutaka/PhpstormProjects/LedgerLeap
./vendor/bin/sail down
# ポート解放確認
lsof -i :80 -i :3306 | grep LISTEN
```

**完了条件:**
- [ ] `sail down` 正常終了
- [ ] ポート 80, 3306 が解放されている

---

### Sprint 3-C: OSS クローンと初期設定

```bash
cd ~/PhpstormProjects  # または適切なディレクトリ
git clone https://github.com/torinky/LedgerLeap-oss LedgerLeap-oss
cd LedgerLeap-oss

# .env 準備（setup.sh が自動作成するが事前に内容確認）
cp .env.example .env

# Phase 5 軽量化オプション（初回): AI モデル無効化
sed -i '' 's/VLM_ENABLED=true/VLM_ENABLED=false/' .env
sed -i '' 's/RAG_ENABLED=true/RAG_ENABLED=false/' .env
```

**完了条件:**
- [ ] clone 成功、ファイル一式が存在する
- [ ] `.env.example` から `.env` を作成済み

---

### Sprint 3-D: vendor/ ブートストラップ

```bash
cd ~/PhpstormProjects/LedgerLeap-oss

# Option A: ホスト composer がある場合
composer install --ignore-platform-reqs --no-scripts

# Option B: ホスト composer がない場合
docker run --rm -v $(pwd):/app -w /app composer:latest install \
  --ignore-platform-reqs --no-scripts
  
# 確認
ls vendor/bin/sail
```

**完了条件:**
- [ ] `vendor/bin/sail` が存在する

---

### Sprint 3-E: setup.sh 実行（Phase 3 本体）

```bash
cd ~/PhpstormProjects/LedgerLeap-oss
./bin/setup.sh 2>&1 | tee /tmp/setup-oss.log
```

各フェーズ確認:
- [ ] Docker build 成功（`sail build`）
- [ ] Docker up 成功（`sail up -d`）
- [ ] composer install 成功
- [ ] `artisan key:generate` 成功
- [ ] `artisan migrate` 成功（中央DB + テナント）
- [ ] `npm install` 成功
- [ ] `npm run build` 成功
- [ ] seed 成功（DemoCompleteSeeder）

**失敗時の切り分け:** `/tmp/setup-oss.log` を確認し、どのフェーズで止まったかを記録。

---

### Sprint 3-F: 起動確認（Phase 3 完了条件）

```bash
cd ~/PhpstormProjects/LedgerLeap-oss
./vendor/bin/sail ps  # 全コンテナ healthy か確認
curl -o /dev/null -s -w "%{http_code}" http://localhost
curl -o /dev/null -s -w "%{http_code}" http://localhost/login
```

**完了条件:**
- [ ] 全サービスが healthy
- [ ] `curl http://localhost` → HTTP 200 (またはリダイレクト)
- [ ] `/login` ページが表示される

---

### Sprint 3-G: デモログイン確認（Phase 4）

| アカウント | メール | パスワード | 確認項目 |
|------------|--------|------------|----------|
| Super Admin | superadmin@example.com | demo1234 | 全フォルダ表示、台帳作成 |
| Demo User | demo@example.com | demo1234 | 限定フォルダのみ表示 |

**確認チェックリスト:**
- [ ] superadmin でログイン成功
- [ ] 全フォルダが表示される
- [ ] 台帳の作成・編集・削除が動作する
- [ ] ワークフロー（DRAFT → 点検依頼 → 承認）が動作する
- [ ] demo@example.com でログイン成功
- [ ] 権限差分が正しく反映されている（営業部フォルダが見えない等）

---

### Sprint 3-H: Phase 5 設定健全性確認

- [ ] `.env.example` のデフォルト値が適切か確認
- [ ] VLM/RAG を有効化した場合の動作確認（時間があれば）
- [ ] `docker-compose.override.yml` の内容が OSS として妥当か確認
  - Meilisearch / Mailpit は開発用途として妥当
  - LDAP / Selenium はコメントアウト済みを確認

---

### Sprint 3-I: クリーンアップと開発環境復旧

```bash
# OSS 環境を停止
cd ~/PhpstormProjects/LedgerLeap-oss
./vendor/bin/sail down

# 開発環境を復旧
cd /Users/kazutaka/PhpstormProjects/LedgerLeap
./vendor/bin/sail up -d

# 開発環境の復旧確認
docker compose ps
curl -o /dev/null -s -w "%{http_code}" http://localhost
```

**完了条件:**
- [ ] OSS 環境が停止している
- [ ] 開発環境が正常に起動した

---

### Sprint 3-J: 記録・課題整理

- [ ] setup.sh の bootstrap 問題（R-1）を fix issue として起票
- [ ] 発見した課題・手順を issue #223 に記録
- [ ] 必要なら `docs/development/environment-setup.md` を更新

---

## 実行順序サマリー

```
3-A: 事前確認（disk, ports）
  ↓
3-B: sail down (dev env)
  ↓
3-C: git clone LedgerLeap-oss
  ↓
3-D: vendor bootstrap (docker run composer)
  ↓
3-E: ./bin/setup.sh | tee /tmp/setup-oss.log
  ↓
3-F: 起動確認 (curl, docker compose ps)
  ↓
3-G: デモログイン確認
  ↓
3-H: Phase 5 健全性確認
  ↓
3-I: sail down (OSS) → sail up (dev env)
  ↓
3-J: 記録・課題整理
```

---

## 関連リソース

- Issue: #223
- OSS repo: https://github.com/torinky/LedgerLeap-oss
- Demo credentials: `docs/development/demo-credentials.md`
- Setup notes: `docs/development/environment-setup.md`
- Runbook: `docs/runbooks/oss-sync-runbook.md`
