<?php

namespace Database\Factories;

use App\Enums\AttachedFileStatus;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttachedFile>
 */
class AttachedFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ledgerDefine = LedgerDefine::factory()->for(Folder::factory())->create();
        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create();
        $user = User::factory()->create();

        $filename = $this->faker->word().'.pdf';
        $hashedbasename = Str::random(40).'.pdf';
        $path = 'public/Ledger/Attachments/'.$ledgerDefine->id.'/'.$hashedbasename;

        return [
            'filename' => $filename,
            'hashedbasename' => $hashedbasename,
            'ledger_define_id' => $ledgerDefine->id,
            'ledger_id' => $ledger->id,
            'column_id' => $this->faker->randomNumber(2),
            'mime' => 'application/pdf',
            'path' => $path,
            'size' => $this->faker->numberBetween(1000, 50000),
            'status' => AttachedFileStatus::COMPLETED->value,
            'contain_content' => $this->faker->boolean(),
            'optimized' => $this->faker->boolean(),
            'creator_id' => $user->id,
            'modifier_id' => $user->id,
            'original_file_path' => null,
            'original_mime_type' => null,
        ];
    }

    public function image(): Factory
    {
        return $this->state(function (array $attributes) {
            $filename = $this->faker->word().'.jpg';
            $hashedbasename = Str::random(40).'.jpg';
            $path = 'public/Ledger/Attachments/'.$attributes['ledger_define_id'].'/'.$hashedbasename;

            return [
                'filename' => $filename,
                'hashedbasename' => $hashedbasename,
                'mime' => 'image/jpeg',
                'path' => $path,
            ];
        });
    }

    public function withOriginalFile(): Factory
    {
        return $this->state(function (array $attributes) {
            $originalFilename = $this->faker->word().'.png';
            $originalMimeType = 'image/png';
            $originalPath = 'public/Ledger/Attachments/'.$attributes['ledger_define_id'].'/Originals/'.Str::random(40).'.png';

            return [
                'original_file_path' => $originalPath,
                'original_mime_type' => $originalMimeType,
            ];
        });
    }
}
