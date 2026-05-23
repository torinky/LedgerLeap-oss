<?php

namespace App\Services;

use App\Models\AdminAnnouncement;
use Carbon\CarbonImmutable;

class AdminAnnouncementService
{
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    public function currentAnnouncement(): ?array
    {
        return $this->notificationCenterAnnouncements()[0] ?? null;
    }

    public function notificationCenterAnnouncements(): array
    {
        $announcements = AdminAnnouncement::query()
            ->where('status', 'published')
            ->orderByDesc('priority')
            ->orderBy('starts_at')
            ->orderByDesc('updated_at')
            ->get();

        if ($announcements->isNotEmpty()) {
            return $announcements
                ->values()
                ->map(
                    fn (AdminAnnouncement $announcement, int $index): array => $this->normalizeAnnouncement(
                        $announcement,
                        $index,
                    ),
                )
                ->filter(fn (array $announcement): bool => $this->isAnnouncementActive($announcement))
                ->all();
        }

        $feed = config('ledgerleap.announcement_banner.feed', []);
        $feed = is_array($feed) ? $feed : [];

        if ($feed === []) {
            $current = config('ledgerleap.announcement_banner.current');

            if (is_array($current) && ! empty($current)) {
                $feed = [$current];
            }
        }

        return collect($feed)
            ->filter(fn ($announcement): bool => is_array($announcement))
            ->map(
                fn (array $announcement, int $index): array => $this->normalizeAnnouncement(
                    $announcement,
                    $index,
                ),
            )
            ->filter(fn (array $announcement): bool => $this->isAnnouncementActive($announcement))
            ->filter(function (array $announcement): bool {
                if (($announcement['status'] ?? 'published') === 'draft') {
                    return false;
                }

                return filled($announcement['title']) || filled($announcement['body']);
            })
            ->sort(function (array $left, array $right): int {
                $priorityComparison = $right['priority'] <=> $left['priority'];
                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                $startsAtComparison = strcmp((string) ($left['starts_at'] ?? ''), (string) ($right['starts_at'] ?? ''));
                if ($startsAtComparison !== 0) {
                    return $startsAtComparison;
                }

                return $left['sort_index'] <=> $right['sort_index'];
            })
            ->values()
            ->all();
    }

    protected function isAnnouncementActive(array $announcement, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        $startsAt = filled($announcement['starts_at'] ?? null)
            ? CarbonImmutable::parse((string) $announcement['starts_at'])
            : null;
        $endsAt = filled($announcement['ends_at'] ?? null)
            ? CarbonImmutable::parse((string) $announcement['ends_at'])
            : null;

        return ($announcement['status'] ?? 'published') === 'published'
            && (! $startsAt || ! $startsAt->greaterThan($now))
            && (! $endsAt || ! $endsAt->lessThan($now));
    }

    protected function normalizeAnnouncement(array|AdminAnnouncement $announcement, int $sortIndex = 0): array
    {
        if ($announcement instanceof AdminAnnouncement) {
            return $this->normalizeModelAnnouncement($announcement, $sortIndex);
        }

        $links = collect($announcement['links'] ?? [])
            ->filter(fn ($link): bool => is_array($link))
            ->map(function (array $link): ?array {
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));

                if ($label === '' || $url === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $revision = (string) ($announcement['revision'] ?? $this->revisionFor($announcement, $links));
        $dismissStorageKey = $announcement['dismiss_storage_key']
            ?? sprintf('ledgerleap.admin_announcement_banner.dismissed:%s', $revision);

        return [
            'title' => (string) ($announcement['title'] ?? ''),
            'body' => (string) ($announcement['body'] ?? ''),
            'level' => $this->normalizeLevel($announcement['level'] ?? 'info'),
            'sticky' => (bool) ($announcement['sticky'] ?? false),
            'scope' => $this->normalizeScope($announcement['scope'] ?? ['current_tenant']),
            'status' => (string) ($announcement['status'] ?? 'published'),
            'priority' => (int) ($announcement['priority'] ?? 0),
            'starts_at' => $announcement['starts_at'] ?? null,
            'ends_at' => $announcement['ends_at'] ?? null,
            'published_at' => $announcement['published_at'] ?? $announcement['starts_at'] ?? null,
            'links' => $links,
            'revision' => $revision,
            'dismiss_storage_key' => $dismissStorageKey,
            'sort_index' => $sortIndex,
        ];
    }

    protected function normalizeModelAnnouncement(AdminAnnouncement $announcement, int $sortIndex = 0): array
    {
        $links = collect($announcement->links ?? [])
            ->filter(fn ($link): bool => is_array($link))
            ->map(function (array $link): ?array {
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));

                if ($label === '' || $url === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $revision = (string) ($announcement->revision ?: $this->revisionForModel($announcement, $links));

        return [
            'id' => $announcement->id,
            'title' => (string) $announcement->title,
            'body' => (string) $announcement->body,
            'level' => $this->normalizeLevel($announcement->level),
            'sticky' => (bool) $announcement->sticky,
            'scope' => $this->normalizeScope($announcement->scope ?? ['current_tenant']),
            'status' => (string) $announcement->status,
            'priority' => (int) $announcement->priority,
            'starts_at' => optional($announcement->starts_at)?->format(self::DATE_TIME_FORMAT),
            'ends_at' => optional($announcement->ends_at)?->format(self::DATE_TIME_FORMAT),
            'published_at' => optional($announcement->published_at)?->format(self::DATE_TIME_FORMAT),
            'links' => $links,
            'revision' => $revision,
            'dismiss_storage_key' => sprintf('ledgerleap.admin_announcement_banner.dismissed:%s', $revision),
            'sort_index' => $sortIndex,
        ];
    }

    protected function normalizeLevel(mixed $level): string
    {
        return in_array($level, ['info', 'warning', 'critical'], true) ? $level : 'info';
    }

    protected function revisionFor(array $announcement, array $links): string
    {
        $linkParts = collect($links)
            ->map(fn (array $link): string => ($link['label'] ?? '').'|'.($link['url'] ?? ''))
            ->implode('||');
        $scopeHash = json_encode(
            $this->normalizeScope($announcement['scope'] ?? ['current_tenant']),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $parts = [
            (string) ($announcement['title'] ?? ''),
            (string) ($announcement['body'] ?? ''),
            (string) ($announcement['level'] ?? 'info'),
            $scopeHash,
            (string) ((bool) ($announcement['sticky'] ?? false) ? 1 : 0),
            (string) ($announcement['status'] ?? 'published'),
            (string) ($announcement['starts_at'] ?? ''),
            (string) ($announcement['ends_at'] ?? ''),
            (string) ($announcement['priority'] ?? 0),
            $linkParts,
        ];

        return sha1(implode('|', $parts));
    }

    protected function revisionForModel(AdminAnnouncement $announcement, array $links): string
    {
        $linkParts = collect($links)
            ->map(fn (array $link): string => ($link['label'] ?? '').'|'.($link['url'] ?? ''))
            ->implode('||');
        $scopeHash = json_encode(
            $this->normalizeScope($announcement->scope ?? ['current_tenant']),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $parts = [
            (string) $announcement->title,
            (string) $announcement->body,
            (string) $announcement->level,
            $scopeHash,
            (string) ((bool) $announcement->sticky ? 1 : 0),
            (string) $announcement->status,
            (string) optional($announcement->starts_at)?->format(self::DATE_TIME_FORMAT),
            (string) optional($announcement->ends_at)?->format(self::DATE_TIME_FORMAT),
            (string) $announcement->priority,
            $linkParts,
        ];

        return sha1(implode('|', $parts));
    }

    protected function normalizeScope(mixed $scope): array
    {
        if (is_array($scope)) {
            $normalizedScope = $scope;
        } elseif (filled($scope)) {
            $normalizedScope = [(string) $scope];
        } else {
            $normalizedScope = [];
        }

        return array_values(array_filter(
            $normalizedScope,
            fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
    }
}
