<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case DRAFT = 'draft';                   // 作成中/編集中
    case PENDING_INSPECTION = 'pending_inspection'; // 点検待ち
    case PENDING_APPROVAL = 'pending_approval';     // 承認待ち
    case APPROVED = 'approved';               // 承認済み

    // (オプション) 日本語ラベル等が必要であれば追加
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('ledger.workflow_status.draft'),
            self::PENDING_INSPECTION => __('ledger.workflow_status.pending_inspection'),
            self::PENDING_APPROVAL => __('ledger.workflow_status.pending_approval'),
            self::APPROVED => __('ledger.workflow_status.approved'),
        };
    }
}
