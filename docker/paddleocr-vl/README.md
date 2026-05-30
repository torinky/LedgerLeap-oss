# PaddleOCR-VL コンテナ

このディレクトリは、PaddleOCR-VL 0.9BのGPU実行環境を提供します。

## ⚠️ 重要事項（2025年11月更新）

### システム要件
- **GPU必須**: PaddleOCR-VLは現在GPUのみサポート（CPU非対応）
- **NVIDIA GPU**: Compute Capability ≥ 8.5推奨
- **CUDA 12.6** + cuDNN 9.5
- **メモリ**: 最低8GB RAM + 4GB VRAM推奨

### 最新の修正内容
1. **PaddleX 3.3.5+**: bfloat16重みフォーマット問題を修正
2. **特別なsafetensorsパッケージ**: Paddleフレームワーク対応版を使用
3. **GPU専用設定**: CPUモードは公式サポート外

## 📁 ファイル構成

```
docker/paddleocr-vl/
├── Dockerfile          # テストコンテナのビルド定義
├── app_vl.py          # 最小限のテストAPI
└── README.md          # このファイル
```

## 🎯 目的

PaddleOCR-VL 0.9BのGPU環境での運用：
- NVIDIA GPU上での高速文書解析
- Docker環境でのスケーラブルなデプロイ
- FastAPI経由でのRESTful API提供

## 📊 既知の問題と解決策

### ❌ "framework paddle is invalid" エラー
**原因**: bfloat16形式の重みとsafetensorsライブラリの互換性問題

**解決策**（適用済み）:
1. PaddleX 3.3.5+にアップグレード
2. Paddle対応safetensorsパッケージをインストール
3. GPU環境で実行（CPUは非サポート）

### 💡 CPU環境での代替案
公式にCPUサポートされていないため、以下を検討：
- [paddleocr-vl-cpu](https://github.com/Think-Core/paddleocr-vl-cpu) - コミュニティ版CPUフォーク
- 従来のPaddleOCR（PP-OCRv5）- CPU完全対応

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

### safetensors エラーが継続する場合

1. **PaddleXバージョン確認**:
```bash
docker exec -it ledgerleap-vlm-1 pip show paddlex
# バージョンが3.3.5以上であることを確認
```

2. **safetensorsパッケージ確認**:
```bash
docker exec -it ledgerleap-vlm-1 pip show safetensors
# Paddle対応版がインストールされているか確認
```

3. **GPU認識確認**:
```bash
docker exec -it ledgerleap-vlm-1 python -c "import paddle; print(paddle.device.is_compiled_with_cuda())"
# Trueが返ることを確認
```

### メモリ不足エラー

docker-compose.ymlのリソース制限を調整：
```yaml
deploy:
  resources:
    limits:
      memory: 16G  # 増量
```

### GPU関連エラー

- NVIDIA Dockerランタイムがインストールされているか確認
- GPUドライバーバージョンが550.54.14以上か確認

## 📝 ログの確認

```bash
docker logs test_vlm_advanced
```

## 🔗 関連情報

- [PaddleOCR-VL公式ドキュメント](https://www.paddleocr.ai/latest/en/version3.x/pipeline_usage/PaddleOCR-VL.html)
- [GitHub Issue #16803 - bfloat16問題](https://github.com/PaddlePaddle/PaddleOCR/issues/16803)
- [GitHub Issue #16678 - CPU非サポート](https://github.com/PaddlePaddle/PaddleOCR/issues/16678)
- [実装計画書](../../docs/work/rag-implementation/2025-10-26_paddleocr-vl-trial-plan.md)

## 📝 更新履歴

- **2025-11-01**: 最新情報に基づく修正
  - PaddleX 3.3.5+へのアップグレード追加
  - Paddle対応safetensorsパッケージ明示的インストール
  - GPU専用環境への設定変更
  - トラブルシューティングセクション拡充
