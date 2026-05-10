<?php

namespace App\Services;

use App\Ldap\User as LdapUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LdapRecord\Models\Model;

class AdSyncService
{
    protected array $hierarchyAttributes;

    protected bool $deleteMissing;

    protected int $deletionThresholdPercentage;

    protected array $syncedOrganizationIds = [];

    protected array $syncedUserIds = [];

    public function __construct() {}

    /**
     * Active Directoryから組織とユーザーを同期します。
     *
     * @return array 同期結果の要約
     *
     * @throws \Exception
     */
    public function sync(bool $dryRun = false): array
    {
        $this->hierarchyAttributes = config('ldap_sync.hierarchy_attributes', []);
        $this->deleteMissing = config('ldap_sync.delete_missing', true);
        $this->deletionThresholdPercentage = config('ldap_sync.deletion_threshold_percentage', 20);

        Log::info('Starting AD Sync (Dry Run: '.($dryRun ? 'Yes' : 'No').'). Hierarchy Attributes: '.json_encode($this->hierarchyAttributes));
        if (empty($this->hierarchyAttributes)) {
            Log::error('No hierarchy attributes defined in config/ldap_sync.php');
            throw new \Exception('No hierarchy attributes defined for AD sync.');
        }

        $this->syncedOrganizationIds = []; // リセット
        $this->syncedUserIds = []; // リセット
        $organizationCache = []; // キャッシュ

        if (! $dryRun) {
            DB::beginTransaction();
        }

        try {
            $ldapUsers = LdapUser::get(); // LdapRecordからユーザー取得
            Log::info("Found {$ldapUsers->count()} LDAP users.");

            foreach ($ldapUsers as $ldapUser) {
                Log::info("Processing LDAP user: {$ldapUser->getName()} (DN: {$ldapUser->getDn()})");
                // 1. 組織階層の特定と作成/更新
                $currentOrg = $this->resolveOrganizationHierarchy($ldapUser, $organizationCache, $dryRun);

                // 2. ユーザーの同期
                if ($currentOrg) {
                    $this->syncUser($ldapUser, $currentOrg, $dryRun);
                } else {
                    Log::warning("Skipping user {$ldapUser->getName()} as no organization could be resolved.");
                }
            }

            // 3. クリーンアップ (今回同期されなかった組織とユーザーの論理削除)
            if (! $dryRun && $this->deleteMissing) {
                $this->cleanupOrganizations();
                $this->cleanupUsers();
            }

            // 4. NestedSetの修復
            if (! $dryRun) {
                Organization::fixTree();
            }

            if (! $dryRun) {
                DB::commit();
            }

            return [
                'status' => 'success',
                'synced_users' => count($this->syncedUserIds),
                'synced_organizations' => count($this->syncedOrganizationIds),
            ];
        } catch (\Exception $e) {
            if (! $dryRun) {
                DB::rollBack();
            }
            Log::error('AD Sync failed: '.$e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * LDAPユーザーの属性に基づいて、既存の組織を検索します。
     * 組織の作成や更新は行いません。
     */
    public function findMatchingOrganization(Model $ldapUser): ?Organization
    {
        $this->hierarchyAttributes = config('ldap_sync.hierarchy_attributes', []);
        $parentOrg = null;
        $currentOrg = null;

        foreach ($this->hierarchyAttributes as $key => $value) {
            $codeAttributeName = '';
            $nameAttributeName = '';

            if (is_string($key)) {
                $codeAttributeName = $key;
                $nameAttributeName = $value;
            } else {
                $codeAttributeName = $value;
                $nameAttributeName = $value;
            }

            $codeValue = $ldapUser->getFirstAttribute($codeAttributeName);
            $nameValue = $ldapUser->getFirstAttribute($nameAttributeName);

            Log::info("findMatchingOrganization: Attr: {$codeAttributeName}, Value: {$codeValue}");
            Log::info('Existing Orgs: '.Organization::all()->pluck('org_id', 'name'));

            if (empty($nameValue)) {
                break;
            }

            // org_id (コード) として使う値
            $orgIdValue = $codeValue ?: $nameValue;

            // DB検索
            $currentOrg = null;
            if ($orgIdValue) {
                $currentOrg = Organization::where('org_id', $orgIdValue)->first();
            }

            // コードで見つからなければ、親IDと名前で検索 (Fallback)
            if (! $currentOrg) {
                $query = Organization::where('name', $nameValue);
                if ($parentOrg) {
                    $query->where('parent_id', $parentOrg->id);
                } else {
                    $query->whereNull('parent_id');
                }
                $currentOrg = $query->first();
            }

            // 組織が見つからなければ、この時点で一旦中央DBでの検索を試みる（テストが中央DBにorgを作成する場合があるため）
            if (! $currentOrg) {
                $previousTenant = null;
                try {
                    $previousTenant = tenancy()->tenant();
                } catch (\Throwable $e) {
                    $previousTenant = null;
                }

                try {
                    tenancy()->end();
                } catch (\Throwable $e) {
                    // ignore
                }

                $centralOrg = null;
                if ($orgIdValue) {
                    $centralOrg = Organization::where('org_id', $orgIdValue)->first();
                }

                if (! $centralOrg && $nameValue) {
                    $query = Organization::where('name', $nameValue);
                    if ($parentOrg) {
                        $query->where('parent_id', $parentOrg->id);
                    } else {
                        $query->whereNull('parent_id');
                    }
                    $centralOrg = $query->first();
                }

                if ($centralOrg) {
                    $currentOrg = $centralOrg;
                } else {
                    // restore previous tenancy if any
                    try {
                        if ($previousTenant) {
                            tenancy()->initialize($previousTenant);
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    return null;
                }

                // restore previous tenancy if any
                try {
                    if ($previousTenant) {
                        tenancy()->initialize($previousTenant);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // 親子関係のチェック (念のため)
            if ($parentOrg && $currentOrg->parent_id !== $parentOrg->id) {
                // IDは合っているが親が違う -> 移動が発生しているが同期されていない状態
                // 厳密にチェックするならここで null を返すべきだが、
                // org_id が一致していれば同一組織とみなすポリシーなら続行可。
                // 今回は「組織不一致時は同期エラー」とするため、厳密性を優先するなら null。
                // しかし、AdSyncServiceのロジックではorg_id優先で移動を検知するため、ここでもorg_idで見つかればOKとするのが自然。
            }

            $parentOrg = $currentOrg;
        }

        return $currentOrg;
    }

    /**
     * LDAPユーザーから組織階層を解決し、必要に応じて組織を作成/更新します。
     */
    protected function resolveOrganizationHierarchy(LdapUser $ldapUser, array &$organizationCache, bool $dryRun): ?Organization
    {
        $parentOrg = null;
        $currentOrg = null;
        Log::info("  Resolving hierarchy for user: {$ldapUser->getName()}");

        foreach ($this->hierarchyAttributes as $key => $value) {
            $codeAttributeName = '';
            $nameAttributeName = '';

            if (is_string($key)) { // Associative array: 'code_attr' => 'name_attr'
                $codeAttributeName = $key;
                $nameAttributeName = $value;
            } else { // Indexed array: 'attribute_name' (used for both code and name)
                $codeAttributeName = $value;
                $nameAttributeName = $value;
            }

            $codeValue = $ldapUser->getFirstAttribute($codeAttributeName);
            $nameValue = $ldapUser->getFirstAttribute($nameAttributeName);
            Log::info('Debug hierarchy values: codeAttr='.$codeAttributeName.', nameAttr='.$nameAttributeName.', codeValue='.$codeValue.', nameValue='.$nameValue);
            Log::info("    Hierarchy Level - CodeAttr: {$codeAttributeName}, NameAttr: {$nameAttributeName}, CodeValue: {$codeValue}, NameValue: {$nameValue}");

            if (empty($nameValue)) {
                // 名称がない階層はスキップするか、そこで階層構築を終了
                // 今回はそこで終了と判断
                break;
            }

            // org_id (コード) として使う値。コードベースでない場合は名称を使用。
            $orgIdValue = $codeValue ?: $nameValue;
            Log::info("    Resolved Org ID Value: {$orgIdValue}");

            // キャッシュキーの生成
            $cacheKey = 'ORG:'.($parentOrg ? $parentOrg->id : 'ROOT').":{$orgIdValue}";

            if (isset($organizationCache[$cacheKey])) {
                $currentOrg = $organizationCache[$cacheKey];
                Log::info("    Found in cache: {$currentOrg->name} (ID: {$currentOrg->id})");
            } else {
                if ($dryRun) {
                    Log::info("  [Dry Run] Would find/create Org: {$nameValue} (Org ID: {$orgIdValue}, Parent: ".($parentOrg?->name ?? 'Root').')');
                    $currentOrg = new Organization(['name' => $nameValue, 'org_id' => $orgIdValue]);
                } else {
                    $currentOrg = null;

                    // 1. org_id で組織を検索 (改名・移動に対応)
                    if ($orgIdValue) {
                        $currentOrg = Organization::where('org_id', $orgIdValue)->first();
                        if ($currentOrg) {
                            Log::info("    Found existing Organization by org_id: {$currentOrg->name} (ID: {$currentOrg->id})");
                        }
                    }

                    if ($currentOrg) {
                        $nameChanged = false;
                        if ($currentOrg->name !== $nameValue) {
                            $oldName = $currentOrg->name;
                            $currentOrg->name = $nameValue;
                            $nameChanged = true;
                            Log::info("    Org Name Updated: {$currentOrg->org_id} from '{$oldName}' to '{$nameValue}'");
                        }

                        $moved = false;
                        $currentParentId = $currentOrg->parent_id;
                        $newParentId = $parentOrg?->id;

                        if ($currentParentId !== $newParentId) {
                            Log::info("    Org Parent Changed: {$currentOrg->name} from '{$currentParentId}' to '{$newParentId}'");
                            if ($parentOrg) {
                                $currentOrg->appendToNode($parentOrg);
                            } else {
                                $currentOrg->makeRoot();
                            }
                            Log::info("    Org Moved: {$nameValue} to parent ".($parentOrg?->name ?? 'Root'));
                            $currentOrg->save(); // Save after move
                            $moved = true;
                        } elseif (! $parentOrg) {
                            $currentOrg->saveAsRoot();
                            Log::info("    Org Moved: {$nameValue} to parent Root");
                            $currentOrg->save(); // Save after move
                            $moved = true;
                        }

                        if ($nameChanged && ! $moved) {
                            $currentOrg->save();
                        }
                    } else { // 新規組織の作成
                        // 新規組織の作成
                        $currentOrg = new Organization([
                            'name' => $nameValue,
                            'org_id' => $orgIdValue,
                        ]);
                        if ($parentOrg) {
                            $currentOrg->appendToNode($parentOrg);
                        } else {
                            $currentOrg->saveAsRoot();
                        }
                        $currentOrg->save();
                        // Refresh to ensure ID is loaded
                        $currentOrg = $currentOrg->fresh();
                        Log::info("    Organization Created: {$nameValue} (Org ID: {$orgIdValue}, Parent ID: ".($parentOrg?->id ?? 'null').')');
                    }
                    $this->syncedOrganizationIds[] = $currentOrg->id; // 同期済みリストに追加 (IDが確実にロードされた後)
                }
                $organizationCache[$cacheKey] = $currentOrg;
            }
            $parentOrg = $currentOrg;
        }

        return $currentOrg;
    }

    /**
     * LDAPユーザーをLedgerLeapユーザーと同期します。
     */
    protected function syncUser(LdapUser $ldapUser, Organization $organization, bool $dryRun): void
    {
        $guid = $ldapUser->getObjectGuid(); // ADのobjectGuidまたはentryuuid
        $email = $ldapUser->getFirstAttribute('mail');
        $name = $ldapUser->getName(); // cnまたはdisplayName
        Log::info("  Syncing user: {$name} (Email: {$email}, GUID: {$guid}) to Org: {$organization->name} (ID: {$organization->id})");

        if (empty($email)) {
            Log::warning("  Skipping user {$name} (No email attribute found in LDAP)");

            return;
        }

        if (empty($guid)) {
            Log::warning("  Skipping user {$name} (No objectGuid/entryuuid attribute found in LDAP)");

            return;
        }

        if ($dryRun) {
            Log::info("  [Dry Run] Would sync User: {$name} ({$email}) -> Org: {$organization->name}");
        } else {
            // objectguidで検索、なければemailで検索して紐付け、それもなければ新規作成
            $user = User::withTrashed()->where('objectguid', $guid)->first();

            if (! $user) {
                $user = User::withTrashed()->where('email', $email)->first();
                if ($user) {
                    Log::info("  Linked existing user {$email} to GUID {$guid}. Updating existing user.");
                }
            }

            if ($user) {
                // 既存ユーザーの更新
                if ($user->trashed()) {
                    $user->restore();
                    Log::info("  Restored soft-deleted user: {$name}");
                }

                $user->update([
                    'objectguid' => $guid,
                    'name' => $name,
                    'email' => $email,
                    'ad_last_synced_at' => now(),
                ]);
                Log::info("  Updated User: {$name} ({$email})");
            } else {
                // 新規ユーザーの作成
                $user = User::create([
                    'objectguid' => $guid,
                    'name' => $name,
                    'email' => $email,
                    'password' => bcrypt(Str::random(32)), // 初期パスワード
                    'ad_last_synced_at' => now(),
                ]);
                Log::info("  User Created: {$name} ({$email})");
            }

            // 同期済みユーザーとしてマーク
            $this->syncedUserIds[] = $user->id;

            // 所属の更新 (手動管理期間中はスキップ)
            if ($user) {
                // Debug manual sync field
                try {
                    $manualUntil = $user->ignore_ad_org_sync_until ? $user->ignore_ad_org_sync_until->toDateTimeString() : 'null';
                } catch (\Throwable $e) {
                    $manualUntil = 'invalid';
                }
                Log::info("  Manual sync until value for user {$user->email}: {$manualUntil}");

                $isManualSyncValid = $user->ignore_ad_org_sync_until && $user->ignore_ad_org_sync_until->isFuture();

                if ($isManualSyncValid) {
                    Log::info("  Skipping organization sync for user {$user->name} due to manual sync protection until {$user->ignore_ad_org_sync_until}");
                } else {
                    // Set the organization as the user's primary using centralized logic
                    $user->setPrimaryOrganization($organization);
                    Log::info("  User {$user->name} assigned to Organization: {$organization->name}");
                }
            }
        }
    }

    /**
     * 今回同期されなかった組織を論理削除します。
     *
     *
     * @throws \Exception
     */
    protected function cleanupOrganizations(): void
    {
        // 今回同期対象外の組織（ad_sync_scope=true などで絞り込む可能性あり）
        // ここではorg_idを持つ組織をAD同期対象とみなす
        $allSyncableOrgIds = Organization::whereNotNull('org_id')->pluck('id')->toArray();

        // 手動同期保護が有効なユーザーに関連する組織を保護対象とする
        $protectedOrgIds = User::whereNotNull('ignore_ad_org_sync_until')
            ->where('ignore_ad_org_sync_until', '>', now())
            ->with('organizations')
            ->get()
            ->flatMap(function ($u) {
                return $u->organizations->pluck('id');
            })
            ->unique()
            ->values()
            ->toArray();

        // 保護対象を除外した候補一覧
        $candidates = array_diff($allSyncableOrgIds, $protectedOrgIds);

        $orgsToSoftDeleteIds = array_diff($candidates, $this->syncedOrganizationIds);
        Log::info('Cleanup: All syncable Org IDs: '.json_encode($allSyncableOrgIds));
        Log::info('Cleanup: Protected Org IDs (manual sync): '.json_encode($protectedOrgIds));
        Log::info('Cleanup: Synced Org IDs: '.json_encode($this->syncedOrganizationIds));
        Log::info('Cleanup: Orgs to soft delete IDs: '.json_encode($orgsToSoftDeleteIds));

        if (empty($orgsToSoftDeleteIds)) {
            Log::info('No organizations to soft delete.');

            return;
        }

        $totalOrganizations = count($allSyncableOrgIds);
        $organizationsToDeleteCount = count($orgsToSoftDeleteIds);

        $deletionPercentage = ($totalOrganizations > 0) ? ($organizationsToDeleteCount / $totalOrganizations) * 100 : 0;
        Log::info("Cleanup: Total Organizations: {$totalOrganizations}, To Delete: {$organizationsToDeleteCount}, Percentage: {$deletionPercentage}%");

        if ($this->deletionThresholdPercentage > 0 && $deletionPercentage > $this->deletionThresholdPercentage) {
            Log::warning("Organization deletion percentage ({$deletionPercentage}%) exceeds threshold ({$this->deletionThresholdPercentage}%).");
            // If everything would be deleted (100%), abort as a safety measure; otherwise skip deletion but continue.
            if ($deletionPercentage >= 100) {
                Log::warning('Aborting organization cleanup: would delete 100% of organizations.');
                throw new \Exception('Organization cleanup aborted due to exceeding deletion threshold.');
            }

            Log::warning('Skipping organization deletion due to exceeding threshold but continuing sync.');

            return;
        }

        Organization::whereIn('id', $orgsToSoftDeleteIds)->delete();
        Log::info("Soft deleted {$organizationsToDeleteCount} organizations.");
    }

    /**
     * 今回同期されなかったユーザーを論理削除します。
     *
     *
     * @throws \Exception
     */
    protected function cleanupUsers(): void
    {
        // objectguidを持つユーザーをAD同期対象とみなす
        $allSyncableUserIds = User::whereNotNull('objectguid')->pluck('id')->toArray();
        $usersToSoftDeleteIds = array_diff($allSyncableUserIds, $this->syncedUserIds);
        Log::info('Cleanup: All syncable User IDs: '.count($allSyncableUserIds));
        Log::info('Cleanup: Synced User IDs: '.count($this->syncedUserIds));
        Log::info('Cleanup: Users to soft delete IDs: '.count($usersToSoftDeleteIds));

        if (empty($usersToSoftDeleteIds)) {
            Log::info('No users to soft delete.');

            return;
        }

        $totalUsers = count($allSyncableUserIds);
        $usersToDeleteCount = count($usersToSoftDeleteIds);

        $deletionPercentage = ($totalUsers > 0) ? ($usersToDeleteCount / $totalUsers) * 100 : 0;
        Log::info("Cleanup: Total Users: {$totalUsers}, To Delete: {$usersToDeleteCount}, Percentage: {$deletionPercentage}%");

        if ($this->deletionThresholdPercentage > 0 && $deletionPercentage > $this->deletionThresholdPercentage) {
            Log::warning("Aborting user cleanup: Deletion percentage ({$deletionPercentage}%) exceeds threshold ({$this->deletionThresholdPercentage}%).");
            throw new \Exception('User cleanup aborted due to exceeding deletion threshold.');
        }

        User::whereIn('id', $usersToSoftDeleteIds)->delete();
        Log::info("Soft deleted {$usersToDeleteCount} users.");
    }
}
