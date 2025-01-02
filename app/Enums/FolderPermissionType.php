<?php

namespace App\Enums;

enum FolderPermissionType: string
{
    case READ = 'read';
    case WRITE = 'write';
    case ADMIN = 'admin';
    case DELETE = 'delete';

    public static function asSelectArray(): array
    {
        return [
            self::READ->value => __('ledger.permissions.read'),
            self::WRITE->value => __('ledger.permissions.write'),
            self::ADMIN->value => __('ledger.permissions.admin'),
            self::DELETE->value => __('ledger.permissions.delete'),
        ];
    }
}
