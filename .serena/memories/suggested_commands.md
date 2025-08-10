Development:
- `./vendor/bin/sail up -d`: Start the development environment using Laravel Sail.
- `./vendor/bin/sail npm install`: Install frontend dependencies.
- `./vendor/bin/sail composer install`: Install backend dependencies.
- `./vendor/bin/sail npm run dev`: Start the Vite development server for frontend development.
- `./vendor/bin/sail npm run build`: Build the frontend assets for production.
- `./vendor/bin/sail artisan migrate`: Run database migrations.
- `./vendor/bin/sail artisan test`: Run the test suite.

Code Quality:
- `./vendor/bin/sail artisan pint`: Format the code.
- `./vendor/bin/sail artisan phpcs`: Check for coding standard violations.

Testing:
- `sail pest`: Run Pest tests.
- `sail phpunit`: Run PHPUnit tests.

Utility:
- `./vendor/bin/sail artisan ide-helper:generate`: Generate IDE helper files.
- `./vendor/bin/sail artisan filament:upgrade`: Upgrade Filament assets.
- `./vendor/bin/sail artisan lang:update`: Update language files.
- `./vendor/bin/sail artisan translations:compare --force`: Compare translations.