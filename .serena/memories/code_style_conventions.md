Formatting: `laravel/pint`.
Naming Conventions: Laravel standard conventions (variables: snake_case, methods: camelCase, classes: PascalCase).
Comments: PHPDoc recommended.
Branch Strategy: Gitflow-based.
Commit Messages: Conventional Commits format (Japanese).
Design Principles: Avoid fat controllers, separate business logic into service classes. Actively use FormRequest, API Resources, and Enums.
Livewire: Single Source of Truth for complex states, use simple associative arrays for public properties. Understand DOM manipulation lifecycle and event control.
Enums: Actively use PHP Enums for states and types, including helper methods for logic like permission inclusion.