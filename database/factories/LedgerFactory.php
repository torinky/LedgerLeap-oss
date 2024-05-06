<?php

namespace Database\Factories;

use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory
 */
class LedgerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        if (!Storage::exists('public/Ledger/Attachments')) {
            Storage::makeDirectory('public/Ledger/Attachments');
        }
        if (!Storage::exists('public/Ledger/thumbs')) {
            Storage::makeDirectory('public/Ledger/thumbs');
        }
        if (!Storage::exists('filepond')) {
            Storage::makeDirectory('filepond');
        }

        return [
            //            'ledger_define_id' => $this->faker->numberBetween(1, 10),
            'ledger_define_id' => LedgerDefine::factory(),
            'content' => [
                [$this->faker->realText(10), $this->faker->realText(10), $this->faker->realText(10)],
                [$this->faker->realText(10) => $this->faker->realText(10), $this->faker->realText(10) => $this->faker->realText(10)],
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
                $this->faker->realText(10),
            ],
            //            'creator_id' => 1,
            'creator_id' => User::factory(),
            //            'modifier_id' => 1,
            'modifier_id' => User::factory(),
        ];
    }
}
