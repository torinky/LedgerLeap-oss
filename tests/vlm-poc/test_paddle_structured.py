#!/usr/bin/env python3
"""
Test script for PaddleOCR structured data extraction
Tests various files in tests/fixtures/files/
"""
import requests
import json
import os
from pathlib import Path
from typing import Dict, Any

PADDLE_URL = os.environ.get("PADDLE_OCR_URL", "http://localhost:8765")

def test_file(file_path: Path) -> Dict[str, Any]:
    """Test OCR extraction on a file"""
    print(f"\n{'='*80}")
    print(f"Testing: {file_path.name}")
    print('='*80)
    
    with open(file_path, 'rb') as f:
        files = {'file': (file_path.name, f, 'application/octet-stream')}
        
        try:
            response = requests.post(
                f"{PADDLE_URL}/extract/structured",
                files=files,
                timeout=120
            )
            response.raise_for_status()
            result = response.json()
            
            print(f"✅ Success!")
            print(f"   Model: {result.get('model', 'unknown')}")
            print(f"   Device: {result.get('device', 'unknown')}")
            print(f"   Processing time: {result.get('processing_time_s', 0):.2f}s")
            
            # Structured data analysis
            structured = result.get('structured_data', {})
            print(f"\n📊 Structured Data:")
            print(f"   Pages: {len(structured.get('pages', []))}")
            print(f"   Text blocks: {len(structured.get('text_blocks', []))}")
            print(f"   Key-value pairs: {len(structured.get('key_value_pairs', []))}")
            print(f"   Tables: {len(structured.get('tables', []))}")
            
            # Show first few text blocks
            text_blocks = structured.get('text_blocks', [])
            if text_blocks:
                print(f"\n📝 First 3 text blocks:")
                for i, block in enumerate(text_blocks[:3], 1):
                    content = block.get('content', '')[:80]
                    confidence = block.get('confidence', 0)
                    print(f"   {i}. [{confidence:.2f}] {content}")
            
            # Show key-value pairs
            kv_pairs = structured.get('key_value_pairs', [])
            if kv_pairs:
                print(f"\n🔑 Key-Value Pairs:")
                for kv in kv_pairs[:5]:
                    key = kv.get('key', '')
                    value = kv.get('value', '')[:50]
                    confidence = kv.get('confidence', 0)
                    print(f"   [{confidence:.2f}] {key}: {value}")
            
            # Show markdown preview
            markdown = result.get('markdown', '')
            if markdown:
                preview = markdown[:200]
                print(f"\n📄 Markdown preview:")
                print(f"   {preview}...")
            
            return result
            
        except requests.exceptions.RequestException as e:
            print(f"❌ Error: {e}")
            return None

def main():
    """Run tests on all fixture files"""
    
    # Check health
    try:
        response = requests.get(f"{PADDLE_URL}/health", timeout=5)
        health = response.json()
        print(f"🏥 Service Health Check:")
        print(f"   Status: {health.get('status', 'unknown')}")
        print(f"   Model: {health.get('model', 'unknown')}")
        print(f"   Device: {health.get('device', 'unknown')}")
    except Exception as e:
        print(f"❌ Service not available: {e}")
        return
    
    # Test files
    fixtures_dir = Path(__file__).parent.parent / "fixtures" / "files"
    
    test_files = [
        "invoice_simple.pdf",
        "receipt_01.jpg",
        "hand_writing_01.png",
        "meeting_notes.pdf",
    ]
    
    results = {}
    for file_name in test_files:
        file_path = fixtures_dir / file_name
        if file_path.exists():
            result = test_file(file_path)
            results[file_name] = result
        else:
            print(f"⚠️  File not found: {file_path}")
    
    # Summary
    print(f"\n{'='*80}")
    print("📊 SUMMARY")
    print('='*80)
    
    successful = sum(1 for r in results.values() if r is not None)
    total = len(results)
    print(f"Successful: {successful}/{total}")
    
    # Save detailed results
    output_file = Path(__file__).parent / "paddle_test_results.json"
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print(f"\n💾 Detailed results saved to: {output_file}")

if __name__ == "__main__":
    main()
