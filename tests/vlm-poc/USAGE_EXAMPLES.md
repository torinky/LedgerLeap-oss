# VLM Model Comparison - 使用例

## 基本的な使い方

### 1. 単一モデルのテスト

```bash
cd tests/vlm-poc

# test.pdfをPaddleOCRでテスト
python compare_models.py test.pdf
```

**出力:**
```
======================================================================
Model Comparison Test
======================================================================
File: test.pdf
Size: 776.9 KB
Time: 2025-11-02T18:21:34.945527

📡 Current VLM service: paddleocr

Testing current model configuration...
  Testing paddleocr... ✅ (500 blocks, 51 kv, 0 tables)

💾 Full result saved to: storage/model_comparison/paddleocr_test_20251102_182211.json
📄 Markdown saved to: storage/model_comparison/paddleocr_test_20251102_182211.md
📊 Summary saved to: storage/model_comparison/summary_paddleocr_test_20251102_182211.json

======================================================================
SUMMARY
======================================================================
Model: paddleocr
Processing time: 36.86s

Extraction:
  Text blocks: 500
  Key-value pairs: 51
  Tables: 0

Quality:
  Avg confidence: 0.951
  Min confidence: 0.523
  Max confidence: 1.000

Output:
  Markdown: 4649 chars
  HTML: 7678 chars
```

### 2. 複数モデルの比較

```bash
# Step 1: PaddleOCRでテスト
./bin/vlm-switch.sh paddleocr
cd tests/vlm-poc
python compare_models.py test.pdf

# Step 2: Markerでテスト
cd ../..
./bin/vlm-switch.sh marker
cd tests/vlm-poc
python compare_models.py test.pdf

# Step 3: 結果を比較
python compare_results.py test
```

**比較結果の例:**
```
Found 2 result file(s) matching 'test'

================================================================================
EXTRACTION COMPARISON
================================================================================
Model            Text Blocks    Key-Value     Tables   Time (s)
--------------------------------------------------------------------------------
paddleocr                500           51          0      36.86
marker                   450            8          5      42.30

================================================================================
QUALITY COMPARISON
================================================================================
Model             Avg Conf   Min Conf   Max Conf    MD Length
--------------------------------------------------------------------------------
paddleocr            0.951      0.523      1.000         4649
marker               0.945      0.580      0.999         5230
```

### 3. 複数ファイルのテスト

```bash
# 複数ファイルを順次テスト
python compare_models.py invoice_simple.pdf
python compare_models.py receipt_01.jpg
python compare_models.py test.pdf

# 各ファイルの結果を確認
python compare_results.py invoice
python compare_results.py receipt
python compare_results.py test
```

## 高度な使い方

### モデル切り替えスクリプト

複数モデルで自動テストする簡易スクリプト:

```bash
#!/bin/bash
# test_all_models.sh

TEST_FILE="test.pdf"

for model in paddleocr marker mineru; do
    echo "Testing with $model..."
    ./bin/vlm-switch.sh $model
    sleep 10  # サービス起動待ち
    
    cd tests/vlm-poc
    python compare_models.py $TEST_FILE
    cd ../..
    
    echo "Completed: $model"
    echo ""
done

echo "All tests completed. Compare results:"
cd tests/vlm-poc
python compare_results.py ${TEST_FILE%.*}
```

### カスタム分析スクリプト

保存されたJSONから独自の分析:

```python
#!/usr/bin/env python3
"""
Custom analysis of VLM results
"""
import json
from pathlib import Path

results_dir = Path("storage/model_comparison")

for json_file in results_dir.glob("paddleocr_*.json"):
    with open(json_file) as f:
        data = json.load(f)
    
    # カスタム分析
    structured = data['structured_data']
    
    # 低信頼度のテキストブロックを抽出
    low_confidence = [
        block for block in structured['text_blocks']
        if block['confidence'] < 0.8
    ]
    
    print(f"{json_file.name}: {len(low_confidence)} low confidence blocks")
    for block in low_confidence[:3]:
        print(f"  [{block['confidence']:.3f}] {block['content'][:50]}")
```

## 実際の比較シナリオ

### シナリオ1: 請求書のOCR精度比較

```bash
# 目的: 請求書でどのモデルが一番正確にKey-Valueを抽出できるか

# 1. PaddleOCRでテスト
./bin/vlm-switch.sh paddleocr
python compare_models.py invoice_simple.pdf

# 2. PaddleOCR-VL (GPU版)でテスト
./bin/vlm-switch.sh paddleocr-vl
python compare_models.py invoice_simple.pdf

# 3. 比較
python compare_results.py invoice_simple
```

**期待される結果:**
- PaddleOCR: 高速、基本的なKey-Value検出
- PaddleOCR-VL: 低速だが、より高度な構造理解

### シナリオ2: 表を含むPDFの処理

```bash
# 目的: 表構造を保持できるモデルの選定

# 1. PaddleOCR (表認識なし)
./bin/vlm-switch.sh paddleocr
python compare_models.py meeting_notes.pdf

# 2. Marker (Markdown表形式)
./bin/vlm-switch.sh marker
python compare_models.py meeting_notes.pdf

# 3. 比較
python compare_results.py meeting_notes
```

**期待される結果:**
- PaddleOCR: `tables: 0` (表構造なし)
- Marker: `tables: N` (Markdown形式で表を保持)

### シナリオ3: 処理速度の比較

```bash
# 目的: 大量処理時のスループット確認

# 複数ファイルで速度測定
for file in invoice_simple.pdf receipt_01.jpg test.pdf; do
    time python compare_models.py $file
done

# 結果を確認
python compare_results.py invoice
python compare_results.py receipt
python compare_results.py test
```

## トラブルシューティング

### 問題: "Service not available"

```bash
# VLMサービスの状態確認
./bin/vlm-switch.sh status

# ログ確認
docker-compose logs vlm

# 再起動
docker-compose restart vlm
```

### 問題: "No results found"

```bash
# 保存されたファイル一覧
ls -lh tests/vlm-poc/storage/model_comparison/

# パターンを変えて検索
python compare_results.py test
python compare_results.py invoice
python compare_results.py ""  # 全て表示
```

### 問題: 処理が遅い

```bash
# タイムアウトを長くする
# compare_models.py の timeout=180 を timeout=300 に変更

# または軽量なファイルでテスト
python compare_models.py receipt_01.jpg  # 小さい画像
```

## ベストプラクティス

### 1. テスト前の準備

```bash
# VLMサービスのヘルスチェック
curl http://localhost:8001/health | jq

# ディスク容量確認
df -h tests/vlm-poc/storage/model_comparison/
```

### 2. 結果ファイルの管理

```bash
# 古い結果を削除
find tests/vlm-poc/storage/model_comparison/ -name "*.json" -mtime +7 -delete

# 特定のモデルの結果のみ削除
rm tests/vlm-poc/storage/model_comparison/marker_*

# バックアップ
tar czf vlm_results_backup_$(date +%Y%m%d).tar.gz \
    tests/vlm-poc/storage/model_comparison/
```

### 3. 結果の分析

```bash
# JSONからの情報抽出
cd tests/vlm-poc/storage/model_comparison

# 全モデルの処理時間を比較
jq '.processing_time_s' *.json

# Key-Valueペア数の統計
jq '.structured_data.key_value_pairs | length' paddleocr_*.json

# 平均信頼度の確認
jq '[.structured_data.text_blocks[].confidence] | add / length' paddleocr_*.json
```

## まとめ

このテストフレームワークを使って:
- ✅ 複数のVLMモデルを簡単に比較できる
- ✅ 結果は自動的に保存される
- ✅ JSON/Markdownで詳細な分析が可能
- ✅ 既存のテストスクリプトと共存できる

---

**作成日:** 2025年11月2日
