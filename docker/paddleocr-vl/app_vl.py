# docker/paddleocr-vl/app_vl.py
"""
PaddleOCR-VL 0.9B Test API
CPU実行可否検証用の最小実装
"""
from fastapi import FastAPI, File, UploadFile, HTTPException
import logging
import time
from pathlib import Path
import tempfile
import os

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="PaddleOCR-VL Test API", version="0.1.0")

pipeline = None
initialization_error = None

@app.on_event("startup")
async def startup_event():
    global pipeline, initialization_error
    logger.info("=" * 80)
    device = os.environ.get("PADDLEOCR_DEVICE", "gpu")  # Default to GPU (CPU not officially supported as of 2025-11)
    logger.info(f'Attempting to initialize PaddleOCR-VL on {device}...')
    logger.info("NOTE: PaddleOCR-VL requires GPU and CUDA. CPU inference is not officially supported.")
    logger.info("=" * 80)
    
    try:
        from paddleocr import PaddleOCRVL
        
        logger.info("PaddleOCRVL module imported successfully")
        
        # GPU版での初期化（全機能有効化）
        logger.info(f'Initializing PaddleOCRVL with device={device}...')
        pipeline = PaddleOCRVL(
            device=device,
            use_doc_orientation_classify=True,  # 文書向き分類
            use_layout_detection=True,           # レイアウト検出
            use_doc_unwarping=True,              # 文書歪み補正
            use_chart_recognition=True,          # チャート認識
            format_block_content=True            # ブロックコンテンツのフォーマット
        )
        
        logger.info("=" * 80)
        logger.info(f"✅ SUCCESS! PaddleOCR-VL initialized on {device}!")
        logger.info("=" * 80)
        
    except Exception as e:
        error_msg = str(e)
        initialization_error = error_msg
        logger.error("=" * 80)
        logger.error(f"❌ FAILED to initialize PaddleOCR-VL")
        logger.error(f"Error: {error_msg}")
        logger.error("=" * 80)
        
        # エラーの種類を判定
        if "safetensors" in error_msg.lower():
            logger.error("→ safetensors互換性問題")
        elif "gpu" in error_msg.lower() or "cuda" in error_msg.lower():
            logger.error("→ GPU/CUDA関連エラー（CPU非対応の可能性）")
        elif "memory" in error_msg.lower():
            logger.error("→ メモリ不足")
        else:
            logger.error("→ その他のエラー")
        
        pipeline = None

@app.get("/health")
async def health_check():
    """ヘルスチェックエンドポイント"""
    if pipeline is None:
        raise HTTPException(
            status_code=503,
            detail={"status": "failed", "model": "PaddleOCR-VL-0.9B", "error": initialization_error or "Unknown error", "message": "PaddleOCR-VL is not available"}
        )
    
    device = os.environ.get("PADDLEOCR_DEVICE", "gpu")
    return {
        "status": "healthy",
        "model": "PaddleOCR-VL-0.9B",
        "device": device,
        "message": "PaddleOCR-VL is ready"
    }

@app.post("/extract/structured")
async def extract_structured(file: UploadFile = File(...)):
    """
    構造化テキスト抽出エンドポイント（テスト版）
    """
    if pipeline is None:
        raise HTTPException(
            status_code=503,
            detail=f"PaddleOCR-VL is not available. Error: {initialization_error}"
        )
    
    tmp_path = None
    try:
        # ファイルを一時保存
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name

        start_time = time.time()
        print(f"[DEBUG] Processing file: {file.filename}", flush=True)
        logger.info(f"Processing file: {file.filename}")
        
        # PaddleOCR-VLで処理
        output = pipeline.predict(tmp_path)
        print(f"[DEBUG] Pipeline output type: {type(output)}", flush=True)
        print(f"[DEBUG] Pipeline output length: {len(output) if hasattr(output, '__len__') else 'N/A'}", flush=True)
        print(f"[DEBUG] Pipeline output: {output}", flush=True)
        logger.info(f"📊 Pipeline output type: {type(output)}")
        logger.info(f"📊 Pipeline output length: {len(output) if hasattr(output, '__len__') else 'N/A'}")
        
        # 結果を処理 - 構造化データとして抽出
        structured_data = {
            "pages": [],
            "tables": [],
            "key_value_pairs": [],
            "text_blocks": [],
            "layout_info": []
        }
        
        for idx, res in enumerate(output):
            if not isinstance(res, dict):
                continue
            
            page_data = {
                "page_index": idx,
                "elements": []
            }
            
            # レイアウト検出結果
            if 'layout_det_res' in res and res['layout_det_res']:
                layout_res = res['layout_det_res']
                if 'boxes' in layout_res:
                    for box in layout_res['boxes']:
                        layout_info = {
                            "type": box.get('label', 'unknown'),
                            "bbox": [float(c) for c in box.get('coordinate', [])],
                            "confidence": float(box.get('score', 0.0))
                        }
                        structured_data["layout_info"].append(layout_info)
                        page_data["elements"].append(layout_info)
            
            # 表検出結果
            if 'table_res_list' in res and res['table_res_list']:
                for table_idx, table in enumerate(res['table_res_list']):
                    table_data = {
                        "page": idx,
                        "table_index": table_idx,
                        "bbox": table.get('bbox', []) if isinstance(table, dict) else [],
                        "cells": [],
                        "structure": None
                    }
                    
                    # 表の構造を抽出
                    if isinstance(table, dict):
                        if 'html' in table:
                            table_data["html"] = table['html']
                        if 'cells' in table:
                            table_data["cells"] = table['cells']
                        if 'structure' in table:
                            table_data["structure"] = table['structure']
                    
                    structured_data["tables"].append(table_data)
                    page_data["elements"].append({
                        "type": "table",
                        "data": table_data
                    })
            
            # テキストブロック・キーバリュー抽出
            if 'parsing_res_list' in res:
                parsing_results = res['parsing_res_list']
                logger.info(f"📊 Found {len(parsing_results)} parsing results in page {idx}")
                
                for item in parsing_results:
                    # itemからデータを抽出
                    item_str = str(item)
                    
                    # ラベルとbboxを抽出
                    label = None
                    bbox = []
                    content = ""
                    
                    if 'label:' in item_str:
                        label_start = item_str.find('label:') + 6
                        label_end = item_str.find('\n', label_start)
                        label = item_str[label_start:label_end].strip()
                    
                    if 'bbox:' in item_str:
                        bbox_start = item_str.find('bbox:') + 5
                        bbox_end = item_str.find('\n', bbox_start)
                        bbox_str = item_str[bbox_start:bbox_end].strip()
                        try:
                            bbox = eval(bbox_str)  # [x1, y1, x2, y2]
                        except:
                            bbox = []
                    
                    if 'content:' in item_str:
                        content_start = item_str.find('content:') + 8
                        content_end = item_str.find('\n#################', content_start)
                        if content_end == -1:
                            content_end = len(item_str)
                        content = item_str[content_start:content_end].strip()
                    
                    if content:
                        text_block = {
                            "type": label or "text",
                            "bbox": bbox,
                            "content": content
                        }
                        structured_data["text_blocks"].append(text_block)
                        page_data["elements"].append(text_block)
                        
                        # キーバリューペアの検出（コロンで分割できる行）
                        lines = content.split('\n')
                        for line in lines:
                            if ':' in line or '：' in line:
                                parts = line.replace('：', ':').split(':', 1)
                                if len(parts) == 2:
                                    key = parts[0].strip()
                                    value = parts[1].strip()
                                    if key and value:
                                        structured_data["key_value_pairs"].append({
                                            "key": key,
                                            "value": value,
                                            "page": idx,
                                            "bbox": bbox
                                        })
                        
                        logger.info(f"✅ Extracted {label}: {content[:50]}...")
            
            structured_data["pages"].append(page_data)
        
        # マークダウンとHTML生成
        markdown_parts = []
        html_parts = []
        
        # テキストブロック
        for block in structured_data["text_blocks"]:
            markdown_parts.append(f"## {block['type']}\n\n{block['content']}\n")
            html_parts.append(f"<div class='text-block' data-type='{block['type']}'><p>{block['content']}</p></div>")
        
        # 表
        for table in structured_data["tables"]:
            if 'html' in table:
                markdown_parts.append(f"\n### Table {table['table_index']}\n\n")
                html_parts.append(table['html'])
        
        # キーバリューペア
        if structured_data["key_value_pairs"]:
            markdown_parts.append("\n## Key-Value Pairs\n")
            html_parts.append("<div class='key-value-section'><h2>Key-Value Pairs</h2><dl>")
            for kv in structured_data["key_value_pairs"]:
                markdown_parts.append(f"- **{kv['key']}**: {kv['value']}")
                html_parts.append(f"<dt>{kv['key']}</dt><dd>{kv['value']}</dd>")
            html_parts.append("</dl></div>")
        
        markdown_text = "\n".join(markdown_parts) if markdown_parts else ""
        html_output = "<html><body>\n" + "\n".join(html_parts) + "\n</body></html>" if html_parts else "<html><body>\n\n</body></html>"
        
        logger.info(f"📊 Extracted: {len(structured_data['text_blocks'])} text blocks, "
                   f"{len(structured_data['tables'])} tables, "
                   f"{len(structured_data['key_value_pairs'])} key-value pairs")
        
        processing_time_s = time.time() - start_time
        logger.info(f"✅ Processing completed in {processing_time_s:.2f}s")
        
        return {
            "success": True,
            "html": html_output,
            "markdown": markdown_text,
            "structured_data": structured_data,  # 構造化データを追加
            "processing_time_s": processing_time_s,
            "model": "PaddleOCR-VL-0.9B",
            "device": os.environ.get("PADDLEOCR_DEVICE", "gpu")
        }
        
    except Exception as e:
        logger.error(f"Error during processing: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)

@app.get("/")
async def root():
    """ルートエンドポイント"""
    return {
        "name": "PaddleOCR-VL Test API",
        "version": "0.1.0",
        "status": "available" if pipeline else "unavailable",
        "endpoints": {
            "health": "/health",
            "extract": "/extract/structured",
            "docs": "/docs"
        }
    }
