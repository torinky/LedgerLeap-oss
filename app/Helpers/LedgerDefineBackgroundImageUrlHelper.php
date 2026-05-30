<?php

namespace App\Helpers;

class LedgerDefineBackgroundImageUrlHelper
{
    public static function downloadUrl(
        int $ledgerDefineId,
        int $columnId,
        ?string $tenantId = null,
        bool $thumbnail = false,
    ): string {
        $tenantId = $tenantId ?? (string) (tenant()?->id ?? '');

        if (! $tenantId) {
            return '';
        }

        $parameters = [
            'tenant' => $tenantId,
            'ledgerDefineId' => $ledgerDefineId,
            'columnId' => $columnId,
        ];

        if ($thumbnail) {
            $parameters['thumbnail'] = true;
        }

        return route('ledgerDefine.background-image', $parameters);
    }

    public static function thumbnailUrl(int $ledgerDefineId, int $columnId, ?string $tenantId = null): string
    {
        return self::downloadUrl($ledgerDefineId, $columnId, $tenantId, true);
    }
}
