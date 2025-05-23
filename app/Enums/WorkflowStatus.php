<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case NONE = 'none';                     // ワークフロー非適用 (または Not Applicable)
    case DRAFT = 'draft';                   // 作成中/編集中
    case PENDING_INSPECTION = 'pending_inspection'; // 点検待ち
    case PENDING_APPROVAL = 'pending_approval';     // 承認待ち
    case APPROVED = 'approved';               // 承認済み

    // (オプション) 日本語ラベル等が必要であれば追加
    public function label(): string
    {
        return match ($this) {
            self::NONE => __('ledger.workflow.status.none'), // 新規追加
            self::DRAFT => __('ledger.workflow.status.draft'),
            self::PENDING_INSPECTION => __('ledger.workflow.status.pending_inspection'),
            self::PENDING_APPROVAL => __('ledger.workflow.status.pending_approval'),
            self::APPROVED => __('ledger.workflow.status.approved'),
        };
    }

    /**
     * ステータスに応じた DaisyUI/Tailwind の色クラスを返すメソッド (新規追加)
     * 例: badge-warning, badge-info, badge-success など
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::NONE => 'badge-ghost', // 目立たない色
            self::DRAFT => 'badge-ghost', // 下書きは目立たない色
            self::PENDING_INSPECTION => 'badge-warning', // 点検待ちは警告色
            self::PENDING_APPROVAL => 'badge-info',    // 承認待ちは情報色
            self::APPROVED => 'badge-success',  // 承認済みは成功色
            // default => 'badge-secondary', // 万が一の場合のデフォルト
        };
    }

    /**
     * このステータスが承認済みかどうかを判定する
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    // ワークフロー対象の状態か判定するヘルパー (新規追加)
    public function isWorkflowActive(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::PENDING_INSPECTION,
            self::PENDING_APPROVAL,
            self::APPROVED // 承認済みもワークフローの結果
        ]);
    }

    // ワークフローが現在進行中か判定するヘルパー (新規追加)
    public function isWorkflowPending(): bool
    {
        return in_array($this, [
            self::PENDING_INSPECTION,
            self::PENDING_APPROVAL
        ]);
    }
}
