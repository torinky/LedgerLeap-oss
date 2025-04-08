<?php

namespace App\Models;

use App\Casts\AsColumnArrayJson;
use App\Casts\AsColumnDefinesArrayJson;
use App\Enums\WorkflowStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method create(array $array)
 */
class LedgerDiff extends Model
{
    use HasFactory;

    protected $casts = [
        'content' => AsColumnArrayJson::class,
        'column_define' => AsColumnDefinesArrayJson::class,
        // --- ワークフロー関連 ---
        'status' => WorkflowStatus::class, // Enum キャスト
        'requested_at' => 'datetime',
        'inspected_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    protected $fillable = [
        'content', 'ledger_id', 'column_define', 'ledger_define_id',
        'creator_id', 'modifier_id',
        // --- ワークフロー関連  ---
        'status',
        'inspector_id',
        'approver_id',
        'requested_at',
        'inspected_at',
        'approved_at',
        'returned_at',
        'comments',

        'created_at', 'updated_at',
    ];

    public function ledger()
    {
        return $this->hasOne(Ledger::class, 'ledger_id');
    }

    /**
     * LedgerDefine モデルへのリレーションを定義します。
     *
     * @return BelongsTo
     */
    public function define()
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }


    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
