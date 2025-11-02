# VLM Model Comparison Tests

このディレクトリは、異なるVLMモデル（PaddleOCR、PaddleOCR-VL、Marker、MinerU）の出力結果を比較するためのテスト環境です。

## ディレクトリ構成

```
tests/vlm-poc/
├── README.md                     # このファイル
├── compare_models.py             # モデルテスト実行スクリプト
├── compare_results.py            # 結果比較スクリプト
├── test_paddle_structured.py     # PaddleOCR専用テスト
├── test_vlm_basic.sh            # 基本的なVLMテスト（既存）
├── test_mineru.sh               # MinerU専用テスト（既存）
└── storage/
    └── model_comparison/         # テスト結果の保存先
        ├── paddleocr_test_20251102_123456.json
        ├── paddleocr_test_20251102_123456.md
        ├── marker_test_20251102_123457.json
        ├── marker_test_20251102_123457.md
        └── summary_*.json        # サマリーファイル
```

## 使い方

### 1. モデルの切り替え

テストしたいモデルに切り替えます：

```bash
# PaddleOCR (安定版)
./bin/vlm-switch.sh paddleocr

# PaddleOCR-VL (実験版、GPU推奨)
./bin/vlm-switch.sh paddleocr-vl

# Marker (PDF→Markdown)
./bin/vlm-switch.sh marker

# MinerU (PDF→Markdown)
./bin/vlm-switch.sh mineru
```

### 2. テストの実行

```bash
cd tests/vlm-poc

# 特定のファイルでテスト
python compare_models.py test.pdf
python compare_models.py invoice_simple.pdf

# 利用可能なテストファイル一覧
python compare_models.py
```

### 3. 結果の比較

```bash
# パターンで結果を検索して比較
python compare_results.py test.pdf
python compare_results.py invoice_simple

# 保存済みの結果一覧
python compare_results.py
```

## 出力ファイル

### JSON結果ファイル

完全なOCR結果が保存されます：

```json
{
  "success": true,
  "model": "paddleocr",
  "device": "cpu",
  "processing_time_s": 37.67,
  "html": "<html>...",
  "markdown": "...",
  "structured_data": {
    "pages": [...],
    "text_blocks": [...],
    "key_value_pairs": [...],
    "tables": [...]
  },
  "_test_metadata": {
    "model_name": "paddleocr",
    "processing_time_total": 38.12,
    "timestamp": "2025-11-02T18:30:00"
  }
}
```

### Markdownファイル

抽出されたMarkdownテキストが保存されます。

### サマリーファイル

主要な統計情報のみを含むコンパクトなファイル：

```json
{
  "test_info": {
    "file": "test",
    "timestamp": "20251102_183000",
    "model": "paddleocr"
  },
  "performance": {
    "processing_time_s": 37.67,
    "total_time_s": 38.12
  },
  "extraction_results": {
    "pages": 1,
    "text_blocks": 500,
    "key_value_pairs": 51,
    "tables": 0
  },
  "quality_metrics": {
    "markdown_length": 4649,
    "html_length": 7678,
    "confidence": {
      "average": 0.951,
      "min": 0.523,
      "max": 1.000
    }
  }
}
```

## 比較例

### 1. 異なるモデルで同じファイルをテスト

```bash
# PaddleOCRでテスト
./bin/vlm-switch.sh paddleocr
python compare_models.py test.pdf

# Markerでテスト
./bin/vlm-switch.sh marker
python compare_models.py test.pdf

# 結果を比較
python compare_results.py test.pdf
```

### 2. 複数のファイルで同じモデルをテスト

```bash
# PaddleOCRで複数ファイルをテスト
python compare_models.py invoice_simple.pdf
python compare_models.py receipt_01.jpg
python compare_models.py test.pdf

# 全結果を確認
ls -lh storage/model_comparison/
```

## 比較レポートの見方

`compare_results.py`の出力例：

```
EXTRACTION COMPARISON
================================================================================
Model           Text Blocks   Key-Value      Tables    Time (s)
--------------------------------------------------------------------------------
paddleocr               500           51           0      37.67
marker                  450            8          12      42.30

QUALITY COMPARISON
================================================================================
Model            Avg Conf   Min Conf   Max Conf   MD Length
--------------------------------------------------------------------------------
paddleocr           0.951      0.523      1.000        4649
marker              0.945      0.580      0.999        5230

KEY-VALUE PAIRS COMPARISON
================================================================================

paddleocr (51 pairs):
------------------------------------------------------------
   1. [0.977] 16: 28
   2. [1.000] 今日10: 19
   ...
```

## 注意事項

### モデル別の特性

| モデル | 強み | 弱み | 推奨用途 |
|--------|------|------|---------|
| **PaddleOCR** | CPU高速、日本語精度高 | 表認識未対応 | 通常の帳票OCR |
| **PaddleOCR-VL** | 表認識対応、高精度 | GPU必須、遅い | 複雑な文書 |
| **Marker** | 表をMarkdownで保持 | PDF特化 | PDF→Markdown |
| **MinerU** | 高精度な構造化 | PDF特化 | 学術論文など |

### ファイル管理

- テスト結果は`storage/model_comparison/`に自動保存
- ファイル名パターン: `{model}_{filename}_{timestamp}.{json|md}`
- 古い結果は手動で削除してください

### トラブルシューティング

**VLMサービスが応答しない:**
```bash
# ステータス確認
./bin/vlm-switch.sh status

# サービス再起動
docker-compose restart vlm
```

**結果ファイルが見つからない:**
```bash
# 結果ディレクトリを確認
ls -la storage/model_comparison/

# パターンを変えて検索
python compare_results.py invoice
```

## 既存のスクリプトとの関係

### test_paddle_structured.py

PaddleOCR専用の詳細テストスクリプト。複数ファイルを一括テスト。

```bash
PADDLE_OCR_URL=http://localhost:8001 python test_paddle_structured.py
```

### test_vlm_basic.sh

基本的な動作確認用。curlベースのシンプルなテスト。

```bash
./test_vlm_basic.sh
```

### test_mineru.sh

MinerU専用の詳細テストスクリプト。

```bash
./test_mineru.sh
```

## 開発メモ

### 新しいモデルの追加

1. `bin/vlm-switch.sh`に新モデルを追加
2. `docker-compose.yml`で新しいサービスを定義
3. `compare_models.py`はモデル名を自動検出するため変更不要

### カスタムテスト

`compare_models.py`をベースに独自のテストスクリプトを作成可能：

```python
from pathlib import Path
import sys
sys.path.append(str(Path(__file__).parent))
from compare_models import test_model

# カスタムテストロジック
result = test_model(my_file, "paddleocr")
# 独自の分析処理...
```

---

**作成日:** 2025年11月2日  
**更新日:** 2025年11月2日
