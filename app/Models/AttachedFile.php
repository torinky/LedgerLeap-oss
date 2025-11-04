<?php

namespace App\Models;

use App\Enums\AttachedFileStatus;
use App\Jobs\Ledger\GenerateThumbnail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class AttachedFile extends Model
{
    use HasFactory, SoftDeletes, \Stancl\Tenancy\Database\Concerns\BelongsToTenant;

    protected $fillable = [
        'filename', 'hashedbasename', 'ledger_define_id',
        'ledger_id', 'column_id', 'mime', 'path', 'size', 'status',
        'contain_content', 'optimized', 'creator_id', 'modifier_id', 'original_file_path', 'original_mime_type',
        'vlm_markdown', 'vlm_structured_data', 'vlm_model', 'vlm_confidence', 'vlm_processing_time_ms', 'vlm_processed_at',
    ];

    protected $casts = [
        'optimized' => 'boolean',
        'status' => AttachedFileStatus::class,
        'vlm_structured_data' => 'array',
        'vlm_processed_at' => 'datetime',
    ];

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

    public function retryProcessing(): void
    {
        $thumbnailFailed = ($this->status === AttachedFileStatus::THUMBNAIL_FAILED);

        // ステータスをPENDING_INITIAL_PROCESSINGにリセット
        $this->status = AttachedFileStatus::PENDING_INITIAL_PROCESSING;
        $this->save();

        // メインの処理ジョブを再ディスパッチ
        \App\Jobs\Ledger\ProcessAttachedFile::dispatch($this);

        // サムネイル生成に失敗していた場合、サムネイル生成ジョブも再ディスパッチ
        if ($thumbnailFailed) {
            Bus::dispatch(new GenerateThumbnail($this->id));
        }
    }

    public function hasVlmResult(): bool
    {
        return ! empty($this->vlm_markdown) && $this->status === AttachedFileStatus::COMPLETED;
    }

    public function isVlmProcessing(): bool
    {
        return $this->status === AttachedFileStatus::VLM_PROCESSING;
    }

    public function isVlmFailed(): bool
    {
        return $this->status === AttachedFileStatus::VLM_FAILED;
    }

    public function getPhysicalPath(): ?string
    {
        if (empty($this->path)) {
            return null;
        }

        if (Storage::disk('public')->exists($this->path)) {
            return Storage::disk('public')->path($this->path);
        }

        return null;
    }
}
