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
        $this->faker = \Faker\Factory::create('en_US');
        $columnDefineLoop = random_int(3, 20);
        $columnDefine = [];
        $columnDefine[] = new ColumnDefine(
            0,
            $this->faker->word(),
            'chk',
            1,
            $this->faker->words(random_int(3, 10)),
            $this->faker->boolean(),
            $this->faker->boolean(),
            $this->faker->boolean()
        );
        $columnDefine[] = new ColumnDefine(
            1,
            $this->faker->word(),
            'chk',
            2,
            $this->faker->words(random_int(3, 10)),
            $this->faker->boolean(),
            $this->faker->boolean(),
            $this->faker->boolean()
        );
        /*        $columnDefine[]=new ColumnDefine(
                    1,
                    $this->faker->word(),
                    'files',
                    2,
                    [],
                    $this->faker->boolean(),
                    $this->faker->boolean(),
                    $this->faker->boolean()
                );*/
        // Correctly get type identifiers once before the loop
        $typeIdentifiers = \App\Models\ColumnTypes\InputTypeFactory::getTypeIdentifiers();

        for ($i = 2; $i < $columnDefineLoop; $i++) {
            $tempColumnDefine = new ColumnDefine(
                $i,
                $this->faker->word(),
                $this->faker->randomElement($typeIdentifiers), // Use the fetched type identifiers
                $i + 1,
                $this->faker->words(random_int(3, 10)),
                $this->faker->boolean(),
                $this->faker->boolean(),
                $this->faker->boolean(),
                $this->faker->word(),
                ['name' => $this->faker->word() . '.png', 'path' => $this->faker->word() . '.png']
            );

            $columnDefine[] = $tempColumnDefine;
        }


        $markdownText = $this->faker->paragraph();
        $markdownText = str_replace("
", "

", $markdownText);

        return [
            'title' => $this->faker->word(),
            'column_define' => $columnDefine,
            'folder_id' => random_int(1, 10),
            'create_description' => $markdownText,
            'list_description' => $this->faker->word(),
            'detail_description' => $this->faker->word(),
            //            'folder_id' => Folder::factory(),
            //            'creator_id' => 1,
            //            'modifier_id' => 1,
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
        ];
    }
}
