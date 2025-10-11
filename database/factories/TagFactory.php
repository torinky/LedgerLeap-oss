<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'ledger_define_id' => \App\Models\LedgerDefine::factory(),
            'folder_id' => \App\Models\Folder::factory(),
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
            'name' => $this->faker->word(), // realText(10) から word() に変更
        ];
    }
}
