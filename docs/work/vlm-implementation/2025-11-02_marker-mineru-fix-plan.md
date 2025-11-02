# Marker/MinerU実装の問題と修正方針

**作成日:** 2025年11月2日  
**問題:** Marker/MinerUの出力が単一のMarkdownブロックになり、構造化データが失われている

## 問題の原因

### 現在の実装（CLI方式）

```python
# Marker
subprocess.run(["marker_single", file_path, "--output_dir", tmp_out_dir], ...)
# 出力: 1つのMarkdownファイル → 1つのtext_blockとして扱う

# MinerU  
subprocess.run(["mineru", "-p", file_path, "-o", tmp_out_dir], ...)
# 出力: 1つのMarkdownファイル → 1つのtext_blockとして扱う
```

**結果:**
- `text_blocks`: 1個のみ（Markdown全体）
- `key_value_pairs`: 0個
- `tables`: 0個
- 信頼度スコア: なし

### 期待される出力（Python API方式）

調査結果によると、Marker/MinerUは両方ともPython APIで構造化データを直接取得できます：

**Marker Python API:**
```python
from marker.convert import convert_single_pdf
from marker.models import load_all_models

model_lst = load_all_models()
full_text, images, out_metadata = convert_single_pdf(fname, model_lst)

# out_metadata には構造化情報が含まれる
# - ページ情報
# - ブロックタイプ（title, paragraph, table, image, formula）
# - テーブルのHTML
# - 画像の位置
# - 数式のLaTeX
```

**MinerU Python API:**
```python
from magic_pdf.pipe.UNIPipe import UNIPipe
from magic_pdf.rw.DiskReaderWriter import DiskReaderWriter

pipe = UNIPipe(pdf_bytes, model_list)
pipe.pipe_classify()
pipe.pipe_parse()

# 構造化出力
md_content = pipe.pipe_mk_markdown()
content_list = pipe.pipe_mk_uni_format()  # 詳細な構造化データ

# content_listには以下が含まれる
# - type: title, text, table, image, formula
# - bbox: 座標情報
# - text/html/latex: コンテンツ
```

## 修正方針

### Option 1: Python APIを使用（推奨）

**メリット:**
- 構造化データを直接取得
- 細かい制御が可能
- PaddleOCRと同等の詳細度

**デメリット:**
- コード変更が必要
- 依存関係の管理

### Option 2: CLIの出力を解析

**メリット:**
- 現在の実装を維持
- 外部プロセスの隔離

**デメリット:**
- Markdownからの再構造化が必要
- 情報損失の可能性
- 複雑な解析ロジック

## 推奨実装

### Phase 1: Markdownの解析による改善（短期）

現在のCLI方式を維持しつつ、Markdown出力を解析して構造化データを抽出：

```python
def parse_markdown_structure(markdown_text: str) -> Dict[str, Any]:
    """
    Markdownテキストから構造化データを抽出
    
    検出するパターン:
    - ヘッダー (# Title)
    - テーブル (| col1 | col2 |)
    - リスト (- item, 1. item)
    - Key-Value (key: value)
    - 画像参照 (![](path))
    """
    import re
    
    text_blocks = []
    tables = []
    key_value_pairs = []
    
    lines = markdown_text.split('\n')
    current_block = []
    block_type = "text"
    
    for idx, line in enumerate(lines):
        # ヘッダー検出
        if line.startswith('#'):
            if current_block:
                text_blocks.append({
                    "type": block_type,
                    "content": '\n'.join(current_block),
                    "line_index": idx
                })
                current_block = []
            
            level = len(re.match(r'^#+', line).group())
            text_blocks.append({
                "type": f"header_{level}",
                "content": line.lstrip('#').strip(),
                "line_index": idx
            })
            continue
        
        # テーブル検出
        if '|' in line and not line.startswith('!['):
            # テーブル行を収集
            # ...
            
        # Key-Value検出
        if ':' in line:
            parts = line.split(':', 1)
            if len(parts) == 2:
                key, value = parts
                key = key.strip()
                value = value.strip()
                if key and value and len(key) < 50:  # 妥当な長さ
                    key_value_pairs.append({
                        "key": key,
                        "value": value,
                        "line_index": idx
                    })
        
        # 通常のテキスト
        if line.strip():
            current_block.append(line)
    
    return {
        "text_blocks": text_blocks,
        "tables": tables,
        "key_value_pairs": key_value_pairs
    }
```

### Phase 2: Python API統合（中期）

Marker/MinerUをPython APIとして直接呼び出し：

**Marker統合:**
```python
def process_with_marker_api(file_path: str) -> Dict[str, Any]:
    """Process document with Marker Python API"""
    from marker.convert import convert_single_pdf
    from marker.models import load_all_models
    
    # モデルロード（初回のみ）
    if not hasattr(process_with_marker_api, 'models'):
        process_with_marker_api.models = load_all_models()
    
    # PDF変換
    full_text, images, out_meta = convert_single_pdf(
        file_path, 
        process_with_marker_api.models
    )
    
    # 構造化データ抽出
    text_blocks = []
    tables = []
    key_value_pairs = []
    
    for block in out_meta.get('blocks', []):
        block_type = block.get('type', 'text')
        
        if block_type == 'table':
            tables.append({
                "html": block.get('html', ''),
                "caption": block.get('caption', ''),
                "bbox": block.get('bbox', [])
            })
        elif block_type in ['title', 'paragraph', 'list']:
            text_blocks.append({
                "type": block_type,
                "content": block.get('text', ''),
                "bbox": block.get('bbox', [])
            })
    
    return {
        "html": f"<html><body>{full_text}</body></html>",
        "markdown": full_text,
        "structured_data": {
            "pages": out_meta.get('pages', []),
            "text_blocks": text_blocks,
            "tables": tables,
            "key_value_pairs": key_value_pairs
        }
    }
```

**MinerU統合:**
```python
def process_with_mineru_api(file_path: str) -> Dict[str, Any]:
    """Process document with MinerU Python API"""
    from magic_pdf.pipe.UNIPipe import UNIPipe
    from magic_pdf.rw.DiskReaderWriter import DiskReaderWriter
    import json
    
    # PDFバイト読み込み
    with open(file_path, 'rb') as f:
        pdf_bytes = f.read()
    
    # MinerUパイプライン
    pipe = UNIPipe(pdf_bytes, {
        "device_mode": "cpu",
        "formula-config": {"enable": True},
        "table-config": {"enable": True}
    })
    
    # 処理実行
    pipe.pipe_classify()
    pipe.pipe_parse()
    
    # 構造化データ取得
    content_list = pipe.pipe_mk_uni_format()
    markdown = pipe.pipe_mk_markdown()
    
    # データ変換
    text_blocks = []
    tables = []
    
    for item in content_list:
        item_type = item.get('type', 'text')
        
        if item_type == 'table':
            tables.append({
                "html": item.get('html', ''),
                "caption": item.get('caption', ''),
                "bbox": item.get('bbox', [])
            })
        elif item_type in ['title', 'text', 'paragraph']:
            text_blocks.append({
                "type": item_type,
                "content": item.get('text', ''),
                "bbox": item.get('bbox', []),
                "confidence": item.get('confidence', 0.0)
            })
    
    return {
        "html": f"<html><body><pre>{markdown}</pre></body></html>",
        "markdown": markdown,
        "structured_data": {
            "pages": content_list,
            "text_blocks": text_blocks,
            "tables": tables,
            "key_value_pairs": []
        }
    }
```

## 実装スケジュール

### 即座に実施（今回）

1. ✅ 問題の調査と原因特定
2. ✅ 最新ドキュメントの調査
3. 📝 修正方針の文書化

### 次のステップ（優先度順）

1. **Phase 1: Markdown解析の実装**（1-2時間）
   - `parse_markdown_structure()`関数の実装
   - 既存のCLI方式を維持
   - テーブル・Key-Value・ヘッダーの検出

2. **Phase 2: Python API統合**（4-6時間）
   - Marker Python APIの統合
   - MinerU Python APIの統合
   - 依存関係の更新
   - テストとベンチマーク

3. **Phase 3: 性能最適化**（2-3時間）
   - モデルの事前ロード
   - バッチ処理対応
   - キャッシュ戦略

## 参考資料

- [Marker GitHub](https://github.com/datalab-to/marker)
- [Marker API Documentation](https://documentation.datalab.to/docs/recipes/structured-extraction/api-overview)
- [MinerU Documentation](https://opendatalab.github.io/MinerU/)
- [MinerU API Usage](https://deepwiki.com/loorr-fork/MinerU/3.2-api-usage)

---

**作成日:** 2025年11月2日  
**次のアクション:** Phase 1の実装（Markdown解析）
