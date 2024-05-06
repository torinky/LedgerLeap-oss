<?php

namespace Database\Factories;

use App\Models\ColumnDefine;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory
 */
class LedgerDefineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function definition()
    {
        $columnDefineLoop = random_int(3, 20);
        $columnDefine = [];
        $columnDefine[] = new ColumnDefine(
            0,
            $this->faker->realText(10),
            'chk',
            1,
            $this->faker->words(random_int(3, 10)),
            $this->faker->boolean(),
            $this->faker->boolean(),
            $this->faker->boolean()
        );
        $columnDefine[] = new ColumnDefine(
            1,
            $this->faker->realText(10),
            'chk',
            2,
            $this->faker->words(random_int(3, 10)),
            $this->faker->boolean(),
            $this->faker->boolean(),
            $this->faker->boolean()
        );
        /*        $columnDefine[]=new ColumnDefine(
                    1,
                    $this->faker->realText(10),
                    'files',
                    2,
                    [],
                    $this->faker->boolean(),
                    $this->faker->boolean(),
                    $this->faker->boolean()
                );*/
        for ($i = 2; $i < $columnDefineLoop; $i++) {
            $tempColumnDefine = new ColumnDefine(
                $i,
                $this->faker->realText(10),
                $this->faker->randomElement(ColumnDefine::$types),
                $i + 1,
                $this->faker->words(random_int(3, 10)),
                $this->faker->boolean(),
                $this->faker->boolean(),
                $this->faker->boolean()
            );

            $columnDefine[] = $tempColumnDefine;
        }

        return [
            'title' => $this->faker->realText(10),
            'column_define' => $columnDefine,
            'folder_id' => random_int(1, 10),
            //            'folder_id' => Folder::factory(),
            //            'creator_id' => 1,
            //            'modifier_id' => 1,
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
        ];
    }
}
