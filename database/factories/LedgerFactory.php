<?php

namespace Database\Factories;

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
            'ledger_define_id' => $this->faker->numberBetween(1, 10),
            'content' =>
                [
                    [$this->faker->realText(10), $this->faker->realText(10), $this->faker->realText(10)],
                    [$this->faker->realText(10) => $this->faker->realText(10), $this->faker->realText(10) => $this->faker->realText(10),],
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
            'creator_id' => 1,
            'modifier_id' => 1,
        ];
    }
}
