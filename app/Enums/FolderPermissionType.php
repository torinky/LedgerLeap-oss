<?php

namespace App\Enums;

enum FolderPermissionType: string
{
    case READ = 'read';
    case WRITE = 'write';
    case ADMIN = 'admin';
    case DELETE = 'delete';
    case NOTIFY_ON = 'notify_on';
    case NOTIFY_OFF = 'notify_off';

    public static function asSelectArray(): array
    {
        return [
            self::READ->value => __('ledger.permissions.read'),
            self::WRITE->value => __('ledger.permissions.write'),
            self::ADMIN->value => __('ledger.permissions.admin'),
            self::DELETE->value => __('ledger.permissions.delete'),
            self::NOTIFY_ON->value => __('ledger.permissions.notify_on'),
            self::NOTIFY_OFF->value => __('ledger.permissions.notify_off'),
        ];
    }

    // 追加: 通知設定のタイプかどうかを判定
    public function isNotificationType(): bool
    {
        return match ($this) {
            self::NOTIFY_ON, self::NOTIFY_OFF => true,
            default => false,
        };
    }

    // 追加: アクセス権限のタイプかどうかを判定 (必要に応じて)
    public function isAccessType(): bool
    {
        return !$this->isNotificationType();
    }
}
