#!/bin/bash
# Development environment launcher
# This is a wrapper script for setup.sh

set -e

# .env.development を .env にコピー（存在する場合）
if [ -f .env.development ]; then
    echo "INFO: Copying .env.development to .env"
    cp .env.development .env
fi

# setup.sh を呼び出し
./bin/setup.sh "$@"
