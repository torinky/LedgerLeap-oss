<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company(),
            'parent_id' => null,
            'org_id' => Str::uuid(), // 一意の値を設定
        ];
    }
}
