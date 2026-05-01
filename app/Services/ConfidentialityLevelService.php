<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;

class ConfidentialityLevelService
{
    /**
     * 設定ファイルから全秘密区分定義を取得
     */
    public static function allLevels(): array
    {
        return config('confidentiality.levels', []);
    }

    /**
     * コード値からレベル定義を取得
     */
    public static function levelDefinition(string $code): ?array
    {
        $levels = self::allLevels();

        return $levels[$code] ?? null;
    }

    /**
     * コード値から翻訳済みラベルを取得
     */
    public static function label(string $code): string
    {
        $def = self::levelDefinition($code);

        if ($def && isset($def['label_key'])) {
            return __($def['label_key']);
        }

        return $code;
    }

    /**
     * セレクトボックス用オプション配列
     */
    public static function selectOptions(): array
    {
        return collect(self::allLevels())->map(fn ($cfg, $code) => [
            'id' => $code,
            'name' => isset($cfg['label_key']) ? __($cfg['label_key']) : $code,
        ])->values()->all();
    }

    /**
     * DBから公開範囲選択肢を取得（組織・ロール統合リスト）
     */
    public static function allScopes(): array
    {
        $tenantId = tenant()?->id ?? 'global';
        $cacheKey = "confidentiality:{$tenantId}:scopes";
        $cacheTags = config('confidentiality.cache.tags', ['confidentiality', 'tenant_access']);
        $cacheTtl = config('confidentiality.cache.ttl', 3600);

        return Cache::tags($cacheTags)->remember($cacheKey, $cacheTtl, function () {
            $organizations = Organization::all()->map(fn ($org) => [
                'id' => "org:{$org->id}",
                'name' => $org->abbreviation ?? $org->name,
                'full' => $org->name,
                'type' => 'organization',
            ]);

            $roles = Role::all()->map(fn ($role) => [
                'id' => "role:{$role->id}",
                'name' => $role->abbreviation ?? $role->description ?? $role->name,
                'full' => $role->description ?? $role->name,
                'type' => 'role',
            ]);

            return $organizations->merge($roles)->values()->all();
        });
    }

    /**
     * 保存された scopes JSON から表示ラベル配列を取得
     *
     * Sprint 1 決定: JSON は {"org_ids":[{"id":1,"name":"人事部"}],"role_ids":[{"id":3,"name":"管理者"}]}
     * の形式で、保存時点の名前スナップショットを含む。
     * スナップショットがない場合は DB から取得する。
     */
    public static function scopeLabels(?array $scopes): array
    {
        if (empty($scopes)) {
            return [];
        }

        $labels = [];

        // 組織ラベル解決
        $orgData = $scopes['org_ids'] ?? [];
        foreach ($orgData as $org) {
            if (is_array($org) && isset($org['name'])) {
                $labels[] = $org['name'];
            } elseif (is_numeric($org)) {
                $model = Organization::find($org);
                $labels[] = $model?->abbreviation ?? $model?->name ?? "org:{$org}";
            }
        }

        // ロールラベル解決
        $roleData = $scopes['role_ids'] ?? [];
        foreach ($roleData as $role) {
            if (is_array($role) && isset($role['name'])) {
                $labels[] = $role['name'];
            } elseif (is_numeric($role)) {
                $model = Role::find($role);
                $labels[] = $model?->abbreviation ?? $model?->description ?? $model?->name ?? "role:{$role}";
            }
        }

        return $labels;
    }

    /**
     * フォルダ・台帳定義の秘密区分を解決（継承・上書き）
     *
     * Sprint 1 決定: Folder は ancestors()->reverse() で親を遡る
     */
    public static function resolve(Folder|LedgerDefine $model): ?array
    {
        if ($model instanceof LedgerDefine) {
            // LedgerDefine に直接設定がある場合
            if ($model->confidentiality_level) {
                return self::buildResolvedArray($model, 'ledger_define');
            }

            // 親 Folder を遡る
            $folder = $model->folder;
            while ($folder) {
                if ($folder->confidentiality_level) {
                    return self::buildResolvedArray($folder, 'folder', true);
                }
                $folder = $folder->parent;
            }

            // フォールバック: default_level
            return self::buildFallbackArray();
        }

        if ($model instanceof Folder) {
            // 自分自身から祖先を遡る
            $folder = $model;
            while ($folder) {
                if ($folder->confidentiality_level) {
                    return self::buildResolvedArray($folder, 'folder');
                }
                $folder = $folder->parent;
            }

            // フォールバック: default_level
            return self::buildFallbackArray();
        }

        return null;
    }

    /**
     * 継承を解決した最終的なレベル定義を取得
     */
    public static function getEffectiveLevel(Folder|LedgerDefine $model): array
    {
        $resolved = self::resolve($model);

        if (! $resolved) {
            $defaultLevel = config('confidentiality.default_level', 'public');

            return [
                'level' => $defaultLevel,
                'label' => self::label($defaultLevel),
                'scopes' => [],
                'source' => null,
                'inherited' => false,
            ];
        }

        $levelDef = self::levelDefinition($resolved['level']);

        return [
            'level' => $resolved['level'],
            'label' => self::label($resolved['level']),
            'color' => $levelDef['color'] ?? null,
            'scopes' => $resolved['scopes'] ?? [],
            'scope_labels' => self::scopeLabels($resolved['scopes'] ?? []),
            'source' => $resolved['source'] ?? null,
            'inherited' => $resolved['inherited'] ?? false,
        ];
    }

    /**
     * 解決結果配列を構築
     */
    private static function buildResolvedArray(Folder|LedgerDefine $model, string $type, bool $inherited = false): array
    {
        return [
            'level' => $model->confidentiality_level,
            'scopes' => $model->confidentiality_scopes,
            'source' => [
                'type' => $type,
                'name' => $model instanceof Folder ? $model->title : $model->title,
                'id' => $model->id,
            ],
            'inherited' => $inherited,
        ];
    }

    /**
     * フォールバック配列を構築
     */
    private static function buildFallbackArray(): array
    {
        $defaultLevel = config('confidentiality.default_level', 'public');

        return [
            'level' => $defaultLevel,
            'scopes' => [],
            'source' => null,
            'inherited' => false,
        ];
    }
}
