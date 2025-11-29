<?php

namespace App\Console\Commands;

use App\Ldap\User as LdapUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ad:sync {--dry-run : Run the sync without making changes to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize users and organizations from Active Directory';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $mode = config('ldap_sync.mode', 'attribute');

        $this->info("Starting AD Sync (Mode: $mode, Dry Run: ".($dryRun ? 'Yes' : 'No').")...");

        if ($mode === 'attribute') {
            $this->syncAttributeBased($dryRun);
        } else {
            $this->error("Mode '$mode' is not yet implemented.");

            return 1;
        }

        $this->info("AD Sync completed.");

        return 0;
    }

    protected function syncAttributeBased(bool $dryRun)
    {
        $hierarchyAttributes = config('ldap_sync.hierarchy_attributes', []);
        if (empty($hierarchyAttributes)) {
            $this->error("No hierarchy attributes defined in config/ldap_sync.php");

            return;
        }

        $this->info("Fetching LDAP users...");
        $ldapUsers = LdapUser::get(); // フィルタはモデル側またはクエリで適用

        $this->info("Found {$ldapUsers->count()} users.");

        // Organization Cache: [ 'Path String' => Organization Model ]
        $orgCache = [];

        if (! $dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($ldapUsers as $ldapUser) {
                $this->info("Processing user: {$ldapUser->getName()}");

                // 1. 組織階層の特定と作成
                $orgPath = [];
                $parentOrg = null;
                $currentOrg = null;

                foreach ($hierarchyAttributes as $attr) {
                    $value = $ldapUser->getFirstAttribute($attr);

                    if (empty($value)) {
                        continue; // 値がない階層はスキップ
                    }

                    $orgPath[] = $value;
                    $pathKey = implode('>', $orgPath);

                    if (isset($orgCache[$pathKey])) {
                        $currentOrg = $orgCache[$pathKey];
                    } else {
                        // DB検索または作成
                        if ($dryRun) {
                            $this->info("  [Dry Run] Would create/find Organization: $value (Parent: ".($parentOrg?->name ?? 'Root').")");
                            // ダミーのモデルを入れておく
                            $currentOrg = new Organization(['name' => $value]);
                            // IDがないと次の階層検索で困るが、DryRunなので許容
                        } else {
                            // 名前と親IDで検索
                            $parentId = $parentOrg?->id;
                            $query = Organization::where('name', $value);
                            
                            if (is_null($parentId)) {
                                $query->whereNull('parent_id');
                            } else {
                                $query->where('parent_id', $parentId);
                            }
                            
                            $currentOrg = $query->first();

                            if (! $currentOrg) {
                                $currentOrg = new Organization([
                                    'name' => $value,
                                    'org_id' => (string) Str::uuid(),
                                ]);
                                if ($parentOrg) {
                                    $currentOrg->appendToNode($parentOrg)->save();
                                } else {
                                    $currentOrg->saveAsRoot();
                                }
                                $this->info("  Created Organization: $value");
                            }
                        }
                        $orgCache[$pathKey] = $currentOrg;
                    }
                    $parentOrg = $currentOrg;
                }

                // 2. ユーザーの同期
                $guid = $ldapUser->getObjectGuid();
                $email = $ldapUser->getFirstAttribute('mail');
                $name = $ldapUser->getFirstAttribute('cn');

                if (empty($email)) {
                    $this->warn("  Skipping user {$name} (No email)");

                    continue;
                }

                if ($dryRun) {
                    $this->info("  [Dry Run] Would sync User: $name ($email) -> Org: ".($currentOrg?->name ?? 'None'));
                } else {
                    // objectguid で検索、なければ email で検索して紐付け、それもなければ新規作成
                    $user = User::where('objectguid', $guid)->first();
                    
                    if (!$user) {
                        $user = User::where('email', $email)->first();
                        if ($user) {
                            $this->info("  Linked existing user {$email} to GUID {$guid}");
                        }
                    }

                    if ($user) {
                        $user->update([
                            'objectguid' => $guid,
                            'name' => $name,
                            'email' => $email,
                        ]);
                    } else {
                        $user = User::create([
                            'objectguid' => $guid,
                            'name' => $name,
                            'email' => $email,
                            'password' => bcrypt(Str::random(32)),
                        ]);
                        $this->info("  Created User: $name");
                    }

                    // 所属の更新
                    if ($currentOrg && $currentOrg->exists) {
                        if (! $user->organizations->contains($currentOrg->id)) {
                            // 既存のPrimaryを解除
                            $user->organizations()->updateExistingPivot($user->organizations->pluck('id'), ['is_primary' => false]);
                            // 新しい所属を追加してPrimaryに
                            $user->organizations()->syncWithoutDetaching([$currentOrg->id => ['is_primary' => true]]);
                            $this->info("  Assigned to Org: {$currentOrg->name}");
                        }
                    }
                }
            }

            if (! $dryRun) {
                // NestedSetの修復
                $this->info("Fixing tree structure...");
                Organization::fixTree();
                DB::commit();
            }

        } catch (\Exception $e) {
            if (! $dryRun) {
                DB::rollBack();
            }
            $this->error("Sync failed: ".$e->getMessage());
            Log::error("AD Sync failed", ['exception' => $e]);

            return 1;
        }
    }
}