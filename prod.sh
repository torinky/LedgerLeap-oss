#!/bin/bash
# Production environment launcher
# This is a wrapper script for setup.sh

set -e

# .env.production を .env にコピー（存在する場合）
if [ -f .env.production ]; then
    echo "INFO: Copying .env.production to .env"
    cp .env.production .env
fi

# setup.sh を -p オプション付きで呼び出し
./bin/setup.sh -p "$@"
