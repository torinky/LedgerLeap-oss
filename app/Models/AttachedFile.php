<?php

namespace App\Models;

use App\Enums\AttachedFileStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachedFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename', 'hashedbasename', 'ledger_define_id', 'ledger_id', 'column_id', 'mime', 'path', 'status', 'contain_content', 'optimized', 'creator_id', 'modifier_id'
    ];

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function define(): BelongsTo
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = AttachedFileStatus::tryFrom($value)?->value;
    }

    public function getStatusAttribute($value)
    {
        return AttachedFileStatus::from($value);
    }

    public function optimize()
    {
//        $this->status = AttachedFileStatus::OPTIMIZING;
        // ファイルの最適化処理
        $this->status = AttachedFileStatus::OPTIMIZED;
    }

    public function extractMetadata()
    {
//        $this->status = AttachedFileStatus::EXTRACTING;
        // メタデータ抽出処理
        $this->status = AttachedFileStatus::EXTRACTED_AND_SAVED;
    }
}
