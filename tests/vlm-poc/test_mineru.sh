#!/bin/bash

set -e

echo "=== MinerU VLM Model Test ==="
echo "Model: MinerU"
echo "Test Date: $(date)"
echo ""

TEST_DIR="../fixtures/files"
RESULT_DIR="../../storage/test/vlm-poc/results"
mkdir -p "$RESULT_DIR"

# Result file with timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RESULT_FILE="$RESULT_DIR/mineru_test_${TIMESTAMP}.json"
LOG_FILE="$RESULT_DIR/mineru_test_${TIMESTAMP}.log"

# Redirect all output to log file as well
exec > >(tee -a "$LOG_FILE")
exec 2>&1

echo "{"
echo "  \"model\": \"MinerU\","
echo "  \"test_start\": \"$(date -Iseconds)\","
echo "  \"endpoint\": \"http://localhost:8001/extract/structured\","
echo "  \"results\": ["

first=true

# Test each file
for file in "$TEST_DIR"/*.{pdf,jpg,png}; do
    [ -e "$file" ] || continue
    
    filename=$(basename "$file")
    echo "Testing: $filename" >&2
    
    # Add comma for JSON array
    if [ "$first" = false ]; then
        echo ","
    fi
    first=false
    
    # API call
    start_time=$(python3 -c "import time; print(int(time.time() * 1000))")
    response=$(curl -s -X POST \
        -F "file=@$file" \
        http://localhost:8001/extract/structured 2>&1 || echo '{"error": "API call failed"}')
    end_time=$(python3 -c "import time; print(int(time.time() * 1000))")
    processing_time=$((end_time - start_time))
    
    # Check if response is valid JSON and successful
    if echo "$response" | jq . > /dev/null 2>&1; then
        success=$(echo "$response" | jq -r '.success // false')
        if [ "$success" = "true" ]; then
            markdown=$(echo "$response" | jq -r '.markdown // ""')
            markdown_length=${#markdown}
            echo "  ✅ Success (${processing_time}ms, ${markdown_length} chars)" >&2
            
            # Save markdown to separate file for review
            markdown_file="$RESULT_DIR/mineru_${filename}_${TIMESTAMP}.md"
            echo "$markdown" > "$markdown_file"
            echo "     Saved to: $markdown_file" >&2
        else
            success="false"
            markdown_length=0
            error_detail=$(echo "$response" | jq -r '.detail // "Unknown error"')
            echo "  ❌ Failed (${processing_time}ms): $error_detail" >&2
        fi
    else
        success="false"
        markdown_length=0
        echo "  ❌ Failed (${processing_time}ms) - Invalid JSON response" >&2
    fi
    
    # Save result (escape JSON properly)
    echo "    {"
    echo "      \"filename\": \"$filename\","
    echo "      \"success\": $success,"
    echo "      \"processing_time_ms\": $processing_time,"
    echo "      \"markdown_length\": $markdown_length,"
    echo "      \"response\": $(echo "$response" | jq -c .)"
    echo -n "    }"
    
    echo ""
done

echo ""
echo "  ],"
echo "  \"test_end\": \"$(date -Iseconds)\""
echo "}" | tee "$RESULT_FILE"

echo ""
echo "=== Test Summary ===" >&2
echo "Results saved to: $RESULT_FILE" >&2
echo "Log saved to: $LOG_FILE" >&2

# Extract and display summary statistics
echo "" >&2
echo "Processing times:" >&2
jq -r '.results[] | "\(.filename): \(.processing_time_ms)ms (\(.markdown_length) chars) - \(if .success then "✅" else "❌" end)"' "$RESULT_FILE" 2>/dev/null || echo "No results" >&2

echo "" >&2
echo "Success rate:" >&2
total=$(jq '.results | length' "$RESULT_FILE" 2>/dev/null || echo "0")
success=$(jq '[.results[] | select(.success == true)] | length' "$RESULT_FILE" 2>/dev/null || echo "0")
echo "$success / $total tests passed" >&2

# Show extracted markdown files
echo "" >&2
echo "Extracted markdown files:" >&2
ls -lh "$RESULT_DIR"/mineru_*_${TIMESTAMP}.md 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'  >&2 || echo "  None" >&2
