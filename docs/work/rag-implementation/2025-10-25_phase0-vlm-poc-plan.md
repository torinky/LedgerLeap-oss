# Phase 0: VLM動作検証PoC計画書

**作成日:** 2025年10月25日  
**ステータス:** ✅ **PoC完了**
**実施記録:** [Phase 0: VLM動作検証PoC 実施記録](./2025-10-25_phase0-vlm-poc-execution-log.md)

---

## 📋 エグゼクティブサマリー

本格的なVLM/RAG統合実装（[VLM/RAG統合実装計画書](./2025-10-25_vlm-rag-integration-plan-final.md)）を開始する前に、**VLMコンテナの動作確認と基本機能の検証**を行います。

**Phase 0の位置づけ:**
```
Phase 0: VLM動作検証（1週間）★このドキュメント
  ↓
Phase 1: 基盤整備（Week 1-2）
  ↓
Phase 2: VLM処理実装（Week 2-3）
  ↓
Phase 3: RAG統合（Week 3-4）
  ↓
Phase 4: UI機能追加（Week 4-6）
```

---

## 1. Phase 0の目的

### 1.1. 主要な検証項目

1. ✅ **VLMコンテナの動作確認**
   - Docker環境での起動可否
   - CPU環境での動作確認
   - メモリ・CPU使用率の測定

2. ✅ **VLMモデルの基本性能確認**
   - 日本語帳票の認識精度
   - 処理時間の実測
   - Markdown出力品質の評価

3. ✅ **インフラ要件の確認**
   - 必要なリソース量の見積もり
   - ネットワーク設定の確認
   - ストレージ要件の確認

4. ✅ **技術的課題の洗い出し**
   - 想定外の問題の早期発見
   - 対策の検討
   - 実装計画の調整

### 1.2. Phase 0で確認しないこと（Phase 1以降）

- ❌ Laravel統合（ProcessVlmExtractionジョブ等）
- ❌ データベーススキーマ変更
- ❌ RAG統合（ledger_chunks）
- ❌ UI実装

**Phase 0は純粋に「VLMが動くか」の確認に集中します。**

---

## 2. 実施計画（5日間）

### Day 1: 環境準備とVLMコンテナ起動

**目標:** VLMコンテナが起動し、ヘルスチェックが通ること

#### タスク

**2-1. VLMコンテナイメージの準備**

```bash
# 1. Dockerfileの作成
mkdir -p docker/vlm
cd docker/vlm
```

**Dockerfile（PaddleOCR-VL-0.9B用）:**

```dockerfile
# docker/vlm/Dockerfile
FROM python:3.10-slim

# 作業ディレクトリ
WORKDIR /app

# システム依存パッケージ
RUN apt-get update && apt-get install -y \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1-mesa-glx \
    && rm -rf /var/lib/apt/lists/*

# Pythonパッケージ
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# アプリケーションコード
COPY app.py .

# ポート公開
EXPOSE 8000

# ヘルスチェック
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
  CMD curl -f http://localhost:8000/health || exit 1

# 起動
CMD ["python", "app.py"]
```

**requirements.txt:**

```txt
# docker/vlm/requirements.txt
fastapi==0.104.1
uvicorn[standard]==0.24.0
paddlepaddle==2.6.0
paddleocr==2.7.0
python-multipart==0.0.6
Pillow==10.1.0
numpy==1.24.3
opencv-python-headless==4.8.1.78
```

**app.py（シンプルなAPI実装）:**

```python
# docker/vlm/app.py
from fastapi import FastAPI, File, UploadFile, HTTPException
from paddleocr import PaddleOCR
import logging
import time
from pathlib import Path
import tempfile
import os

# ロギング設定
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="VLM API", version="1.0.0")

# PaddleOCR初期化（グローバル変数として1回だけ初期化）
ocr = None

@app.on_event("startup")
async def startup_event():
    global ocr
    logger.info("Initializing PaddleOCR model...")
    try:
        ocr = PaddleOCR(
            use_angle_cls=True,
            lang='japan',  # 日本語モデル
            use_gpu=False,  # CPU使用
            show_log=False
        )
        logger.info("PaddleOCR model initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize PaddleOCR: {e}")
        raise

@app.get("/health")
async def health_check():
    """ヘルスチェックエンドポイント"""
    if ocr is None:
        raise HTTPException(status_code=503, detail="Model not initialized")
    return {
        "status": "healthy",
        "model": "PaddleOCR",
        "version": "2.7.0"
    }

@app.post("/extract")
async def extract_text(file: UploadFile = File(...)):
    """
    画像/PDFからテキストを抽出
    
    Returns:
        {
            "success": bool,
            "text": str,
            "confidence": float,
            "processing_time_ms": int
        }
    """
    if ocr is None:
        raise HTTPException(status_code=503, detail="Model not initialized")
    
    # ファイル保存
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name
        
        logger.info(f"Processing file: {file.filename} ({len(content)} bytes)")
        
        # OCR実行
        start_time = time.time()
        result = ocr.ocr(tmp_path, cls=True)
        processing_time_ms = int((time.time() - start_time) * 1000)
        
        # 結果解析
        if result is None or len(result) == 0:
            return {
                "success": False,
                "text": "",
                "confidence": 0.0,
                "processing_time_ms": processing_time_ms,
                "error": "No text detected"
            }
        
        # テキストと信頼度を抽出
        text_lines = []
        confidences = []
        
        for line in result[0]:  # result[0]が検出結果のリスト
            if line:
                text = line[1][0]  # テキスト
                confidence = line[1][1]  # 信頼度
                text_lines.append(text)
                confidences.append(confidence)
        
        full_text = "\n".join(text_lines)
        avg_confidence = sum(confidences) / len(confidences) if confidences else 0.0
        
        logger.info(f"Extraction completed: {len(text_lines)} lines, confidence={avg_confidence:.3f}")
        
        return {
            "success": True,
            "text": full_text,
            "confidence": avg_confidence,
            "processing_time_ms": processing_time_ms,
            "line_count": len(text_lines)
        }
        
    except Exception as e:
        logger.error(f"Extraction failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))
    
    finally:
        # 一時ファイル削除
        if os.path.exists(tmp_path):
            os.unlink(tmp_path)

@app.get("/")
async def root():
    return {
        "message": "VLM API Server",
        "endpoints": {
            "health": "/health",
            "extract": "POST /extract"
        }
    }
```

**2-2. docker-compose.yml への追加**

```yaml
# docker-compose.yml に追加

services:
  # 既存サービス...
  
  vlm:
    build:
      context: ./docker/vlm
      dockerfile: Dockerfile
    container_name: ledgerleap_vlm
    ports:
      - "8000:8000"
    volumes:
      - ./storage/vlm-cache:/root/.paddleocr  # モデルキャッシュ
    environment:
      - PYTHONUNBUFFERED=1
    # リソース制限（Phase 0検証用）
    deploy:
      resources:
        limits:
          cpus: '4'
          memory: 8G
        reservations:
          cpus: '2'
          memory: 4G
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s
    networks:
      - sail
```

**2-3. コンテナ起動と動作確認**

```bash
# 1. ビルド
docker-compose build vlm

# 2. 起動
docker-compose up -d vlm

# 3. ログ確認（モデルダウンロード進捗）
docker-compose logs -f vlm

# 4. ヘルスチェック
curl http://localhost:8000/health

# 期待される出力:
# {
#   "status": "healthy",
#   "model": "PaddleOCR",
#   "version": "2.7.0"
# }
```

#### 成功基準

- ✅ VLMコンテナが起動する
- ✅ ヘルスチェックが200 OKを返す
- ✅ 初回起動時に日本語モデルがダウンロードされる
- ✅ メモリ使用量が8GB以内

#### トラブルシューティング

**問題1: モデルダウンロードが失敗**
```bash
# 手動ダウンロード
docker-compose exec vlm python -c "from paddleocr import PaddleOCR; PaddleOCR(lang='japan')"
```

**問題2: メモリ不足**
```bash
# Docker Desktopのメモリ設定を確認
# Settings → Resources → Memory: 最低8GB推奨
```

---

### Day 2-3: 基本的なOCR機能テスト

**目標:** 実際の帳票でOCR精度を確認

#### タスク

**3-1. テストデータの準備**

```bash
# テスト画像ディレクトリ作成
mkdir -p storage/test/vlm-poc
```

**テストケース（計10件）:**

| ID | ファイル名 | 種類 | 言語 | 複雑度 | 期待結果 |
|----|-----------|------|------|--------|---------|
| T1 | invoice_simple.pdf | 請求書 | 日本語 | 低 | 請求番号、金額を認識 |
| T2 | invoice_complex.pdf | 請求書 | 日本語 | 高 | テーブル構造を認識 |
| T3 | receipt_01.jpg | 領収書 | 日本語 | 低 | 店名、金額を認識 |
| T4 | receipt_handwritten.jpg | 領収書 | 手書き | 高 | 手書き文字を認識 |
| T5 | meeting_notes.pdf | 議事録 | 日本語 | 中 | 箇条書きを認識 |
| T6 | contract.pdf | 契約書 | 日本語 | 高 | 複数ページを認識 |
| T7 | receipt_blurry.jpg | 領収書 | 日本語 | 高 | 低解像度でも認識 |
| T8 | invoice_english.pdf | 請求書 | 英語 | 低 | 英語も認識可能か |
| T9 | mixed_lang.pdf | 混在 | 日英混在 | 中 | 混在言語を認識 |
| T10 | table_heavy.pdf | 明細書 | 日本語 | 高 | 複雑なテーブル |

**3-2. テストスクリプト作成**

```bash
# tests/vlm-poc/test_vlm_basic.sh
#!/bin/bash

set -e

echo "=== VLM Basic Function Test ==="
echo ""

TEST_DIR="storage/test/vlm-poc"
RESULT_DIR="storage/test/vlm-poc/results"
mkdir -p "$RESULT_DIR"

# 結果ファイル
RESULT_FILE="$RESULT_DIR/test_results_$(date +%Y%m%d_%H%M%S).json"
echo "{" > "$RESULT_FILE"
echo "  \"test_start\": \"$(date -Iseconds)\"," >> "$RESULT_FILE"
echo "  \"results\": [" >> "$RESULT_FILE"

# テストケース実行
for file in "$TEST_DIR"/*.{pdf,jpg,png}; do
    [ -e "$file" ] || continue
    
    filename=$(basename "$file")
    echo "Testing: $filename"
    
    # API呼び出し
    response=$(curl -s -X POST \
        -F "file=@$file" \
        http://localhost:8000/extract)
    
    # 結果保存
    echo "    {" >> "$RESULT_FILE"
    echo "      \"filename\": \"$filename\"," >> "$RESULT_FILE"
    echo "      \"response\": $response" >> "$RESULT_FILE"
    echo "    }," >> "$RESULT_FILE"
    
    # サマリー表示
    success=$(echo "$response" | jq -r '.success')
    confidence=$(echo "$response" | jq -r '.confidence')
    time_ms=$(echo "$response" | jq -r '.processing_time_ms')
    
    if [ "$success" = "true" ]; then
        echo "  ✅ Success (confidence: $confidence, time: ${time_ms}ms)"
    else
        echo "  ❌ Failed"
    fi
    echo ""
done

# 結果ファイル閉じる
echo "  ]," >> "$RESULT_FILE"
echo "  \"test_end\": \"$(date -Iseconds)\"" >> "$RESULT_FILE"
echo "}" >> "$RESULT_FILE"

echo "Results saved to: $RESULT_FILE"
echo ""
echo "=== Test Summary ==="
jq -r '.results[] | "\(.filename): \(.response.success) (confidence: \(.response.confidence))"' "$RESULT_FILE"
```

**3-3. テスト実行**

```bash
# 実行権限付与
chmod +x tests/vlm-poc/test_vlm_basic.sh

# テスト実行
./tests/vlm-poc/test_vlm_basic.sh
```

#### 評価基準

**定量評価:**

| 指標 | 目標値 | 評価方法 |
|------|--------|---------|
| OCR成功率 | > 80% | 10件中8件以上が成功 |
| 平均信頼度 | > 0.75 | confidence平均値 |
| 平均処理時間 | < 30秒 | processing_time_ms平均 |
| メモリ使用量 | < 6GB | docker stats vlm |

**定性評価（人手確認）:**

- [ ] 請求番号が正しく認識できている
- [ ] 金額が正しく認識できている
- [ ] テーブル構造が認識できている
- [ ] 手書き文字がある程度認識できている

#### Day 2-3 成功基準

- ✅ 10件中8件以上でテキスト抽出成功
- ✅ 平均信頼度 > 0.75
- ✅ 平均処理時間 < 30秒
- ✅ 重大なエラーなし（メモリ不足、クラッシュ等）

---

### Day 4: 負荷テストとリソース測定

**目標:** 本番想定の負荷でのリソース使用量を測定

#### タスク

**4-1. 負荷テストスクリプト作成**

```bash
# tests/vlm-poc/load_test.sh
#!/bin/bash

CONCURRENT=5  # 同時実行数
TOTAL=50      # 総テスト数

echo "=== VLM Load Test ==="
echo "Concurrent: $CONCURRENT"
echo "Total: $TOTAL"
echo ""

# 開始前のリソース確認
echo "Initial resources:"
docker stats vlm --no-stream

# 負荷テスト実行
seq 1 $TOTAL | xargs -P $CONCURRENT -I {} bash -c '
    file="storage/test/vlm-poc/invoice_simple.pdf"
    start=$(date +%s%3N)
    curl -s -X POST -F "file=@$file" http://localhost:8000/extract > /dev/null
    end=$(date +%s%3N)
    echo "Request {}: $((end - start))ms"
'

# 終了後のリソース確認
echo ""
echo "Final resources:"
docker stats vlm --no-stream
```

**4-2. リソースモニタリング**

```bash
# 別ターミナルで実行（リアルタイムモニタリング）
docker stats vlm

# 負荷テスト実行
./tests/vlm-poc/load_test.sh
```

#### 評価基準

| 指標 | 目標値 | 評価方法 |
|------|--------|---------|
| CPU使用率（ピーク） | < 80% | docker stats |
| メモリ使用量（ピーク） | < 6GB | docker stats |
| 平均レスポンスタイム | < 20秒 | load_test.sh出力 |
| エラー率 | < 5% | 50件中45件以上成功 |

#### Day 4 成功基準

- ✅ 同時5リクエストを処理可能
- ✅ メモリ使用量が8GB以内
- ✅ OOM Killerが発動しない
- ✅ エラー率 < 5%

---

### Day 5: 結果まとめと実装計画調整

**目標:** Phase 0の結果を評価し、Phase 1以降の計画を最終確認

#### タスク

**5-1. PoC結果レポート作成**

```markdown
# Phase 0 PoC結果レポート（テンプレート）

## 実施日
2025年10月XX日 - XX日

## テスト結果サマリー

### 1. 基本機能テスト（Day 2-3）

| 指標 | 目標値 | 実測値 | 評価 |
|------|--------|--------|------|
| OCR成功率 | > 80% | XX% | ✅/❌ |
| 平均信頼度 | > 0.75 | X.XX | ✅/❌ |
| 平均処理時間 | < 30秒 | XXs | ✅/❌ |
| メモリ使用量 | < 6GB | X.XGB | ✅/❌ |

**詳細:**
- 成功: X/10件
- 失敗: X/10件（ファイル名: ...）

### 2. 負荷テスト（Day 4）

| 指標 | 目標値 | 実測値 | 評価 |
|------|--------|--------|------|
| CPU使用率（ピーク） | < 80% | XX% | ✅/❌ |
| メモリ（ピーク） | < 6GB | X.XGB | ✅/❌ |
| 平均レスポンス | < 20秒 | XXs | ✅/❌ |
| エラー率 | < 5% | X% | ✅/❌ |

### 3. 発見された課題

1. **課題1: XXX**
   - 現象: ...
   - 影響: ...
   - 対策案: ...

2. **課題2: XXX**
   - ...

### 4. Phase 1以降への推奨事項

- [ ] 推奨事項1
- [ ] 推奨事項2
- [ ] 推奨事項3

### 5. Go/No-Go判断

**判断:** ✅ Go / ❌ No-Go

**理由:**
- ...

---

**作成者:** XXX  
**承認者:** XXX
```

**5-2. ステークホルダー報告会**

- Phase 0結果の共有
- Phase 1以降の実施可否判断
- 予算・リソースの最終確認

#### Day 5 成果物

- ✅ PoC結果レポート
- ✅ Go/No-Go判断
- ✅ Phase 1実施計画の最終確認

---

## 3. Go/No-Go判断基準

### 3.1. Go判断（Phase 1以降に進む）

以下の条件をすべて満たす場合:

1. ✅ **基本機能**: OCR成功率 > 70%（目標80%、最低ライン70%）
2. ✅ **性能**: 平均処理時間 < 40秒（目標30秒、最低ライン40秒）
3. ✅ **安定性**: 負荷テストでクラッシュなし
4. ✅ **リソース**: メモリ使用量 < 8GB（限界値）

### 3.2. 条件付きGo判断

以下の場合は計画調整の上でGo:

- OCR成功率 60-70%: モデル変更を検討（Donut等）
- 処理時間 40-60秒: バッチサイズ調整、並列化を検討
- メモリ 8-10GB: リソース増強を検討

### 3.3. No-Go判断（Phase 0を延長）

以下の場合はPhase 0を延長して対策:

- ❌ OCR成功率 < 60%
- ❌ 処理時間 > 60秒
- ❌ 頻繁なクラッシュ
- ❌ メモリ > 10GB

---

## 4. 必要なリソース

### 4.1. 人的リソース

| 役割 | 担当者 | 工数 |
|------|--------|------|
| テクニカルリード | XXX | 5日間フルタイム |
| バックエンド開発者 | XXX | 2日間（Day 1, 5） |
| インフラエンジニア | XXX | 1日間（Day 1） |

### 4.2. インフラリソース

| リソース | スペック | 用途 |
|---------|---------|------|
| 開発サーバー | CPU 4コア, メモリ 16GB | VLMコンテナ実行 |
| ストレージ | 50GB | モデルキャッシュ、テストデータ |

### 4.3. テストデータ

- 実データ（匿名化済み）: 10件
- サンプルデータ: 必要に応じて作成

---

## 5. リスクと対策

| リスク | 発生確率 | 影響度 | 対策 |
|--------|---------|--------|------|
| モデルダウンロード失敗 | 低 | 高 | 事前ダウンロード、プロキシ設定 |
| 日本語精度が低い | 中 | 高 | 別モデル（Donut）を検証 |
| メモリ不足 | 中 | 中 | リソース増強、モデル軽量化 |
| 処理時間が長い | 中 | 中 | バッチサイズ調整、GPU検討 |
| Docker環境問題 | 低 | 高 | 事前環境確認、トラブルシューティング |

---

## 6. Phase 0完了後のアクション

### 6.1. Go判断の場合

1. **即座に実施:**
   - [ ] PoC結果レポートの承認
   - [ ] Phase 1キックオフミーティング
   - [ ] Phase 1タスクチケット作成

2. **Week 1（Phase 1）:**
   - [ ] attached_filesテーブル拡張マイグレーション
   - [ ] VlmClientService実装開始
   - [ ] Phase 0で発見された課題の対応

### 6.2. No-Go判断の場合

1. **Phase 0延長:**
   - [ ] 課題の詳細分析
   - [ ] 代替モデルの検証（Donut, MinerU等）
   - [ ] インフラ要件の見直し

2. **再評価:**
   - [ ] 2週間後に再度Go/No-Go判断
   - [ ] 必要に応じて計画全体の見直し

---

## 7. チェックリスト

### Day 1: 環境準備

- [ ] docker/vlm/Dockerfile 作成
- [ ] docker/vlm/requirements.txt 作成
- [ ] docker/vlm/app.py 作成
- [ ] docker-compose.yml 更新
- [ ] VLMコンテナのビルド・起動
- [ ] ヘルスチェック確認

### Day 2-3: 基本機能テスト

- [ ] テストデータ準備（10件）
- [ ] tests/vlm-poc/test_vlm_basic.sh 作成
- [ ] テスト実行
- [ ] 結果の定量評価
- [ ] 結果の定性評価（人手確認）
- [ ] 課題の洗い出し

### Day 4: 負荷テスト

- [ ] tests/vlm-poc/load_test.sh 作成
- [ ] 負荷テスト実行
- [ ] リソース使用量の測定
- [ ] パフォーマンスボトルネックの特定

### Day 5: まとめ

- [ ] PoC結果レポート作成
- [ ] ステークホルダー報告会
- [ ] Go/No-Go判断
- [ ] Phase 1計画の最終確認

---

## 8. 参考資料

### 8.1. 関連ドキュメント

- **[VLM/RAG統合実装計画書（最終版）](./2025-10-25_vlm-rag-integration-plan-final.md)** - Phase 1以降の詳細計画
- **[VLM保存戦略変更提案](./2025-10-25_vlm-storage-strategy-proposal.md)** - データスキーマ設計
- **[VLM-OCR技術調査](./2025-10-23_vlm-ocr-and-indexing-strategy-review.md)** - VLMモデル選定の背景

### 8.2. 技術参考

- PaddleOCR公式ドキュメント: https://github.com/PaddlePaddle/PaddleOCR
- FastAPI公式ドキュメント: https://fastapi.tiangolo.com/
- Docker Compose公式ドキュメント: https://docs.docker.com/compose/

---

## ✅ 結論

Phase 0は、本格実装前の**最も重要なリスク低減策**です。

**Phase 0を実施することで:**
1. ✅ VLMの基本動作を確認できる
2. ✅ 技術的課題を早期発見できる
3. ✅ リソース要件を正確に見積もれる
4. ✅ 実装計画を調整できる
5. ✅ ステークホルダーへの説明根拠を得られる

**推奨:** Phase 1開始前の1週間でPhase 0を必ず実施してください。

---

**作成者:** GitHub Copilot CLI (Serena)  
**最終更新:** 2025年10月25日  
**バージョン:** 1.0
