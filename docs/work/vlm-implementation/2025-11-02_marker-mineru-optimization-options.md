# Marker/MinerU 最適化オプション調査結果

**調査日:** 2025年11月2日  
**目的:** Marker/MinerUの処理速度を改善するための最新オプションを調査

---

## 🚀 Marker 最適化オプション

### 重要な発見：OCR無効化オプション

**`--disable_ocr`**フラグが最も効果的な高速化手段です。

```bash
marker_single input.pdf --output_dir ./output --disable_ocr
```

**効果:**
- ✅ **大幅な速度向上**（テキスト埋め込みPDFの場合）
- ✅ OCR処理をスキップし、埋め込みテキストのみ使用
- ✅ CPU負荷を大幅削減

**使用条件:**
- PDFに選択可能なテキストが埋め込まれている場合
- スキャンPDFや画像ベースPDFには不向き

### その他の最適化オプション

#### 1. ページ範囲指定
```bash
marker_single input.pdf --output_dir ./output \
  --start_page 0 \
  --end_page 10
```
- 必要なページのみ処理
- 大きなPDFの一部だけを高速処理

#### 2. バッチサイズとワーカー数
```bash
# 複数ファイルの並列処理（marker コマンド）
marker input_folder --output_dir ./output \
  --workers 4 \
  --batch_size 2
```

**推奨設定:**
- `--workers`: CPUコア数と同じ（例: 4コアなら4）
- `--batch_size`: メモリに応じて1-4

#### 3. チャンク処理（開発中機能）
```bash
marker input_folder --output_dir ./output \
  --workers 4 \
  --chunk_size 100  # 100ページごとに分割
```
- メモリ使用量を制限
- 大きなPDFでのOOM回避

### Python API 使用例

```python
from marker.config.parser import ConfigParser
from marker.models import create_model_dict
from marker.converters.pdf import PDFConverter

# OCR無効化設定
models = create_model_dict()
config_parser = ConfigParser({
    'disable_ocr': True,  # 高速化の鍵
    'lowres_image_dpi': 72,
    'highres_image_dpi': 300,
})

converter = PDFConverter(
    config=config_parser.generate_config_dict(),
    artifact_dict=models,
    processor_list=config_parser.get_processors(),
    renderer=config_parser.get_renderer(),
)
```

---

## 🔧 MinerU 最適化オプション

### 環境変数による最適化

```bash
export MINERU_DEVICE_MODE=cpu
export OMP_NUM_THREADS=8        # CPUコア数
export MKL_NUM_THREADS=8
export OPENBLAS_NUM_THREADS=8
export MINERU_CPU_OPT_LEVEL=high
export MINERU_MEMORY_POOL_SIZE=512MB

mineru -p ./input.pdf -o ./output
```

### magic-pdf.json 設定

```json
{
  "device-mode": "cpu",
  "models-dir": "/absolute/path/to/models",
  
  "layout-config": {
    "model": "layoutlmv3"
  },
  
  "formula-config": {
    "mfd_model": "yolo_v8_mfd",
    "mfr_model": "unimernet_small",
    "enable": true
  },
  
  "table-config": {
    "model": "rapid_table",
    "sub_model": "slanet_plus",
    "enable": true,
    "max_time": 400
  },
  
  "performance": {
    "batch_size": 4,
    "max_workers": 8,
    "memory_pool_size": "1024MB"
  }
}
```

### システム最適化

**Linux /etc/sysctl.conf:**
```conf
vm.swappiness=10
vm.vfs_cache_pressure=50
```

**CPU機能確認:**
```python
import psutil
import cpuinfo

info = cpuinfo.get_cpu_info()
print('AVX:', 'avx' in info.get('flags', []))
print('Physical cores:', psutil.cpu_count(logical=False))
print('Total memory GB:', psutil.virtual_memory().total / (1024 ** 3))
```

### 軽量モデルの使用

```bash
# 軽量モデル（6倍高速）
mineru -p ./input.pdf -o ./output --lang ch_lite

# 標準モデル（精度優先）
mineru -p ./input.pdf -o ./output --lang ch
```

**バージョン注意:**
- V1.3.6以前: `ch_lite`を明示的に指定すべき
- V1.3.7+: 自動で最適モデル選択

### バッチ処理最適化

```bash
MINERU_DEVICE_MODE=cpu \
OMP_NUM_THREADS=8 \
MKL_NUM_THREADS=8 \
mineru -p ./papers -o ./output \
  -b pipeline \
  --batch-size 4 \
  --max-workers 8 \
  --memory-pool-size 1024
```

### INT8量子化（メモリ削減）

```python
from mineru import MinerU

mineru = MinerU(
    backend='vlm-transformers',
    device='cpu',
    quantization='int8',          # メモリ削減
    inference_precision='fp32',
    use_bettertransformer=True,
    max_memory_usage='2GB',
    batch_size=1,
    num_workers=2,
)
```

### 実測結果（最適化前後）

| 指標 | 最適化前 | 最適化後 | 改善率 |
|------|----------|----------|--------|
| 処理時間（15ページ） | 45-60秒 | 12-18秒 | **72%削減** |
| メモリ使用量 | 2.1GB | 890MB | **58%削減** |

---

## 📊 推奨設定の実装

### Markerの最適化実装

```python
def process_with_marker_optimized(file_path: str) -> Dict[str, Any]:
    """Process with optimized Marker settings"""
    
    # OCR無効化（テキストPDF専用）
    os.environ["TORCH_DEVICE"] = "cpu"
    
    cmd = [
        "marker_single", file_path,
        "--output_dir", tmp_out_dir,
        "--disable_ocr",  # 🔥 高速化の鍵
        "--lowres_image_dpi", "72",
        "--highres_image_dpi", "300",
    ]
    
    # バッチサイズ最適化
    cmd.extend([
        "--layout_batch_size", "1",
        "--recognition_batch_size", "1",
    ])
    
    subprocess.run(cmd, check=True, timeout=600)
```

### MinerUの最適化実装

```python
def process_with_mineru_optimized(file_path: str) -> Dict[str, Any]:
    """Process with optimized MinerU settings"""
    
    # 環境変数設定
    os.environ.update({
        "MINERU_DEVICE_MODE": "cpu",
        "OMP_NUM_THREADS": "8",
        "MKL_NUM_THREADS": "8",
        "OPENBLAS_NUM_THREADS": "8",
        "MINERU_CPU_OPT_LEVEL": "high",
        "MINERU_MEMORY_POOL_SIZE": "512MB",
    })
    
    cmd = [
        "mineru",
        "-p", file_path,
        "-o", tmp_out_dir,
        "--lang", "ch_lite",  # 軽量モデル
    ]
    
    subprocess.run(cmd, check=True, timeout=300)
```

---

## 🎯 期待される改善効果

### Marker

| 設定 | 現在 | 最適化後（予測） | 改善率 |
|------|------|------------------|--------|
| 処理時間 | 490秒 | **60-120秒** | **75-88%削減** |
| テキストPDF | 490秒 | **30-60秒** | **88-94%削減** |
| メモリ使用 | 不明 | 大幅削減 | - |

**最適化の鍵:**
- `--disable_ocr`: テキストPDFで**最大90%高速化**
- DPI削減: 適度な品質維持で高速化
- バッチサイズ調整: メモリとCPU使用の最適化

### MinerU

| 設定 | 現在 | 最適化後（予測） | 改善率 |
|------|------|------------------|--------|
| 処理時間 | 28.73秒 | **8-12秒** | **58-72%削減** |
| メモリ使用 | 不明 | 約50%削減 | - |

**最適化の鍵:**
- 環境変数設定: スレッド数最適化
- 軽量モデル: 6倍高速化
- バッチ処理: 5-14倍高速化（複数ファイル）

---

## 📋 実装チェックリスト

### Marker最適化

- [ ] `--disable_ocr`オプション追加
- [ ] ページ範囲指定機能
- [ ] チャンク処理対応（開発中機能）
- [ ] ワーカー数設定（複数ファイル用）
- [ ] テキストPDF検出ロジック

### MinerU最適化

- [ ] 環境変数設定（OMP/MKL/OPENBLAS）
- [ ] magic-pdf.json最適化
- [ ] 軽量モデル（ch_lite）使用
- [ ] バッチサイズ調整
- [ ] INT8量子化対応

### 共通最適化

- [ ] システムスレッド数取得（`os.cpu_count()`）
- [ ] メモリ監視機能
- [ ] 処理時間ロギング
- [ ] エラーハンドリング改善

---

## 🔬 次のステップ

### テスト計画

1. **Marker `--disable_ocr` テスト**
   - invoice_simple.pdf（テキストPDF）で測定
   - 期待: 490秒 → 60秒以下

2. **MinerU環境変数最適化テスト**
   - 同じPDFで測定
   - 期待: 28.73秒 → 10秒以下

3. **比較分析**
   - 最適化前後の詳細比較
   - メモリ使用量測定
   - 品質チェック（出力の劣化確認）

### 実装優先度

**高優先度:**
1. Marker `--disable_ocr` 実装
2. MinerU環境変数設定

**中優先度:**
3. バッチサイズ調整
4. 軽量モデル対応

**低優先度:**
5. チャンク処理
6. INT8量子化

---

## 📚 参考資料

### Marker
- [Marker GitHub - Disable OCR](https://github.com/datalab-to/marker/issues/639)
- [DeepWiki - Single File Conversion](https://deepwiki.com/VikParuchuri/marker/4.1-single-file-conversion)
- [Command Line Interface](https://deepwiki.com/VikParuchuri/marker/4.1-command-line-interface)

### MinerU
- [CSDN - CPU Mode Optimization](https://blog.csdn.net/gitblog_00090/article/details/151112993)
- [GitCode - Magic-PDF Configuration](https://blog.gitcode.com/1be19a1367a6698868df3964ed996d25.html)
- [GitHub - MinerU CPU Docker](https://github.com/lavuy/MinerU-add-cpu-docker)

---

**作成日:** 2025年11月2日  
**次のアクション:** `--disable_ocr`と環境変数最適化の実装とテスト
