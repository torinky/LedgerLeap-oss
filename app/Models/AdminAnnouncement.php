<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Database model for an admin announcement banner.
 *
 * Supports draft/published/archived status lifecycle with scheduled
 * visibility windows (starts_at/ends_at). Each content change
 * regenerates a revision hash for dismissal tracking so that
 * previously dismissed users see updated content again.
 */
class AdminAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'creator_id',
        'modifier_id',
        'title',
        'body',
        'level',
        'status',
        'scope',
        'sticky',
        'priority',
        'starts_at',
        'ends_at',
        'published_at',
        'links',
        'revision',
    ];

    protected $casts = [
        'sticky' => 'bool',
        'priority' => 'int',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'published_at' => 'datetime',
        'scope' => 'array',
        'links' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $announcement): void {
            if (Auth::check()) {
                $announcement->creator_id = $announcement->creator_id ?? Auth::id();
                $announcement->modifier_id = $announcement->modifier_id ?? Auth::id();
            }
        });

        static::updating(function (self $announcement): void {
            if (Auth::check()) {
                $announcement->modifier_id = Auth::id();
            }
        });

        static::saving(function (self $announcement): void {
            if ($announcement->status === 'published' && ! $announcement->published_at) {
                $announcement->published_at = now();
            }

            $announcement->refreshRevision();
        });
    }

    /**
     * Whether the announcement is published and within its time window.
     */
    public function isCurrentlyVisible(?CarbonImmutable $now = null): bool
    {
        return $this->displayStatusKey($now) === 'published';
    }

    /**
     * Returns the effective display status based on the current time.
     *
     * Derives a presentation-level status from the stored status and
     * the starts_at/ends_at time window. Even when stored status is
     * `published`, the display status may be `scheduled` (before starts_at)
     * or `ended` (after ends_at).
     *
     * @return string One of: draft, published, scheduled, ended, archived
     */
    public function displayStatusKey(?CarbonImmutable $now = null): string
    {
        if ($this->status !== 'published') {
            return $this->status;
        }

        $now ??= CarbonImmutable::now();

        $startsAt = filled($this->starts_at) ? CarbonImmutable::parse($this->starts_at) : null;
        $endsAt = filled($this->ends_at) ? CarbonImmutable::parse($this->ends_at) : null;

        if ($startsAt && $startsAt->greaterThan($now)) {
            return 'scheduled';
        }

        if ($endsAt && $endsAt->lessThan($now)) {
            return 'ended';
        }

        return 'published';
    }

    /**
     * Regenerates the revision hash from the current content fields.
     *
     * The revision hash is used to construct the dismiss_storage_key.
     * When content changes, the key changes and previously dismissed
     * users see the updated banner again.
     */
    public function refreshRevision(): void
    {
        $this->revision = sha1(implode('|', [
            (string) $this->title,
            (string) $this->body,
            (string) $this->level,
            json_encode(array_values(array_filter((array) ($this->scope ?? []))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string) ((bool) $this->sticky ? 1 : 0),
            (string) $this->status,
            (string) optional($this->starts_at)->format('Y-m-d H:i:s'),
            (string) optional($this->ends_at)->format('Y-m-d H:i:s'),
            (string) $this->priority,
            json_encode($this->links ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }
}
