<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run()
    {
        $organizations = Organization::factory()
            ->count(random_int(20, 50))
            ->create();

        $organizations->each(function ($organization) {
            $childCount = random_int(0, 4);
            $childOrganizations = Organization::factory()
                ->count($childCount)
                ->make()
                ->each(function ($childOrganization) use ($organization) {
                    $childOrganization->parent_id = $organization->id;
                    $childOrganization->org_id = uniqid('', true); // 一意の値を設定
                    $childOrganization->save();
                });

            $organization->children()->saveMany($childOrganizations);
        });
    }
}
