<?php

namespace App\Services;

use App\Ldap\User as LdapUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdSyncService
{
    protected array $hierarchyAttributes;

    protected bool $deleteMissing;

    protected int $deletionThresholdPercentage;

    protected array $syncedOrganizationIds = [];

    public function __construct()
    {
        $this->hierarchyAttributes = config('ldap_sync.hierarchy_attributes', []);
        $this->deleteMissing = config('ldap_sync.delete_missing', true);
        $this->deletionThresholdPercentage = config('ldap_sync.deletion_threshold_percentage', 20);
    }

    /**
     * Active Directoryから組織とユーザーを同期します。
     *
     * @param  bool  $dryRun
     * @return array 同期結果の要約
     *
     * @throws \Exception
     */
    public function sync(bool $dryRun = false): array
    {
        Log::info("Starting AD Sync (Dry Run: " . ($dryRun ? 'Yes' : 'No') . "). Hierarchy Attributes: " . json_encode($this->hierarchyAttributes));
        if (empty($this->hierarchyAttributes)) {
            Log::error("No hierarchy attributes defined in config/ldap_sync.php");
            throw new \Exception("No hierarchy attributes defined for AD sync.");
        }

        $this->syncedOrganizationIds = []; // リセット
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

            // 3. クリーンアップ (今回同期されなかった組織の論理削除)
            if (! $dryRun && $this->deleteMissing) {
                $this->cleanupOrganizations();
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
                'synced_users' => $ldapUsers->count(),
                'synced_organizations' => count($this->syncedOrganizationIds),
            ];
        } catch (\Exception $e) {
            if (! $dryRun) {
                DB::rollBack();
            }
            Log::error("AD Sync failed: ".$e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * LDAPユーザーから組織階層を解決し、必要に応じて組織を作成/更新します。
     *
     * @param  LdapUser  $ldapUser
     * @param  array  $organizationCache
     * @param  bool  $dryRun
     * @return Organization|null
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
            $cacheKey = "ORG:".($parentOrg ? $parentOrg->id : 'ROOT').":{$orgIdValue}";

            if (isset($organizationCache[$cacheKey])) {
                $currentOrg = $organizationCache[$cacheKey];
                Log::info("    Found in cache: {$currentOrg->name} (ID: {$currentOrg->id})");
            } else {
                if ($dryRun) {
                    Log::info("  [Dry Run] Would find/create Org: {$nameValue} (Org ID: {$orgIdValue}, Parent: ".($parentOrg?->name ?? 'Root').")");
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
                            }
                            Log::info("    Org Moved: {$nameValue} to parent " . ($parentOrg?->name ?? 'Root'));
                            $currentOrg->save(); // Save after move
                            $moved = true;
                        } else if (!$parentOrg) {
                            $currentOrg->saveAsRoot();
                            Log::info("    Org Moved: {$nameValue} to parent Root");
                            $currentOrg->save(); // Save after move
                            $moved = true;
                        }

                        if ($nameChanged && !$moved) {
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
                        Log::info("    Organization Created: {$nameValue} (Org ID: {$orgIdValue}, Parent ID: ".($parentOrg?->id ?? 'null').")");
                    }
                    $this->syncedOrganizationIds[] = $currentOrg->id; // 同期済みリストに追加
                }
                $organizationCache[$cacheKey] = $currentOrg;
            }
            $parentOrg = $currentOrg;
        }

        return $currentOrg;
    }

    /**
     * LDAPユーザーをLedgerLeapユーザーと同期します。
     *
     * @param  LdapUser  $ldapUser
     * @param  Organization  $organization
     * @param  bool  $dryRun
     * @return void
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
            $user = User::where('objectguid', $guid)->first();

            if (! $user) {
                $user = User::where('email', $email)->first();
                if ($user) {
                    Log::info("  Linked existing user {$email} to GUID {$guid}. Updating existing user.");
                }
            }

            if ($user) {
                // 既存ユーザーの更新
                $user->update([
                    'objectguid' => $guid,
                    'name' => $name,
                    'email' => $email,
                ]);
                Log::info("  Updated User: {$name} ({$email})");
            } else {
                // 新規ユーザーの作成
                $user = User::create([
                    'objectguid' => $guid,
                    'name' => $name,
                    'email' => $email,
                    'password' => bcrypt(Str::random(32)), // 初期パスワード
                ]);
                Log::info("  User Created: {$name} ({$email})");
            }

            // 所属の更新
            if ($user) {
                // 現在のPrimary組織を解除し、新しい組織をPrimaryに設定
                $user->organizations()->updateExistingPivot($user->organizations()->pluck('organization_id'), ['is_primary' => false]);
                $user->organizations()->syncWithoutDetaching([$organization->id => ['is_primary' => true]]);
                Log::info("  User {$user->name} assigned to Organization: {$organization->name}");
            }
        }
    }

    /**
     * 今回同期されなかった組織を論理削除します。
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function cleanupOrganizations(): void
    {
        // 今回同期対象外の組織（ad_sync_scope=true などで絞り込む可能性あり）
        // ここではorg_idを持つ組織をAD同期対象とみなす
        $allSyncableOrgIds = Organization::whereNotNull('org_id')->pluck('id')->toArray();
        $orgsToSoftDeleteIds = array_diff($allSyncableOrgIds, $this->syncedOrganizationIds);
        Log::info("Cleanup: All syncable Org IDs: " . json_encode($allSyncableOrgIds));
        Log::info("Cleanup: Synced Org IDs: " . json_encode($this->syncedOrganizationIds));
        Log::info("Cleanup: Orgs to soft delete IDs: " . json_encode($orgsToSoftDeleteIds));

        if (empty($orgsToSoftDeleteIds)) {
            Log::info("No organizations to soft delete.");
            return;
        }

        $totalOrganizations = count($allSyncableOrgIds);
        $organizationsToDeleteCount = count($orgsToSoftDeleteIds);

        $deletionPercentage = ($totalOrganizations > 0) ? ($organizationsToDeleteCount / $totalOrganizations) * 100 : 0;
        Log::info("Cleanup: Total Organizations: {$totalOrganizations}, To Delete: {$organizationsToDeleteCount}, Percentage: {$deletionPercentage}%");

        if ($this->deletionThresholdPercentage > 0 && $deletionPercentage > $this->deletionThresholdPercentage) {
            Log::warning("Aborting organization cleanup: Deletion percentage ({$deletionPercentage}%) exceeds threshold ({$this->deletionThresholdPercentage}%).");
            throw new \Exception("Organization cleanup aborted due to exceeding deletion threshold.");
        }

        Organization::whereIn('id', $orgsToSoftDeleteIds)->delete();
        Log::info("Soft deleted {$organizationsToDeleteCount} organizations.");
    }
}