# MinerU CPU-optimized Container

## Overview
CPU環境でMinerU（magic-pdf）を実行するためのDockerコンテナ設定です。

## Key Features
- ✅ CPU専用実行（CUDA不要）
- ✅ transformers 4.54.0（4.54.1のバグ回避）
- ✅ magic-pdf[full-cpu]使用
- ✅ 日本語フォント対応（Noto CJK）
- ✅ CPU最適化設定

## Fixed Issues

### 1. transformers バージョン問題
**問題:** transformers 4.54.1以降で`cache_position`引数エラー
```
TypeError: UnimerMBartForCausalLM.forward() got an unexpected keyword argument 'cache_position'
```

**解決策:** transformers==4.54.0を最後に強制インストール
```dockerfile
RUN pip install ... 'magic-pdf[full-cpu]' && \
    pip install --force-reinstall transformers==4.54.0
```

### 2. OpenCV/numpy 互換性
**問題:** `ImportError: numpy.core.multiarray failed to import`

**解決策:** numpy 1.24.3とopencv-python-headless 4.8.1.78の組み合わせ
```dockerfile
RUN pip install --no-cache-dir \
    numpy==1.24.3 \
    opencv-python-headless==4.8.1.78
```

### 3. magic-pdf.json 設定ファイル
**問題:** `FileNotFoundError: /root/magic-pdf.json not found`

**解決策:** CPU専用設定ファイルを追加
```json
{
  "models-dir": "/app/models",
  "device-mode": "cpu",
  "latex-delimiter": {
    "inline": ["$", "$"],
    "display": ["$$", "$$"]
  }
}
```

## Build & Run

### ビルド
```bash
cd docker/mineru
docker build -t ledgerleap-mineru:cpu .
```

### 実行
```bash
docker run -d \
  --name ledgerleap-mineru \
  -p 8001:8000 \
  ledgerleap-mineru:cpu
```

### ヘルスチェック
```bash
curl http://localhost:8001/health
# Expected: {"status":"healthy","model":"MinerU","backend":"CPU"}
```

### PDF処理テスト
```bash
curl -X POST \
  -F "file=@test.pdf" \
  http://localhost:8001/extract/structured
```

## Environment Variables
```
CUDA_VISIBLE_DEVICES=""    # GPU無効化
DEVICE_MODE=cpu             # CPU専用モード
OMP_NUM_THREADS=4           # OpenMP最適化
MKL_NUM_THREADS=4           # MKL最適化
TORCH_DEVICE=cpu            # PyTorch CPU実行
```

## Package Versions (Critical)
```
transformers==4.54.0              # 4.54.1以降はNG
numpy==1.24.3                     # OpenCV互換性
opencv-python-headless==4.8.1.78  # headless版（GUI不要）
magic-pdf[full-cpu]               # CPU専用版
torch==2.9.0+cpu                  # CPU専用PyTorch
```

## Troubleshooting

### transformersバージョンが4.54.1になってしまう
```bash
docker exec ledgerleap-mineru pip list | grep transformers
# 4.54.1の場合、コンテナ再ビルドが必要
```

### numpy互換性エラー
```bash
# コンテナ内で確認
docker exec ledgerleap-mineru python -c "import cv2; print(cv2.__version__)"
docker exec ledgerleap-mineru python -c "import numpy; print(numpy.__version__)"
```

### magic-pdf.json設定確認
```bash
docker exec ledgerleap-mineru cat /root/magic-pdf.json
```

## Related Documentation
- [VLM/RAG統合実装計画](../../docs/work/rag-implementation/2025-10-25_vlm-rag-integration-plan-final.md)
- [VLM-OCR技術検討](../../docs/work/rag-implementation/2025-10-23_vlm-ocr-and-indexing-strategy-review.md)
