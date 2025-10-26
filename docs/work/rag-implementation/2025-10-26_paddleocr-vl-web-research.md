# PaddleOCR-VL CPU対応に関するインターネット調査結果

**調査日時:** 2025年10月26日 午前2時53分  
**調査目的:** safetensors互換性問題とCPU対応状況の確認  
**調査方法:** Web検索（GitHub Issues、公式ドキュメント、コミュニティ報告）

---

## 🔍 調査結果サマリー

### 結論: CPU対応に関する情報の矛盾

**2つの矛盾する情報源が存在:**

#### 情報源A: 公式ドキュメント + GitHub Issues ❌

**CPU非対応**を明記:
- 公式: "PaddleOCR-VL currently does not support CPU or ARM architecture"
- GPU必須（NVIDIA、CUDA >= 8、8GB+ VRAM）
- GitHub Issues #16678, #16731で複数のユーザーが同様のエラー報告

#### 情報源B: マーケティング資料 + ブログ ✅

**CPU対応可能**と記載:
- "designed for efficient CPU deployment"
- "0.9 billion parameters, suitable for standard CPUs"
- "browser plugin level deployment"

---

## 📊 詳細分析

### 1. GitHub Issue報告

**Issue #16678: "PaddleOCR-VL not working in CPU"**
- 報告日: 2025年初頭
- エラー: `safetensors_rust.SafetensorError: framework paddle is invalid`
- 状態: 未解決（Open）
- 開発者コメント: "CPU/ARM support will be expanded based on actual requirements in the future"

**Issue #16731: "safetensors_rust.SafetensorError: framework paddle is invalid"**
- 同様の問題報告
- 複数ユーザーが再現
- 回避策なし

### 2. 公式ドキュメント

**PaddleOCR Documentation - Usage Tutorial**
```
Hardware Requirements:
- GPU: NVIDIA (CUDA >= 8)
- Memory: 8GB+ VRAM
- CPU: Not Supported
- ARM: Not Supported
```

**公式声明:**
> "The PaddleOCR-VL currently does not support CPU or Arm architecture. 
> Support for more hardware will be expanded based on actual requirements in the future. 
> Stay tuned!"

### 3. 矛盾する情報の出所

**CPU対応と主張する情報:**
- DEV Community blog（非公式）
- 一部の技術記事（マーケティング寄り）
- Baidu ERNIE公式ブログ（一部の記述）

**考えられる理由:**
1. **誤訳・誤解:** "lightweight model"を"CPU runnable"と誤認
2. **vLLMサーバー経由:** GPU推論サーバー経由のCPUクライアント利用を指している
3. **将来計画:** ロードマップ上の計画を現状と混同
4. **マーケティング:** 技術的制約を省略した表現

---

## 🎯 技術的根本原因

### safetensors互換性問題

**safetensorsライブラリの対応状況:**
```
✅ PyTorch
✅ TensorFlow
✅ JAX
❌ PaddlePaddle（未対応）
```

**エラーの発生メカニズム:**
1. PaddleOCR-VLは`safetensors`形式でモデルを保存
2. safetensorsライブラリがPaddlePaddleフレームワークを認識できない
3. "framework paddle is invalid"エラーが発生

**Baiduのカスタムビルド:**
- GPU版用のカスタムsafetensorsを提供
- URL: `https://paddle-whl.bj.bcebos.com/nightly/cu126/safetensors/...`
- CPU版は提供されていない（またはURLが不明）

---

## 📈 他のPaddleOCRモデルとの比較

| モデル | CPU対応 | GPU対応 | 表構造 | 数式 | 状態 |
|--------|---------|---------|--------|------|------|
| PP-OCRv5 | ✅ 完全対応 | ✅ | ❌ | ❌ | 安定 |
| PP-OCRv4 | ✅ 完全対応 | ✅ | ❌ | ❌ | 安定 |
| PaddleOCR 2.7.3 | ✅ 完全対応 | ✅ | ❌ | ❌ | 安定 |
| PaddleOCR-VL 0.9B | ❌ 未対応 | ✅ | ✅ | ✅ | 新規 |

**重要な発見:**
- 標準PaddleOCRモデル（PP-OCRv4/v5）は完全にCPU対応
- VLモデル（Vision-Language）のみがCPU非対応

---

## 💡 回避策と代替案

### 現在利用可能な方法

#### 1. 標準PaddleOCRの使用（推奨）✅
```python
from paddleocr import PaddleOCR
ocr = PaddleOCR(use_angle_cls=True, lang='japan')
result = ocr.ocr('document.pdf', cls=True)
```
- CPU完全対応
- 日本語高精度
- 実績多数

#### 2. GPU環境での実行
```python
from paddleocr import PaddleOCRVL
pipeline = PaddleOCRVL(device="gpu:0")
```
- NVIDIA GPU必須
- CUDA 11.2+
- 8GB+ VRAM

#### 3. vLLMサーバー経由（理論上）
```
Client (CPU) → vLLM Server (GPU) → PaddleOCR-VL
```
- 公式Dockerイメージあり
- GPU推論サーバーが必要
- ネットワーク経由で利用

### 試行したが失敗した方法

#### カスタムsafetensorsのインストール ❌
```bash
pip install https://paddle-whl.bj.bcebos.com/nightly/cpu/safetensors/...
```
- CPU版URLが存在しない
- GPU版URLはCPU環境では動作しない

#### デバイス指定の変更 ❌
```python
pipeline = PaddleOCRVL(device="cpu")
```
- デバイス指定だけでは解決しない
- モデルファイル自体がGPU用

---

## 🔮 将来の展望

### 公式の対応予定

**PaddleOCR公式声明:**
> "Support for more hardware will be expanded based on actual requirements in the future. 
> Stay tuned!"

**予想されるタイムライン:**
- **短期（3-6ヶ月）:** 対応なし（優先度低）
- **中期（6-12ヶ月）:** コミュニティからの要望次第
- **長期（12ヶ月+）:** safetensors対応またはモデル形式変更

### 技術的な課題

1. **safetensors対応:**
   - safetensorsライブラリ側の対応が必要
   - またはPaddlePaddle独自の実装

2. **モデル最適化:**
   - CPU用の軽量版モデル
   - 量子化（INT8/INT4）

3. **代替フォーマット:**
   - ONNXへの変換
   - TensorRTへの変換

---

## 📚 参考リンク

### 公式ソース

1. **PaddleOCR Documentation**
   - https://www.paddleocr.ai/latest/en/version3.x/pipeline_usage/PaddleOCR-VL.html
   - CPU非対応を明記

2. **GitHub Issues**
   - #16678: https://github.com/PaddlePaddle/PaddleOCR/issues/16678
   - #16731: https://github.com/PaddlePaddle/PaddleOCR/issues/16731
   - 複数のユーザーが同様の問題報告

3. **Hugging Face**
   - https://huggingface.co/PaddlePaddle/PaddleOCR-VL
   - モデルカードとドキュメント

### コミュニティ

4. **DEV Community Blog**
   - https://dev.to/czmilo/2025-complete-guide-paddleocr-vl-09b-...
   - CPU対応と記載（矛盾あり）

5. **Baidu ERNIE Blog**
   - https://ernie.baidu.com/blog/posts/paddleocr-vl/
   - 公式技術解説

---

## 🎯 LedgerLeapへの推奨事項（更新）

### 確定事項

1. **PaddleOCR-VLのCPU対応は現時点で不可能**
   - 公式ドキュメントで明記
   - GitHub Issuesで多数報告
   - 回避策なし

2. **PaddleOCR 2.7.3は完全にCPU対応**
   - 検証済み（全テスト成功）
   - 日本語高精度
   - 本番環境使用可能

### 実装方針

**短期（現在）:**
- ✅ PaddleOCR 2.7.3を本番環境で使用
- ✅ 安定性と実績を重視
- ✅ CPU環境で完全動作

**中期（GPU環境移行後）:**
- 🔄 PaddleOCR-VLをGPU環境で検証
- 📊 表構造認識の活用
- 🔢 数式認識の活用

**長期（将来）:**
- 📅 PaddleOCR-VL CPU対応のアップデート監視
- 🔄 ONNX変換の検討
- 🚀 vLLMサーバー構成の検討

---

## ✅ 結論

### 調査で確認できたこと

1. **CPU非対応は確実**
   - 公式ドキュメントで明記
   - 技術的根拠（safetensors互換性）
   - コミュニティでも確認済み

2. **"CPU対応"の記載は誤解または将来計画**
   - vLLMサーバー経由の利用を指している可能性
   - マーケティング資料の過度な簡略化
   - 将来のロードマップを現状と混同

3. **我々の検証結果は正確**
   - エラー内容が完全に一致
   - 同じ回避策を試行済み
   - 同じ結論に到達

### 最終判定

**PaddleOCR-VL 0.9B:**
- ❌ CPU環境では使用不可（確定）
- ✅ GPU環境でのみ使用可能
- 📅 将来のアップデート待ち

**LedgerLeapの方針:**
- ✅ PaddleOCR 2.7.3で運用継続
- 📝 GPU環境移行時にVL版を再検証
- 📚 全ドキュメントが最新情報を反映

---

**調査完了日:** 2025年10月26日 午前2時53分  
**調査者:** GitHub Copilot CLI + Web Search  
**信頼度:** 高（複数の公式ソースで確認）
