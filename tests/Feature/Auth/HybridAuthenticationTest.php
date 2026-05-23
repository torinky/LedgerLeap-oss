<?php

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Organization;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use Spatie\Permission\Models\Role;
use Tests\Ldap\OpenLdapUser;

beforeEach(function () {
    $this->tenant = Tenant::create();
    // tenancy()->initialize($this->tenant); // ログインはセントラルコンテキストで行われるため、テナント初期化は不要

    // テスト用の検索ベースDNを設定 (OpenLDAPコンテナに合わせて)
    config()->set('ldap_sync.login_search_base_dns', ['dc=planetexpress,dc=com']);
    // テスト用のLDAPユーザーモデルを設定 (OpenLDAPスキーマに合わせて)
    config()->set('ldap.auth.user_model', OpenLdapUser::class);
    config()->set('auth.providers.ldap.model', OpenLdapUser::class);

    DirectoryEmulator::setup('default');
    Container::getConnection('default')->getLdapConnection()->shouldAllowAnyBind();
});

afterEach(function () {
    DirectoryEmulator::tearDown();
});

test('ad user can login and auto-register (hybrid auth)', function () {
    // テスト用に階層属性を 'sn' (姓) に変更 (fryユーザーは ou 属性を持っていない可能性があるため)
    config()->set('ldap_sync.hierarchy_attributes', ['sn']);

    // DB上の組織を作成
    $org = Organization::create(['name' => 'Fry', 'org_id' => 'Fry']);

    // テストに必要なロール、フォルダ、ロールフォルダ権限を設定
    // ユーザー作成後に紐付けるため、ロールとフォルダを先に作成
    $role = Role::create(['name' => 'test-ad-role']);

    // ユーザーを作成する前に仮のユーザーIDを用意 (Folderのcreator/modifier用)
    // 自動登録されるユーザーが作成された後に、改めてそのユーザーにロールを割り当てる
    $tempUserId = User::factory()->create()->id;

    $folder = Folder::create([
        'title' => 'AD Sync Test Folder',
        'creator_id' => $tempUserId,
        'modifier_id' => $tempUserId,
        'tenant_id' => $this->tenant->id, // テナントに紐付ける
    ]);

    RoleFolderPermission::create([
        'role_id' => $role->id,
        'folder_id' => $folder->id,
        'permission' => FolderPermissionType::READ, // 読み取り権限で十分
        'modifier_id' => $tempUserId,
    ]);

    // LDAPユーザーの作成
    $ldapUser = OpenLdapUser::create([
        'dn' => 'uid=fry,dc=planetexpress,dc=com',
        'uid' => 'fry',
        'cn' => 'Philip J. Fry',
        'sn' => 'Fry',
        'mail' => 'fry@planetexpress.com',
        'objectguid' => 'uuid-fry',
        'entryuuid' => 'uuid-fry',
        'userpassword' => 'fry',
    ]);

    // 既存ユーザーでのログイン試行
    $response = $this->post('/login', [
        'email' => 'fry@planetexpress.com',
        'password' => 'fry',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/'.$this->tenant->getTenantKey().'/my-portal'); // テナント付きリダイレクトを期待

    // ローカルユーザーが作成されているか確認
    $user = User::where('email', 'fry@planetexpress.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->ad_last_synced_at)->not->toBeNull()
        ->and($user->primaryOrganization()->id)->toBe($org->id);

    // 作成されたユーザーにロールを割り当てる
    $user->assignRole($role);

    // 不要な仮ユーザーを削除
    User::find($tempUserId)->forceDelete();
});

test('local user fallback works when ad user not found', function () {
    // ローカルユーザーのみ作成
    $user = User::factory()->create([
        'email' => 'tarou@example.com',
        'password' => bcrypt('secret'),
        'objectguid' => null,
    ]);

    $response = $this->post('/login', [
        'email' => 'tarou@example.com',
        'password' => 'secret',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('ad user rejected if organization mismatch (strict check)', function () {
    // テスト用に階層属性を 'sn' に変更
    config()->set('ldap_sync.hierarchy_attributes', ['sn']);

    // DB上の組織を作成しない (不一致状態を作る)
    // $org = Organization::create(['name' => 'Fry', 'org_id' => 'Fry']);

    // LDAPユーザーの作成 (SN=Fry)
    OpenLdapUser::create([
        'dn' => 'uid=fry,dc=planetexpress,dc=com',
        'uid' => 'fry',
        'cn' => 'Philip J. Fry',
        'sn' => 'Fry',
        'mail' => 'fry@planetexpress.com',
        'objectguid' => 'uuid-fry',
        'entryuuid' => 'uuid-fry',
        'userpassword' => 'fry',
    ]);

    // 既存ユーザーでのログイン試行 -> 組織不一致で拒否されるはず
    $response = $this->post('/login', [
        'email' => 'fry@planetexpress.com',
        'password' => 'fry',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email']); // バリデーションエラーを確認
});
