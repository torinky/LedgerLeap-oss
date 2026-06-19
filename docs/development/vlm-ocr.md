# VLM/OCR開発者ガイド

**最終更新:** 2026年1月3日  
**ステータス:** ✅ **Phase 1-5実装完了（添付ファイル機能統合、2025年12月-2026年1月）**

---

## 1. 概要

LedgerLeapは、添付ファイルからのテキスト抽出に3つのエンジンを統合し、高精度かつ堅牢な処理を実現しています。

**3エンジン統合:**
- **VLM (Visual Language Model)**: PaddleOCR-VL 0.9B - Markdown生成、構造化データ抽出
- **OCR**: OcrMyPDF - 画像・PDFのOCR処理とPDF最適化
- **Tika**: Apache Tika - Office文書等の汎用テキスト抽出

**エンジン選択優先順位:** VLM（最優先） > OCR（次点） > Tika（フォールバック）

**本ドキュメントの対象:**
- VLM/OCR機能の開発・拡張を行う開発者
- トラブルシューティングが必要な運用担当者

**記載しない内容:**
- ユーザー向け機能説明 → `docs/function/Attachment.md`
- アーキテクチャ設計 → `docs/architecture/vlm-ocr-technology-selection.md`
- 非同期処理詳細 → `docs/architecture/QueueProcessing.md`

---

## 2. 開発環境のセットアップ

### 2.1. 環境変数の設定（自動）

`bin/setup.sh` が環境を自動判定し、最適な VLM 設定を `.env` に書き込みます。  
特別な要件がない限り、手動設定は不要です。

```bash
./bin/setup.sh
```

自動設定される値:

| 環境 | `PADDLEOCR_DEVICE` | `VLM_MODEL` | `VLM_URL` |
|------|-------------------|-------------|-----------|
| x86 + NVIDIA GPU | `gpu` | `paddleocr-vl` | `http://vlm:8000` |
| x86 + CPU | `cpu` | `paddleocr-vl-cpu` | `http://vlm:8000` |
| Mac Apple Silicon (MLX成功) | `cpu` | `auto` | `http://host.docker.internal:8000` |
| Mac Apple Silicon (MLX失敗) | `cpu` | `paddleocr` | `http://vlm:8000` |
| ARM64 Linux | `cpu` | `paddleocr` | `http://vlm:8000` |

### 2.2. 環境変数の設定（手動）

自動設定を使用せず手動で設定する場合:

```env
# VLM設定
VLM_ENABLED=true
VLM_URL=http://vlm:8000
VLM_DEFAULT_MODEL=PaddleOCR-VL-1.6
VLM_TIMEOUT=600
VLM_RETRY_TIMES=2
VLM_RETRY_BACKOFF=300

# VLMバックエンド選択
#   paddleocr (デフォルト、OCRのみ)
#   paddleocr-vl (GPU加速、最高品質)
#   paddleocr-vl-cpu (CPU最適化)
#   paddleocr-vl-mlx (Mac Apple Silicon、Metal加速)
#   auto (環境自動判定)
VLM_MODEL=paddleocr

# デバイス選択（auto = NVIDIA GPU 自動検出）
PADDLEOCR_DEVICE=auto

# OCR設定
OCR_ENABLED=true

# Tika設定
TIKA_ENABLED=true
TIKA_URL=http://tika:9998
```

### 2.3. VLMコンテナの起動

```bash
# VLMコンテナの起動（Docker）
docker-compose up -d vlm

# ヘルスチェック
curl http://localhost:8001/health | jq .
```

**期待される出力:**
```json
{
  "status": "healthy",
  "model": "paddleocr-vl",
  "device": "gpu"
}
```

### 2.4. Mac Apple Silicon (MLX-VLM)

Mac M1/M2/M3/M4 では、MLX-VLM をホスト上で直接実行します（Docker 非対応）。

```bash
# 依存関係のインストールと起動
./scripts/start-vlm-mlx.sh

# ヘルスチェック（Mac ホスト上のサーバー）
curl http://localhost:8000/health | jq .
# {"status": "healthy", "model": "paddleocr-vl-mlx", "device": "Metal/ANE"}
```

MLX-VLM は Apple Metal GPU を直接使用するため、Docker 内では動作しません。  
Laravel (Sail) からは `host.docker.internal:8000` 経由で接続します。

### 2.5. GPU環境のセットアップ

GPU環境で実行する場合（自動検出されるため通常は手動設定不要）:

```bash
# .envでGPUを有効化（自動検出時は不要）
PADDLEOCR_DEVICE=gpu

# GPU用コンテナの起動
docker-compose -f docker-compose.yml -f docker-compose.gpu.yml up -d vlm
```

---

## 3. 主要コンポーネント

### 3.1. VLM処理ジョブ

**ファイル:** `app/Jobs/Ledger/ProcessVlmExtraction.php`

**役割:** PaddleOCR-VL APIを呼び出し、Markdown抽出と構造化データ生成を実行

**キュー:** `vlm-processing`（専用キュー）

**リトライ設定:**
- 試行回数: 2回（設定可能）
- バックオフ: 300秒（設定可能）
- タイムアウト: 600秒（設定可能）

**処理フロー:**
```php
1. ステータスを VLM_PROCESSING に更新
2. VlmClientService::extract() を呼び出し
3. 成功時:
   - vlm_markdown, vlm_structured_data, vlm_confidence を保存
   - vlm_processed_at タイムスタンプを設定
4. 失敗時:
   - vlm_failed_at タイムスタンプを設定
   - エラーログを記録
```

### 3.2. VLM APIクライアント

**ファイル:** `app/Services/VlmClientService.php`

**役割:** VLM APIとの通信を抽象化

**主要メソッド:**

```php
// ファイルからテキストを抽出
public function extract(AttachedFile $file): array

// 戻り値:
[
    'markdown' => string,        // Markdown形式テキスト
    'structured_data' => array,  // 構造化データ（JSON）
    'confidence' => float,       // 信頼度スコア（0-1）
    'model' => string,          // 使用モデル名
    'processing_time_ms' => int // 処理時間（ミリ秒）
]
```

### 3.3. OCR処理ジョブ

**ファイル:** `app/Jobs/Ledger/OcrAndOptimizeFile.php`

**役割:** OcrMyPDFを使用した OCR処理とPDF最適化

**処理フロー:**
```
1. 画像ファイル: PDF化してOCR処理
2. PDF（テキスト付き）: --skip-text で最適化のみ
3. PDF（画像のみ）: OCR処理でテキスト抽出
4. 処理完了後、Tika再抽出をディスパッチ
```

### 3.4. 最終化コマンド

**ファイル:** `app/Console/Commands/Ledger/FinalizeAttachedFileProcessing.php`

**役割:** VLM/OCR/Tikaの結果から最適なテキストソースを選択

**実行タイミング:** スケジューラーで5分ごと

**選択ロジック:**
```
1. VLM結果が存在 → vlm_markdown を採用
2. VLM失敗、OCR成功 → OCR結果を採用
3. 両方失敗 → Tika結果を採用
```

---

## 4. API仕様

### 4.1. VLM API

**エンドポイント:** `http://vlm:8000/extract/structured`

**リクエスト:**
```http
POST /extract/structured HTTP/1.1
Content-Type: multipart/form-data

file: (binary data)
```

**レスポンス:**
```json
{
  "success": true,
  "markdown": "# タイトル\n\n本文...",
  "html": "<html>...</html>",
  "structured_data": {
    "tables": [...],
    "images": [...]
  },
  "confidence": 0.95,
  "processing_time_s": 12.5
}
```

### 4.2. ヘルスチェック

**エンドポイント:** `http://vlm:8000/health`

**レスポンス:**
```json
{
  "status": "healthy",
  "model": "PaddleOCR-VL-0.9B",
  "version": "2.8.1",
  "gpu_available": false
}
```

---

## 5. テスト

### 5.1. テストの実行

```bash
# VLM統合テスト
./vendor/bin/sail test tests/Feature/Vlm/

# 特定のテスト
./vendor/bin/sail test --filter=test_vlm_extraction_success

# カバレッジ付き実行
./vendor/bin/sail test --coverage
```

### 5.2. テストデータの準備

テストファイルは `tests/fixtures/files/` に配置：

```
tests/fixtures/files/
├── test_invoice.pdf      # 請求書サンプル
├── test_handwriting.png  # 手書きメモ
├── test_receipt.jpg      # 領収書
└── test_document.docx    # Office文書
```

### 5.3. モックの使用

VLM APIをモックする例：

```php
use App\Services\VlmClientService;

$mock = Mockery::mock(VlmClientService::class);
$mock->shouldReceive('extract')
    ->once()
    ->andReturn([
        'markdown' => 'テストテキスト',
        'structured_data' => [],
        'confidence' => 0.95,
        'model' => 'PaddleOCR-VL-0.9B',
        'processing_time_ms' => 5000,
    ]);

$this->app->instance(VlmClientService::class, $mock);
```

### 5.4. キャッシュ判定の回帰テスト

`docker/paddle/unified_api.py` の起動前キャッシュ判定や offline mode を変更した場合は、
純粋ロジックの回帰テストを追加で実行してください。

```bash
python3 -m unittest discover -s docker/paddle/tests -p "test_*.py"
```

このテストは次を固定化します。

- `rec/japan/japan_PP-OCRv4_rec_infer` を含む現行の日本語モデルキャッシュ
- 旧 multilingual キャッシュとの後方互換
- `VLM_OFFLINE=auto/1/0` の判定
- 不完全キャッシュを誤って complete と見なさないこと

CI では `.github/workflows/vlm-cache-regression.yml` が同じテストを実行します。

---

## 6. トラブルシューティング

### 6.1. VLMコンテナが起動しない

**症状:** VLMコンテナが即座に停止する

**確認手順:**
```bash
# ログの確認
docker logs ledgerleap_vlm

# コンテナの再ビルド
docker-compose build vlm --no-cache
docker-compose up -d vlm
```

**よくある原因:**
- メモリ不足（最低4GB必要）
- ポート8001が既に使用されている
- Dockerイメージのビルドエラー

### 6.2. VLM処理が失敗する

**症状:** `vlm_failed_at`タイムスタンプが設定される

**確認手順:**
```bash
# VLM APIの動作確認
curl -X POST http://localhost:8001/extract/structured \
  -F "file=@tests/fixtures/files/test_invoice.pdf" | jq .

# ログの確認
tail -f storage/logs/laravel.log | grep VLM
```

**よくある原因:**
- VLMコンテナが停止している
- タイムアウト（大きいファイルの場合、VLM_TIMEOUTを増やす）
- ファイル形式が非対応（現在はPDF、PNG、JPGのみ対応）

### 6.3. OCR処理が遅い

**症状:** OCR処理に2分以上かかる

**対策:**
1. GPU環境に切り替え
2. OCR処理の並列数を調整（`config/queue.php`）
3. ファイルサイズを確認（10MB以上は処理時間が長い）

### 6.4. 最終化処理が実行されない

**症状:** `processing_finalized_at`が設定されない

**確認手順:**
```bash
# スケジューラーの動作確認
./vendor/bin/sail artisan schedule:list

# 手動で最終化処理を実行
./vendor/bin/sail artisan ledger:finalize-processing --limit=10
```

**よくある原因:**
- スケジューラーが動作していない
- VLM/OCR処理が完了していない
- タイムアウト設定が短すぎる（300秒以上推奨）

---

## 7. パフォーマンス最適化

### 7.1. 処理時間の目安

| エンジン | ファイルタイプ | CPU環境 | GPU環境 |
|---------|--------------|---------|---------|
| VLM | 画像（1MB） | 8-15秒 | 2-5秒 |
| VLM | PDF（1ページ） | 10-18秒 | 3-8秒 |
| OCR | 画像（1MB） | 30-60秒 | 10-20秒 |
| Tika | Office文書 | 3-5秒 | 3-5秒 |

### 7.2. 並列処理の調整

`config/queue.php`でキューワーカー数を調整：

```php
'connections' => [
    'vlm-processing' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'vlm-processing',
        'retry_after' => 600,
        'block_for' => null,
        'after_commit' => false,
        'processes' => 2, // 並列処理数
    ],
],
```

### 7.3. メモリ使用量の監視

```bash
# VLMコンテナのメモリ使用量
docker stats ledgerleap_vlm --no-stream
```

---

## 8. モデルの切り替え

### 8.1. 利用可能なモデル

| モデル | 対象環境 | 出力形式 | 特徴 |
|--------|---------|---------|------|
| `paddleocr` | 汎用 | テキスト+BBox | 安定、軽量、本番環境推奨 |
| `paddleocr-vl` | x86 + GPU | **構造化** (table HTML, layout, labeled blocks) | 最高品質、GPU 加速 |
| `paddleocr-vl-cpu` | x86 + CPU | **構造化** (table HTML, layout, labeled blocks) | CPU 最適化 VL モデル |
| `paddleocr-vl-mlx` | Mac M1-4 | **プレーンテキストのみ** | Metal GPU ホスト直接実行 |
| `marker` | 汎用 | Markdown | PDF → Markdown 特化 |
| `mineru` | 汎用 | Markdown | PDF → Markdown 特化 |
| `auto` | **推奨** | 環境自動判定 | |

> **構造化出力の制限:** `paddleocr-vl` / `paddleocr-vl-cpu` はネイティブ PaddlePaddle の後処理パイプライン（レイアウト検出・テーブル認識・ラベル付与）により構造化データを出力します。  
> **`paddleocr-vl-mlx` (MLX-VLM) はテキストのみ** 出力します。MLX-VLM は推論エンジンのみで PaddlePaddle の後処理パイプラインを移植していないためです。  
> MLX-VLM 側の構造化出力対応予定はありません（GitHub Issues 検索結果: 0件、2026-06-15 確認）。  
> Mac では自前のパイプライン（`_detect_text_tables()` + KV抽出 + garbage除去）で構造化を行います。

### 8.2. 切り替え手順

```bash
# 現在のモデルを確認
./bin/vlm-switch.sh status

# モデルを切り替え
./bin/vlm-switch.sh paddleocr-vl  # または paddleocr, marker

# コンテナを再起動
docker-compose down vlm
docker-compose build vlm --no-cache
docker-compose up -d vlm
```

---

## 9. 関連ドキュメント

### アーキテクチャ
- **[VLM-OCR技術選定](../architecture/vlm-ocr-technology-selection.md)** - 技術選定理由と実測ベンチマーク
- **[非同期処理](../architecture/QueueProcessing.md)** - ジョブフローとエラーハンドリング

### 機能仕様
- **[添付ファイル機能](../function/Attachment.md)** - ユーザー向け機能説明
- **[AttachedFileモデル](../models/AttachedFile.md)** - データモデル仕様

### 運用ガイド
- **[モデル切り替えガイド](../operations/model-switching-guide.md)** - 運用時のモデル切り替え手順
- **[パフォーマンス監視](../operations/fileinspector-performance-monitoring.md)** - 運用監視設定

### 作業ドキュメント（実装記録）
- **Phase 1-5実装計画:** `docs/work/ui-ux/attachment/` - 添付ファイル機能統合の詳細計画
- **VLM実装記録:** `docs/work/vlm-implementation/` - 2025年10月時点の初期実装記録

---

**実装完了:** Phase 1-5（2025年12月-2026年1月）  
**最終更新:** 2026年1月3日

| 項目 | 状況 | 対応 |
|------|------|------|
| PaddleOCRVL（最新版） | ❌ 使用不可 | safetensors互換性問題 |
| 表構造の保持 | ❌ 未対応 | 行単位の抽出のみ |
| 手書き精度 | ⚠️ 中程度 | 崩し字は認識困難 |
| リアルタイム処理 | ❌ 不向き | 処理時間6秒以上 |

---

## 🔍 トラブルシューティング

### コンテナが起動しない

```bash
# ログの確認
docker logs ledgerleap_vlm

# コンテナの再ビルド
docker-compose build vlm --no-cache
docker-compose up -d vlm
```

### OCR処理でエラーが発生

```bash
# モデルの初期化確認
curl http://localhost:8001/health

# コンテナの再起動
docker restart ledgerleap_vlm
```

### テストが失敗する

```bash
# テストファイルの確認
ls -la tests/fixtures/files/

# 個別テストの実行（詳細表示）
./vendor/bin/sail test tests/Feature/Vlm/PaddleOcrVlmTest.php \
  --filter=test_health_check -v
```

---

## 📝 API仕様

### GET /health

**レスポンス:**
```json
{
  "status": "healthy",
  "model": "PaddleOCR"
}
```

### POST /extract/structured

**リクエスト:**
- Content-Type: `multipart/form-data`
- Parameter: `file` (PDF/PNG/JPG)

**レスポンス:**
```json
{
  "success": true,
  "html": "<html><body>...</body></html>",
  "markdown": "テキスト内容...",
  "processing_time_s": 6.24
}
```

---

## 🎯 推奨用途

### ✅ 適している用途

- **請求書・領収書のテキスト抽出** - 高精度
- **全文検索インデックスの作成** - 十分な精度
- **手書きメモのデジタル化** - 基本的な認識可能
- **文書アーカイブ** - 簡易的な用途

### ❌ 適していない用途

- **表の構造解析** - 列・行の構造が必要な場合
- **高精度手書き認識** - 専門的な認識が必要な場合
- **リアルタイム処理** - 処理時間が重要な場合

---

## 🚀 今後の展開

### 短期的な改善（検討中）

1. **GPU対応** - 処理速度の向上
2. **バッチ処理** - 複数ファイルの同時処理
3. **キャッシュ機能** - 同一ファイルの再処理回避

### 中長期的な機能追加（計画）

1. **PaddleOCRVL対応** - 互換性問題の解決後
2. **表構造解析** - 専用ツールの追加
3. **多言語対応** - 英語・中国語等
4. **Laravel統合** - アプリケーション側実装

---

## 📞 サポート

- **メインドキュメント:** [実装完了記録](../work/vlm-implementation/2025-10-26_paddleocrvl-implementation-log.md)
- **テスト結果:** 実装完了記録の「12. テスト結果」セクション参照
- **トラブルシューティング:** 実装完了記録の「8. トラブルシューティング」セクション参照

---

**実装完了日:** 2025年10月26日 午前2時  
**作成者:** GitHub Copilot CLI + Development Team  
**最終更新:** 2025年10月26日 午前2時

---

## 🚀 PaddleOCR-VL 0.9B 試行版

### 世界1位のOCR性能を試す

**最新情報:** PaddleOCR-VL 0.9Bのテストコンテナが準備完了しました。

**特徴:**
- 🏆 **OmniBenchDoc V1.5 世界1位**（総合スコア90.67）
- 🚀 **GPT-4oを超える性能**（わずか0.9Bパラメータ）
- 📊 **表構造認識**（88%精度）
- 🔢 **数式認識**（85%精度）
- 📷 **QRコード・スタンプ抽出**
- 🌍 **109言語対応**

### 詳細情報

**📖 試行計画書:** [2025-10-26_paddleocr-vl-trial-plan.md](../work/vlm-implementation/2025-10-26_paddleocr-vl-trial-plan.md)

この試行版は実験的なものです。CPU環境での動作可否を検証中です。
検証が成功すれば、LedgerLeapのOCR機能が大幅に向上します。

---

**最終更新:** 2025年10月26日 午前2時30分

## 🔄 モデル切り替え

### クイック切り替え

LedgerLeapは2つのOCRモデルをサポート：

| モデル | ステータス | 特徴 |
|--------|-----------|------|
| **PaddleOCR 2.7.3** | ✅ 安定版 | 実績あり・本番環境使用可能 |
| **PaddleOCR-VL 0.9B** | 🧪 試行版 | 世界1位性能・実験的 |

### 切り替えコマンド

```bash
# 現在のモデルを確認
./bin/vlm-switch.sh status

# 安定版に切り替え
./bin/vlm-switch.sh paddleocr

# 試行版に切り替え
./bin/vlm-switch.sh paddleocr-vl
```

### 切り替え後の手順

```bash
docker-compose down vlm
docker-compose build vlm --no-cache
docker-compose up -d vlm
curl http://localhost:8001/health | jq .
```


### 全モデル対応

現在、3つのVLMモデルをサポート:

| モデル | 切り替えコマンド | 用途 |
|--------|----------------|------|
| PaddleOCR 2.7.3 | `./bin/vlm-switch.sh paddleocr` | 汎用OCR（安定） |
| PaddleOCR-VL 0.9B | `./bin/vlm-switch.sh paddleocr-vl` | 高度OCR（試行） |
| Marker | `./bin/vlm-switch.sh marker` | PDF→Markdown |


---

## 📊 Phase4実装完了情報（2025-11-08追記）

### VLM統合の現状

LedgerLeapのVLM機能は**Phase4で完全実装**され、以下の機能が利用可能です：

#### ✅ 実装済み機能

1. **VLM自動処理フロー**
   - ファイルアップロード時に自動でVLM処理実行
   - OCR失敗時の自動フォールバック
   - 処理結果を`attached_files.vlm_markdown`に保存

2. **VLM結果表示UI**
   - プレビューモーダルでMarkdown表示
   - 信頼度スコアの表示
   - Markdown/JSON形式のダウンロード

3. **ステータス管理**
   - 処理中・完了・失敗の視覚的フィードバック
   - リトライ機能
   - 重複処理の自動防止

### 開発者向け情報

#### VLM処理の実行方式

**重要:** VLM処理は現在**同期実行（dispatchSync）**で実装されています。

```php
// app/Jobs/Ledger/ProcessAttachedFile.php
if ($this->shouldProcessWithVlm($this->attachedFile)) {
    ProcessVlmExtraction::dispatchSync($this->attachedFile); // 同期実行
    $this->attachedFile->refresh();
    return;
}
```

**理由:**
- キュー処理の技術的問題を回避
- 確実な処理完了を保証
- VLM処理は高速（1-2秒）のためパフォーマンス影響は軽微

#### 設定

VLM機能を有効にするため、`.env`ファイルに以下を設定：

```env
VLM_ENABLED=true
VLM_URL=http://vlm:8000
VLM_DEFAULT_MODEL=PaddleOCR-VL-0.9B
VLM_TIMEOUT=300
```

#### 関連ファイル

- `app/Jobs/Ledger/ProcessVlmExtraction.php` - VLM処理ジョブ
- `app/Services/VlmClientService.php` - VLM API クライアント
- `app/Livewire/Ledger/Show.php` - VLM結果表示UI
- `app/Http/Controllers/AttachedFileDownloadController.php` - ダウンロード機能

### トラブルシューティング

#### VLM処理が実行されない

1. VLMコンテナの状態確認:
```bash
docker ps | grep vlm
curl http://localhost:8001/health
```

2. 設定確認:
```bash
./vendor/bin/sail artisan tinker --execute="echo config('vlm.enabled') ? 'Enabled' : 'Disabled';"
```

3. ログ確認:
```bash
tail -f storage/logs/queue-2025-11-08.log | grep VLM
```

#### VLM結果が表示されない

1. VLM処理が完了しているか確認:
```bash
./vendor/bin/sail artisan tinker --execute="
\$file = App\Models\AttachedFile::find(FILE_ID);
echo 'VLM processed: ' . (\$file->vlm_processed_at ? 'Yes' : 'No');
"
```

2. ブラウザのキャッシュクリア・リロード

### 関連ドキュメント

- [Phase4実装完了レポート](../work/vlm-rag-integration/2025-11-08_phase4-id3-implementation-report.md)
- [VLM-RAG統合アーキテクチャ](../architecture/vlm-rag-integration.md)
- [Phase4テストガイド](../work/vlm-rag-integration/2025-11-08_phase4-id3-testing-guide.md)

---

**最終更新:** 2025年11月8日（Phase4実装完了）
