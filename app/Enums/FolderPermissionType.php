<?php

namespace App\Enums;

use Illuminate\Support\Arr; // Arr ファサードを use

enum FolderPermissionType: string
{
    // アクセス権限 (包含関係を意識した順序で定義すると便利)
    case READ = 'read';
    case WRITE = 'write';
    case INSPECT = 'inspect'; // 追加
    case APPROVE = 'approve'; // 追加
    case ADMIN = 'admin';
    // case DELETE = 'delete'; // DELETE は ADMIN に含めるか、別の概念とするか？ -> ADMIN が包含すると考えるのが一般的か。一旦削除

    // 通知設定 (アクセス権限とは別の概念)
    case NOTIFY_ON = 'notify_on';
    case NOTIFY_OFF = 'notify_off';

    // 包含関係を定義 (上位が下位を含む)
    public const HIERARCHY = [
        self::ADMIN->value => [self::APPROVE->value, self::INSPECT->value, self::WRITE->value, self::READ->value],
        self::APPROVE->value => [self::INSPECT->value, self::WRITE->value, self::READ->value], // 承認者は点検・書き込み・読み込みも可能
        self::INSPECT->value => [self::WRITE->value, self::READ->value], // 点検者は書き込み・読み込みも可能
        self::WRITE->value => [self::READ->value], // 書き込み権限は読み込み権限を含む
        self::READ->value => [],
    ];

    public static function allPermissions(): array
    {
        return [
            self::READ,
            self::WRITE,
            self::INSPECT,
            self::APPROVE,
            self::ADMIN,
            self::NOTIFY_ON,
            self::NOTIFY_OFF,
        ];
    }

    public static function allPermissionValues(): array
    {
        return Arr::pluck(self::allPermissions(), 'value');
    }

    // アクセス権限のリストを返す静的メソッド
    public static function accessPermissions(): array
    {
        return [
            self::READ,
            self::WRITE,
            self::INSPECT,
            self::APPROVE,
            self::ADMIN,
        ];
    }

    // アクセス権限の Value 配列を返す
    public static function accessPermissionValues(): array
    {
        return Arr::pluck(self::accessPermissions(), 'value');
    }

    // 通知設定のリストを返す静的メソッド
    public static function notificationPermissions(): array
    {
        return [
            self::NOTIFY_ON,
            self::NOTIFY_OFF,
        ];
    }

    // 通知設定の Value 配列を返す
    public static function notificationPermissionValues(): array
    {
        return Arr::pluck(self::notificationPermissions(), 'value');
    }

    // アクセス権限の選択肢配列を返す (Filament Select/CheckboxList用)
    public static function asAccessSelectArray(): array
    {
        $options = [];
        foreach (self::accessPermissions() as $permission) {
            $options[$permission->value] = $permission->getLabel(); // getLabel() を使う
        }

        return $options;
        // または以下のように mapWithKeys でも可
        // return collect(self::accessPermissions())
        //     ->mapWithKeys(fn ($permission) => [$permission->value => $permission->getLabel()])
        //     ->all();
    }

    // 指定された権限タイプを含む（包含する）かどうか
    public function includes(FolderPermissionType $permissionType): bool
    {
        if ($this === $permissionType) {
            return true;
        }

        return in_array($permissionType->value, self::HIERARCHY[$this->value] ?? [], true);
    }

    // アクセス権限かどうか
    public function isAccessType(): bool
    {
        return in_array($this, self::accessPermissions(), true);
    }

    // 通知設定かどうか
    public function isNotificationType(): bool
    {
        return in_array($this, self::notificationPermissions(), true);
    }

    // ラベル取得 (翻訳キー使用)
    public function getLabel(): string
    {
        // 翻訳キー名を permission.name.* に統一 (推奨)
        return __('permission.name.'.$this->value);
        // または既存の ledger.permissions.* を使う場合
        // return __('ledger.permissions.' . $this->value);
    }

    // 色取得
    public function getColor(): string
    {
        return match ($this) {
            self::READ => 'neutral', // READ は基本なのでグレーに
            self::WRITE => 'info',
            self::INSPECT => 'warning', // 点検は注意喚起的な色？
            self::APPROVE => 'success', // 承認は成功色
            self::ADMIN => 'error', // ADMIN は強い権限なので赤系？ or primary?
            self::NOTIFY_ON, self::NOTIFY_OFF => 'primary', // 通知は別の色
            default => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::READ => 'o-eye',
            self::WRITE => 'o-pencil-square',
            self::INSPECT => 'o-magnifying-glass',
            self::APPROVE => 'o-check-badge',
            self::ADMIN => 'o-shield-check',
            self::NOTIFY_ON => 'o-lock-closed',
            self::NOTIFY_OFF => 'o-lock-closed',
            default => 'o-lock-closed',
        };
    }

    // 権限の強さ（順序）を返す（ソートや包含関係のロジックで使う）
    public function getOrder(): int
    {
        return match ($this) {
            self::READ => 1,
            self::WRITE => 2,
            self::INSPECT => 3,
            self::APPROVE => 4,
            self::ADMIN => 5,
            default => 0, // 通知などは 0
        };
    }
}
