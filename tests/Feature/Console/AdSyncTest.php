<?php

namespace Tests\Feature\Console;

use App\Ldap\OrganizationalUnit as LdapOu;
use App\Ldap\User as LdapUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use Tests\TestCase;

class AdSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DirectoryEmulator::setup(); // LDAPエミュレータを起動

        // ログを無効化（テストの出力を見やすくするため）
        // Log::partialMock()->shouldReceive('info', 'warning', 'error')->andReturnNull();
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    /**
     * TC-01: 初回同期 - コードと名称を持つ属性セットを与える。
     * 指定通りの階層構造で Organization が作成され、org_id が保存されること。
     *
     * @return void
     */
    public function test_tc01_initial_sync_creates_organizations_with_codes_and_names()
    {
        // 階層属性を設定 (コードと名称のペア)
        Config::set('ldap_sync.hierarchy_attributes', [
            'ou' => 'description', // 'ou'をコード、'description'を名称として利用
        ]);

        // LDAPにユーザーとOUをエミュレート
        // LdapOu::create()は distinguishedname を使って階層を構築
        LdapOu::create([
            'cn' => ['People of Interest'],
            'description' => ['Interesting Folks'],
            'objectguid' => ['{4E8B077A-A14F-455B-A23F-8C3B1E4A3B2C}'],
            'ou' => ['People of Interest'],
            'distinguishedname' => 'ou=People of Interest,dc=local,dc=com'
        ]);
        LdapOu::create([
            'cn' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'objectguid' => ['{1C7D1E9B-736E-4A63-8E5A-67E9F45A3B12}'],
            'ou' => ['Accounting'],
            'distinguishedname' => 'ou=Accounting,ou=People of Interest,dc=local,dc=com'
        ]);
        $ldapFry = LdapUser::create([
            'cn' => ['Philip J Fry'],
            'mail' => ['fry@planetexpress.com'],
            'objectguid' => ['{5C1A6E7D-1D1A-4F2A-A0A3-1A2B3C4D5E6F}'], // Reverted to objectguid
            'ou' => ['Accounting'], // 組織コードとして使用
            'description' => ['The Bean Counters'], // 組織名として使用
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,ou=People of Interest,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $createdUser = User::where('email', 'fry@planetexpress.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('{5C1A6E7D-1D1A-4F2A-A0A3-1A2B3C4D5E6F}', $createdUser->objectguid);


        $accountingOrg = Organization::where('org_id', 'Accounting')->first();
        $this->assertNotNull($accountingOrg);
        $this->assertEquals('The Bean Counters', $accountingOrg->name);

        $peopleOfInterestOrg = Organization::where('org_id', 'People of Interest')->first();
        $this->assertNotNull($peopleOfInterestOrg);
        $this->assertEquals('Interesting Folks', $peopleOfInterestOrg->name);
        $this->assertEquals($peopleOfInterestOrg->id, $accountingOrg->parent_id);


        $this->assertDatabaseHas('user_organizations', [
            'user_id' => User::where('email', 'fry@planetexpress.com')->first()->id,
            'organization_id' => $accountingOrg->id,
            'is_primary' => true,
        ]);
    }

    /**
     * TC-02: ユーザー紐付 - ユーザー属性に対応する組織を作成後、ユーザーを紐付ける。
     * users テーブルにレコードが作成され、正しい Organization と is_primary=1 で紐付くこと。
     * (TC-01でカバーされるため、追加テストは不要な場合が多いが、明示的に記述)
     *
     * @return void
     */
    public function test_tc02_user_is_assigned_to_correct_organization()
    {
        // TC-01のセットアップを再利用
        $this->test_tc01_initial_sync_creates_organizations_with_codes_and_names();

        $user = User::where('email', 'fry@planetexpress.com')->first();
        $accountingOrg = Organization::where('org_id', 'Accounting')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($accountingOrg);
        $this->assertTrue($user->organizations->contains($accountingOrg));
        $this->assertTrue($user->organizations()->where('organization_id', $accountingOrg->id)->first()->pivot->is_primary);
    }

    /**
     * TC-03: 名称変更 - 既存組織のコードは同じままで、AD側の名称属性を変更して同期。
     * Organization の name が更新され、id や org_id は維持されること。
     *
     * @return void
     */
    public function test_tc03_organization_name_is_updated_on_sync()
    {
        Config::set('ldap_sync.hierarchy_attributes', [
            'ou' => 'description',
        ]);

        LdapOu::create([
            'cn' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'objectguid' => ['{1C7D1E9B-736E-4A63-8E5A-67E9F45A3B12}'],
            'ou' => ['Accounting'],
            'distinguishedname' => 'ou=Accounting,dc=local,dc=com'
        ]);
        LdapUser::create([
            'cn' => ['Philip J Fry'],
            'mail' => ['fry@planetexpress.com'],
            'objectguid' => ['{5C1A6E7D-1D1A-4F2A-A0A3-1A2B3C4D5E6F}'],
            'ou' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $initialOrg = Organization::where('org_id', 'Accounting')->first();
        $this->assertNotNull($initialOrg);
        $this->assertEquals('The Bean Counters', $initialOrg->name);
        $initialOrgId = $initialOrg->id;

        // AD側で名称を変更: 'Accounting' -> 'Financial Department'
        DirectoryEmulator::setup(); // エミュレータをリセット
        LdapOu::create([
            'cn' => ['Accounting'],
            'description' => ['Financial Department'],
            'objectguid' => ['{1C7D1E9B-736E-4A63-8E5A-67E9F45A3B12}'],
            'ou' => ['Accounting'],
            'distinguishedname' => 'ou=Accounting,dc=local,dc=com'
        ]);
        LdapUser::create([
            'cn' => ['Philip J Fry'],
            'mail' => ['fry@planetexpress.com'],
            'objectguid' => ['{5C1A6E7D-1D1A-4F2A-A0A3-1A2B3C4D5E6F}'],
            'ou' => ['Accounting'],
            'description' => ['Financial Department'],
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $updatedOrg = Organization::where('org_id', 'Accounting')->first();
        $this->assertNotNull($updatedOrg);
        $this->assertEquals('Financial Department', $updatedOrg->name); // 名称が更新されていること
        $this->assertEquals($initialOrgId, $updatedOrg->id); // IDは変わらないこと
    }

    /**
     * TC-04: 組織移動 - 既存組織 (子) の親となる属性を変更して同期。
     * 当該組織の parent_id が新しい親組織のIDに更新され、ツリー構造が移動すること。
     *
     * @return void
     */
    public function test_tc04_organization_moves_on_sync()
    {
        Config::set('ldap_sync.hierarchy_attributes', [
            'company' => 'company',
            'ou' => 'description',
        ]);

        // 初期状態: Planet Express -> Accounting
        LdapOu::create([
            'cn' => ['Planet Express'],
            'company' => ['Planet Express'],
            'description' => ['Planet Express Inc.'],
            'ou' => ['Planet Express'],
            'distinguishedname' => 'ou=Planet Express,dc=local,dc=com'
        ]);
        LdapOu::create([
            'cn' => ['Accounting'],
            'ou' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'objectguid' => ['{1C7D1E9B-736E-4A63-8E5A-67E9F45A3B12}'],
            'distinguishedname' => 'ou=Accounting,ou=Planet Express,dc=local,dc=com'
        ]);
        LdapUser::create([
            'cn' => ['Philip J Fry'],
            'mail' => ['fry@planetexpress.com'],
            'objectguid' => ['{5C1A6E7D-1D1A-4F2A-A0A3-1A2B3C4D5E6F}'],
            'company' => ['Planet Express'],
            'ou' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,ou=Planet Express,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $planetExpressOrg = Organization::where('org_id', 'Planet Express')->first();
        $accountingOrg = Organization::where('org_id', 'Accounting')->first();
        $this->assertNotNull($planetExpressOrg);
        $this->assertNotNull($accountingOrg);
        $this->assertEquals($planetExpressOrg->id, $accountingOrg->parent_id);

        // AD側で親を変更: Accounting を MomCorp の子に移動
        DirectoryEmulator::setup(); // エミュレータをリセット
        LdapOu::create([
            'cn' => ['MomCorp'],
            'company' => ['MomCorp'],
            'description' => ['MomCorp Inc.'],
            'ou' => ['MomCorp'],
            'distinguishedname' => 'ou=MomCorp,dc=local,dc=com'
        ]);
        LdapOu::create([
            'cn' => ['Accounting'],
            'ou' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'objectguid' => ['{1C7D1E9B-736E-4A63-8E5A-67E9F45A3B12}'], // objectguidは同じ
            'distinguishedname' => 'ou=Accounting,ou=MomCorp,dc=local,dc=com' // 親DNが変わる
        ]);
        LdapUser::create([
            'cn' => ['Philip J Fry'],
            'mail' => ['fry@planetexpress.com'],
            'objectguid' => ['{5C1A6E7D-1D1A-4F2A-A0A3-1A2B3C4D5E6F}'],
            'company' => ['MomCorp'], // 親の会社名も変更
            'ou' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,ou=MomCorp,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $momCorpOrg = Organization::where('org_id', 'MomCorp')->first();
        $accountingOrgUpdated = Organization::where('org_id', 'Accounting')->first();

        $this->assertNotNull($momCorpOrg);
        $this->assertNotNull($accountingOrgUpdated);
        $this->assertEquals($momCorpOrg->id, $accountingOrgUpdated->parent_id); // 親IDが更新されていること
        $this->assertEquals($accountingOrg->id, $accountingOrgUpdated->id); // IDは変わらないこと
    }

    /**
     * TC-05: 廃止(削除) - 既存組織が含まれない属性セットで同期を実行。
     * 同期されなかった組織が SoftDelete (deleted_atが入る) されること。
     *
     * @return void
     */
    public function test_tc05_missing_organizations_are_soft_deleted()
    {
        Config::set('ldap_sync.hierarchy_attributes', ['ou']);
        Config::set('ldap_sync.delete_missing', true); // 論理削除を有効化
        Config::set('ldap_sync.deletion_threshold_percentage', 100); // 削除保護を無効化に近い値に

        // 初期状態: Accounting 組織が存在
        LdapOu::create([
            'cn' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'objectguid' => ['{1C7D1E9B-736E-4A63-8E5A-67E9F45A3B12}'],
            'ou' => ['Accounting'],
            'distinguishedname' => 'ou=Accounting,dc=local,dc=com'
        ]);
        LdapUser::create([
            'cn' => ['Philip J Fry'],
            'mail' => ['fry@planetexpress.com'],
            'objectguid' => ['{5C1A6E7D-1D1A-4F2A-A0A3-1A2B3C4D5E6F}'],
            'ou' => ['Accounting'],
            'description' => ['The Bean Counters'],
            'distinguishedname' => 'cn=Philip J Fry,ou=Accounting,dc=local,dc=com'
        ]);
        Artisan::call('ad:sync');
        $this->assertDatabaseHas('organizations', ['org_id' => 'Accounting', 'deleted_at' => null]);
        
        // LDAPを空にする (全組織が削除対象となる)
        DirectoryEmulator::setup(); // エミュレータを空にする

        Artisan::call('ad:sync');

        $this->assertSoftDeleted('organizations', ['org_id' => 'Accounting']); // 論理削除されていること
    }

    /**
     * TC-06: 削除保護 - 全組織の削除など、閾値を超える削除が発生する場合。
     * 処理が中断され、エラーログが出力されること (削除されないこと)。
     *
     * @return void
     */
    public function test_tc06_deletion_threshold_prevents_mass_deletion()
    {
        Config::set('ldap_sync.hierarchy_attributes', ['ou']);
        Config::set('ldap_sync.delete_missing', true);
        Config::set('ldap_sync.deletion_threshold_percentage', 10); // 閾値を低く設定

        // 複数の組織を作成
        LdapOu::create(['cn' => ['OrgA'], 'ou' => ['ORGA'], 'description' => ['Org A'], 'objectguid' => ['{AAAAAAAA-AAAA-BBBB-CCCC-111111111111}'], 'distinguishedname' => 'ou=ORGA,dc=local,dc=com']);
        LdapOu::create(['cn' => ['OrgB'], 'ou' => ['ORGB'], 'description' => ['Org B'], 'objectguid' => ['{BBBBBBBB-AAAA-BBBB-CCCC-111111111111}'], 'distinguishedname' => 'ou=ORGB,dc=local,dc=com']);
        LdapOu::create(['cn' => ['OrgC'], 'ou' => ['ORGC'], 'description' => ['Org C'], 'objectguid' => ['{CCCCCCCC-AAAA-BBBB-CCCC-111111111111}'], 'distinguishedname' => 'ou=ORGC,dc=local,dc=com']);
        LdapUser::create(['cn' => ['UserA'], 'mail' => ['usera@example.com'], 'objectguid' => ['{12345678-ABCD-EFGH-IJKL-1234567890AB}'], 'ou' => ['ORGA'], 'description' => ['Org A'], 'distinguishedname' => 'cn=UserA,ou=ORGA,dc=local,dc=com']);
        Artisan::call('ad:sync');

        $this->assertEquals(3, Organization::count());

        // LDAPを空にする (全組織が削除対象となる)
        DirectoryEmulator::setup();

        // エラーが発生し、例外がスローされることを期待
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Organization cleanup aborted due to exceeding deletion threshold.');

        Artisan::call('ad:sync');

        // 削除が実行されていないことを確認
        $this->assertEquals(3, Organization::count());
        $this->assertDatabaseHas('organizations', ['org_id' => 'ORGA', 'deleted_at' => null]);
    }

    /**
     * TC-07: 整合性修復 - NestedSetの左右値を意図的に壊した状態で同期実行。
     * 処理完了後、fixTree() により _lft, _rgt が正しい値に修復されていること。
     *
     * @return void
     */
    public function test_tc07_fixtree_repairs_nestedset_integrity()
    {
        Config::set('ldap_sync.hierarchy_attributes', ['ou']);

        // 組織を作成
        // NestedSet操作をServiceが自動的に行うため、ここでは直接DBを操作して作成する
        DB::table('organizations')->insert([
            ['id' => 1, 'name' => 'Root Org', 'org_id' => 'ROOT', 'created_at' => now(), 'updated_at' => now(), '_lft' => 1, '_rgt' => 6, 'parent_id' => null],
            ['id' => 2, 'name' => 'Child 1', 'org_id' => 'CHILD1', 'created_at' => now(), 'updated_at' => now(), '_lft' => 2, '_rgt' => 3, 'parent_id' => 1],
        ]);
        
        // 意図的にNestedSetの値を壊す
        DB::table('organizations')->where('org_id', 'CHILD1')->update(['_lft' => 0, '_rgt' => 0]);

        $brokenOrg = DB::table('organizations')->where('org_id', 'CHILD1')->first();
        $this->assertEquals(0, $brokenOrg->_lft);
        $this->assertEquals(0, $brokenOrg->_rgt);

        // LDAPをエミュレート (同期処理を実行してfixTreeを呼び出す)
        LdapOu::create(['cn' => ['Root Org'], 'ou' => ['ROOT'], 'description' => ['Root Org'], 'objectguid' => ['{00000000-0000-0000-0000-000000000001}'], 'distinguishedname' => 'ou=ROOT,dc=local,dc=com']);
        LdapOu::create(['cn' => ['Child 1'], 'ou' => ['CHILD1'], 'description' => ['Child 1'], 'objectguid' => ['{00000000-0000-0000-0000-000000000002}'], 'distinguishedname' => 'ou=CHILD1,ou=ROOT,dc=local,dc=com']);
        LdapUser::create(['cn' => ['Test User'], 'mail' => ['test@example.com'], 'objectguid' => ['{ABCDEF12-ABCD-EF12-ABCD-EF1234567890}'], 'ou' => ['CHILD1'], 'description' => ['Child 1'], 'distinguishedname' => 'cn=Test User,ou=CHILD1,ou=ROOT,dc=local,dc=com']);

        Artisan::call('ad:sync');

        // fixTreeが実行され、エラーが修復されていることを確認
        $repairedOrg = Organization::where('org_id', 'CHILD1')->first();
        $this->assertNotEquals(0, $repairedOrg->_lft);
        $this->assertNotEquals(0, $repairedOrg->_rgt);
        // ルートノードはRoot Orgであるはず
        $rootOrg = Organization::where('org_id', 'ROOT')->first();
        $this->assertFalse($rootOrg->hasNestedSetErrors());
        $this->assertFalse(Organization::hasNestedSetErrors());
    }

    /**
     * TC-08: 混合キー - コードあり階層(会社)とコードなし階層(課)が混在する場合。
     * それぞれのロジックで正しく特定・作成されること。
     *
     * @return void
     */
    public function test_tc08_mixed_hierarchy_attributes_are_handled_correctly()
    {
        // 会社コード => 会社名, 部署名 (コードなし)
        Config::set('ldap_sync.hierarchy_attributes', [
            'companycode' => 'companyname', // コードあり
            'department', // コードなし (department属性をorg_idとnameに使う)
        ]);

        // LDAPエミュレート
        LdapUser::create([
            'cn' => ['Zapp Brannigan'],
            'mail' => ['zapp@example.com'],
            'objectguid' => ['{11111111-AAAA-BBBB-CCCC-111111111111}'],
            'companycode' => ['DOOP'],
            'companyname' => ['Democratic Order of Planets'],
            'department' => ['Command'],
            'distinguishedname' => 'cn=Zapp Brannigan,ou=Command,ou=Democratic Order of Planets,dc=local,dc=com'
        ]);

        Artisan::call('ad:sync');

        $doopOrg = Organization::where('org_id', 'DOOP')->first();
        $commandOrg = Organization::where('org_id', 'Command')->first();

        $this->assertNotNull($doopOrg);
        $this->assertEquals('Democratic Order of Planets', $doopOrg->name);
        $this->assertNull($doopOrg->parent_id);

        $this->assertNotNull($commandOrg);
        $this->assertEquals('Command', $commandOrg->name);
        $this->assertEquals($doopOrg->id, $commandOrg->parent_id);

        $this->assertDatabaseHas('user_organizations', [
            'user_id' => User::where('email', 'zapp@example.com')->first()->id,
            'organization_id' => $commandOrg->id,
            'is_primary' => true,
        ]);
    }
}