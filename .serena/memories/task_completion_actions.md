After completing a task, ensure the following:
1. Run code formatting: `sail pint`
2. Run code style checks: `sail phpcs`
3. Run tests: `sail pest` or `sail phpunit`
4. Ensure all database migrations are applied: `php artisan migrate`
5. Update IDE helper files: `php artisan ide-helper:generate`
6. Update language files and compare translations: `php artisan lang:update` and `php artisan translations:compare --force`
7. For frontend changes, compile assets: `npm run dev` or `npm run build`