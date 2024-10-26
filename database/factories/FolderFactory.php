<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //            'parent_id'=>Folder::factory(),
            'title' => $this->faker->realText(10),
            'modifier_id' => User::factory(),
            'creator_id' => User::factory(),
        ];
    }
}
