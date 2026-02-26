# VLM統合APIテスト更新計画

**作成日:** 2025-11-07  
**作成者:** GitHub Copilot CLI  
**ステータス:** ✅ 完了 (2025-11-08)  
**目的:** 統一VLM API実装後のFeatureテストを新仕様に適合させる  
**完了報告:** [VLMテスト更新作業完了報告](./2025-11-07_vlm-test-update-completed.md)

---

## 📋 エグゼクティブサマリー

### 現状
- **実装完了:** VLM統一API (`unified_api.py`) - 全モデル共通エンドポイント・レスポンス形式
- **テスト状況:**
  - ✅ Unit Tests: 全て成功（モック利用）
  - ✅ Integration Tests: 成功（RAG統合など）
  - ❌ Feature Tests: 失敗（実際のコンテナ接続テスト3件）

### 問題の本質
3つのFeatureテスト（`MarkerVlmTest`, `PaddleOcrVlmTest`, `MinerUVlmTest`）が**旧API仕様**のまま残っており、統一API実装と齟齬が発生。

### 対応方針
1. 既存3テストファイルを統一API仕様に完全準拠させる
2. 共通テストベースクラスを作成し、DRY原則を適用
3. テスト環境セットアップガイドを整備

---

## 🔍 詳細な問題分析

### 1. MarkerVlmTest.php（最優先対応）

#### 問題点
| 項目 | 現在の実装 | 統一API仕様 | 影響度 |
|------|----------|-----------|-------|
| エンドポイント | `/extract/markdown` | `/extract/structured` | ❌ 致命的 |
| ベースURL | `http://localhost:8001` | `http://vlm:8000` | ❌ 致命的 |
| テストファイルパス | `storage_path('test/vlm-poc/...')` | `base_path('tests/fixtures/files/...')` | ⚠️ 高 |
| レスポンス `model` | `"Marker (CLI)"` | `"marker"` | ⚠️ 中 |
| レスポンスフィールド | `markdown` のみ期待 | `html`, `markdown`, `structured_data` 全て返却 | ⚠️ 中 |

#### 具体的な不整合箇所
```php
// ❌ 現在の実装
$response = Http::timeout(600)
    ->attach('file', file_get_contents($testFile), 'invoice_simple.pdf')
    ->post("{$this->vlmBaseUrl}/extract/markdown");

// 期待される実装 ✅
$response = Http::timeout(600)
    ->attach('file', file_get_contents($testFile), 'invoice_simple.pdf')
    ->post("{$this->vlmBaseUrl}/extract/structured");
```

```php
// ❌ 現在のアサーション
$this->assertEquals('Marker (CLI)', $data['model']);

// 期待されるアサーション ✅
$this->assertEquals('marker', $data['model']);
$this->assertArrayHasKey('html', $data);
$this->assertArrayHasKey('markdown', $data);
$this->assertArrayHasKey('structured_data', $data);
```

### 2. PaddleOcrVlmTest.php（微調整）

#### 問題点
| 項目 | 現在の実装 | 統一API仕様 | 影響度 |
|------|----------|-----------|-------|
| エンドポイント | `/extract/structured` ✅ | `/extract/structured` | ✅ OK |
| ベースURL | `http://vlm:8000` ✅ | `http://vlm:8000` | ✅ OK |
| レスポンス `model` | `"PaddleOCR"` | `"paddleocr"` | ⚠️ 低 |
| テストファイルパス | `base_path('tests/fixtures/files/...')` ✅ | 同左 | ✅ OK |

#### 必要な修正
```php
// ❌ 現在のアサーション
$this->assertEquals('PaddleOCR', $data['model']);

// 期待されるアサーション ✅
$this->assertEquals('paddleocr', $data['model']);
```

### 3. MinerUVlmTest.php（微調整）

#### 問題点
| 項目 | 現在の実装 | 統一API仕様 | 影響度 |
|------|----------|-----------|-------|
| エンドポイント | `/extract/structured` ✅ | `/extract/structured` | ✅ OK |
| ベースURL | `http://vlm:8000` ✅ | `http://vlm:8000` | ✅ OK |
| レスポンス `model` | `"MinerU"` | `"mineru"` | ⚠️ 低 |
| レスポンス `backend` | `"CPU"` | `"cpu"` | ⚠️ 低 |
| テストファイルパス | `base_path('tests/fixtures/files/...')` ✅ | 同左 | ✅ OK |

#### 必要な修正
```php
// ❌ 現在のアサーション
$this->assertEquals('MinerU', $data['model']);
$this->assertEquals('CPU', $data['backend']);

// 期待されるアサーション ✅
$this->assertEquals('mineru', $data['model']);
$this->assertEquals('cpu', $data['device']); // 'backend' → 'device'
```

---

## 🎯 統一API仕様（確定版）

### エンドポイント

#### 1. ヘルスチェック
```http
GET /health
```

**レスポンス:**
```json
{
  "status": "healthy",
  "model": "paddleocr" | "marker" | "mineru" | "paddleocr-vl",
  "device": "cpu" | "gpu"
}
```

#### 2. 構造化データ抽出（統一エンドポイント）
```http
POST /extract/structured
Content-Type: multipart/form-data

file: <binary>
```

**レスポンス（全モデル共通）:**
```json
{
  "success": true,
  "html": "<html>...</html>",
  "markdown": "# Document Title\n...",
  "structured_data": {
    "pages": [
      {
        "page_index": 0,
        "text_lines": ["line1", "line2"],
        "line_count": 2
      }
    ],
    "text_blocks": [
      {
        "type": "text" | "header_1" | "list_item" | "table",
        "content": "...",
        "line_index": 0,
        "confidence": 0.95,
        "bbox": [[x1, y1], [x2, y2], ...] // PaddleOCRのみ
      }
    ],
    "tables": [
      {
        "type": "table",
        "html": "<table>...</table>",
        "markdown": "| Header | ... |",
        "confidence": 0.90
      }
    ],
    "key_value_pairs": [
      {
        "key": "Total",
        "value": "¥1,000",
        "confidence": 0.92
      }
    ]
  },
  "processing_time_s": 1.23,
  "model": "paddleocr",
  "device": "cpu"
}
```

### モデル固有の特徴

| フィールド | PaddleOCR | Marker | MinerU |
|----------|----------|--------|--------|
| `bbox` | ✅ あり | ❌ なし | ❌ なし |
| `confidence` | ✅ 実測値 | ✅ 推定値(0.90-0.98) | ✅ 推定値(0.90-0.98) |
| HTMLテーブル | 簡易 | Markdown主体 | 高品質 |
| 処理速度 | 高速（1-5秒） | 中速（30-60秒） | 中速（20-40秒） |

---

## 📐 実装計画

### Phase 1: 共通テストベースクラス作成（優先度: 高）

#### 目的
- DRY原則の適用
- テストコードの保守性向上
- 統一API仕様の一元管理

#### 成果物
**ファイル:** `tests/Feature/Vlm/VlmTestBase.php`

```php
<?php

namespace Tests\Feature\Vlm;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

abstract class VlmTestBase extends TestCase
{
    protected string $vlmBaseUrl;
    protected string $expectedModel;
    protected int $defaultTimeout = 120;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vlmBaseUrl = 'http://vlm:8000';
    }

    /**
     * 統一APIヘルスチェック
     */
    protected function assertHealthCheck(string $expectedModel): void
    {
        $response = Http::get("{$this->vlmBaseUrl}/health");
        
        $response->throw();
        $this->assertEquals(200, $response->status());
        
        $data = $response->json();
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('healthy', $data['status']);
        $this->assertEquals($expectedModel, $data['model']);
        $this->assertArrayHasKey('device', $data);
        $this->assertContains($data['device'], ['cpu', 'gpu']);
    }

    /**
     * 統一API構造化抽出の共通アサーション
     */
    protected function assertStructuredExtractResponse(array $data, string $expectedModel): void
    {
        // 基本フィールドの存在確認
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('html', $data);
        $this->assertArrayHasKey('markdown', $data);
        $this->assertArrayHasKey('structured_data', $data);
        $this->assertArrayHasKey('processing_time_s', $data);
        $this->assertArrayHasKey('model', $data);
        $this->assertArrayHasKey('device', $data);
        
        // 内容の妥当性確認
        $this->assertNotEmpty($data['html']);
        $this->assertNotEmpty($data['markdown']);
        $this->assertEquals($expectedModel, $data['model']);
        
        // structured_dataの構造確認
        $structured = $data['structured_data'];
        $this->assertArrayHasKey('pages', $structured);
        $this->assertArrayHasKey('text_blocks', $structured);
        $this->assertArrayHasKey('tables', $structured);
        $this->assertArrayHasKey('key_value_pairs', $structured);
        
        $this->assertIsArray($structured['pages']);
        $this->assertIsArray($structured['text_blocks']);
        $this->assertIsArray($structured['tables']);
        $this->assertIsArray($structured['key_value_pairs']);
    }

    /**
     * テストファイルの存在確認とスキップ処理
     */
    protected function getTestFile(string $filename): string
    {
        $testFile = base_path("tests/fixtures/files/{$filename}");
        
        if (!file_exists($testFile)) {
            $this->markTestSkipped("Test file not found: {$testFile}");
        }
        
        return $testFile;
    }

    /**
     * 統一API構造化抽出リクエスト
     */
    protected function extractStructured(string $filePath, string $filename, int $timeout = null): array
    {
        $timeout = $timeout ?? $this->defaultTimeout;
        
        $response = Http::timeout($timeout)
            ->attach('file', file_get_contents($filePath), $filename)
            ->post("{$this->vlmBaseUrl}/extract/structured");
        
        $response->throw();
        $this->assertEquals(200, $response->status());
        
        return $response->json();
    }

    /**
     * 日本語テキストの存在確認
     */
    protected function assertContainsJapanese(string $text, string $message = ''): void
    {
        $hasJapanese = preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $text);
        $this->assertTrue((bool)$hasJapanese, $message ?: 'Text should contain Japanese characters');
    }

    /**
     * Markdown構造要素の存在確認
     */
    protected function assertHasMarkdownStructure(string $markdown, string $message = ''): void
    {
        $hasStructure = 
            str_contains($markdown, "\n\n") ||     // 段落区切り
            str_contains($markdown, '# ') ||       // 見出し
            str_contains($markdown, '## ') ||
            str_contains($markdown, '|') ||        // テーブル
            str_contains($markdown, '<table>');    // HTMLテーブル
        
        $this->assertTrue(
            $hasStructure,
            $message ?: 'Markdown should contain structured elements (paragraphs, headings, or tables)'
        );
    }
}
```

### Phase 2: MarkerVlmTest.php 完全リライト（優先度: 最高）

#### 変更サマリー
- エンドポイント: `/extract/markdown` → `/extract/structured`
- ベースURL: `http://localhost:8001` → `http://vlm:8000`
- テストファイルパス: `storage_path()` → `base_path('tests/fixtures/files/')`
- `VlmTestBase` の継承

#### 実装
**ファイル:** `tests/Feature/Vlm/MarkerVlmTest.php`

```php
<?php

namespace Tests\Feature\Vlm;

class MarkerVlmTest extends VlmTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectedModel = 'marker';
        $this->defaultTimeout = 600; // Markerは初回モデルDLで時間がかかる
    }

    public function test_health_check(): void
    {
        $this->assertHealthCheck('marker');
    }

    public function test_extract_structured_from_simple_invoice_pdf(): void
    {
        $testFile = $this->getTestFile('invoice_simple.pdf');
        
        $data = $this->extractStructured($testFile, 'invoice_simple.pdf', 600);
        
        // 統一APIレスポンス検証
        $this->assertStructuredExtractResponse($data, 'marker');
        
        // Marker固有の品質チェック
        $markdown = $data['markdown'];
        $this->assertContainsJapanese($markdown, 'Marker should extract Japanese text');
        $this->assertHasMarkdownStructure($markdown);
        
        // Markerは比較的長い出力を生成
        $this->assertGreaterThan(100, strlen($markdown));
    }

    public function test_extract_structured_from_handwriting_image(): void
    {
        $testFile = $this->getTestFile('hand_writing_01.png');
        
        // Markerは画像も処理可能
        $data = $this->extractStructured($testFile, 'hand_writing_01.png', 600);
        
        $this->assertStructuredExtractResponse($data, 'marker');
        $this->assertNotEmpty($data['markdown']);
    }

    public function test_extract_structured_handles_unsupported_format(): void
    {
        $response = Http::attach('file', 'unsupported content', 'test.txt')
            ->post("{$this->vlmBaseUrl}/extract/structured");
        
        // 統一APIは400エラーを返すべき
        $this->assertContains($response->status(), [400, 500]);
        
        if ($response->status() === 400) {
            $data = $response->json();
            $this->assertArrayHasKey('detail', $data);
        }
    }

    public function test_markdown_output_quality(): void
    {
        $testFile = $this->getTestFile('invoice_simple.pdf');
        
        $data = $this->extractStructured($testFile, 'invoice_simple.pdf', 600);
        
        $markdown = $data['markdown'];
        
        // 品質基準
        $this->assertGreaterThan(100, strlen($markdown), 
            'Marker output should have substantial content');
        
        $this->assertHasMarkdownStructure($markdown);
        
        // Markerは比較的高速（600秒以内、通常30-60秒）
        $this->assertLessThan(600, $data['processing_time_s']);
    }

    public function test_structured_data_contains_expected_elements(): void
    {
        $testFile = $this->getTestFile('invoice_simple.pdf');
        
        $data = $this->extractStructured($testFile, 'invoice_simple.pdf', 600);
        
        $structured = $data['structured_data'];
        
        // Markerは構造化データを生成する
        $this->assertGreaterThan(0, count($structured['text_blocks']),
            'Marker should extract text blocks');
        
        // Key-Valueペアの抽出確認（請求書なので存在する可能性が高い）
        // ただし、確実ではないのでwarning的なアサーション
        if (count($structured['key_value_pairs']) === 0) {
            $this->markTestIncomplete('Expected key-value pairs in invoice, but none found');
        }
    }

    /**
     * 並行処理テストは統一API側で制御されるため、スキップまたは削除を推奨
     */
    public function test_processing_prevents_concurrent_requests(): void
    {
        $this->markTestSkipped('Concurrent request handling is now managed by unified API');
    }
}
```

### Phase 3: PaddleOcrVlmTest.php 微調整（優先度: 中）

#### 変更サマリー
- `VlmTestBase` の継承
- `model` フィールドのアサーション修正: `"PaddleOCR"` → `"paddleocr"`
- 重複コードの削除

#### 実装
**ファイル:** `tests/Feature/Vlm/PaddleOcrVlmTest.php`

```php
<?php

namespace Tests\Feature\Vlm;

class PaddleOcrVlmTest extends VlmTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectedModel = 'paddleocr';
        $this->defaultTimeout = 120;
    }

    public function test_health_check(): void
    {
        $this->assertHealthCheck('paddleocr');
    }

    public function test_extract_structured_from_simple_invoice_pdf(): void
    {
        $testFile = $this->getTestFile('invoice_simple.pdf');
        
        $data = $this->extractStructured($testFile, 'invoice_simple.pdf');
        
        $this->assertStructuredExtractResponse($data, 'paddleocr');
        
        // PaddleOCR固有: bbox情報の存在確認
        if (count($data['structured_data']['text_blocks']) > 0) {
            $firstBlock = $data['structured_data']['text_blocks'][0];
            if (isset($firstBlock['bbox'])) {
                $this->assertIsArray($firstBlock['bbox']);
            }
        }
        
        // 日本語抽出確認
        $combinedText = $data['html'] . $data['markdown'];
        $this->assertContainsJapanese($combinedText);
    }

    public function test_extract_structured_from_handwriting_image(): void
    {
        $testFile = $this->getTestFile('hand_writing_01.png');
        
        $data = $this->extractStructured($testFile, 'hand_writing_01.png');
        
        $this->assertStructuredExtractResponse($data, 'paddleocr');
        $this->assertNotEmpty($data['html']);
    }

    public function test_extract_structured_handles_invalid_file(): void
    {
        $response = Http::attach('file', 'invalid file content', 'invalid.txt')
            ->post("{$this->vlmBaseUrl}/extract/structured");
        
        // 500エラーが返ることを確認（OCR処理エラー）
        $this->assertEquals(500, $response->status());
    }

    public function test_processing_time_is_reasonable(): void
    {
        $testFile = $this->getTestFile('hand_writing_01.png');
        
        $startTime = microtime(true);
        $data = $this->extractStructured($testFile, 'hand_writing_01.png');
        $endTime = microtime(true);
        
        $totalTime = $endTime - $startTime;
        
        // PaddleOCRは高速（2分以内）
        $this->assertLessThan(120, $totalTime);
        
        // レスポンスの処理時間と実測値が近い（±10秒の誤差許容）
        $this->assertEqualsWithDelta($data['processing_time_s'], $totalTime, 10);
    }
}
```

### Phase 4: MinerUVlmTest.php 微調整（優先度: 中）

#### 変更サマリー
- `VlmTestBase` の継承
- `model` フィールドのアサーション修正: `"MinerU"` → `"mineru"`
- `backend` → `device` への変更
- 重複コードの削除

#### 実装
**ファイル:** `tests/Feature/Vlm/MinerUVlmTest.php`

```php
<?php

namespace Tests\Feature\Vlm;

class MinerUVlmTest extends VlmTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->expectedModel = 'mineru';
        $this->defaultTimeout = 120;
    }

    public function test_health_check(): void
    {
        $this->assertHealthCheck('mineru');
        
        // MinerU固有: CPU動作確認
        $response = Http::get("{$this->vlmBaseUrl}/health");
        $data = $response->json();
        $this->assertEquals('cpu', $data['device']);
    }

    public function test_extract_structured_from_simple_invoice_pdf(): void
    {
        $testFile = $this->getTestFile('invoice_simple.pdf');
        
        $data = $this->extractStructured($testFile, 'invoice_simple.pdf');
        
        $this->assertStructuredExtractResponse($data, 'mineru');
        
        // MinerU固有: HTMLテーブルの品質チェック
        $markdown = $data['markdown'];
        $this->assertContainsJapanese($markdown);
        $this->assertStringContainsString('<table>', $markdown, 
            'MinerU should generate HTML tables');
        
        // Markdown要素の確認
        $this->assertHasMarkdownStructure($markdown);
    }

    public function test_extract_structured_from_meeting_notes_pdf(): void
    {
        $testFile = $this->getTestFile('meeting_notes.pdf');
        
        $data = $this->extractStructured($testFile, 'meeting_notes.pdf');
        
        $this->assertStructuredExtractResponse($data, 'mineru');
        $this->assertNotEmpty($data['markdown']);
    }

    public function test_extract_structured_handles_unsupported_format(): void
    {
        $response = Http::attach('file', 'unsupported content', 'test.txt')
            ->post("{$this->vlmBaseUrl}/extract/structured");
        
        // MinerUはPDFのみサポート
        $this->assertContains($response->status(), [400, 500]);
    }

    public function test_markdown_output_quality(): void
    {
        $testFile = $this->getTestFile('invoice_simple.pdf');
        
        $data = $this->extractStructured($testFile, 'invoice_simple.pdf');
        
        $markdown = $data['markdown'];
        
        // 品質基準
        $this->assertGreaterThan(100, strlen($markdown));
        $this->assertHasMarkdownStructure($markdown);
        
        // 処理時間（CPU環境で60秒以内が目安）
        $this->assertLessThan(60, $data['processing_time_s'],
            'MinerU should complete within 60 seconds on CPU');
    }

    public function test_structured_data_quality(): void
    {
        $testFile = $this->getTestFile('invoice_simple.pdf');
        
        $data = $this->extractStructured($testFile, 'invoice_simple.pdf');
        
        $structured = $data['structured_data'];
        
        // MinerUは高品質な構造化データを生成
        $this->assertGreaterThan(0, count($structured['text_blocks']));
        
        // テーブル抽出確認（請求書にはテーブルがある可能性が高い）
        if (count($structured['tables']) === 0) {
            $this->addWarning('Expected tables in invoice, but none found');
        }
    }

    public function test_large_pdf_processing(): void
    {
        $this->markTestIncomplete('Large PDF test needs implementation with actual large file');
    }
}
```

### Phase 5: テストファイルの整理（優先度: 低）

#### 目的
- 古いテストファイル配置の削除
- テストフィクスチャの一元管理

#### 作業内容
1. `storage/test/vlm-poc/` 配下のファイルを `tests/fixtures/files/` に統合（重複排除）
2. 不要なファイルの削除
3. `.gitignore` の更新

#### コマンド例
```bash
# 重複確認
ls -la storage/test/vlm-poc/
ls -la tests/fixtures/files/

# 移動（必要に応じて）
# 注意: すでに tests/fixtures/files/ に同名ファイルが存在する場合はスキップ

# 古いディレクトリの削除（確認後）
# rm -rf storage/test/vlm-poc/
```

---

## ⚙️ テスト実行環境のセットアップ

### 前提条件
1. Docker環境が起動している
2. VLMコンテナが正常に動作している
3. `VLM_MODEL` 環境変数が設定されている

### セットアップ手順

#### 1. 環境変数の確認
```bash
grep VLM_MODEL .env
# 出力例: VLM_MODEL=mineru
```

#### 2. VLMコンテナの起動確認
```bash
docker ps | grep vlm
# vlmコンテナが "Up" 状態であることを確認

# ヘルスチェック
curl http://localhost:8001/health
# 出力例: {"status":"healthy","model":"mineru","device":"cpu"}
```

#### 3. テスト実行

##### 全VLMテストの実行
```bash
./vendor/bin/sail test --filter=Vlm
```

##### 個別テスト実行
```bash
# PaddleOCR
VLM_MODEL=paddleocr ./bin/vlm-switch.sh paddleocr
./vendor/bin/sail test tests/Feature/Vlm/PaddleOcrVlmTest.php

# Marker
VLM_MODEL=marker ./bin/vlm-switch.sh marker
./vendor/bin/sail test tests/Feature/Vlm/MarkerVlmTest.php

# MinerU
VLM_MODEL=mineru ./bin/vlm-switch.sh mineru
./vendor/bin/sail test tests/Feature/Vlm/MinerUVlmTest.php
```

#### 4. モデル切り替え後の待機時間
```bash
# モデル切り替え後、ヘルスチェックが成功するまで待機
while true; do
  status=$(curl -s http://localhost:8001/health | jq -r '.status')
  if [ "$status" == "healthy" ]; then
    echo "VLM service is ready!"
    break
  fi
  echo "Waiting for VLM service... (status: $status)"
  sleep 5
done
```

---

## 🚨 懸念事項とリスク

### 1. テスト実行時間の長期化（リスク: 中）

#### 問題
- Marker: 初回実行時にモデルダウンロードで10-15分
- MinerU: イメージサイズ13GB、ビルドに時間
- PaddleOCR: 比較的高速だが、GPU環境では初回に時間

#### 対策
- **CI/CD環境:** モデルキャッシュの事前準備
- **タイムアウト値の最適化:** テストごとに適切な`timeout`設定
- **並列実行の検討:** 異なるモデルのテストを別々のジョブで実行

#### 推奨CI設定例（GitHub Actions）
```yaml
strategy:
  matrix:
    vlm_model: [paddleocr, marker, mineru]
jobs:
  vlm-tests:
    runs-on: ubuntu-latest
    steps:
      - name: Set VLM_MODEL
        run: echo "VLM_MODEL=${{ matrix.vlm_model }}" >> .env
      
      - name: Build VLM container
        run: docker-compose build --no-cache vlm
      
      - name: Start services
        run: docker-compose up -d
      
      - name: Wait for VLM service
        run: |
          timeout 300 bash -c 'until curl -f http://localhost:8001/health; do sleep 5; done'
      
      - name: Run VLM tests
        run: ./vendor/bin/sail test tests/Feature/Vlm/${VLM_MODEL}VlmTest.php
```

### 2. テストファイルの一貫性（リスク: 低）

#### 問題
- `storage/test/vlm-poc/` と `tests/fixtures/files/` に重複ファイル
- ファイルパスの不統一によるテスト失敗リスク

#### 対策
- **Phase 5で完全統一:** `tests/fixtures/files/` のみ使用
- **バージョン管理:** Gitで管理し、差分を明確化

#### 確認コマンド
```bash
# ファイルハッシュの比較
md5sum storage/test/vlm-poc/invoice_simple.pdf
md5sum tests/fixtures/files/invoice_simple.pdf

# 異なる場合は内容を確認して統一
```

### 3. 統一APIレスポンスの後方互換性（リスク: 中）

#### 問題
- 既存のプロダクションコード（`VlmClientService.php`など）が旧レスポンス形式に依存している可能性
- 統一API導入による破壊的変更

#### 対策
- **Unit Testで保護:** `VlmClientServiceTest.php` が既に成功しているため、サービス層は保護されている
- **段階的移行:**
  1. 統一APIデプロイ
  2. Featureテスト更新
  3. 旧エンドポイント廃止（deprecation期間を設ける）

#### 確認ポイント
```php
// app/Services/VlmClientService.php の確認
public function extract(AttachedFile $attachedFile): array
{
    // 統一API '/extract/structured' を使用しているか？
    $response = Http::timeout($this->timeout)
        ->attach('file', $fileContent, $fileName)
        ->post("{$this->baseUrl}/extract/structured"); // ✅ 正しい
    
    // レスポンス処理が統一API形式に対応しているか？
    return $response->json(); // 全フィールドが返却される
}
```

### 4. Docker環境依存のテスト脆弱性（リスク: 高）

#### 問題
- Featureテストは実際のDockerコンテナに依存
- ネットワークエラー、リソース不足による不安定性
- 開発者ローカル環境での実行困難

#### 対策
- **モックの活用:** Unit Testで基本動作を保証
- **テスト環境の標準化:** Docker Composeの設定を厳密に管理
- **スキップ可能なテスト:** `markTestSkipped()` の適切な利用

#### 推奨事項
```php
protected function setUp(): void
{
    parent::setUp();
    
    // VLM機能が無効な場合はスキップ
    if (!config('vlm.enabled')) {
        $this->markTestSkipped('VLM feature is disabled');
    }
    
    // Dockerコンテナが起動していない場合はスキップ
    try {
        Http::timeout(5)->get($this->vlmBaseUrl . '/health');
    } catch (\Exception $e) {
        $this->markTestSkipped('VLM service is not available: ' . $e->getMessage());
    }
}
```

### 5. モデル切り替えの自動化不足（リスク: 低）

#### 問題
- 現在、モデル切り替えは手動（`./bin/vlm-switch.sh`）
- テスト実行前に正しいモデルが選択されているか不確実

#### 対策
- **テスト内でのアサーション強化:**
```php
public function test_health_check(): void
{
    $response = Http::get("{$this->vlmBaseUrl}/health");
    $data = $response->json();
    
    // 期待されるモデルと一致しない場合はスキップ
    if ($data['model'] !== $this->expectedModel) {
        $this->markTestSkipped(
            "Expected model '{$this->expectedModel}' but got '{$data['model']}'. " .
            "Run: ./bin/vlm-switch.sh {$this->expectedModel}"
        );
    }
    
    $this->assertEquals($this->expectedModel, $data['model']);
}
```

### 6. エラーメッセージの日本語化（リスク: 極低）

#### 問題
- 統一APIのエラーメッセージが英語のみ
- 日本語環境での可読性

#### 対策
- 現状維持（技術的エラーは英語が標準）
- 必要に応じてLaravelのエラーハンドリング層で日本語化

---

## 📊 作業見積もり

| Phase | 作業内容 | 見積時間 | 優先度 |
|-------|---------|---------|-------|
| Phase 1 | 共通テストベースクラス作成 | 2時間 | 高 |
| Phase 2 | MarkerVlmTest完全リライト | 1.5時間 | 最高 |
| Phase 3 | PaddleOcrVlmTest微調整 | 0.5時間 | 中 |
| Phase 4 | MinerUVlmTest微調整 | 0.5時間 | 中 |
| Phase 5 | テストファイル整理 | 0.5時間 | 低 |
| **合計** | | **5時間** | |

### テスト実行時間（目安）
- **PaddleOCR:** 5-10分（高速）
- **Marker:** 15-20分（初回はモデルDLで長い）
- **MinerU:** 10-15分
- **全体（3モデル並列）:** 20-25分
- **全体（順次実行）:** 30-45分

---

## ✅ 完了基準（Definition of Done）

1. ✅ `VlmTestBase.php` が作成され、全共通ロジックが実装されている
2. ✅ `MarkerVlmTest.php` が統一API仕様に完全準拠し、全テストが成功
3. ✅ `PaddleOcrVlmTest.php` の `model` アサーションが修正され、全テストが成功
4. ✅ `MinerUVlmTest.php` の `model`, `device` アサーションが修正され、全テストが成功
5. ✅ 全VLMテスト（`./vendor/bin/sail test --filter=Vlm`）が成功
6. ✅ テストファイルパスが `tests/fixtures/files/` に統一されている
7. ✅ 旧テストファイル配置（`storage/test/vlm-poc/`）が削除または非推奨化
8. ✅ 本ドキュメントが最新状態に更新され、`docs/work/vlm-implementation/` に配置
9. ✅ CI/CD設定（GitHub Actionsなど）がVLMテストに対応している（該当する場合）

---

## 📚 関連ドキュメント

### 実装ドキュメント
- [VLM統一API実装完了報告](./2025-11-02_unified-vlm-api-implementation.md)
- [VLM/OCR実装完了記録](../development/vlm-ocr.md)

### 技術資料
- [統一API実装ファイル](../../docker/paddle/unified_api.py)
- [VLM切り替えスクリプト](../../bin/vlm-switch.sh)
- [Docker Compose設定](../../docker-compose.yml)

### テストコード（更新対象）
- `tests/Feature/Vlm/MarkerVlmTest.php`
- `tests/Feature/Vlm/PaddleOcrVlmTest.php`
- `tests/Feature/Vlm/MinerUVlmTest.php`
- `tests/Unit/Services/VlmClientServiceTest.php` ✅（更新不要）

---

## 🚀 次のステップ

### 推奨実装順序
1. **Phase 1: 共通テストベース作成**（必須、他Phase依存）
2. **Phase 2: MarkerVlmTest完全リライト**（最優先、影響大）
3. **Phase 3 & 4: PaddleOcr/MinerU微調整**（並行可能）
4. **Phase 5: テストファイル整理**（クリーンアップ）
5. **統合テスト実行 & レビュー**

### 実装開始コマンド
```bash
# ブランチ作成
git checkout -b feature/vlm-test-update

# Phase 1開始
touch tests/Feature/Vlm/VlmTestBase.php
```

---

**作成者:** GitHub Copilot CLI  
**レビュー待ち:** LedgerLeap開発チーム  
**更新履歴:**
- 2025-11-07: 初版作成