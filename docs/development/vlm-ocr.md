# VLM/OCR Engine Integration

## Purpose

LedgerLeap integrates three text extraction engines for attached files to achieve high-accuracy, resilient document processing. This guide covers the architecture, configuration, and development workflow for the VLM, OCR, and Tika integration layer.

## Scope

- Architecture of the 3-engine pipeline (VLM → OCR → Tika priority chain)
- Environment variables, model selection, and queue configuration
- Development setup for VLM/OCR containers
- API contracts for the VLM service
- Testing approach and fixture layout
- Troubleshooting common failures
- Known constraints and edge cases

### Out of scope

- User-facing feature descriptions → [Attachment feature](../function/Attachment.md)
- High-level technology selection rationale → [VLM-OCR technology selection](../architecture/vlm-ocr-technology-selection.md)
- Async job flow internals → [Queue processing architecture](../architecture/QueueProcessing.md)
- AttachedFile data model → [AttachedFile model](../models/AttachedFile.md)

## Architecture

LedgerLeap runs three engines in a priority chain:

| Priority | Engine | Role | Output |
|----------|--------|------|--------|
| 1 (preferred) | **PaddleOCR-VL** (VLM) | Visual language model — layout-aware extraction | Markdown, structured JSON |
| 2 (fallback) | **OcrMyPDF** (OCR) | Tesseract-based OCR + PDF optimisation | Plain text, optimised PDF |
| 3 (last resort) | **Apache Tika** (Tika) | Generic document text extraction | Plain text, metadata |

### Processing flow

```
Upload → Tika (immediate, ~5s) → user regains control
         ↓
  VLM + OCR dispatched in parallel (VLM immediate, OCR 2s delay)
         ↓
  Scheduler (every 5min) runs FinalizeAttachedFileProcessing
         ↓
  Best source selected: VLM > OCR > Tika → written to attached_files.content
```

User wait time: ~5 seconds (Tika completion). Full pipeline completion: ~1-2 minutes depending on file size and type.

### Key components

| Component | Path | Responsibility |
|-----------|------|----------------|
| VLM extraction job | `app/Jobs/Ledger/ProcessVlmExtraction.php` | Calls PaddleOCR-VL API, stores markdown + structured data |
| OCR optimisation job | `app/Jobs/Ledger/OcrAndOptimizeFile.php` | Runs OcrMyPDF, dispatches Tika re-extraction |
| VLM API client | `app/Services/VlmClientService.php` | Abstracts HTTP communication with the VLM service |
| Finalize command | `app/Console/Commands/Ledger/FinalizeAttachedFileProcessing.php` | Selects best text source, updates `attached_files.content` |

### Queue settings

VLM processing uses a dedicated `vlm-processing` queue:

- Retry attempts: 2 (configurable via `VLM_RETRY_TIMES`)
- Retry backoff: 300 seconds (configurable via `VLM_RETRY_BACKOFF`)
- Timeout: 600 seconds (configurable via `VLM_TIMEOUT`)

### Database schema

Results are stored in `attached_files`:

| Column | Type | Content |
|--------|------|---------|
| `vlm_markdown` | LONGTEXT | VLM-extracted markdown |
| `vlm_structured_data` | JSON | Structured entities, tables |
| `vlm_model` | VARCHAR(100) | Model name used |
| `vlm_confidence` | DECIMAL(5,4) | Confidence score (0–1) |
| `vlm_processing_time_ms` | INT UNSIGNED | Processing duration |
| `vlm_processed_at` | TIMESTAMP | Completion timestamp |
| `vlm_failed_at` | TIMESTAMP | Failure timestamp |
| `ocr_processed_at` | TIMESTAMP | OCR completion |
| `ocr_failed_at` | TIMESTAMP | OCR failure |
| `tika_processed_at` | TIMESTAMP | Tika completion |
| `processing_finalized_at` | TIMESTAMP | Finalization timestamp |
| `finalized_source` | VARCHAR(20) | Winning source: `vlm`, `ocr`, or `tika` |

## Configuration

### Automatic setup

`./bin/setup.sh` detects the host architecture and writes the optimal VLM configuration to `.env`. Manual configuration is not needed unless you have specific requirements.

| Environment | `PADDLEOCR_DEVICE` | `VLM_MODEL` | `VLM_URL` |
|-------------|-------------------|-------------|-----------|
| x86 + NVIDIA GPU | `gpu` | `paddleocr-vl` | `http://vlm:8000` |
| x86 + CPU | `cpu` | `paddleocr-vl-cpu` | `http://vlm:8000` |
| Mac Apple Silicon (MLX OK) | `cpu` | `auto` | `http://host.docker.internal:8000` |
| Mac Apple Silicon (MLX fail) | `cpu` | `paddleocr` | `http://vlm:8000` |
| ARM64 Linux | `cpu` | `paddleocr` | `http://vlm:8000` |

### Manual configuration

```env
# VLM
VLM_ENABLED=true
VLM_URL=http://vlm:8000
VLM_DEFAULT_MODEL=PaddleOCR-VL-1.6
VLM_TIMEOUT=600
VLM_RETRY_TIMES=2
VLM_RETRY_BACKOFF=300
VLM_MODEL=paddleocr
PADDLEOCR_DEVICE=auto

# OCR
OCR_ENABLED=true

# Tika
TIKA_ENABLED=true
TIKA_URL=http://tika:9998
```

### Model selection

| Model | Target | Output | Notes |
|-------|--------|--------|-------|
| `paddleocr` | General | Text + BBox | Stable, production-recommended |
| `paddleocr-vl` | x86 + GPU | Structured (table HTML, layout, labelled blocks) | Highest quality, GPU accelerated |
| `paddleocr-vl-cpu` | x86 + CPU | Structured (table HTML, layout, labelled blocks) | CPU-optimised VL |
| `paddleocr-vl-mlx` | Mac M1–M4 | Plain text only | Metal GPU, runs on host (not Docker) |
| `marker` | General | Markdown | PDF → Markdown specialist |
| `mineru` | General | Markdown | PDF → Markdown specialist |
| `auto` | Any | Auto-detect | Recommended default |

Switch models with:

```bash
./bin/vlm-switch.sh status           # check current model
./bin/vlm-switch.sh paddleocr-vl     # switch to a specific model
docker-compose build vlm --no-cache  # rebuild after switch
docker-compose up -d vlm
```

### Queue worker tuning

Adjust concurrent VLM workers in `config/queue.php`:

```php
'connections' => [
    'vlm-processing' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'vlm-processing',
        'retry_after' => 600,
        'block_for' => null,
        'after_commit' => false,
        'processes' => 2,
    ],
],
```

## Development Setup

### Start VLM container

```bash
docker-compose up -d vlm
curl http://localhost:8001/health | jq .
# Expected: {"status":"healthy","model":"paddleocr-vl","device":"gpu"}
```

### Mac Apple Silicon (MLX-VLM)

On Apple Silicon, MLX-VLM runs directly on the host (not inside Docker) to use the Metal GPU:

```bash
./scripts/start-vlm-mlx.sh
curl http://localhost:8000/health | jq .
# Expected: {"status":"healthy","model":"paddleocr-vl-mlx","device":"Metal/ANE"}
```

Laravel (Sail) connects via `host.docker.internal:8000`.

### GPU environment

```bash
PADDLEOCR_DEVICE=gpu
docker-compose -f docker-compose.yml -f docker-compose.gpu.yml up -d vlm
```

## API Reference

### VLM extraction

```
POST http://vlm:8000/extract/structured
Content-Type: multipart/form-data

file: (binary — PDF, PNG, or JPG)
```

**Response:**

```json
{
  "success": true,
  "markdown": "# Title\n\nContent...",
  "html": "<html>...</html>",
  "structured_data": {
    "tables": [...],
    "images": [...]
  },
  "confidence": 0.95,
  "processing_time_s": 12.5
}
```

### Health check

```
GET http://vlm:8000/health
```

**Response:**

```json
{
  "status": "healthy",
  "model": "PaddleOCR-VL-0.9B",
  "version": "2.8.1",
  "gpu_available": false
}
```

## Validation

### Running tests

```bash
# Full VLM integration suite
./vendor/bin/sail test tests/Feature/Vlm/

# Specific test
./vendor/bin/sail test --filter=test_vlm_extraction_success

# With coverage
./vendor/bin/sail test --coverage
```

### Test fixtures

Place test files in `tests/fixtures/files/`:

```
tests/fixtures/files/
├── test_invoice.pdf
├── test_handwriting.png
├── test_receipt.jpg
└── test_document.docx
```

### Mocking the VLM client

```php
use App\Services\VlmClientService;

$mock = Mockery::mock(VlmClientService::class);
$mock->shouldReceive('extract')
    ->once()
    ->andReturn([
        'markdown' => 'Test text',
        'structured_data' => [],
        'confidence' => 0.95,
        'model' => 'PaddleOCR-VL-0.9B',
        'processing_time_ms' => 5000,
    ]);

$this->app->instance(VlmClientService::class, $mock);
```

### Cache regression tests

When modifying `docker/paddle/unified_api.py` startup cache detection or offline mode, run:

```bash
python3 -m unittest discover -s docker/paddle/tests -p "test_*.py"
```

These tests lock in the Japanese model cache (`rec/japan/japan_PP-OCRv4_rec_infer`), backward compatibility with older multilingual caches, `VLM_OFFLINE=auto/1/0` logic, and guard against incomplete caches being treated as complete.

CI runs the same tests via `.github/workflows/vlm-cache-regression.yml`.

## Troubleshooting

### VLM container exits immediately

```bash
docker logs ledgerleap_vlm
docker-compose build vlm --no-cache
docker-compose up -d vlm
```

Common causes: insufficient memory (minimum 4 GB required), port 8001 already in use, Docker image build error.

### VLM processing fails

```bash
# Test VLM API directly
curl -X POST http://localhost:8001/extract/structured \
  -F "file=@tests/fixtures/files/test_invoice.pdf" | jq .

# Check application logs
tail -f storage/logs/laravel.log | grep VLM
```

Common causes: VLM container not running, timeout exceeded (increase `VLM_TIMEOUT` for large files), unsupported file format.

### OCR processing is slow (>2 minutes)

- Switch to a GPU environment if available
- Adjust parallel worker count in `config/queue.php`
- Verify file size (files >10 MB have proportionally longer processing)

### Finalization does not run

```bash
./vendor/bin/sail artisan schedule:list
./vendor/bin/sail artisan ledger:finalize-processing --limit=10
```

Common causes: scheduler not running, VLM/OCR not yet complete, timeout too short (recommend ≥300 seconds).

### Processing time reference

| Engine | File type | CPU | GPU |
|--------|-----------|-----|-----|
| VLM | Image (1 MB) | 8–15 s | 2–5 s |
| VLM | PDF (1 page) | 10–18 s | 3–8 s |
| OCR | Image (1 MB) | 30–60 s | 10–20 s |
| Tika | Office document | 3–5 s | 3–5 s |

Measured on Docker on macOS (ARM64), 4-core CPU, 16 GB RAM.

## Edge Cases and Constraints

### MLX-VLM outputs plain text only

`paddleocr-vl-mlx` (Mac Apple Silicon) performs inference only — it does not include PaddlePaddle's post-processing pipeline (layout detection, table recognition, labelling). Structured output is not available on this backend. On Mac, a custom pipeline (`_detect_text_tables()` + KV extraction + garbage removal) compensates for the missing structured output.

### PaddleOCR-VL structured output limitation

`paddleocr-vl` and `paddleocr-vl-cpu` output structured data via the native PaddlePaddle post-processing pipeline. Only these backends support table HTML, layout detection, and labelled blocks.

### File format support

VLM/extract currently supports PDF, PNG, and JPG. Office documents (DOCX, XLSX) are processed by Tika only.

### Memory requirements

- VLM container: minimum 4 GB RAM
- OCR processing (OcrMyPDF): +300 MB peak
- VLM processing (PaddleOCR-VL): +500 MB peak

### Mroonga full-text search

VLM/OCR/Tika results are written to `attached_files.content` (plain text) for Mroonga indexing. Mroonga supports single-column `MATCH() AGAINST()` only — composite indexes do not work.

### Synchronous VLM dispatch

VLM processing currently uses `dispatchSync` (synchronous). The VLM extraction job runs inline during file upload processing, avoiding queue infrastructure issues. VLM requests typically complete in 1–2 seconds in GPU mode, so the performance impact is acceptable.

## Evidence

- Implementation: `app/Jobs/Ledger/ProcessVlmExtraction.php`, `app/Jobs/Ledger/OcrAndOptimizeFile.php`, `app/Services/VlmClientService.php`, `app/Console/Commands/Ledger/FinalizeAttachedFileProcessing.php`
- VLM service: `docker/paddle/unified_api.py`
- VLM cache regression tests: `docker/paddle/tests/`
- Laravel integration tests: `tests/Feature/Vlm/`
- CI workflow: `.github/workflows/vlm-cache-regression.yml`
- Model switching script: `bin/vlm-switch.sh`
- MLX-VLM launcher: `scripts/start-vlm-mlx.sh`
- Setup auto-configuration: `bin/setup.sh`
- Technology selection and benchmarks: [VLM-OCR technology selection](../architecture/vlm-ocr-technology-selection.md)
- Queue architecture: [Queue processing](../architecture/QueueProcessing.md)
