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
        $tenant = \App\Models\Tenant::factory()->create(); // テスト用に新しいテナントを作成
        tenancy()->initialize($tenant); // テナントコンテキストを初期化

        $ledgerDefine = LedgerDefine::factory()->for(Folder::factory()->create(['tenant_id' => $tenant->id]))->create(['tenant_id' => $tenant->id]);
        $ledger = Ledger::factory()->for($ledgerDefine, 'define')->create(['tenant_id' => $tenant->id]);
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
            'tenant_id' => $tenant->id, // AttachedFileにもtenant_idを追加
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

    public function forLedger(Ledger $ledger): Factory
    {
        return $this->state(function (array $attributes) use ($ledger) {
            return [
                'ledger_id' => $ledger->id,
                'ledger_define_id' => $ledger->ledger_define_id,
                'tenant_id' => $ledger->tenant_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $ledger->modifier_id,
            ];
        });
    }
}
