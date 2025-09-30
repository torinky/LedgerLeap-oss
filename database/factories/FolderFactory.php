<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FolderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->word(), // realText(10) から word() に変更
            'modifier_id' => User::factory(),
            'creator_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the folder has required roles.
     *
     * @param  array  $inspectors
     * @param  array  $approvers
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withRequiredRoles(array $inspectors = [], array $approvers = [])
    {
        return $this->afterCreating(function (Folder $folder) use ($inspectors, $approvers) {
            foreach ($inspectors as $role) {
                $folder->requiredInspectorRoles()->attach($role->id, ['type' => 'inspector']);
            }
            foreach ($approvers as $role) {
                $folder->requiredApproverRoles()->attach($role->id, ['type' => 'approver']);
            }
        });
    }
}
