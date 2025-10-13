<?php

namespace Database\Factories;

use App\Models\AutoLink;
use Illuminate\Database\Eloquent\Factories\Factory;

class AutoLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AutoLink::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'label' => $this->faker->unique()->word.' AutoLink',
            'pattern' => $this->faker->regexify('[A-Z]{3}-\\d{3}'),
            'url_template' => '/l/'.$this->faker->word,
            'is_enabled' => $this->faker->boolean,
        ];
    }
}
