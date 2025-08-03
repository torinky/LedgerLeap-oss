
# Suggested Commands for LedgerLeap

## Development Environment
- Start development environment: `sh dev.sh`
- Stop development environment: `docker compose down`

## Running the Application
- Start the application (development): `php artisan serve` (after `sh dev.sh` and `php artisan migrate`)
- Start the application (production): `sh prod.sh`

## Testing
- Run all tests (Pest/PHPUnit): `php artisan test` or `vendor/bin/pest`
- Run PHPUnit tests: `vendor/bin/phpunit`

## Linting and Formatting
- Format PHP code with Pint: `php artisan pint`
- Check PHP code style with Pint (dry run): `php artisan pint --test`

## Frontend Development
- Run Vite development server: `npm run dev`
- Build frontend assets for production: `npm run build`

## Composer Commands
- Install PHP dependencies: `composer install`
- Update PHP dependencies: `composer update`

## Artisan Commands
- Run database migrations: `php artisan migrate`
- Clear cache: `php artisan cache:clear`
- Optimize application: `php artisan optimize`

## Git Commands
- Check status: `git status`
- View changes: `git diff`
- Add changes to staging: `git add .`
- Commit changes: `git commit -m "Your commit message"`
- View commit history: `git log`

## Docker Commands (Sail)
- Start Sail: `vendor/bin/sail up -d`
- Stop Sail: `vendor/bin/sail down`
- Execute Artisan command via Sail: `vendor/bin/sail artisan [command]`
- Execute Composer command via Sail: `vendor/bin/sail composer [command]`
- Execute NPM command via Sail: `vendor/bin/sail npm [command]`
