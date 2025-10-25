#!/bin/bash

CONCURRENT=5  # 同時実行数
TOTAL=50      # 総テスト数

echo "=== VLM Load Test ==="
echo "Concurrent: $CONCURRENT"
echo "Total: $TOTAL"
echo ""

# 開始前のリソース確認
echo "Initial resources:"
docker stats ledgerleap_vlm --no-stream
echo ""

# APIを呼び出す関数
run_test() {
    local request_num=$1
    local file="storage/test/vlm-poc/invoice_simple.pdf"
    local absolute_path="$(pwd)/$file"
    local start=$(python3 -c 'import time; print(int(time.time() * 1000))')
    
    # curlの出力を変数に格納し、HTTPステータスコードも取得
    local response=$(curl -s -w "HTTP_CODE:%{http_code}" -X POST -F "file=@$absolute_path" http://localhost:8001/extract)
    
    local end=$(python3 -c 'import time; print(int(time.time() * 1000))')
    
    # レスポンスからHTTPコードとボディを安全に分離
    local http_code=$(echo "$response" | sed -n 's/.*HTTP_CODE:\([0-9]*\)$/\1/p')
    local body=$(echo "$response" | sed 's/HTTP_CODE:[0-9]*$//')

    if [ -n "$http_code" ] && [ "$http_code" -eq 200 ]; then
        echo "Request $request_num: Success, $((end - start))ms"
    elif [ -n "$http_code" ]; then
        echo "Request $request_num: Failed with HTTP code $http_code, $((end - start))ms"
    else
        echo "Request $request_num: Failed with no response, $((end - start))ms"
    fi
}

export -f run_test

# 負荷テスト実行
seq 1 $TOTAL | xargs -P $CONCURRENT -I {} bash -c 'run_test {}'

# 終了後のリソース確認
echo ""
echo "Final resources:"
docker stats ledgerleap_vlm --no-stream
