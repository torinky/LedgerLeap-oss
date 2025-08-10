## Backend
- **PHP**: ^8.4
- **Laravel Framework**: ^12.0
- **Filament**: ^3.2 (Admin panel)
- **Livewire**: ^3.6
- **MaryUI**: ^2.0 (General UI)
- **Database**: MySQL/MariaDB (implied by Laravel, Mroonga for full-text search)
- **Redis**: `predis/predis`
- **Full-text search/Document processing**: `vaites/php-apache-tika` (Apache Tika integration), `logue/igo-php` (Japanese morphological analysis, likely for Mroonga)
- **Permissions**: `spatie/laravel-permission`
- **Activity Log**: `spatie/laravel-activitylog`
- **Markdown**: `spatie/laravel-markdown`
- **Excel import/export**: `maatwebsite/excel`
- **Nested Sets**: `kalnoy/nestedset`

## Frontend
- **Build Tool**: Vite
- **CSS Frameworks**: Tailwind CSS, DaisyUI
- **JavaScript Frameworks/Libraries**: Alpine.js, axios
- **File Uploads**: FilePond (with various plugins)
- **Rich Text Editor**: EasyMDE
- **Date Picker**: Flatpickr
- **Icons**: Font Awesome
- **Drag and Drop**: `@wotz/livewire-sortablejs` (SortableJS)

## Development Tools
- **Code Formatting**: `laravel/pint`
- **Testing**: `pestphp/pest`, `phpunit/phpunit`
- **Code Analysis**: Qodana (via `qodana.yaml`)
- **Development Environment**: Laravel Sail (Docker)