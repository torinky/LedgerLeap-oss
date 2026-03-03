# LedgerLeap — Coding Conventions & Patterns

## Formatter
`./vendor/bin/sail pint` — run before every commit. Canonical style source: `/docs/development/coding_standards.md`

## Naming (PHP)
- variables: `snake_case` | methods: `camelCase` | classes: `PascalCase` | constants: `UPPER_SNAKE`
- DB tables: `snake_case` plural | Blade files: `kebab-case.blade.php`

## Architecture Layers
```
Controller (thin)  →  Service (business logic)  →  Model (Eloquent)
Livewire Component →  Service                   →  Model
```
- Fat controllers are an anti-pattern; delegate to `app/Services/`
- Policies in `app/Policies/` for all authorization
- Observers in `app/Observers/` for model lifecycle hooks

## Key Patterns

### Livewire state — single array (SSoT)
```php
// ✅
public array $columns = [['type'=>'text','name'=>'title']];
// ❌ multiple parallel arrays
```

### Livewire parent calls — `$parent.method()` not `Livewire.dispatch()`

### Service pattern
```php
public function createLedger(array $data): Ledger
{
    return DB::transaction(fn () => ...);
}
```

### Enums for status/permission types (e.g. `FolderPermissionType`)

### No `json_encode()` on cast-array columns (`files`, `chk`, `content`, etc.)

## Test Conventions
- Feature tests: `use RefreshDatabase` (default)
- Full-text search tests: `use DatabaseMigrationsOnce` — NOT RefreshDatabase (Mroonga constraint)
- Every Feature `setUp()`: `tenancy()->initialize($tenant)`
- Mock: `$this->mock(SomeService::class, fn($m) => ...)`
- Mutation testing: `covers(ClassName::class)` in test file; run with `./vendor/bin/sail pest --mutate`

## Commit Convention
```
feat|fix|docs|refactor|test|chore(scope): subject
```
Branch: `feature/<issue-id>-<name>` | `bugfix/<issue-id>-<name>`

## Sprint-end checklist
1. `sail pint` 2. `sail test` 3. Update docs/work plan 4. Update GitHub issue 5. Load `skill-maintenance` skill
