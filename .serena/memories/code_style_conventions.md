## Code Style and Conventions

- **Formatting**: Uses `laravel/pint`. Run `./vendor/bin/sail pint` before commit.
- **PHP Version**: ^8.4
- **Laravel Version**: ^12.0

### Naming Conventions
- **Variables**: `snake_case` (e.g., `$ledger_item`)
- **Methods**: `camelCase` (e.g., `getUserProfile()`)
- **Classes**: `PascalCase` (e.g., `LedgerController`)
- **Database Tables**: `snake_case` plural (e.g., `ledger_items`)
- **Columns**: `snake_case` (e.g., `item_name`)
- **Routes**: `kebab-case` (e.g., `ledger-items.show`)
- **Config Keys**: `snake_case`
- **Environment Variables**: `UPPER_SNAKE_CASE`
- **Blade File Names**: `kebab-case` (e.g., `list-items.blade.php`)
- **Livewire Component Class Names**: `PascalCase`
- **Livewire Component View Names**: `kebab-case`

### Comments
- **PHPDoc**: Strongly recommended for classes, methods, and properties, especially for complex logic or public APIs.
- **Inline Comments**: For complex algorithms or hard-to-understand steps.
- **TODO/FIXME**: Use `// TODO:` for future tasks and `// FIXME:` for known bugs.

### Controllers
- Adhere to the Single Responsibility Principle.
- Avoid "Fat Controllers"; delegate business logic to service or action classes.
- Use `FormRequest` for validation.
- Utilize Resource Controllers and API Resources.

### Models
- Clearly define Eloquent relations.
- Use local and global query scopes.
- Use Accessors/Mutators judiciously.
- Use `$fillable` (recommended) or `$guarded` for mass assignment protection.
- Use `$casts` for attribute type casting, including Enums.
- Actively use PHP 8.1+ Enums.
- Keep simple model-specific logic in models; delegate complex logic to services.

### Views (Blade)
- Maintain readable indentation.
- Use Blade and Livewire components for reusable UI.
- Avoid excessive PHP logic in views; prepare data in controllers or view composers.
- Use `{{ $variable }}` for escaping user input; explicitly justify ` {!! $variable !!} ` usage.

### Livewire Components
- Create components with appropriate granularity and single responsibility.
- **Parent-Child Data Passing**: Props for parent to child; events (`$this->dispatch`) for child to parent.
- Manage state with public properties.
- Keep actions concise; call service methods.
- Use Livewire's real-time validation and `validate()` method.

### Tests
- **Unit Tests**: Verify individual class/method logic; mock dependencies.
- **Feature Tests**: Test application functionality (HTTP request to response); include database and component integration.
- Aim for high test coverage on key features and complex logic.
- Use clear and descriptive test method names.
- Use test databases (e.g., `RefreshDatabase` trait) to ensure test independence.

### Other Guidelines
- Access configuration values via `config()` helper, not `env()`.
- Create dedicated configuration files for application-specific settings (e.g., `config/ledgerleap.php`).
- `.env` files for sensitive and environment-specific values; `.env.example` must be kept up-to-date.
- Actively use PHP 8.1+ Enums for fixed value sets.
- Centralize reusable business logic in service classes.
- Consider the Repository Pattern for complex database operations (optional).
- Adhere to **DRY** (Don't Repeat Yourself), **KISS** (Keep It Simple, Stupid), and **YAGNI** (You Ain't Gonna Need It) principles.

## Formatting Rules from .editorconfig
- `charset = utf-8`
- `end_of_line = lf`
- `indent_size = 4` (for most files, 2 for yaml, 4 for docker-compose.yml)
- `indent_style = space`
- `insert_final_newline = true`
- `trim_trailing_whitespace = true` (except for `.md` files)