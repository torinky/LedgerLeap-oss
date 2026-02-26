<?php

namespace Database\Factories;

use App\Models\ColumnDefine;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LedgerDefine>
 */
class AutoNumberLedgerDefineFactory extends Factory
{
    protected $model = LedgerDefine::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $columnDefine = [];

        // 自動採番カラム (unique = false)
        $columnDefine[] = new ColumnDefine(
            0,
            '資料番号 (連番+版記号)',
            'auto_number',
            1,
            (object) [
                'prefix' => 'DOC-',
                'digits' => 3,
                'revision' => '-A',
            ],
            true, // required
            false, // unique = false
            null,
            '資料番号を自動採番します (版記号考慮)'
        );

        // 自動採番カラム (unique = true)
        $columnDefine[] = new ColumnDefine(
            1,
            'プロジェクトID (重複禁止)',
            'auto_number',
            2,
            (object) [
                'prefix' => 'PROJ-',
                'digits' => 4,
                'revision' => '',
            ],
            true, // required
            true, // unique = true
            null,
            'プロジェクトIDを自動採番します (重複禁止)'
        );

        // 通常のテキストカラム
        $columnDefine[] = new ColumnDefine(
            2,
            'タイトル',
            'text',
            3,
            (object) [],
            true,
            false,
            null,
            '台帳のタイトルを入力してください'
        );

        return [
            'title' => '自動採番テスト台帳 '.$this->faker->unique()->word(),
            'column_define' => $columnDefine,
            'folder_id' => 1, // 既存のフォルダIDを使用
            'create_description' => '自動採番機能のテスト用台帳です。',
            'list_description' => '自動採番カラムの動作確認を行います。',
            'detail_description' => '重複チェックや連番の挙動を確認してください。',
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
        ];
    }
}
