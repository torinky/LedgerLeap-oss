# Scoring System

**Audience:** LedgerLeap developers and maintainers
**Last updated:** 2025-10-13

## Purpose

The scoring system computes a composite score for each Ledger by combining activity, freshness, and importance signals. This score drives the default sort order in search results and ledger lists, surfacing the most relevant records first.

## Scope

- Composite score calculation across three dimensions: activity, freshness, and importance.
- Artisan commands for scheduled and manual score recalculation.
- Configuration via `config/ledgerleap.php`.
- Database schema, indexing, and query patterns for score-based sorting.
- Livewire integration for default sort order.

Out of scope: extended scoring dimensions (relevance, popularity) planned for later phases.

## Architecture

```
┌─────────────────────────────────────────────────┐
│           Artisan Command                        │
│     (scoring:calculate — scheduled execution)    │
└─────────────┬───────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────┐
│         Service Layer                            │
│  ┌──────────────────────────────────────────┐  │
│  │  CompositeScoreCalculator                │  │
│  │  ├─ ActivityScoreService                 │  │
│  │  ├─ FreshnessScoreService                │  │
│  │  └─ ImportanceScoreService               │  │
│  └──────────────────────────────────────────┘  │
└─────────────┬───────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────┐
│         Data Layer                               │
│  ├─ Ledger Model (activity_score,               │
│  │                 composite_score)              │
│  ├─ ActivityLog (event history)                  │
│  └─ Config (ledgerleap.php)                      │
└─────────────────────────────────────────────────┘
```

## Services

### ActivityScoreService

**Location:** `app/Services/Scoring/ActivityScoreService.php`

Aggregates events from `activity_log` to compute an activity score. The calculation uses configurable time windows with per-window multipliers.

```php
public function calculateForLedger(Ledger $ledger): float
```

Implementation avoids N+1 queries by aggregating directly on the `activity_log` table.

### FreshnessScoreService

**Location:** `app/Services/Scoring/FreshnessScoreService.php`

Computes a freshness score from the elapsed time since the last update. The formula is a pure function with no external dependencies or configuration.

```php
public function calculateForLedger(Ledger $ledger): float
```

```
$daysSinceUpdate = now()->diffInDays($ledger->updated_at);
$score = max(0, 100 - ($daysSinceUpdate * 2));
```

### ImportanceScoreService

**Location:** `app/Services/Scoring/ImportanceScoreService.php`

Maps workflow status to an importance score. Each workflow state has a fixed score reflecting business priority.

```php
public function calculateForLedger(Ledger $ledger): float
```

```php
private const STATUS_SCORES = [
    WorkflowStatus::PENDING->value => 100,
    WorkflowStatus::RETURNED->value => 80,
    WorkflowStatus::IN_REVIEW->value => 60,
    WorkflowStatus::DRAFT->value => 20,
    WorkflowStatus::APPROVED->value => 10,
];
```

Planned extensions for a later phase: tag-based importance, comment count, and attachment count weighting.

### CompositeScoreCalculator

**Location:** `app/Services/Scoring/CompositeScoreCalculator.php`

Orchestrates the three scoring services and applies configured weights to produce the final composite score.

```php
public function calculate(Ledger $ledger): array
```

Returns an array with individual scores and the weighted composite:

```php
[
    'activity_score' => 30.0,
    'freshness_score' => 80.0,
    'importance_score' => 20.0,
    'composite_score' => 45.0,
]
```

## Configuration

All scoring parameters live in `config/ledgerleap.php`:

```php
return [
    'scoring' => [
        'activity' => [
            'windows' => [
                ['days' => 7, 'multiplier' => 10],
                ['days' => 30, 'multiplier' => 3],
            ],
        ],
        'weights' => [
            'activity' => 0.40,
            'freshness' => 0.30,
            'importance' => 0.30,
            'relevance' => 0.00,
            'popularity' => 0.00,
        ],
        'batch' => [
            'chunk_size' => 100,
            'schedule' => 'daily',
        ],
        'schedule_frequency' => env('SCORING_SCHEDULE_FREQUENCY', 'daily'),
    ],
];
```

### Schedule frequency

Controlled via `SCORING_SCHEDULE_FREQUENCY` environment variable. Supported values: `everyMinute`, `everyFiveMinutes`, `everyTenMinutes`, `hourly`, `daily`, `weekly`. Defaults to `daily`.

The scheduler is defined in `app/Console/Kernel.php` using a `match` expression on the configured frequency.

## Commands

### scoring:calculate

**Location:** `app/Console/Commands/CalculateScores.php`

Scheduled command that iterates over all tenants, computes scores for every Ledger, and persists them using `saveQuietly()` to avoid recording score updates in the activity log.

```bash
php artisan scoring:calculate
```

Flow:
1. Enumerate all tenants.
2. For each tenant, initialize tenant context.
3. For each Ledger, compute activity score via `ActivityScoreService` and composite score via `CompositeScoreCalculator`.
4. Persist with `saveQuietly()`.
5. Report progress and log results.

### scoring:reset

**Location:** `app/Console/Commands/ResetScores.php`

Manual command that recalculates scores from the existing activity log. Useful after changing scoring logic or recovering from inconsistencies.

```bash
php artisan scoring:reset [--tenant=ID] [--folder=ID] [--force]
```

Options:
- `--tenant=ID` — limit to a specific tenant.
- `--folder=ID` — limit to a folder and its descendants.
- `--force` — skip the confirmation prompt.

Difference from `scoring:calculate`: both use the same calculation logic, but `scoring:reset` is an explicit full rebuild triggered manually, while `scoring:calculate` is the scheduled incremental path.

## Database Schema

Score columns added via migration:

```php
$table->decimal('activity_score', 5, 2)->default(0);
$table->decimal('composite_score', 5, 2)->default(0);
$table->index('composite_score', 'idx_ledgers_composite_score');
```

The `composite_score` index accelerates ORDER BY queries. NULL values are pushed to the end with:

```sql
ORDER BY composite_score = 0, composite_score DESC
```

## Livewire Integration

**Component:** `app/Livewire/Ledger/RecordsTable.php`

The records table defaults to sorting by `composite_score` descending:

```php
public string $orderBy = 'composite_score';
public string $orderDirection = 'desc';
```

Sort labels are resolved through translation keys:

```php
match ($columnName) {
    'composite_score' => __('ledger.composite_score'),
    'created_at' => __('ledger.created_at'),
    'updated_at' => __('ledger.updated_at'),
    default => '',
};
```

## Scoring Calculation

The composite score is a weighted average of the three scoring dimensions. Each dimension produces a value in the range 0–100. The configured weights (defaulting to 0.40/0.30/0.30) are applied in `CompositeScoreCalculator`.

**Activity score**: sum of (event count per window × multiplier) across configured time windows. Example: 3 events in the 7-day window at multiplier 10 → 30 points.

**Freshness score**: `max(0, 100 - (days_since_update × 2))`. A Ledger updated today scores 100; one updated 50+ days ago scores 0.

**Importance score**: fixed mapping from workflow status. Pending approvals score highest; approved records score lowest.

## Edge Cases and Constraints

### Infinite score escalation

If score updates are themselves recorded in the activity log, each `scoring:calculate` run increases the activity score, producing unbounded growth. Mitigation:
- Use `saveQuietly()` in all score-update paths to suppress activity logging.
- The `Ledger` model's `getActivitylogOptions()` should exclude `activity_score` and `composite_score` from triggering activity log entries with `dontLogIfAttributesChangedOnly()`.
- Run `scoring:reset` to rebuild scores from the raw activity log if escalation is detected.

### Tenancy

Score calculations run within tenant context. The `scoring:calculate` command initializes each tenant before querying its Ledgers. Tests must call `tenancy()->initialize($tenant)` in `setUp()`.

### Performance

- `ActivityScoreService` aggregates directly on `activity_log` with a single query per Ledger, avoiding N+1 patterns.
- `CompositeScoreCalculator` processes Ledgers in configurable chunk size (default 100) via `chunk()`.
- The `composite_score` index ensures ORDER BY queries use index scans.

### Config resolution in tests

Tests that invoke scoring services should explicitly set the scoring configuration in `setUp()` rather than relying on `.env` defaults, because test environments may not load the full config chain.

## Testing

### Unit tests

Each scoring service has a dedicated unit test:

- `tests/Unit/Services/Scoring/ActivityScoreServiceTest.php`
- `tests/Unit/Services/Scoring/FreshnessScoreServiceTest.php`
- `tests/Unit/Services/Scoring/ImportanceScoreServiceTest.php`

### Feature tests

- `tests/Feature/Feature/Console/CalculateScoresCommandTest.php` — verifies command execution, multi-tenant behavior, and score correctness.
- `tests/Feature/Feature/Console/CalculateScoresScheduleTest.php` — verifies the scheduler registers the command with the correct frequency.

Run scoring-specific tests:

```bash
./vendor/bin/sail test --filter=Score
./vendor/bin/sail test tests/Feature/Feature/Console/CalculateScoresCommandTest.php
```

## Troubleshooting

### Scores not updating

Verify the scheduler container is running:

```bash
./vendor/bin/sail ps
./vendor/bin/sail up -d scheduler
```

### Scores stuck at zero in tests

Ensure scoring config is explicitly set in `setUp()`:

```php
config([
    'ledgerleap.scoring.activity.windows' => [
        ['days' => 7, 'multiplier' => 10],
        ['days' => 30, 'multiplier' => 3],
    ],
    'ledgerleap.scoring.weights' => [
        'activity' => 0.40,
        'freshness' => 0.30,
        'importance' => 0.30,
    ],
]);
```

### Migration failure

If score columns already exist, use `migrate:fresh --seed` in development only.

## Evidence

- Implementation: `app/Services/Scoring/ActivityScoreService.php`, `app/Services/Scoring/FreshnessScoreService.php`, `app/Services/Scoring/ImportanceScoreService.php`, `app/Services/Scoring/CompositeScoreCalculator.php`
- Commands: `app/Console/Commands/CalculateScores.php`, `app/Console/Commands/ResetScores.php`
- Configuration: `config/ledgerleap.php`
- Scheduler: `app/Console/Kernel.php`
- Livewire: `app/Livewire/Ledger/RecordsTable.php`
- Tests: `tests/Unit/Services/Scoring/`, `tests/Feature/Feature/Console/CalculateScoresCommandTest.php`, `tests/Feature/Feature/Console/CalculateScoresScheduleTest.php`
- Related docs: `docs/features/scoring-system.md`, `docs/development/MCP_Architecture_and_Flow.md`
