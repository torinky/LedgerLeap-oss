<?php

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetPrimaryOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_primary()
    {
        $user = User::create([
            'objectguid' => 'u1',
            'name' => 'Taro',
            'email' => 'taro@example.com',
            'password' => bcrypt('secret'),
        ]);

        $orgA = Organization::create(['org_id' => 'A', 'name' => 'Org A']);
        $orgB = Organization::create(['org_id' => 'B', 'name' => 'Org B']);

        // attach A as primary
        $user->organizations()->attach($orgA->id, ['is_primary' => true]);

        $this->assertEquals($orgA->id, $user->primaryOrganization()->id);

        // switch to B
        $user->setPrimaryOrganization($orgB);
        $user->refresh();

        $this->assertEquals($orgB->id, $user->primaryOrganization()->id);
        $this->assertDatabaseHas('user_organizations', [
            'user_id' => $user->id,
            'organization_id' => $orgB->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('user_organizations', [
            'user_id' => $user->id,
            'organization_id' => $orgA->id,
            'is_primary' => false,
        ]);
    }

    public function test_recover_from_corrupt_pivot()
    {
        $user = User::create([
            'objectguid' => 'u2',
            'name' => 'Hanako',
            'email' => 'hanako@example.com',
            'password' => bcrypt('secret'),
        ]);

        $orgA = Organization::create(['org_id' => 'A2', 'name' => 'Org A2']);
        $orgB = Organization::create(['org_id' => 'B2', 'name' => 'Org B2']);

        // create corrupt pivot: both marked primary
        \Illuminate\Support\Facades\DB::table('user_organizations')->insert([
            ['user_id' => $user->id, 'organization_id' => $orgA->id, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'organization_id' => $orgB->id, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Ensure corruption exists
        $this->assertEquals(2, \Illuminate\Support\Facades\DB::table('user_organizations')->where('user_id', $user->id)->where('is_primary', true)->count());

        // set primary to B -> should fix corruption
        $user->setPrimaryOrganization($orgB);
        $user->refresh();

        $this->assertEquals($orgB->id, $user->primaryOrganization()->id);
        $this->assertEquals(1, \Illuminate\Support\Facades\DB::table('user_organizations')->where('user_id', $user->id)->where('is_primary', true)->count());
    }
}
