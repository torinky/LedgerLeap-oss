# LedgerLeap

LedgerLeap is a web-based ledger and document management system designed to streamline information management and sharing within an organization. It provides robust features like full-text search (including attachments), flexible permission control, and **detailed activity and access tracking** to enhance security and auditability.

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Quick Start

### Development Environment Setup

For detailed setup instructions, please refer to:
- [Developer Documentation (docs/README.md)](docs/README.md) - Comprehensive development guide
- [Environment Setup Details (docs/development/environment-setup.md)](docs/development/environment-setup.md) - Technical implementation details

**Quick setup:**

```bash
# Clone the repository
git clone [repository-url] ledgerleap
cd ledgerleap

# Setup (automatically detects your environment)
./bin/setup.sh        # Development environment
./bin/setup.sh -p     # Production environment
```

The setup script will:
- Build Docker containers
- Install all dependencies (Composer & NPM)
- Run database migrations
- Auto-detect your architecture (ARM64/AMD64)
- Apply GPU settings if configured in .env

### Manual Composer Installation (if needed)

If you need to install Composer dependencies manually before running Sail:

Reference: https://readouble.com/laravel/9.x/ja/sail.html#installing-composer-dependencies-for-existing-projects

```bash
docker run --rm \
-u "$(id -u):$(id -g)" \
-v $(pwd):/var/www/html \
-w /var/www/html \
laravelsail/php81-composer:latest \
composer install --ignore-platform-reqs
```

## Credit

Japanese Wordnet (v1.1) © 2009-2011 NICT, 2012-2015 Francis Bond and 2016-2022 Francis Bond, Takayuki Kuribayashi\
https://bond-lab.github.io/wnja/index.en.html

日本語ワードネット （1.1版）© 2009-2011 NICT, 2012-2015 Francis Bond and 2016-2022 Francis Bond, Takayuki Kuribayashi\
https://bond-lab.github.io/wnja/index.ja.html
