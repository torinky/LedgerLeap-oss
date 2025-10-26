# PaddleOCR バージョン切り替えガイド

**作成日:** 2025年10月26日  
**最終更新:** 2025年10月26日 21:20 JST  

## 概要

PaddleOCR 2.x（安定版）と 3.x（実験版）を簡単に切り替えられるシステムを提供します。

## バージョン比較

| 項目 | PaddleOCR 2.8.1 (v2) | PaddleOCR 3.3+ (v3) |
|------|---------------------|---------------------|
| **ステータス** | ✅ 安定版（推奨） | ⚠️ 実験版 |
| **モデル** | PP-OCRv5 | PP-OCRv5 |
| **動作確認** | ✅ 全テスト成功 | ❌ SIGSEGV発生 |
| **処理速度** | 1.2〜5.5秒 | N/A（クラッシュ） |
| **本番環境** | ✅ 使用可能 | ❌ 非推奨 |
| **ARM64対応** | ✅ 動作確認済み | ❌ セグメンテーションフォルト |

### PaddleOCR 2.8.1 (v2) - 安定版

**✅ 推奨：本番環境で使用**

- PaddlePaddle: 2.6.2
- PaddleOCR: 2.8.1
- PP-OCRv5モデル搭載
- API: 2.x互換（`use_angle_cls`, `use_gpu`, `.ocr(cls=True)`）
- テスト結果: 5/5成功、20/20アサーション
- 日本語OCR精度: 高精度
- 処理時間: 実用的（1.2〜5.5秒）

### PaddleOCR 3.3+ (v3) - 実験版

**⚠️ 注意：本番環境では非推奨**

- PaddlePaddle: 3.0+
- PaddleOCR: 3.3+
- API: 3.x互換（`lang`のみ、シンプル化）
- 既知の問題:
  - ARM64/Apple Silicon環境でSIGSEGV発生
  - 初期化は成功するが、OCR実行時にクラッシュ
  - コミュニティでも同様の報告多数

## バージョン切り替え方法

### 方法1: スクリプトを使用（推奨）

```bash
# プロジェクトルートで実行
cd /path/to/LedgerLeap

# バージョン2.x（安定版）に切り替え
bash bin/switch-paddleocr-version.sh 2

# バージョン3.x（実験版）に切り替え（確認が必要）
bash bin/switch-paddleocr-version.sh 3

# 現在のバージョンを確認
bash bin/switch-paddleocr-version.sh
```

**重要:** スクリプトは**プロジェクトルートディレクトリ**から実行してください。

### 方法2: 手動で切り替え

```bash
# バージョン2.xに切り替え
cp docker/paddle/app.py.v2 docker/paddle/app.py
cp docker/paddle/requirements.txt.v2 docker/paddle/requirements.txt

# バージョン3.xに切り替え
cp docker/paddle/app.py.v3 docker/paddle/app.py
cp docker/paddle/requirements.txt.v3 docker/paddle/requirements.txt
```

## 切り替え後の手順

### 1. コンテナの再ビルド（必須）

```bash
VLM_MODEL=paddleocr ./vendor/bin/sail build --no-cache vlm
```

### 2. サービスの再起動

```bash
VLM_MODEL=paddleocr ./vendor/bin/sail up -d vlm
```

### 3. 動作確認

```bash
# ヘルスチェック
curl http://localhost:8001/health | jq .

# OCRテスト
curl -X POST -F "file=@storage/test/vlm-poc/receipt_01.jpg" \
  http://localhost:8001/extract/structured | jq .

# 自動テスト（推奨）
./vendor/bin/sail test --filter=PaddleOcrVlmTest
```

## ファイル構成

```
docker/paddle/
├── app.py                    # 現在使用中のバージョン
├── app.py.v2                 # バージョン2.x（安定版）
├── app.py.v3                 # バージョン3.x（実験版）
├── requirements.txt          # 現在使用中の依存関係
├── requirements.txt.v2       # バージョン2.x用依存関係
└── requirements.txt.v3       # バージョン3.x用依存関係

bin/
└── switch-paddleocr-version.sh  # バージョン切り替えスクリプト
```

## トラブルシューティング

### バージョン3.xでSIGSEGV発生

**症状:**
```
FatalError: `Segmentation fault` is detected by the operating system.
[SignalInfo: *** SIGSEGV (@0x0) received by PID 1 ***]
```

**対処法:**
1. バージョン2.xに戻す
   ```bash
   bash bin/switch-paddleocr-version.sh 2
   ```

2. コンテナを再ビルド
   ```bash
   VLM_MODEL=paddleocr ./vendor/bin/sail build --no-cache vlm
   VLM_MODEL=paddleocr ./vendor/bin/sail up -d vlm
   ```

### 切り替え後にテストが失敗する

**原因:** コンテナの再ビルドを忘れている

**対処法:**
```bash
# 必ず--no-cacheオプションを付けて再ビルド
VLM_MODEL=paddleocr ./vendor/bin/sail build --no-cache vlm
VLM_MODEL=paddleocr ./vendor/bin/sail up -d vlm
```

## 技術的な詳細

### API の違い

#### バージョン 2.x
```python
from paddleocr import PaddleOCR

ocr_engine = PaddleOCR(
    use_angle_cls=True,
    lang='japan',
    use_gpu=False
)

result = ocr_engine.ocr(img, cls=True)
```

#### バージョン 3.x
```python
from paddleocr import PaddleOCR

# 環境変数でスレッド数を制限
os.environ['OMP_NUM_THREADS'] = '1'
os.environ['MKL_NUM_THREADS'] = '1'

ocr_engine = PaddleOCR(
    lang='japan'
    # use_angle_cls, use_gpu, show_log は削除された
)

result = ocr_engine.ocr(img)  # cls パラメータも削除
```

### バージョン3.xの調査結果

**試行した対策（全て失敗）:**
1. ❌ `device='cpu'` パラメータの明示的指定
2. ❌ `enable_mkldnn=False` で高速化機能を無効化
3. ❌ 環境変数 `OMP_NUM_THREADS=1` でスレッド数を制限
4. ❌ モバイルモデルの使用
5. ❌ 最小限のパラメータ（`lang`のみ）
6. ❌ `.predict()` と `.ocr()` メソッドの両方を試行

**結論:**
- 初期化は成功する
- モデルのダウンロードも完了する
- **OCR実行時に必ずSIGSEGV発生**
- ARM64環境での根本的な互換性問題

**参考資料:**
- [PaddleOCR Issue #11530](https://github.com/PaddlePaddle/PaddleOCR/issues/11530)
- [PaddleOCR Issue #16279](https://github.com/PaddlePaddle/PaddleOCR/issues/16279)
- [動作実績のあるコード例](https://github.com/Cirno10124/stream_recognizer)

## 推奨事項

### 現在（2025年10月）
- ✅ **バージョン2.8.1を使用**（安定版）
- PP-OCRv5モデルで十分な精度
- 全テスト成功、本番環境使用可能

### 将来的な移行
- PaddleOCR 3.4以降でARM64対応が改善される可能性
- 公式からの修正アップデートを待つ
- x86-64環境では3.xも動作する可能性あり

## 関連ドキュメント

- [PaddleOCR バージョンアップ報告書](./2025-10-26_paddleocr-version-upgrade-report.md)
- [PaddleOCRVL API実装完了記録](./2025-10-26_paddleocrvl-implementation-log.md)
- [PaddleOCR最新版実装ガイド](./2025-10-26_paddleocr-latest-impl-guide.md)

---

**更新履歴:**
- 2025-10-26 21:20: 初版作成、バージョン切り替えシステム実装
- 2025-10-26 20:51: バージョン2.8.1から3.3への移行を試行
- 2025-10-26 20:48: バージョン2.8.1での品質評価完了
