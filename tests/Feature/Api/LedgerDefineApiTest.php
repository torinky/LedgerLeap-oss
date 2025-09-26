<?php

use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Facades\Tenancy;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.central_domains' => ['127.0.0.1']]);
    $tenant = Tenant::create();
    $tenant->domains()->create(['domain' => 'localhost']);
    Tenancy::initialize($tenant);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    $this->token = $this->user->createToken('test-token')->plainTextToken;
});

test('ledger defines endpoint requires authentication', function () {
    getJson('/api/v1/ledger-defines')->assertUnauthorized();
});

test('ledger defines endpoint returns correct structure and data', function () {
    // テストデータ作成
    LedgerDefine::factory()->create(['title' => 'Test Define 1']);

    $response = $this->withToken($this->token)->getJson('/api/v1/ledger-defines');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'columns' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'options',
                        ]
                    ],
                ]
            ]
        ])
        ->assertJsonFragment([
            'name' => 'Test Define 1'
        ]);
});