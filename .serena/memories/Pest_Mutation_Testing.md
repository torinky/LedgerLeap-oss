# Pest PHP Mutation Testing & Coverage Guidelines

Based on official Pest PHP documentation:

1. **Mutation Testing Execution**: To run mutation tests, use `pest --mutate`.
2. **Targeting Classes (`covers` vs `mutates`)**:
   - Pest requires specifying which classes a test covers to perform mutation testing efficiently without mutating the entire application.
   - Use the `covers(ClassName::class);` or `mutates(ClassName::class);` function at the top of your test file (or inside specific `describe`/`it` blocks).
   - **Difference**: 
     - `covers(ClassName::class)` tells Pest that the test mutates this class AND it also filters the **Code Coverage** report to only show coverage for this specific class.
     - `mutates(ClassName::class)` tells Pest that the test mutates this class, but DOES NOT filter the code coverage report.
3. **Performance**: Running mutation testing on the whole codebase is extremely slow. Always use `covers()` or `mutates()` in the test files, or run with filters like `--mutate --class='App\Services\WorkflowService'`.
4. **Coverage**: Run coverage with `pest --coverage`. Used in combination with `covers()`, the coverage report will explicitly highlight what parts of the covered class are actually executed by the test.