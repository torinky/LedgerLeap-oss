The primary entrypoints for running the project are via Docker Compose using the provided shell scripts:
- **Development:** `./dev.sh` (starts containers with development environment settings)
- **Production:** `./prod.sh` (starts containers with production environment settings)
These scripts typically execute `docker compose up -d` with environment-specific configurations.