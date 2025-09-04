<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private $tenant1;
    private $tenant2;
    private $tenant2RootFolderId;

    protected function setUp(): void
    {
        parent::setUp();

        // 中央DBに管理者ユーザーを作成
        $this->adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        // テナント設定コマンド実行中の通知イベント発行を抑制
        Notification::fake();

        // テナント設定コマンドを実行してテナントを2つ作成
        Artisan::call('app:setup-tenant', ['tenant_id' => 'tenant1', 'admin_email' => 'admin@example.com']);
        Artisan::call('app:setup-tenant', ['tenant_id' => 'tenant2', 'admin_email' => 'admin@example.com']);

        $this->tenant1 = \App\Models\Tenant::find('tenant1');
        $this->tenant2 = \App\Models\Tenant::find('tenant2');

        // 各テナントにテストデータを作成
        $this->tenant1->run(function () {
            $folder = Folder::firstOrCreate(['title' => '/', 'creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id]);
            $this->tenant1LedgerDefine = LedgerDefine::factory()->create(['title' => 'Tenant 1 Definition', 'folder_id' => $folder->id]);
            $this->tenant1Ledger = Ledger::factory()->create(['ledger_define_id' => $this->tenant1LedgerDefine->id, 'content' => ['col1' => 'tenant1-data']]);
        });

        $tenant2LedgerDefine = null;
        $tenant2Ledger = null;
        $this->tenant2->run(function () use (&$tenant2LedgerDefine, &$tenant2Ledger) {

            // 2) フォルダのルート（"/"）をユニーク条件で取得/作成
            //    検索キーと作成時属性を分離して指定するのが重要
            $folder = \App\Models\Folder::firstOrCreate(
                ['title' => '/', 'parent_id' => null],      // 検索キー（ユニーク条件）
                ['creator_id' => $this->adminUser->id, 'modifier_id' => $this->adminUser->id] // 作成時のみ付与
            );

            // 3) 可能ならトランザクションで整合性を担保（特に NestedSet の場合）
            // LedgerDefine / Ledger を tenant2 内で作成
            $tenant2LedgerDefine  = \App\Models\LedgerDefine::factory()->create([
                'title'     => 'Tenant 2 Definition',
                'folder_id' => $folder->id,
            ]);
            $this->tenant2RootFolderId = $folder->id;

            // tenant2LedgerDefine の最初のカラム定義のIDを取得
            $firstColumnId = $tenant2LedgerDefine->column_define[0]->id;
            $tenant2Ledger = \App\Models\Ledger::factory()->create([
                'ledger_define_id' => $tenant2LedgerDefine->id,
                'content'          => [$firstColumnId => 'tenant2-data'], // 最初のカラムのIDを使用
            ]);

//            $tenant2LedgerDefine = LedgerDefine::factory()->create(['title' => 'Tenant 2 Definition', 'folder_id' => $folder->id]);
//            $tenant2Ledger = Ledger::factory()->create(['ledger_define_id' => $tenant2LedgerDefine->id, 'content' => ['col1' => 'tenant2-data']]);
        });
        $this->tenant2LedgerDefine = $tenant2LedgerDefine;
        $this->tenant2Ledger = $tenant2Ledger;

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function user_in_one_tenant_cannot_see_data_from_another_tenant(): void
    {
        // ログインリクエスト (中央ドメイン)
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();


        // tenant2のコンテキストでLivewireコンポーネントをテスト
        $tenant2LedgerDefine = $this->tenant2LedgerDefine;
        $tenant2RootFolderId = $this->tenant2RootFolderId;
        $this->tenant2->run(function () use ($tenant2LedgerDefine, $tenant2RootFolderId) {
            \Livewire\Livewire::test(\App\Livewire\Ledger\RecordsTable::class, [
                'defineId' => $tenant2LedgerDefine->id,
                'currentFolderId' => $tenant2RootFolderId,
            ])
                ->assertSee('tenant2-data')
                ->assertDontSee('tenant1-data');
        });
    }

    #[Test]
    public function user_cannot_access_another_tenants_resource_via_direct_url(): void
    {
        // ログインリクエスト (中央ドメイン)
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $this->assertAuthenticated();

        // tenant1の台帳IDを取得
        $tenant1LedgerId = $this->tenant1Ledger->id;

        // tenant2のコンテキストでHTTPリクエストを送信
        $response = $this->tenant2->run(function () use ($tenant1LedgerId) {
            return $this->get(route('ledger.show', ['tenant' => 'tenant2', 'ledgerId' => $tenant1LedgerId]));
        });

        $response->assertNotFound();
    }
}
