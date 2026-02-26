# RAG性能パラメータ変更手順

**作成日:** 2025年10月18日  
**重要:** 環境変数を変更した後は、必ずコンテナの再作成が必要です

---

## 問題: 環境変数の変更が反映されない

### 症状

`.env`ファイルを編集しても、コンテナ内の環境変数が更新されない。

```bash
# .envを変更
RAG_BATCH_SIZE=4

# しかしコンテナ内では古い値のまま
docker exec ledgerleap_embedding env | grep EMBEDDING_BATCH_SIZE
# EMBEDDING_BATCH_SIZE=8  ← 古い値
```

### 原因

Docker Composeは、既存のコンテナがある場合、環境変数を再読み込みしません。`docker-compose restart`では環境変数は更新されません。

---

## 正しい変更手順

### Step 1: .envファイルを編集

```bash
# .envを編集
nano .env

# または
vim .env
```

**変更例:**
```bash
RAG_BATCH_SIZE=8
RAG_NUM_THREADS=8
RAG_NUM_INTEROP_THREADS=4
RAG_DEVICE=cpu
RAG_CONVERT_TO_NUMPY=true
EMBEDDING_SERVICE_TIMEOUT=180
```

### Step 2: コンテナを再作成（重要！）

**方法A: sail downとup（推奨）**

```bash
# コンテナを完全に削除
./vendor/bin/sail down embedding

# 新しい環境変数で起動
./vendor/bin/sail up -d embedding
```

**方法B: docker-compose up --force-recreate**

```bash
docker-compose up -d --force-recreate embedding
```

**❌ 間違った方法（これでは反映されません）:**
```bash
# これらは環境変数を更新しない
./vendor/bin/sail restart embedding  # ❌
docker restart ledgerleap_embedding  # ❌
docker-compose restart embedding     # ❌
```

### Step 3: モデルロードを待機

BGE-M3のような大きなモデルは、ロードに1-2分かかります。

```bash
# ログでモデルロードを確認
docker logs -f ledgerleap_embedding

# "Successfully loaded model" が表示されるまで待つ
```

**または90秒待機:**
```bash
sleep 90
```

### Step 4: 環境変数が反映されたことを確認

```bash
# コンテナ内の環境変数を確認
docker exec ledgerleap_embedding env | grep -E "RAG_|EMBEDDING_" | sort
```

**期待される出力:**
```
EMBEDDING_BATCH_SIZE=8
EMBEDDING_MODEL=BAAI/bge-m3
RAG_CONVERT_TO_NUMPY=true
RAG_DEVICE=cpu
RAG_NUM_INTEROP_THREADS=4
RAG_NUM_THREADS=8
```

### Step 5: 動作確認

```bash
# ログで性能設定を確認
docker logs ledgerleap_embedding --tail 30 | grep -A 5 "Performance settings"
```

**期待される出力:**
```
INFO:app:Performance settings:
INFO:app:  - Device: cpu
INFO:app:  - PyTorch threads: 8
INFO:app:  - PyTorch interop threads: 4
```

---

## クイックリファレンス

### パターン1: 単一パラメータの変更

```bash
# 1. バッチサイズを変更
sed -i '' 's/RAG_BATCH_SIZE=.*/RAG_BATCH_SIZE=8/' .env

# 2. コンテナ再作成
./vendor/bin/sail down embedding
./vendor/bin/sail up -d embedding

# 3. 待機
sleep 90

# 4. 確認
docker exec ledgerleap_embedding env | grep EMBEDDING_BATCH_SIZE
```

### パターン2: 複数パラメータの変更

```bash
# 1. .envを編集
cat >> .env.tmp << 'EOF'
RAG_BATCH_SIZE=8
RAG_NUM_THREADS=8
RAG_NUM_INTEROP_THREADS=4
EOF

# 既存の行を削除して新しい値を追加
grep -v "RAG_BATCH_SIZE\|RAG_NUM_THREADS\|RAG_NUM_INTEROP_THREADS" .env > .env.new
cat .env.tmp >> .env.new
mv .env.new .env
rm .env.tmp

# 2. コンテナ再作成
./vendor/bin/sail down embedding && ./vendor/bin/sail up -d embedding

# 3. 待機と確認
sleep 90 && docker logs ledgerleap_embedding --tail 20
```

### パターン3: プリセット適用

```bash
# バランス型プリセット（推奨）
cat > .env.rag.balanced << 'EOF'
RAG_BATCH_SIZE=4
RAG_NUM_THREADS=4
RAG_NUM_INTEROP_THREADS=2
RAG_CONVERT_TO_NUMPY=true
RAG_DEVICE=cpu
EMBEDDING_SERVICE_TIMEOUT=180
EOF

# .envに反映
grep -v "RAG_BATCH_SIZE\|RAG_NUM_THREADS\|RAG_NUM_INTEROP\|RAG_CONVERT\|RAG_DEVICE\|EMBEDDING_SERVICE_TIMEOUT" .env > .env.tmp
cat .env.rag.balanced >> .env.tmp
mv .env.tmp .env

# コンテナ再作成
./vendor/bin/sail down embedding && ./vendor/bin/sail up -d embedding && sleep 90
```

---

## トラブルシューティング

### Q1: 変更が反映されない

**確認:**
```bash
# .envの値
grep RAG_BATCH_SIZE .env

# コンテナ内の値
docker exec ledgerleap_embedding env | grep EMBEDDING_BATCH_SIZE
```

**不一致の場合:**
```bash
# 完全にコンテナを削除して再作成
docker stop ledgerleap_embedding
docker rm ledgerleap_embedding
./vendor/bin/sail up -d embedding
```

---

### Q2: コンテナが起動しない

**原因1:** docker-compose.ymlの構文エラー

**確認:**
```bash
docker-compose config
```

**原因2:** メモリ不足

**確認:**
```bash
docker stats ledgerleap_embedding --no-stream
```

**解決策:**
```bash
# Dockerのメモリ割り当てを増やす（Docker Desktop設定）
# または
# バッチサイズを減らす
RAG_BATCH_SIZE=1
```

---

### Q3: 古い値が残っている

**完全リセット手順:**

```bash
# 1. すべてのコンテナを停止
./vendor/bin/sail down

# 2. embeddingコンテナのイメージを削除
docker rmi ledgerleap-embedding

# 3. .envを再確認
grep "^RAG_" .env

# 4. 再ビルドと起動
./vendor/bin/sail build --no-cache embedding
./vendor/bin/sail up -d embedding

# 5. ログで確認
sleep 90
docker logs ledgerleap_embedding --tail 30
```

---

## 設定変更チェックリスト

環境変数を変更する際は、以下を確認：

- [ ] `.env`ファイルを編集
- [ ] 変更内容を確認（`grep RAG_ .env`）
- [ ] コンテナを完全に再作成（`sail down` → `sail up`）
- [ ] モデルロード完了を待機（90秒）
- [ ] 環境変数が反映されたか確認（`docker exec ... env`）
- [ ] ログで性能設定を確認（`docker logs`）
- [ ] テストまたはベンチマークで動作確認

---

## よくある間違い

### ❌ 間違い1: restartコマンドを使う

```bash
# これでは環境変数は更新されません
./vendor/bin/sail restart embedding  # ❌
docker restart ledgerleap_embedding  # ❌
```

**正解:**
```bash
./vendor/bin/sail down embedding
./vendor/bin/sail up -d embedding
```

---

### ❌ 間違い2: .envを編集せずにdocker-compose.ymlを編集

```bash
# docker-compose.ymlを直接編集
environment:
  - EMBEDDING_BATCH_SIZE=8  # ❌ ハードコード
```

**正解:**
```bash
# .envで管理
RAG_BATCH_SIZE=8

# docker-compose.ymlは変数展開
environment:
  - EMBEDDING_BATCH_SIZE=${RAG_BATCH_SIZE:-1}  # ✅
```

---

### ❌ 間違い3: モデルロード完了を待たずにテスト

```bash
./vendor/bin/sail up -d embedding
# すぐにテスト ❌
./bin/test-rag-performance.sh
```

**正解:**
```bash
./vendor/bin/sail up -d embedding
sleep 90  # モデルロードを待機 ✅
./bin/test-rag-performance.sh
```

---

## まとめ

### 📋 標準的な変更フロー

```bash
# Step 1: 編集
nano .env

# Step 2: 再作成（重要！）
./vendor/bin/sail down embedding
./vendor/bin/sail up -d embedding

# Step 3: 待機
sleep 90

# Step 4: 確認
docker exec ledgerleap_embedding env | grep -E "RAG_|EMBEDDING_"
docker logs ledgerleap_embedding --tail 20

# Step 5: テスト
./bin/test-rag-performance.sh
```

### ⚠️ 重要な注意点

1. **必ず`down`してから`up`する**
   - `restart`では環境変数は更新されない

2. **モデルロードを待つ**
   - BGE-M3: 約90秒
   - all-MiniLM-L6-v2: 約30秒

3. **変更後はテストする**
   - ベンチマークで性能改善を確認
   - クラッシュしないことを確認

---

**環境変数の変更は、必ずコンテナの再作成が必要です。この手順を守ることで、確実に設定を反映できます。**
