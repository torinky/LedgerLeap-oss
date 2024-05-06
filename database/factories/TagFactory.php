<?php

namespace Database\Factories;

use App\Models\LedgerDefine;
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
        $ledgerOrFolder = (bool)random_int(0, 1);

        if ($ledgerOrFolder) {
            return [
                'ledger_define_id' => 0,
                //                'folder_id' => Folder::factory(),
                'folder_id' => $this->faker->numberBetween(1, 10),
                'creator_id' => User::factory(),
                'modifier_id' => User::factory(),
                /*
                'creator_id' => $this->faker->numberBetween(1, 10),
                'modifier_id' => $this->faker->numberBetween(1, 10),*/
                'name' => $this->faker->realText(10),

            ];
        }

        return [
            //            'ledger_define_id' => $this->faker->numberBetween(1, 10),
            'ledger_define_id' => LedgerDefine::factory(),
            //            'folder_id' => Folder::factory(),
            'folder_id' => 0,
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
            /*
            'creator_id' => $this->faker->numberBetween(1, 10),
            'modifier_id' => $this->faker->numberBetween(1, 10),*/
            'name' => $this->faker->realText(10),

        ];
    }
}
