# PaddleOCR-VL 0.9B 試行計画書

**作成日:** 2025年10月26日 午前2時30分  
**ステータス:** 🔄 **準備完了・検証待ち**  
**関連ドキュメント:**
- [現行実装完了記録](./2025-10-26_paddleocrvl-implementation-log.md) - PaddleOCR 2.7.3の実装記録
- [Phase 0追加調査計画書](./2025-10-26_phase0-vlm-additional-investigation-plan.md) - 調査の背景
- [VLM実装README](./VLM_IMPLEMENTATION_README.md) - 現行実装のクイックガイド

---

## 🎯 目的

PaddleOCR-VL 0.9B（世界1位のOCR性能）がCPU環境で動作するか検証する。

### 期待される効果

もし成功すれば、LedgerLeapは以下を獲得：
- ✅ **OmniBenchDoc V1.5 世界1位**（総合スコア90.67）
- ✅ **GPT-4oを超える性能**（わずか0.9Bパラメータ）
- ✅ **表構造認識**（88%精度）
- ✅ **数式認識**（85%精度）
- ✅ **QRコード・スタンプ抽出**
- ✅ **109言語対応**

---

## 📊 現状分析

### 現在の実装（PaddleOCR 2.7.3）

| 項目 | 状態 | 詳細 |
|------|------|------|
| 実装状態 | ✅ 完了 | 全テスト成功（5/5） |
| 日本語OCR | ✅ 高精度 | 請求書で検証済み |
| 表構造認識 | ❌ 未対応 | 行単位のみ |
| 手書き認識 | ⚠️ 中程度 | 基本文字のみ |
| 処理速度 | ⏱️ 6-8秒/PDF | CPU実行 |
| 本番環境 | ✅ 使用可能 | 安定動作確認済み |

### PaddleOCR-VL 0.9Bの優位性

| 機能 | PaddleOCR 2.7.3 | PaddleOCR-VL 0.9B | 差分 |
|------|----------------|-------------------|------|
| 総合スコア | - | **90.67**（世界1位） | - |
| 表構造認識 | ❌ | ✅ 88% | +88pt |
| 数式認識 | ❌ | ✅ 85% | +85pt |
| 読書順序 | ⚠️ | ✅ 90% | +60pt |
| QRコード | ❌ | ✅ | +100% |
| スタンプ | ❌ | ✅ | +100% |
| チャート | ❌ | ✅ 11種類 | +100% |
| 多言語 | ✅ 日本語 | ✅ 109言語 | +108言語 |

---

## 🔍 技術的課題

### CPU実行の可能性

**公式情報の矛盾:**

**情報A（初期ドキュメント）:**
> PaddleOCR-VL currently does not support CPU or ARM architecture.

**情報B（最新リリース情報）:**
> **極限パラメータ効率**
> - 通常のCPUで実行可能
> - ブラウザプラグインレベルのデプロイをサポート

**結論:** 最新版でCPU対応が追加された可能性が高い

### safetensors互換性問題

**過去の経験:**
```
safetensors_rust.SafetensorError: framework paddle is invalid
```

**対策:**
1. カスタムビルド版のsafetensorsを使用
   ```bash
   pip install https://paddle-whl.bj.bcebos.com/nightly/cpu/safetensors/...
   ```
2. 失敗した場合は該当パッケージをスキップ

---

## 🏗️ 実装アーキテクチャ

### コンテナ構成

```
現在の構成:
┌─────────────────────────────────┐
│  vlm (port 8001)                │
│  - PaddleOCR 2.7.3             │
│  - 安定版・実績あり             │
│  - 基本的なOCR機能              │
└─────────────────────────────────┘

追加する構成:
┌─────────────────────────────────┐
│  vlm (port 8001)                │
│  - 環境変数で切り替え可能       │
│                                 │
│  [A] PaddleOCR 2.7.3           │
│      ./docker/paddle            │
│                                 │
│  [B] PaddleOCR-VL 0.9B         │
│      ./docker/paddleocr-vl      │
└─────────────────────────────────┘
```

### 環境変数での切り替え

**.env ファイル:**
```bash
# PaddleOCR 2.7.3（現行・安定版）
VLM_SERVICE_CONTEXT=./docker/paddle
VLM_SERVICE_PORT=8001
VLM_INTERNAL_PORT=8000

# PaddleOCR-VL 0.9B（試行版）
# VLM_SERVICE_CONTEXT=./docker/paddleocr-vl
# VLM_SERVICE_PORT=8001
# VLM_INTERNAL_PORT=8002
```

**切り替え方法:**
```bash
# 現行版（PaddleOCR 2.7.3）
docker-compose up -d vlm

# 試行版（PaddleOCR-VL 0.9B）に切り替え
# .envファイルを編集後
docker-compose down vlm
docker-compose build vlm --no-cache
docker-compose up -d vlm
```

---

## 📁 ファイル構成

### 新規作成ファイル

```
docker/paddleocr-vl/
├── Dockerfile          # PaddleOCR-VL用コンテナ定義
├── app_vl.py          # テストAPI実装
└── README.md          # コンテナ使用方法

docs/work/rag-implementation/
└── 2025-10-26_paddleocr-vl-trial-plan.md  # このファイル
```

### 更新ファイル

```
docker-compose.yml     # 環境変数でのポート切り替え追加
.env.example           # VLM関連環境変数の説明更新
```

---

## 🚀 検証手順

### Phase 1: ビルド検証（推定時間: 5-10分）

```bash
# 1. テストコンテナのビルド
cd /Users/kazutaka/PhpstormProjects/LedgerLeap
docker build -t ledgerleap-vlm-advanced docker/paddleocr-vl/

# 期待される結果:
# - ビルドが成功する
# - PaddlePaddle 3.2.0がインストールされる
# - PaddleOCR[doc-parser]がインストールされる
# - safetensorsのインストールが成功または警告（スキップ可能）
```

### Phase 2: 起動検証（推定時間: 1-5分）

```bash
# 2. コンテナの起動
docker run --rm -p 8002:8002 --name test_vlm_advanced ledgerleap-vlm-advanced

# 3. ログの確認（別ターミナル）
docker logs -f test_vlm_advanced

# 期待されるログ（成功時）:
# ================================================================================
# Attempting to initialize PaddleOCR-VL on CPU...
# ================================================================================
# PaddleOCRVL module imported successfully
# Initializing PaddleOCRVL with device='cpu'...
# ================================================================================
# ✅ SUCCESS! PaddleOCR-VL initialized on CPU!
# ================================================================================
```

### Phase 3: ヘルスチェック（推定時間: 10秒）

```bash
# 4. ヘルスチェック
curl http://localhost:8002/health | jq .
```

**期待される結果（成功）:**
```json
{
  "status": "healthy",
  "model": "PaddleOCR-VL-0.9B",
  "device": "cpu",
  "message": "PaddleOCR-VL is ready"
}
```

**期待される結果（失敗）:**
```json
{
  "status": "failed",
  "model": "PaddleOCR-VL-0.9B",
  "error": "safetensors_rust.SafetensorError: framework paddle is invalid",
  "message": "PaddleOCR-VL is not available"
}
```

### Phase 4: 機能テスト（推定時間: 1-2分）

```bash
# 5. テストファイルでOCR実行
curl -X POST http://localhost:8002/extract/structured \
  -F "file=@tests/fixtures/files/invoice_simple.pdf" \
  | jq .
```

**成功時の確認項目:**
- ✅ `success: true` が返る
- ✅ `markdown` フィールドに日本語テキストが含まれる
- ✅ `processing_time_s` が妥当な範囲（10-30秒）

---

## 📈 判定基準

### 成功の定義

以下のすべてを満たす場合、**成功**と判定：

1. ✅ コンテナのビルドが完了
2. ✅ PaddleOCR-VLの初期化が成功
3. ✅ ヘルスチェックで `status: "healthy"` が返る
4. ✅ テストファイルのOCR処理が成功
5. ✅ 日本語テキストが正常に抽出される

### 失敗のパターンと対応

| 失敗パターン | 原因 | 対応 |
|------------|------|------|
| ビルド失敗 | 依存関係エラー | requirements確認 |
| safetensorsエラー | 互換性問題 | カスタムビルド版試行 |
| GPU要求エラー | CPU非対応 | **検証終了** |
| メモリ不足 | リソース不足 | メモリ制限緩和 |
| 初期化タイムアウト | モデルダウンロード | 待機時間延長 |

---

## 🎯 成功時のアクション

### 短期（即時）

1. **完全なAPI実装**
   - PDFマルチページ処理
   - 表構造の保持
   - エラーハンドリング強化

2. **テストケース作成**
   - 表構造認識テスト
   - 数式認識テスト
   - QRコード抽出テスト

3. **性能比較**
   - PaddleOCR 2.7.3 vs PaddleOCR-VL
   - 処理速度の比較
   - 認識精度の比較

### 中期（1週間以内）

1. **Laravel統合**
   - 環境変数での切り替え実装
   - フォールバック機構
   - 選択的機能利用

2. **ドキュメント更新**
   - 実装完了記録の更新
   - 使用方法ガイドの作成
   - トラブルシューティング

3. **本番環境テスト**
   - 実際の業務ファイルでテスト
   - 長時間運用テスト

### 長期（1ヶ月以内）

1. **機能拡張**
   - 図表認識の活用
   - 多言語ドキュメント対応
   - バッチ処理の実装

2. **最適化**
   - メモリ使用量の最適化
   - 処理速度の改善
   - キャッシュ機構の追加

---

## ⚠️ 失敗時のアクション

### CPU非対応が判明した場合

1. **現行実装の継続**
   - PaddleOCR 2.7.3を本番環境で使用
   - 十分な実用性を確認済み

2. **GPU環境への移行計画**
   - AWS/GCP等のGPUインスタンス検討
   - コスト試算
   - 移行スケジュール作成

3. **代替技術の調査**
   - Tesseract 5.x
   - EasyOCR
   - 商用API（Google Vision API等）

---

## 📊 リスク評価

| リスク | 発生確率 | 影響度 | 対策 |
|--------|---------|--------|------|
| CPU非対応 | 中（50%） | 低 | 現行版継続使用 |
| safetensors問題 | 高（70%） | 中 | カスタムビルド試行 |
| メモリ不足 | 低（20%） | 中 | メモリ制限緩和 |
| 処理速度低下 | 中（40%） | 低 | 基本版と併用 |

**総合リスク評価:** 低〜中

**理由:**
- 現行実装（PaddleOCR 2.7.3）は安定稼働中
- 失敗しても既存機能に影響なし
- 成功すれば大幅な機能向上

---

## 📝 タイムライン

```
T+0min:  ビルド開始
T+10min: ビルド完了
T+11min: コンテナ起動
T+15min: 初期化完了
T+16min: ヘルスチェック
T+17min: 機能テスト開始
T+20min: 判定完了

総所要時間: 約20分
```

---

## 🔗 関連リソース

### プロジェクト内ドキュメント

1. **[現行実装完了記録](./2025-10-26_paddleocrvl-implementation-log.md)**
   - PaddleOCR 2.7.3の実装詳細
   - テスト結果（5/5成功）
   - 性能評価

2. **[Phase 0追加調査計画書](./2025-10-26_phase0-vlm-additional-investigation-plan.md)**
   - 調査の背景と経緯
   - 技術選定の理由
   - 最終実装結果

3. **[VLM実装README](./VLM_IMPLEMENTATION_README.md)**
   - クイックスタートガイド
   - トラブルシューティング
   - API仕様

### 技術ドキュメント

- **コンテナ:** [docker/paddleocr-vl/README.md](../../docker/paddleocr-vl/README.md)
- **API実装:** [docker/paddleocr-vl/app_vl.py](../../docker/paddleocr-vl/app_vl.py)
- **Dockerfile:** [docker/paddleocr-vl/Dockerfile](../../docker/paddleocr-vl/Dockerfile)

### 外部リソース

- [PaddleOCR公式GitHub](https://github.com/PaddlePaddle/PaddleOCR)
- [PaddleOCR-VLドキュメント](https://paddlepaddle.github.io/PaddleOCR/)
- [OmniBenchDocリーダーボード](https://omnibench.org)

---

## ✅ チェックリスト

### 準備完了確認

- [x] テストコンテナのDockerfile作成
- [x] テストAPIの実装（app_vl.py）
- [x] docker-compose.yml更新（環境変数対応）
- [x] .env.example更新（環境変数説明）
- [x] ドキュメント作成（試行計画書）
- [ ] ビルド検証
- [ ] 起動検証
- [ ] 機能テスト
- [ ] 判定・記録

### 検証実施時の記録項目

- [ ] ビルド開始時刻
- [ ] ビルド完了時刻
- [ ] 初期化ログの記録
- [ ] ヘルスチェック結果
- [ ] エラーメッセージ（失敗時）
- [ ] 処理時間の記録
- [ ] 認識精度の評価

---

**作成日:** 2025年10月26日 午前2時30分  
**作成者:** GitHub Copilot CLI + Development Team  
**ステータス:** 🔄 **準備完了・検証待ち**  
**次のアクション:** ビルド・検証の実施

---

## 🔄 モデル切り替え方法

### 環境変数を使用した切り替え

LedgerLeapでは、環境変数 `VLM_MODEL` でOCRモデルを簡単に切り替えられます。

#### 方法1: 自動切り替えスクリプト（推奨）

```bash
# 現在のモデル確認
./bin/vlm-switch.sh status

# PaddleOCR 2.7.3（安定版）に切り替え
./bin/vlm-switch.sh paddleocr

# PaddleOCR-VL 0.9B（試行版）に切り替え
./bin/vlm-switch.sh paddleocr-vl
```

スクリプトが自動的に：
- ✅ `.env`ファイルを更新
- ✅ 適切なDockerコンテキストを設定
- ✅ 内部ポートを設定
- ✅ 次のステップを表示

#### 方法2: 手動で.envファイルを編集

**PaddleOCR 2.7.3（安定版）:**
```bash
VLM_MODEL=paddleocr
VLM_SERVICE_CONTEXT=./docker/paddle
VLM_INTERNAL_PORT=8000
```

**PaddleOCR-VL 0.9B（試行版）:**
```bash
VLM_MODEL=paddleocr-vl
VLM_SERVICE_CONTEXT=./docker/paddleocr-vl
VLM_INTERNAL_PORT=8002
```

### コンテナの再起動

モデルを切り替えた後：

```bash
# コンテナを停止
docker-compose down vlm

# コンテナを再ビルド
docker-compose build vlm --no-cache

# コンテナを起動
docker-compose up -d vlm

# ヘルスチェック
curl http://localhost:8001/health | jq .
```

### 環境変数一覧

| 環境変数 | paddleocr | paddleocr-vl | 説明 |
|---------|-----------|--------------|------|
| VLM_MODEL | `paddleocr` | `paddleocr-vl` | モデル選択 |
| VLM_SERVICE_CONTEXT | `./docker/paddle` | `./docker/paddleocr-vl` | Dockerコンテキスト |
| VLM_SERVICE_PORT | `8001` | `8001` | 外部ポート |
| VLM_INTERNAL_PORT | `8000` | `8002` | 内部ポート |


## 🔄 Marker対応の追加

VLMモデル切り替え機能に**Marker（PDF to Markdown converter）**のサポートを追加しました。

### サポートされるモデル（3種類）

| モデル | ステータス | 特徴 | ポート |
|--------|-----------|------|--------|
| **paddleocr** | ✅ 安定版 | 実績あり・本番環境使用可能 | 8000 |
| **paddleocr-vl** | 🧪 試行版 | 世界1位性能・実験的 | 8002 |
| **marker** | 📄 PDF特化 | PDF→Markdown変換 | 8000 |

### 使用例

```bash
# Markerに切り替え
./bin/vlm-switch.sh marker

# 設定確認
./bin/vlm-switch.sh status

# コンテナ再起動
docker-compose down vlm
docker-compose build vlm --no-cache
docker-compose up -d vlm
```

### 環境変数設定

**Markerを使用する場合:**
```bash
VLM_MODEL=marker
VLM_SERVICE_CONTEXT=./docker/marker
VLM_INTERNAL_PORT=8000
```

### 完全な環境変数マッピング

| 環境変数 | paddleocr | paddleocr-vl | marker |
|---------|-----------|--------------|--------|
| VLM_MODEL | `paddleocr` | `paddleocr-vl` | `marker` |
| VLM_SERVICE_CONTEXT | `./docker/paddle` | `./docker/paddleocr-vl` | `./docker/marker` |
| VLM_INTERNAL_PORT | `8000` | `8002` | `8000` |

