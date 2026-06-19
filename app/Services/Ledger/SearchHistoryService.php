<?php

namespace App\Services\Ledger;

use App\Enums\WorkflowStatus;
use App\Helpers\SearchHelper;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\SynonymService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Manages ledger search history using ActivityLog as the backing store.
 *
 * Search conditions are stored as CustomActivity records with event='searched'.
 * This allows reuse of existing tenant-aware activity infrastructure while
 * keeping the door open to a dedicated search_histories table later.
 */
class SearchHistoryService
{
    public const EVENT_SEARCHED = 'searched';

    /**
     * Record a search execution.
     *
     * Duplicate conditions for the same user/tenant are merged by updating
     * the existing activity's timestamp instead of creating a new row.
     */
    public function record(array $conditions, int $resultCount, ?User $user = null, ?array $searchTrace = null, ?string $savedName = null): ?CustomActivity
    {
        $user ??= Auth::user();
        if (! $user) {
            return null;
        }

        $tenantId = tenant()?->id;
        $normalized = $this->normalizeConditions($conditions);

        $latest = $this->latestActivityFor($user, $tenantId);
        if ($latest && $this->normalizeConditions($latest->properties['conditions'] ?? []) === $normalized) {
            $latest->update([
                'updated_at' => now(),
                'properties->result_count' => $resultCount,
            ]);

            $activity = $latest->fresh();
        } else {
            $label = $this->buildLabel($conditions);

            $activity = CustomActivity::create([
                'log_name' => 'search',
                'description' => $savedName ?: $label,
                'subject_type' => null,
                'subject_id' => null,
                'event' => self::EVENT_SEARCHED,
                'causer_type' => User::class,
                'causer_id' => $user->id,
                'tenant_id' => $tenantId,
                'properties' => [
                    'search_type' => 'webui',
                    'is_saved' => ! empty($savedName),
                    'saved_name' => $savedName,
                    'conditions' => $conditions,
                    'result_count' => $resultCount,
                    'search_trace' => $searchTrace ?? [],
                ],
            ]);
        }

        // 検索キーワードを専用テーブルに記録
        $this->recordKeywords($conditions, $tenantId, $user);

        return $activity;
    }

    /**
     * Record search keywords to dedicated keyword analysis tables.
     */
    private function recordKeywords(array $conditions, ?string $tenantId, User $user): void
    {
        $queryText = $conditions['q'] ?? '';
        if ($queryText === '' || $tenantId === null) {
            return;
        }

        $queryText = SearchHelper::normalizeQuery($queryText);
        // DB 保存時に両端スペースは捨てる (検索クエリとして正規化)
        $queryText = SearchHelper::trimSearch($queryText);
        if ($queryText === '') {
            return;
        }

        $keywords = SynonymService::analyze($queryText);

        app(SearchKeywordService::class)->recordKeywords(
            query: $queryText,
            keywords: $keywords,
            tenantId: $tenantId,
            user: $user,
        );
    }

    /**
     * Get recent searches for the given user, optionally filtered to restorable ones.
     */
    public function getRecent(User $user, int|string|null $tenantId = null, int $limit = 5, bool $onlyRestorable = true): Collection
    {
        $query = CustomActivity::where('event', self::EVENT_SEARCHED)
            ->where('causer_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->latest()
            ->limit($limit);

        $activities = $query->get();

        if ($onlyRestorable) {
            $activities = $activities->filter(fn (CustomActivity $activity) => $this->canRestore($activity->properties['conditions'] ?? [], $user));
        }

        return $activities->values();
    }

    /**
     * Get saved searches for the given user.
     */
    public function getSaved(User $user, int|string|null $tenantId = null, int $limit = 20, bool $onlyRestorable = true): Collection
    {
        $query = CustomActivity::where('event', self::EVENT_SEARCHED)
            ->where('causer_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereJsonContains('properties->is_saved', true)
            ->orderBy('properties->saved_name')
            ->limit($limit);

        $activities = $query->get();

        if ($onlyRestorable) {
            $activities = $activities->filter(fn (CustomActivity $activity) => $this->canRestore($activity->properties['conditions'] ?? [], $user));
        }

        return $activities->values();
    }

    /**
     * Delete a search history entry if it belongs to the user.
     */
    public function delete(User $user, int $activityId): bool
    {
        $activity = CustomActivity::where('event', self::EVENT_SEARCHED)
            ->where('causer_id', $user->id)
            ->find($activityId);

        if (! $activity) {
            return false;
        }

        return $activity->delete();
    }

    /**
     * Check whether the given search conditions can still be restored by the user.
     */
    public function canRestore(array $conditions, ?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user) {
            return false;
        }

        $folderIds = array_filter(array_merge(
            (array) ($conditions['f'] ?? []),
            array_filter([(int) ($conditions['cf'] ?? 0)])
        ));

        if (empty($folderIds)) {
            return true;
        }

        $readableFolderIds = app(WritableFolderRepository::class)->getReadableFolderIds($user);

        return empty(array_diff($folderIds, $readableFolderIds));
    }

    /**
     * Build a short human-readable label from search conditions.
     */
    public function buildLabel(array $conditions): string
    {
        $parts = [];

        $query = $conditions['q'] ?? '';
        if (! empty($query)) {
            $parts[] = str_replace(["\r", "\n"], ' ', mb_strimwidth($query, 0, 40, '…'));
        }

        $status = $conditions['status'] ?? '';
        if (! empty($status)) {
            $statusEnum = WorkflowStatus::tryFrom($status);
            $parts[] = $statusEnum?->label() ?? $status;
        }

        if (! empty($conditions['f'])) {
            $folderNames = Folder::whereIn('id', (array) $conditions['f'])
                ->pluck('title')
                ->implode(', ');
            if ($folderNames) {
                $parts[] = $folderNames;
            }
        }

        $label = implode(' / ', $parts);

        return $label ?: __('ledger.search_suggest.empty_label');
    }

    /**
     * Normalize conditions so equivalent conditions produce the same JSON.
     */
    public function normalizeConditions(array $conditions): string
    {
        $normalized = $this->sortRecursive($conditions);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Sort array keys and values recursively for stable comparison.
     */
    private function sortRecursive(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = array_map([$this, 'sortRecursive'], $value);
            ksort($value);

            // Convert sequential integer-keyed arrays back to list form so
            // [1,2] and {0:1,1:2} compare equal after JSON encoding.
            if (array_keys($value) === range(0, count($value) - 1)) {
                $value = array_values($value);
            }
        }

        return $value;
    }

    private function latestActivityFor(User $user, int|string|null $tenantId): ?CustomActivity
    {
        return CustomActivity::where('event', self::EVENT_SEARCHED)
            ->where('causer_id', $user->id)
            ->latest()
            ->first();
    }
}
