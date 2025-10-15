<?php

namespace App\Models\ColumnTypes;

use App\Models\User;

class UserNameType implements InputType
{
    public array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'name_format' => 'full_name',
            'org_prefix' => 'none',
            'edit_mode' => 'overwrite',
        ], $options);
    }

    public function getName(): string
    {
        return 'user_name';
    }

    public function getLabel(): string
    {
        return __('ledger.form.user_name');
    }

    public function hasOptions(): bool
    {
        return true;
    }

    public function shouldConvertToJson(): bool
    {
        return false;
    }

    public function convertToText($value)
    {
        return (string) $value;
    }

    public function restoreFromString($value)
    {
        return (string) $value;
    }

    public function getValidationRules(): array
    {
        return ['string', 'max:255'];
    }

    /**
     * ユーザー情報から入力者名を生成
     */
    public function generateValue(User $user): string
    {
        $name = $this->formatUserName($user);
        $orgPrefix = $this->getOrganizationPrefix($user);

        return $orgPrefix ? "{$orgPrefix} {$name}" : $name;
    }

    /**
     * 編集時の値を生成（上書きまたは追加）
     */
    public function generateEditValue(User $user, ?string $currentValue): string
    {
        $newValue = $this->generateValue($user);

        if ($this->options['edit_mode'] === 'append' && ! empty($currentValue)) {
            $existingNames = array_map('trim', explode(',', $currentValue));
            if (! in_array($newValue, $existingNames, true)) {
                $existingNames[] = $newValue;

                return implode(', ', $existingNames);
            }

            return $currentValue;
        }

        return $newValue;
    }

    /**
     * ユーザー名をフォーマット
     */
    protected function formatUserName(User $user): string
    {
        if ($this->options['name_format'] === 'family_name_only') {
            // 苗字のみ（スペースで分割して最初の部分を取得）
            $nameParts = explode(' ', $user->name);

            return $nameParts[0];
        }

        // デフォルトはフルネーム
        return $user->name;
    }

    /**
     * 組織名プレフィックスを取得
     */
    protected function getOrganizationPrefix(User $user): ?string
    {
        if ($this->options['org_prefix'] === 'none') {
            return null;
        }

        $primaryOrg = $user->primaryOrganization;
        if (! $primaryOrg) {
            return null;
        }

        if ($this->options['org_prefix'] === 'bottom_only') {
            return $primaryOrg->name;
        }

        if ($this->options['org_prefix'] === 'bottom_3_levels') {
            $ancestors = [];
            $current = $primaryOrg;
            $depth = 0;

            // 最大3階層まで遡る
            while ($current && $depth < 3) {
                array_unshift($ancestors, $current->name);
                $current = $current->parent;
                $depth++;
            }

            return implode(' ', $ancestors);
        }

        return null;
    }
}
