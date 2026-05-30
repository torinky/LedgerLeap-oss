#!/bin/bash

set -e

echo "=== VLM Basic Function Test ==="
echo ""

BASE_DIR=$(pwd)
TEST_DIR="storage/test/vlm-poc"
RESULT_DIR="storage/test/vlm-poc/results"
mkdir -p "$RESULT_DIR"

# 結果ファイル
RESULT_FILE="$RESULT_DIR/test_results_$(date +%Y%m%d_%H%M%S).json"
echo "{" > "$RESULT_FILE"
echo "  \"test_start\": \"$(date -Iseconds)\"," >> "$RESULT_FILE"
echo "  \"results\": [" >> "$RESULT_FILE"

# テストケース実行
FIRST_ITEM=true
for file in "$TEST_DIR"/*.{pdf,jpg,png}; do
    [ -e "$file" ] || continue
    
    if [ "$FIRST_ITEM" = true ]; then
        FIRST_ITEM=false
    else
        echo "," >> "$RESULT_FILE"
    fi

    filename=$(basename "$file")
    echo "Testing: $filename"
    
    # API呼び出し
    absolute_path="$BASE_DIR/$file"
    response=$(curl -s -X POST \
        -F "file=@$absolute_path" \
        http://localhost:8001/extract)
    
    # 結果保存
    echo "    {" >> "$RESULT_FILE"
    echo "      \"filename\": \"$filename\"," >> "$RESULT_FILE"
    echo "      \"response\": $response" >> "$RESULT_FILE"
    echo -n "    }" >> "$RESULT_FILE"
    
    # サマリー表示
    success=$(echo "$response" | jq -r '.success')
    confidence=$(echo "$response" | jq -r '.confidence')
    time_ms=$(echo "$response" | jq -r '.processing_time_ms')
    
    if [ "$success" = "true" ]; then
        echo "  ✅ Success (confidence: $confidence, time: ${time_ms}ms)"
    else
        echo "  ❌ Failed"
    fi
    echo ""
done

# 結果ファイル閉じる
echo "" >> "$RESULT_FILE"
echo "  ]," >> "$RESULT_FILE"
echo "  \"test_end\": \"$(date -Iseconds)\"" >> "$RESULT_FILE"
echo "}" >> "$RESULT_FILE"

echo "Results saved to: $RESULT_FILE"
echo ""
echo "=== Test Summary ==="
jq -r '.results[] | "\(.filename): \(.response.success) (confidence: \(.response.confidence))"' "$RESULT_FILE"
