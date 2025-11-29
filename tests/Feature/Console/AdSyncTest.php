<?php

namespace Tests\Feature\Console;

use App\Ldap\User as LdapUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use Tests\TestCase;

class AdSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DirectoryEmulator::setup('default');
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    protected function clearLdapDirectory()
    {
        try {
            $users = LdapUser::get();
            foreach ($users as $user) {
                $user->delete();
            }
        } catch (\Exception $e) {
            // Ignore if empty or connection fails
        }
    }

    public function test_tc01_initial_sync_creates_organizations_with_codes_and_names()
    {
        // 階層属性を設定 (2階層: Company -> Department)
        Config::set('ldap_sync.hierarchy_attributes', [
            'company' => 'company', // Level 1
            'department' => 'description', // Level 2
        ]);

        $guid = '5C1A6E7D-1D1A-4F2A-A0A3-1A2B3C4D5E6F';

        LdapUser::create([
            'cn' => 'Philip J Fry',
            'mail' => 'fry@planetexpress.com',
            'objectguid' => $guid,
            'company' => 'Planet Express',
            'department' => 'Accounting',
            'description' => 'The Bean Counters',
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,ou=Planet Express,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $createdUser = User::where('email', 'fry@planetexpress.com')->first();
        $this->assertNotNull($createdUser, 'User should be synced');
        $this->assertEquals($guid, $createdUser->objectguid);

        $companyOrg = Organization::where('org_id', 'Planet Express')->first();
        $this->assertNotNull($companyOrg, 'Company Org should be created');
        $this->assertEquals('Planet Express', $companyOrg->name);

        $deptOrg = Organization::where('org_id', 'Accounting')->first();
        $this->assertNotNull($deptOrg, 'Department Org should be created');
        $this->assertEquals('The Bean Counters', $deptOrg->name);
        $this->assertEquals($companyOrg->id, $deptOrg->parent_id);

        $this->assertDatabaseHas('user_organizations', [
            'user_id' => $createdUser->id,
            'organization_id' => $deptOrg->id,
            'is_primary' => true,
        ]);
    }

    public function test_tc03_organization_name_is_updated_on_sync()
    {
        Config::set('ldap_sync.hierarchy_attributes', [
            'department' => 'description',
        ]);

        LdapUser::create([
            'cn' => 'Philip J Fry',
            'mail' => 'fry@planetexpress.com',
            'objectguid' => 'uuid-1',
            'department' => 'Accounting',
            'description' => 'The Bean Counters',
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $initialOrg = Organization::where('org_id', 'Accounting')->first();
        $this->assertNotNull($initialOrg);
        $this->assertEquals('The Bean Counters', $initialOrg->name);
        $initialOrgId = $initialOrg->id;

        // Reset LDAP to simulate AD change
        $this->clearLdapDirectory();

        LdapUser::create([
            'cn' => 'Philip J Fry',
            'mail' => 'fry@planetexpress.com',
            'objectguid' => 'uuid-1',
            'department' => 'Accounting',
            'description' => 'Financial Department', // Changed Name
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $updatedOrg = Organization::where('org_id', 'Accounting')->first();
        $this->assertNotNull($updatedOrg);
        $this->assertEquals('Financial Department', $updatedOrg->name);
        $this->assertEquals($initialOrgId, $updatedOrg->id);
    }

    public function test_tc04_organization_moves_on_sync()
    {
        Config::set('ldap_sync.hierarchy_attributes', [
            'company' => 'company',
            'department' => 'description',
        ]);
        Config::set('ldap_sync.deletion_threshold_percentage', 100);

        // Initial: Planet Express -> Accounting
        LdapUser::create([
            'cn' => 'Philip J Fry',
            'mail' => 'fry@planetexpress.com',
            'objectguid' => 'uuid-1',
            'company' => 'Planet Express',
            'department' => 'Accounting',
            'description' => 'The Bean Counters',
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,ou=Planet Express,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $planetExpressOrg = Organization::where('org_id', 'Planet Express')->first();
        $accountingOrg = Organization::where('org_id', 'Accounting')->first();
        $this->assertEquals($planetExpressOrg->id, $accountingOrg->parent_id);

        // Move: MomCorp -> Accounting
        $this->clearLdapDirectory();

        LdapUser::create([
            'cn' => 'Philip J Fry',
            'mail' => 'fry@planetexpress.com',
            'objectguid' => 'uuid-1',
            'company' => 'MomCorp', // New Parent
            'department' => 'Accounting',
            'description' => 'The Bean Counters',
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,ou=MomCorp,dc=local,dc=com'
        ]);

        $exitCode = Artisan::call('ad:sync');
        $this->assertEquals(0, $exitCode, 'AD Sync failed: ' . Artisan::output());

        $momCorpOrg = Organization::where('org_id', 'MomCorp')->first();
        $accountingOrgUpdated = Organization::where('org_id', 'Accounting')->first();

        $this->assertNotNull($momCorpOrg);
        $this->assertEquals($momCorpOrg->id, $accountingOrgUpdated->parent_id);
    }

    public function test_tc05_missing_organizations_are_soft_deleted()
    {
        Config::set('ldap_sync.hierarchy_attributes', ['department' => 'description']);
        Config::set('ldap_sync.delete_missing', true);
        Config::set('ldap_sync.deletion_threshold_percentage', 100);

        LdapUser::create([
            'cn' => 'Fry', 'mail' => 'fry@ex.com', 'objectguid' => 'uuid-1',
            'department' => 'Accounting', 'description' => 'Accounting',
        ]);
        Artisan::call('ad:sync');
        $this->assertDatabaseHas('organizations', ['org_id' => 'Accounting', 'deleted_at' => null]);

        // Empty LDAP
        $this->clearLdapDirectory();

        Artisan::call('ad:sync');

        $this->assertSoftDeleted('organizations', ['org_id' => 'Accounting']);
    }

    public function test_tc06_deletion_threshold_prevents_mass_deletion()
    {
        Config::set('ldap_sync.hierarchy_attributes', ['department' => 'description']);
        Config::set('ldap_sync.delete_missing', true);
        Config::set('ldap_sync.deletion_threshold_percentage', 10);

        // Create 3 Users in 3 different Orgs
        LdapUser::create(['cn' => 'A', 'mail' => 'a@ex.com', 'objectguid' => 'u1', 'department' => 'OrgA', 'description' => 'Org A']);
        LdapUser::create(['cn' => 'B', 'mail' => 'b@ex.com', 'objectguid' => 'u2', 'department' => 'OrgB', 'description' => 'Org B']);
        LdapUser::create(['cn' => 'C', 'mail' => 'c@ex.com', 'objectguid' => 'u3', 'department' => 'OrgC', 'description' => 'Org C']);

        Artisan::call('ad:sync');
        $this->assertEquals(3, Organization::count());

        // Empty LDAP
        $this->clearLdapDirectory();

        // The command catches the exception and returns 1
        $exitCode = Artisan::call('ad:sync');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Organization cleanup aborted due to exceeding deletion threshold.', $output);

        $this->assertEquals(3, Organization::count());
    }

    public function test_tc07_fixtree_repairs_nestedset_integrity()
    {
        Config::set('ldap_sync.hierarchy_attributes', ['department' => 'description']);
        Config::set('ldap_sync.deletion_threshold_percentage', 100);

        // Create directly
        DB::table('organizations')->insert([
            ['id' => 1, 'name' => 'Root', 'org_id' => 'ROOT', 'created_at' => now(), 'updated_at' => now(), '_lft' => 1, '_rgt' => 6, 'parent_id' => null],
            ['id' => 2, 'name' => 'Child', 'org_id' => 'CHILD', 'created_at' => now(), 'updated_at' => now(), '_lft' => 2, '_rgt' => 3, 'parent_id' => 1],
        ]);
        // Break it
        DB::table('organizations')->where('org_id', 'CHILD')->update(['_lft' => 0, '_rgt' => 0]);

        // Sync (User in Child)
        LdapUser::create(['cn' => 'U', 'mail' => 'u@ex.com', 'objectguid' => 'u1', 'department' => 'CHILD', 'description' => 'Child']);

        $exitCode = Artisan::call('ad:sync');
        $this->assertEquals(0, $exitCode, 'AD Sync failed: ' . Artisan::output());

        $repaired = Organization::where('org_id', 'CHILD')->first();
        $this->assertNotEquals(0, $repaired->_lft);
        $this->assertNotEquals(0, $repaired->_rgt);
    }
}
