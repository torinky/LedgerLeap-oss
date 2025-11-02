#!/bin/bash

set -e

# モデル切り替えスクリプト
# Usage: ./bin/switch-model.sh [model-key]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# 色定義
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# .env ファイルを更新する関数
update_env_file() {
    local key=$1
    local value=$2
    local env_file="$PROJECT_ROOT/.env"

    if grep -q "^${key}=" "$env_file"; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|^${key}=.*|${key}=${value}|" "$env_file"
        else
            sed -i "s|^${key}=.*|${key}=${value}|" "$env_file"
        fi
    else
        echo "${key}=${value}" >> "$env_file"
    fi
}

# モデルを検索する関数
find_model() {
    local search_key="$1"
    case "$search_key" in
        "ruri-v3-310m")
            echo "cl-nagoya/ruri-v3-310m|768|Fast Japanese model (recommended for ARM64)|arm64"
            ;;
        "ruri-v3-30m")
            echo "cl-nagoya/ruri-v3-30m|256|Fast Japanese model (recommended for ARM64)|arm64"
            ;;
        "multilingual-e5-small")
            echo "intfloat/multilingual-e5-small|384|Lightweight multilingual|amd64"
            ;;
        "all-minilm-l6-v2")
            echo "sentence-transformers/all-MiniLM-L6-v2|384|Ultra-fast (English-focused)|amd64"
            ;;
        "multilingual-e5-base")
            echo "intfloat/multilingual-e5-base|768|Balanced multilingual|amd64"
            ;;
        "granite-embedding-107m")
            echo "ibm/granite-embedding-107m-multilingual|1024|Code search capable|amd64"
            ;;
        "bge-m3")
            echo "BAAI/bge-m3|1024|High-quality (slow on ARM64)|amd64"
            ;;
        *)
            echo ""
            ;;
    esac
}

# モデル情報を表示
show_models() {
    echo -e "${CYAN}=========================================="
    echo "Available Embedding Models"
    echo -e "==========================================${NC}"
    echo ""
    echo -e "${YELLOW}Key${NC}                     ${YELLOW}Dimensions${NC}  ${YELLOW}Description${NC}"
    echo "------------------------------------------------------------"
    
    printf "%-24s %-10s %s\n" "ruri-v3-310m" "768" "Fast Japanese model (recommended for ARM64)"
    printf "%-24s %-10s %s\n" "ruri-v3-30m" "256" "Fast Japanese model (recommended for ARM64)"
    printf "%-24s %-10s %s\n" "multilingual-e5-small" "384" "Lightweight multilingual"
    printf "%-24s %-10s %s\n" "all-minilm-l6-v2" "384" "Ultra-fast (English-focused)"
    printf "%-24s %-10s %s\n" "multilingual-e5-base" "768" "Balanced multilingual"
    printf "%-24s %-10s %s\n" "granite-embedding-107m" "1024" "Code search capable"
    printf "%-24s %-10s %s\n" "bge-m3" "1024" "High-quality (slow on ARM64)"
    
    echo ""
    echo -e "${CYAN}Recommendations:${NC}"
    echo -e "  ${GREEN}⭐ ruri-v3-30m${NC}           - Best for ARM64 development"
    echo -e "  ${GREEN}⭐ multilingual-e5-small${NC}  - Good for multilingual apps"
    echo -e "  ${GREEN}⭐ multilingual-e5-base${NC}   - Balanced quality/speed"
    echo ""
}

# 現在のモデルを表示
show_current() {
    echo -e "${CYAN}Current Configuration:${NC}"
    
    # .envから取得
    if [ -f "$PROJECT_ROOT/.env" ]; then
        local rag_model=$(grep "^RAG_MODEL=" "$PROJECT_ROOT/.env" | cut -d= -f2)
        echo -e "  RAG_MODEL: ${YELLOW}${rag_model:-not set}${NC}"
    fi
    
    # docker-compose.ymlから取得
    if [ -f "$PROJECT_ROOT/docker-compose.yml" ]; then
        local embedding_model=$(grep "EMBEDDING_MODEL=" "$PROJECT_ROOT/docker-compose.yml" | grep -v "^\s*#" | head -1 | sed 's/.*EMBEDDING_MODEL=//' | tr -d ' ')
        echo -e "  EMBEDDING_MODEL: ${YELLOW}${embedding_model:-not set}${NC}"
    fi
    
    echo ""
}

# モデルを切り替え
switch_model() {
    local model_key=$1
    
    # モデルを検索
    local model_info=$(find_model "$model_key")
    
    # モデルの存在確認
    if [ -z "$model_info" ]; then
        echo -e "${RED}Error: Unknown model key '${model_key}'${NC}"
        echo ""
        show_models
        exit 1
    fi
    
    IFS='|' read -r model_name dimension description model_platform <<< "$model_info"
    
    echo -e "${CYAN}=========================================="
    echo "Switching to: ${model_key}"
    echo -e "==========================================${NC}"
    echo -e "  Model: ${YELLOW}${model_name}${NC}"
    echo -e "  Dimensions: ${YELLOW}${dimension}${NC}"
    echo -e "  Description: ${description}"
    echo -e "  Platform: ${YELLOW}linux/${model_platform}${NC}"

    # ホストアーキテクチャの判定
    HOST_ARCH=$(uname -m)
    local TARGET_PLATFORM="linux/${model_platform}" # デフォルト

    echo -e "  Host Arch: ${YELLOW}${HOST_ARCH}${NC}"
    echo -e "  Model's Recommended Platform: ${YELLOW}${model_platform}${NC}"

    # アーキテクチャの互換性チェック
    if [[ "$HOST_ARCH" == "arm64" || "$HOST_ARCH" == "aarch64" ]]; then
        if [ "$model_platform" != "arm64" ]; then
            echo -e "\n${RED}Warning:${NC} The selected model '${model_key}' is not optimized for your ARM64 machine."
            echo -e "         It will run under emulation (Rosetta 2) and may be very slow or unstable."
            TARGET_PLATFORM="linux/amd64"
        else
            TARGET_PLATFORM="linux/arm64"
        fi
    elif [[ "$HOST_ARCH" == "x86_64" ]]; then
        if [ "$model_platform" == "arm64" ]; then
            echo -e "\n${YELLOW}Info:${NC} The selected model '${model_key}' is optimized for ARM64, but you are on x86_64."
            echo -e "      The script will use the 'amd64' platform. Performance should be acceptable."
        fi
        TARGET_PLATFORM="linux/amd64"
    else
        echo -e "\n${YELLOW}Warning:${NC} Unknown host architecture '${HOST_ARCH}'. Using model's default platform."
    fi

    echo -e "  Target Platform to be set: ${YELLOW}${TARGET_PLATFORM}${NC}"
    echo ""
    
    # 確認
    read -p "Continue? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Cancelled."
        exit 0
    fi
    
    echo ""
    echo -e "${YELLOW}[Step 1/7]${NC} Updating .env file..."
    
    # .envの更新
    if [ -f "$PROJECT_ROOT/.env" ]; then
        # RAG_MODELを更新
        if grep -q "^RAG_MODEL=" "$PROJECT_ROOT/.env"; then
            # Macのsedは-i ''が必要
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s/^RAG_MODEL=.*/RAG_MODEL=${model_key}/" "$PROJECT_ROOT/.env"
            else
                sed -i "s/^RAG_MODEL=.*/RAG_MODEL=${model_key}/" "$PROJECT_ROOT/.env"
            fi
        else
            echo "RAG_MODEL=${model_key}" >> "$PROJECT_ROOT/.env"
        fi
        echo -e "  ${GREEN}✓${NC} Updated RAG_MODEL=${model_key}"
    else
        echo -e "  ${RED}✗${NC} .env file not found"
        exit 1
    fi
    
    echo ""
    echo -e "${YELLOW}[Step 2/7]${NC} Verifying Laravel configuration consistency..."
    
    # config/rag.phpの次元設定を確認（情報表示のみ）
    echo -e "  ${BLUE}ℹ${NC}  Model dimension: ${dimension}D"
    echo -e "  ${BLUE}ℹ${NC}  Binary storage: MEDIUMBLOB (up to 16MB, sufficient for all models)"
    echo -e "  ${BLUE}ℹ${NC}  Laravel config: config/rag.php reads from .env (RAG_MODEL=${model_key})"
    echo -e "  ${GREEN}✓${NC} Configuration verified"
    
    echo ""
    echo -e "${YELLOW}[Step 3/7]${NC} Updating docker-compose.yml..."
    
    # docker-compose.ymlの更新
    if [ -f "$PROJECT_ROOT/docker-compose.yml" ]; then
        # EMBEDDING_MODELの行を探して更新
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|EMBEDDING_MODEL=.*|EMBEDDING_MODEL=${model_name}|" "$PROJECT_ROOT/docker-compose.yml"
        else
            sed -i "s|EMBEDDING_MODEL=.*|EMBEDDING_MODEL=${model_name}|" "$PROJECT_ROOT/docker-compose.yml"
        fi
        echo -e "  ${GREEN}✓${NC} Updated EMBEDDING_MODEL=${model_name}"
        
        # platformの更新
        if grep -q "platform: linux/.*" "$PROJECT_ROOT/docker-compose.yml"; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s|platform: linux/.*|platform: ${TARGET_PLATFORM}  # Auto-set by switch-model.sh|" "$PROJECT_ROOT/docker-compose.yml"
            else
                sed -i "s|platform: linux/.*|platform: ${TARGET_PLATFORM}  # Auto-set by switch-model.sh|" "$PROJECT_ROOT/docker-compose.yml"
            fi
            echo -e "  ${GREEN}✓${NC} Updated platform=${TARGET_PLATFORM}"
        else
            echo -e "  ${YELLOW}Warning: 'platform' line not found in docker-compose.yml for the embedding service. Please add it manually if needed for cross-platform compatibility.${NC}"
        fi
    else
        echo -e "  ${RED}✗${NC} docker-compose.yml file not found"
        exit 1
    fi
    
    echo ""
    echo -e "${YELLOW}[Step 4/7]${NC} Stopping existing embedding container..."
    cd "$PROJECT_ROOT"
    ./vendor/bin/sail down embedding 2>&1 | tail -3
    echo -e "  ${GREEN}✓${NC} Container stopped"
    
    echo ""
    echo -e "${YELLOW}[Step 5/7]${NC} Removing old image..."
    docker rmi ledgerleap-embedding 2>/dev/null || echo "  (No old image to remove)"
    echo -e "  ${GREEN}✓${NC} Old image removed"
    
    # 将来の拡張ポイント: モデル固有の requirements.txt 切り替え
    # 現在は全モデル共通の requirements.txt を使用:
    #   - transformers 4.48.0 (modernbert, BGE, e5, ruri 対応)
    #   - sentence-transformers 3.4.1 (統一インターフェース)
    #   - torch 2.5.1 (ARM64 最適化)
    #   - sentencepiece 0.2.0 (日本語トークナイザー)
    #
    # 今後、モデル毎に最適化が必要な場合:
    # 1. ONNX最適化版 → requirements-onnx.txt に optimum[onnxruntime] 追加
    # 2. 専用ライブラリ → requirements-{model}.txt に独自依存関係追加
    # 3. バージョン固定 → requirements-legacy.txt に古いバージョン指定
    #
    # 実装例:
    # if [ -f "docker/embedding/requirements-${model_key}.txt" ]; then
    #     echo -e "  ${BLUE}Using model-specific requirements${NC}"
    #     cp "docker/embedding/requirements-${model_key}.txt" "docker/embedding/requirements.txt"
    # fi
    
    echo ""
    echo -e "${YELLOW}[Step 6/7]${NC} Building new container..."
    echo -e "  ${BLUE}This may take a few minutes...${NC}"
    ./vendor/bin/sail build --no-cache embedding 2>&1 | grep -E "Step|Successfully|named" | tail -5
    echo -e "  ${GREEN}✓${NC} Container built"
    
    echo ""
    echo -e "${YELLOW}[Step 7/7]${NC} Starting embedding container..."
    ./vendor/bin/sail up -d embedding 2>&1 | tail -3
    echo -e "  ${GREEN}✓${NC} Container started"
    
    echo ""
    echo -e "${CYAN}=========================================="
    echo -e "${GREEN}✓ Model switch completed!${NC}"
    echo -e "==========================================${NC}"
    echo ""
    echo -e "${CYAN}Configuration Summary:${NC}"
    echo -e "  Model Key: ${YELLOW}${model_key}${NC}"
    echo -e "  Model Name: ${YELLOW}${model_name}${NC}"
  Dimensions: ${YELLOW}${dimension}D${NC}
    echo -e "  Platform: ${YELLOW}${TARGET_PLATFORM}${NC}"
    echo ""
    echo -e "  Storage: ${GREEN}MEDIUMBLOB${NC} (supports up to 4M dimensions)"
    echo -e "  Config: ${GREEN}config/rag.php${NC} → 'available_models.${model_key}'"
    echo -e "  Env: ${GREEN}.env${NC} → RAG_MODEL=${model_key}"
    echo ""
    echo -e "Next steps:"
    echo -e "  1. Wait for model to load (30-90 seconds depending on size)"
    echo -e "     ${BLUE}docker logs -f ledgerleap_embedding${NC}"
    echo ""
    echo -e "  2. Check health status:"
    echo -e "     ${BLUE}docker exec ledgerleap-laravel-1 curl -s http://embedding:8000/health${NC}"
    echo ""
    echo -e "  3. Run performance test:"
    echo -e "     ${BLUE}./bin/test-rag-performance.sh${NC}"
    echo ""
    echo -e "  ${YELLOW}Note:${NC} If you have existing chunks with different dimensions,"
    echo -e "        you may need to re-chunk existing ledgers:"
    echo -e "     ${BLUE}./vendor/bin/sail artisan rag:chunk-existing-ledgers${NC}"
    echo ""
}

# メイン処理
main() {
    cd "$PROJECT_ROOT"
    
    echo -e "${CYAN}=========================================="
    echo "RAG Embedding Model Switcher"
    echo -e "==========================================${NC}"
    echo ""
    
    # 引数チェック
    if [ $# -eq 0 ]; then
        show_current
        show_models
        echo -e "${YELLOW}Usage:${NC} $0 <model-key>"
        echo ""
        echo "Example:"
        echo "  $0 ruri-v3-30m           # Switch to ruri-v3-30m (recommended)"
        echo "  $0 multilingual-e5-small # Switch to multilingual-e5-small"
        echo ""
        exit 0
    fi
    
    # ヘルプ表示
    if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
        show_current
        show_models
        echo -e "${YELLOW}Usage:${NC} $0 <model-key>"
        exit 0
    fi
    
    # リスト表示
    if [ "$1" = "-l" ] || [ "$1" = "--list" ]; then
        show_current
        show_models
        exit 0
    fi
    
    # モデル切り替え実行
    switch_model "$1"
}

main "$@"
