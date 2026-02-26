<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerChunk extends Model
{
    protected $fillable = [
        'ledger_id',
        'ledger_define_id',
        'folder_id',
        'attached_file_id',
        'chunk_index',
        'chunk_text',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function attachedFile(): BelongsTo
    {
        return $this->belongsTo(AttachedFile::class);
    }

    public function ledgerDefine(): BelongsTo
    {
        return $this->belongsTo(LedgerDefine::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
