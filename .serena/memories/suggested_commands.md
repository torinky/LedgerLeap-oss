## Suggested Commands

This project uses Laravel Sail for its development environment, so most commands should be prefixed with `./vendor/bin/sail` or `sail` if you have the Sail alias configured.

### General Laravel Commands
- **Serve the application**: `./vendor/bin/sail artisan serve`
- **Run migrations**: `./vendor/bin/sail artisan migrate`
- **Clear cache**: `./vendor/bin/sail artisan cache:clear`
- **Clear config cache**: `./vendor/bin/sail artisan config:clear`
- **Clear route cache**: `./vendor/bin/sail artisan route:clear`
- **Clear view cache**: `./vendor/bin/sail artisan view:clear`
- **Generate application key**: `./vendor/bin/sail artisan key:generate`
- **Run database seeders**: `./vendor/bin/sail artisan db:seed`
- **Open Tinker console**: `./vendor/bin/sail artisan tinker`

### Testing
- **Run all tests**: `./vendor/bin/sail artisan test` or `./vendor/bin/sail phpunit`

### Code Quality and Formatting
- **Format code with Pint**: `./vendor/bin/sail pint`

### Frontend Development
- **Run Vite development server**: `npm run dev` or `npm run watch` (if configured)
- **Build frontend assets for production**: `npm run build`

### Composer Commands
- **Install PHP dependencies**: `./vendor/bin/sail composer install`
- **Update PHP dependencies**: `./vendor/bin/sail composer update`

### Docker/Sail Commands
- **Start Sail services**: `./vendor/bin/sail up -d`
- **Stop Sail services**: `./vendor/bin/sail down`
- **Execute a command in a Sail service (e.g., bash in app container)**: `./vendor/bin/sail bash`

### Git Commands (Standard)
- `git status`
- `git add .`
- `git commit -m "<message>"`
- `git push`
- `git pull`
- `git checkout <branch-name>`
- `git branch`

### Other Utility Commands (Standard Darwin/Unix)
- `ls`
- `cd`
- `pwd`
- `grep`
- `find`
- `cat`
- `less`
- `cp`
- `mv`
- `rm`