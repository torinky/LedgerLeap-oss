<?php

namespace Database\Seeders;

use App\Models\ScoringConfig;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ScoringConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            ScoringConfig::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'profile_name' => 'activity',
                    'is_system_default' => true,
                ],
                [
                    'activity_weight' => 0.40,
                    'freshness_weight' => 0.30,
                    'importance_weight' => 0.30,
                    'relevance_weight' => 0.00,
                    'popularity_weight' => 0.00,
                ]
            );
        }
    }
}