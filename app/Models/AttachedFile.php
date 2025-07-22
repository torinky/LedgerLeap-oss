<?php

namespace App\Models;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\GenerateThumbnail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Bus;

class AttachedFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'filename', 'hashedbasename', 'ledger_define_id',
        'ledger_id', 'column_id', 'mime', 'path','size', 'status',
        'contain_content', 'optimized', 'creator_id', 'modifier_id', 'original_file_path', 'original_mime_type',
    ];

    protected $casts = [
        'optimized' => 'boolean',
        'status' => AttachedFileStatus::class,
    ];

    protected static function booted(): void
    {
        static::created(function (AttachedFile $attachedFile) {
            // サムネイル生成ジョブをディスパッチ
            Bus::dispatch(new GenerateThumbnail($attachedFile->id));
        });
    }

    public function getOriginalFilenameAttribute(): ?string
    {
        if ($this->ledger && $this->ledger->content) {
            $ledgerContent = $this->ledger->content; // json_decode() を削除
            foreach ($ledgerContent as $columnData) {
                if (isset($columnData[$this->hashedbasename])) {
                    return $columnData[$this->hashedbasename];
                }
            }
        }

        return null;
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function define(): BelongsTo
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    public function optimize()
    {
        //        $this->status = AttachedFileStatus::OPTIMIZING->value;
        // ファイルの最適化処理
        $this->status = AttachedFileStatus::OPTIMIZED->value;
    }

    public function extractMetadata()
    {
        //        $this->status = AttachedFileStatus::EXTRACTING->value;
        // メタデータ抽出処理
        $this->status = AttachedFileStatus::EXTRACTED_AND_SAVED->value;
    }
}