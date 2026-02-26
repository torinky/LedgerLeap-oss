#!/usr/bin/env python3
"""
Compare output from different VLM models
Tests: PaddleOCR, PaddleOCR-VL, Marker, MinerU

Usage:
    python compare_models.py [test_file]
    
Example:
    python compare_models.py test.pdf
    python compare_models.py invoice_simple.pdf
"""
import requests
import json
import sys
from pathlib import Path
from datetime import datetime
from typing import Dict, Any, Optional

# Base directories
SCRIPT_DIR = Path(__file__).parent
FIXTURES_DIR = SCRIPT_DIR.parent / "fixtures" / "files"
OUTPUT_DIR = SCRIPT_DIR / "storage" / "model_comparison"

# Ensure output directory exists
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

# VLM service endpoint
VLM_URL = "http://localhost:8001"

def test_model(file_path: Path, model_name: str) -> Optional[Dict[str, Any]]:
    """Test a single model with a file"""
    print(f"  Testing {model_name}...", end=" ", flush=True)
    
    try:
        with open(file_path, 'rb') as f:
            files = {'file': (file_path.name, f, 'application/octet-stream')}
            
            start_time = datetime.now()
            response = requests.post(
                f"{VLM_URL}/extract/structured",
                files=files,
                timeout=600  # Increased for Marker (can take 6+ minutes)
            )
            end_time = datetime.now()
            
            response.raise_for_status()
            result = response.json()
            
            # Add timing info
            result['_test_metadata'] = {
                'model_name': model_name,
                'processing_time_total': (end_time - start_time).total_seconds(),
                'timestamp': datetime.now().isoformat()
            }
            
            # Get summary stats
            structured = result.get('structured_data', {})
            text_blocks = len(structured.get('text_blocks', []))
            kv_pairs = len(structured.get('key_value_pairs', []))
            tables = len(structured.get('tables', []))
            
            print(f"✅ ({text_blocks} blocks, {kv_pairs} kv, {tables} tables)")
            return result
            
    except requests.exceptions.RequestException as e:
        print(f"❌ Error: {e}")
        return None
    except Exception as e:
        print(f"❌ Exception: {e}")
        return None

def compare_models(test_file: str):
    """Compare all models with a test file"""
    
    # Find test file
    file_path = FIXTURES_DIR / test_file
    if not file_path.exists():
        print(f"❌ Test file not found: {file_path}")
        return
    
    print(f"{'='*70}")
    print(f"Model Comparison Test")
    print(f"{'='*70}")
    print(f"File: {test_file}")
    print(f"Size: {file_path.stat().st_size / 1024:.1f} KB")
    print(f"Time: {datetime.now().isoformat()}")
    print()
    
    # Check health
    try:
        response = requests.get(f"{VLM_URL}/health", timeout=5)
        health = response.json()
        current_model = health.get('model', 'unknown')
        print(f"📡 Current VLM service: {current_model}")
        print()
    except Exception as e:
        print(f"⚠️  Warning: Could not check service health: {e}")
        print()
    
    # Test with current model
    print(f"Testing current model configuration...")
    result = test_model(file_path, current_model)
    
    if not result:
        print(f"❌ Test failed. Aborting.")
        return
    
    # Save results
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    base_name = file_path.stem
    
    # Save full JSON result
    output_file = OUTPUT_DIR / f"{current_model}_{base_name}_{timestamp}.json"
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(result, f, ensure_ascii=False, indent=2)
    print(f"\n💾 Full result saved to: {output_file}")
    
    # Save markdown output
    if 'markdown' in result:
        md_file = OUTPUT_DIR / f"{current_model}_{base_name}_{timestamp}.md"
        with open(md_file, 'w', encoding='utf-8') as f:
            f.write(result['markdown'])
        print(f"📄 Markdown saved to: {md_file}")
    
    # Generate comparison summary
    generate_summary(result, output_file.parent, base_name, timestamp)

def generate_summary(result: Dict, output_dir: Path, base_name: str, timestamp: str):
    """Generate a summary report"""
    
    metadata = result.get('_test_metadata', {})
    model_name = metadata.get('model_name', 'unknown')
    structured = result.get('structured_data', {})
    
    summary = {
        'test_info': {
            'file': base_name,
            'timestamp': timestamp,
            'model': model_name
        },
        'performance': {
            'processing_time_s': result.get('processing_time_s', 0),
            'total_time_s': metadata.get('processing_time_total', 0)
        },
        'extraction_results': {
            'pages': len(structured.get('pages', [])),
            'text_blocks': len(structured.get('text_blocks', [])),
            'key_value_pairs': len(structured.get('key_value_pairs', [])),
            'tables': len(structured.get('tables', []))
        },
        'quality_metrics': {
            'markdown_length': len(result.get('markdown', '')),
            'html_length': len(result.get('html', ''))
        }
    }
    
    # Add confidence stats if available
    text_blocks = structured.get('text_blocks', [])
    if text_blocks:
        confidences = [b.get('confidence', 0) for b in text_blocks]
        summary['quality_metrics']['confidence'] = {
            'average': sum(confidences) / len(confidences),
            'min': min(confidences),
            'max': max(confidences)
        }
    
    # Save summary
    summary_file = output_dir / f"summary_{model_name}_{base_name}_{timestamp}.json"
    with open(summary_file, 'w', encoding='utf-8') as f:
        json.dump(summary, f, ensure_ascii=False, indent=2)
    
    print(f"📊 Summary saved to: {summary_file}")
    
    # Print summary
    print(f"\n{'='*70}")
    print("SUMMARY")
    print('='*70)
    print(f"Model: {model_name}")
    print(f"Processing time: {summary['performance']['processing_time_s']:.2f}s")
    print(f"\nExtraction:")
    print(f"  Text blocks: {summary['extraction_results']['text_blocks']}")
    print(f"  Key-value pairs: {summary['extraction_results']['key_value_pairs']}")
    print(f"  Tables: {summary['extraction_results']['tables']}")
    
    if 'confidence' in summary['quality_metrics']:
        conf = summary['quality_metrics']['confidence']
        print(f"\nQuality:")
        print(f"  Avg confidence: {conf['average']:.3f}")
        print(f"  Min confidence: {conf['min']:.3f}")
        print(f"  Max confidence: {conf['max']:.3f}")
    
    print(f"\nOutput:")
    print(f"  Markdown: {summary['quality_metrics']['markdown_length']} chars")
    print(f"  HTML: {summary['quality_metrics']['html_length']} chars")

def list_available_files():
    """List available test files"""
    print("Available test files:")
    for file in sorted(FIXTURES_DIR.glob("*")):
        if file.suffix.lower() in ['.pdf', '.jpg', '.png', '.jpeg']:
            size_kb = file.stat().st_size / 1024
            print(f"  • {file.name:40s} ({size_kb:7.1f} KB)")

def main():
    if len(sys.argv) < 2:
        print("Usage: python compare_models.py <test_file>")
        print()
        list_available_files()
        sys.exit(1)
    
    test_file = sys.argv[1]
    compare_models(test_file)

if __name__ == "__main__":
    main()
