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
            'ledger_define_id' => LedgerDefine::factory(),
            'content' => [
                $this->faker->word(),
                $this->faker->sentence(3),
            ],
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
        ];
    }

    /**
     * テスト用の軽量バージョン
     */
    public function minimal()
    {
        return $this->state(function (array $attributes) {
            return [
                'content' => [
                    'test_field' => $this->faker->word(),
                ],
            ];
        });
    }
}
