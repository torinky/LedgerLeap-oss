<?php

namespace Database\Factories;

use App\Models\Tag;
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
        $ledgerOrDefine = (bool)mt_rand(0, 1);

        if ($ledgerOrDefine) {
            return [
                'ledger_define_id' => 0,
                'folder_id' => $this->faker->numberBetween(1, 99),
                'creator_id' => $this->faker->numberBetween(1, 10),
                'modifier_id' => $this->faker->numberBetween(1, 10),
                'name' => $this->faker->realText(10),

            ];
        }

        return [
            'ledger_define_id' => $this->faker->numberBetween(1, 10),
            'folder_id' => 0,
            'creator_id' => $this->faker->numberBetween(1, 10),
            'modifier_id' => $this->faker->numberBetween(1, 10),
            'name' => $this->faker->realText(10),

        ];
    }
}
