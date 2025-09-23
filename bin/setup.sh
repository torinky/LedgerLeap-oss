#!/bin/bash
#
# LedgerLeap Project Setup Script
#
# This script automates the initial setup of the development environment.
# It builds the Docker containers, installs dependencies, and runs migrations.
#
# Usage:
# 1. Make sure you have Docker Desktop installed and running.
# 2. Clone the project repository.
# 3. Copy .env.example to .env and configure if needed. (sail share does not require this)
# 4. Run this script from the project root: ./bin/setup.sh
#
# Exit immediately if a command exits with a non-zero status.
set -e

# --- Helper Functions ---
info() {
    echo "INFO: $1"
}

# --- Main Setup ---

info "Starting LedgerLeap setup..."

# 0. Copy .env file
if [ ! -f .env ]; then
    info "Creating .env file from .env.example..."
    cp .env.example .env
fi

# 1. Build and start Docker containers
info "Building and starting Docker containers with Sail... (This may take a while)"
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d

# 2. Install dependencies and run migrations
info "Installing dependencies and running migrations..."

# Clean Node.js modules and lock file on the host
info "Cleaning Node.js modules and lock file on the host..."
rm -rf node_modules package-lock.json

./bin/install_dependencies_and_migrate.sh

info "Setup complete! The application should be running at http://localhost"
echo "You can now create a tenant using 'sail artisan tinker'."