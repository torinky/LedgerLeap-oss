<?php

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Stancl\Tenancy\Facades\Tenancy;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $tenant = Tenant::create();
    Tenancy::initialize($tenant);

    // 必要なシーダーのみを実行
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    // Super Adminロールを割り当て、全データにアクセスできるようにする
    $this->user->assignRole('Super Admin');
    $this->token = $this->user->createToken('test-token')->plainTextToken;
});

test('search endpoint requires authentication', function () {
    getJson('/api/v1/search')->assertUnauthorized();
});

test('search endpoint with valid token returns success', function () {
    $this->withToken($this->token)->getJson('/api/v1/search')->assertOk();
});

test('search response has correct structure', function () {
    $folder = Folder::factory()->create();
    $define = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
    Ledger::factory()->create(['ledger_define_id' => $define->id]);

    $response = $this->withToken($this->token)->getJson('/api/v1/search');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'content',
                    'folder' => ['id', 'name'],
                    'tags',
                    'updated_at',
                ]
            ],
            'meta' => ['total', 'limit', 'offset']
        ]);
});

test('it can filter by keyword', function () {
    $folder = Folder::factory()->create();
    $define = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
    $content1 = [];
    Arr::set($content1, '0', 'unique_keyword_123');
    $content2 = [];
    Arr::set($content2, '0', 'another_content');
    $ledger1 = Ledger::factory()->create(['ledger_define_id' => $define->id, 'content' => $content1]);
    Ledger::factory()->create(['ledger_define_id' => $define->id, 'content' => $content2]);

    $response = $this->withToken($this->token)->getJson('/api/v1/search?q=unique_keyword_123');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ledger1->id);
});

test('it can filter by tags', function () {
    $folder = Folder::factory()->create();
    $define1 = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
    $define1->tags()->create(['name' => 'tag1', 'folder_id' => 0, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);
    $define2 = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
    $define2->tags()->create(['name' => 'tag2', 'folder_id' => 0, 'creator_id' => $this->user->id, 'modifier_id' => $this->user->id]);

    $ledger1 = Ledger::factory()->create(['ledger_define_id' => $define1->id]);
    Ledger::factory()->create(['ledger_define_id' => $define2->id]);

    $response = $this->withToken($this->token)->getJson('/api/v1/search?tags=tag1');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ledger1->id);
});

test('it can filter by folder', function () {
    $folder1 = Folder::factory()->create();
    $folder2 = Folder::factory()->create();
    $define1 = LedgerDefine::factory()->create(['folder_id' => $folder1->id]);
    $define2 = LedgerDefine::factory()->create(['folder_id' => $folder2->id]);

    $ledger1 = Ledger::factory()->create(['ledger_define_id' => $define1->id]);
    Ledger::factory()->create(['ledger_define_id' => $define2->id]);

    $response = $this->withToken($this->token)->getJson('/api/v1/search?folder_id=' . $folder1->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ledger1->id);
});

test('it returns only the count with mode=count', function () {
    $folder = Folder::factory()->create();
    $define = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
    Ledger::factory(5)->create(['ledger_define_id' => $define->id]);

    $response = $this->withToken($this->token)->getJson('/api/v1/search?mode=count');

    $response->assertOk()
        ->assertJsonStructure(['meta' => ['total']])
        ->assertJsonPath('meta.total', 5)
        ->assertJsonMissingPath('data');
});