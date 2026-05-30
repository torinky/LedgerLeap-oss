#!/usr/bin/env python3
"""
Compare results from different models side-by-side

Usage:
    python compare_results.py <pattern>
    
Example:
    python compare_results.py test.pdf
    python compare_results.py invoice_simple
"""
import json
import sys
from pathlib import Path
from typing import List, Dict, Any

SCRIPT_DIR = Path(__file__).parent
RESULTS_DIR = SCRIPT_DIR / "storage" / "model_comparison"

def load_results(pattern: str) -> List[Dict[str, Any]]:
    """Load all result files matching the pattern"""
    results = []
    
    if not RESULTS_DIR.exists():
        print(f"❌ Results directory not found: {RESULTS_DIR}")
        return results
    
    # Find matching result files
    for file_path in RESULTS_DIR.glob(f"*{pattern}*.json"):
        # Skip summary files
        if file_path.name.startswith("summary_"):
            continue
            
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
                data['_file_path'] = str(file_path)
                data['_file_name'] = file_path.name
                results.append(data)
        except Exception as e:
            print(f"⚠️  Error loading {file_path.name}: {e}")
    
    return results

def compare_extraction(results: List[Dict[str, Any]]):
    """Compare extraction results"""
    print(f"\n{'='*80}")
    print("EXTRACTION COMPARISON")
    print('='*80)
    
    headers = ["Model", "Text Blocks", "Key-Value", "Tables", "Time (s)"]
    print(f"{headers[0]:15s} {headers[1]:>12s} {headers[2]:>12s} {headers[3]:>10s} {headers[4]:>10s}")
    print("-" * 80)
    
    for result in results:
        metadata = result.get('_test_metadata', {})
        model = metadata.get('model_name', 'unknown')
        
        structured = result.get('structured_data', {})
        text_blocks = len(structured.get('text_blocks', []))
        kv_pairs = len(structured.get('key_value_pairs', []))
        tables = len(structured.get('tables', []))
        time_s = result.get('processing_time_s', 0)
        
        print(f"{model:15s} {text_blocks:12d} {kv_pairs:12d} {tables:10d} {time_s:10.2f}")

def compare_quality(results: List[Dict[str, Any]]):
    """Compare quality metrics"""
    print(f"\n{'='*80}")
    print("QUALITY COMPARISON")
    print('='*80)
    
    headers = ["Model", "Avg Conf", "Min Conf", "Max Conf", "MD Length"]
    print(f"{headers[0]:15s} {headers[1]:>10s} {headers[2]:>10s} {headers[3]:>10s} {headers[4]:>12s}")
    print("-" * 80)
    
    for result in results:
        metadata = result.get('_test_metadata', {})
        model = metadata.get('model_name', 'unknown')
        
        structured = result.get('structured_data', {})
        text_blocks = structured.get('text_blocks', [])
        
        if text_blocks:
            confidences = [b.get('confidence', 0) for b in text_blocks]
            avg_conf = sum(confidences) / len(confidences)
            min_conf = min(confidences)
            max_conf = max(confidences)
        else:
            avg_conf = min_conf = max_conf = 0.0
        
        md_length = len(result.get('markdown', ''))
        
        print(f"{model:15s} {avg_conf:10.3f} {min_conf:10.3f} {max_conf:10.3f} {md_length:12d}")

def compare_key_values(results: List[Dict[str, Any]]):
    """Compare key-value pairs detected"""
    print(f"\n{'='*80}")
    print("KEY-VALUE PAIRS COMPARISON")
    print('='*80)
    
    for result in results:
        metadata = result.get('_test_metadata', {})
        model = metadata.get('model_name', 'unknown')
        
        structured = result.get('structured_data', {})
        kv_pairs = structured.get('key_value_pairs', [])
        
        print(f"\n{model} ({len(kv_pairs)} pairs):")
        print("-" * 60)
        
        if kv_pairs:
            for i, kv in enumerate(kv_pairs[:10], 1):
                key = kv.get('key', '')
                value = kv.get('value', '')[:50]
                conf = kv.get('confidence', 0)
                print(f"  {i:2d}. [{conf:.3f}] {key}: {value}")
            
            if len(kv_pairs) > 10:
                print(f"  ... and {len(kv_pairs) - 10} more")
        else:
            print("  (none detected)")

def show_markdown_comparison(results: List[Dict[str, Any]]):
    """Show markdown output comparison"""
    print(f"\n{'='*80}")
    print("MARKDOWN OUTPUT COMPARISON (first 500 chars)")
    print('='*80)
    
    for result in results:
        metadata = result.get('_test_metadata', {})
        model = metadata.get('model_name', 'unknown')
        
        markdown = result.get('markdown', '')
        preview = markdown[:500] if markdown else "(empty)"
        
        print(f"\n{model}:")
        print("-" * 60)
        print(preview)
        if len(markdown) > 500:
            print(f"... ({len(markdown) - 500} more chars)")

def list_available_results():
    """List available result files"""
    print("Available result files:")
    
    if not RESULTS_DIR.exists():
        print(f"  Results directory not found: {RESULTS_DIR}")
        return
    
    result_files = sorted(RESULTS_DIR.glob("*.json"))
    if not result_files:
        print("  (no results yet)")
        return
    
    # Group by base name
    groups = {}
    for file_path in result_files:
        if file_path.name.startswith("summary_"):
            continue
        
        # Extract base name (remove model prefix and timestamp suffix)
        parts = file_path.stem.split('_')
        if len(parts) >= 3:
            model = parts[0]
            base = '_'.join(parts[1:-2])
            timestamp = '_'.join(parts[-2:])
            
            if base not in groups:
                groups[base] = []
            groups[base].append((model, timestamp, file_path))
    
    for base, files in sorted(groups.items()):
        print(f"\n  {base}:")
        for model, timestamp, file_path in files:
            size_kb = file_path.stat().st_size / 1024
            print(f"    • {model:12s} {timestamp} ({size_kb:6.1f} KB)")

def main():
    if len(sys.argv) < 2:
        print("Usage: python compare_results.py <pattern>")
        print()
        list_available_results()
        sys.exit(1)
    
    pattern = sys.argv[1]
    results = load_results(pattern)
    
    if not results:
        print(f"❌ No results found for pattern: {pattern}")
        print()
        list_available_results()
        sys.exit(1)
    
    print(f"Found {len(results)} result file(s) matching '{pattern}'")
    
    # Sort by model name for consistent output
    results.sort(key=lambda r: r.get('_test_metadata', {}).get('model_name', ''))
    
    # Show comparisons
    compare_extraction(results)
    compare_quality(results)
    compare_key_values(results)
    show_markdown_comparison(results)
    
    # Show file locations
    print(f"\n{'='*80}")
    print("RESULT FILES")
    print('='*80)
    for result in results:
        print(f"  • {result['_file_name']}")

if __name__ == "__main__":
    main()
