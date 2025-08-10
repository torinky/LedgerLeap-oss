## Development Workflow

### Git Branch Strategy (Gitflow-based)
- **`main`**: Reflects the latest stable version released to production. Merges only from `release/*` or `hotfix/*`. Tagged with `vX.Y.Z` upon merge. Direct commits are strictly prohibited.
- **`develop`**: Integration branch for the next release. Merges from `feature/*` or `hotfix/*`.
- **`feature/<issue-id>-<feature-name>`**: For new feature development or bug fixes included in the release plan. Branch from `develop`. Merge to `develop` via Pull Request (PR).
- **`release/<version>`**: For release preparation (bug fixes, documentation, final testing). Branch from `develop`. Merge to both `main` and `develop`. Tag `main` with the release version.
- **`hotfix/<issue-id>-<fix-name>`**: For urgent bug fixes in production. Branch from the relevant tag on `main`. Merge to both `main` and `develop`. Tag `main` with a new patch version.

### Pull Request (PR) Rules
- All merges to `develop` and `main` must go through a PR.
- **Creation**: `feature/*` -> `develop`; `release/*` -> `main`, `develop`; `hotfix/*` -> `main`, `develop`.
- **Review**: Requires approval from at least one reviewer (project leader or other developer). Review checks include coding standard compliance, logic validity, test code presence, and documentation updates. Self-review is also encouraged.
- **Merge Conditions**: Reviewer approval, all automated tests (CI) passed, conflicts resolved, and (if possible) related issues closed.
- **Branch Deletion**: `feature/*`, `release/*`, `hotfix/*` branches should be deleted promptly after merging.

### Commit Message Convention
- **Conventional Commits** format, written in **Japanese**.
- **Format**: `<type>(<scope>): <subject>

<body>

<footer>`
- **`<type>`**: `feat` (new feature), `fix` (bug fix), `docs` (documentation), `style` (formatting), `refactor` (code structure), `perf` (performance), `test` (tests), `build` (build system/deps), `ci` (CI/CD config), `chore` (miscellaneous), `revert` (revert previous commit).
- **`<scope>`**: Optional, e.g., `(auth)`, `(ledger-api)`.
- **`<subject>`**: Concise summary (max 50 chars), imperative mood (e.g., `ユーザー登録機能を追加`).
- **`<body>`**: Optional, detailed explanation, reasons, background.
- **`<footer>`**: Optional, Breaking Changes, Issue references (e.g., `Closes #123`).

### Task Completion Checklist
1. Run `./vendor/bin/sail pint` for code formatting.
2. Ensure all automated tests (CI) pass.
3. Create a Pull Request following the specified rules.
4. Write commit messages following the Conventional Commits format in Japanese.