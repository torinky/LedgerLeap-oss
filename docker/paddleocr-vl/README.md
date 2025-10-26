# PaddleOCR-VL テストコンテナ

このディレクトリは、PaddleOCR-VL 0.9BのCPU実行可否を検証するためのテストコンテナです。

## 📁 ファイル構成

```
docker/paddleocr-vl/
├── Dockerfile          # テストコンテナのビルド定義
├── app_vl.py          # 最小限のテストAPI
└── README.md          # このファイル
```

## 🎯 目的

PaddleOCR-VL 0.9Bが以下の環境で動作するか検証：
- CPU実行（GPUなし）
- Mac環境（Apple Silicon/x86_64）
- Dockerコンテナ内

## 🚀 使用方法

### ビルド

```bash
docker build -t ledgerleap-vlm-advanced docker/paddleocr-vl/
```

### 起動

```bash
docker run --rm -p 8002:8002 --name test_vlm_advanced ledgerleap-vlm-advanced
```

### ヘルスチェック

```bash
curl http://localhost:8002/health | jq .
```

## 📊 期待される結果

### 成功の場合

```json
{
  "status": "healthy",
  "model": "PaddleOCR-VL-0.9B",
  "device": "cpu",
  "message": "PaddleOCR-VL is ready"
}
```

### 失敗の場合

```json
{
  "status": "failed",
  "model": "PaddleOCR-VL-0.9B",
  "error": "safetensors_rust.SafetensorError: ...",
  "message": "PaddleOCR-VL is not available"
}
```

## 🔍 トラブルシューティング

### safetensors エラーが発生する場合

Dockerfileの以下の行をコメントアウト：

```dockerfile
# RUN pip install --no-cache-dir \
#     https://paddle-whl.bj.bcebos.com/.../safetensors-0.6.2.dev0-...
```

### GPU関連エラーが発生する場合

CPU版が非対応の可能性があります。ログを確認してください。

## 📝 ログの確認

```bash
docker logs test_vlm_advanced
```

## 🔗 関連ドキュメント

- [実装計画書](../../docs/work/rag-implementation/2025-10-26_paddleocr-vl-trial-plan.md)
- [現行実装記録](../../docs/work/rag-implementation/2025-10-26_paddleocrvl-implementation-log.md)
