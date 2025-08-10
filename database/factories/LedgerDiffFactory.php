<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LedgerDiffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'ledger_define_id' => LedgerDefine::factory(),
            'creator_id' => User::factory(),
            'modifier_id' => User::factory(),
            'inspector_id' => User::factory(),
            'approver_id' => User::factory(),
            'status' => $this->faker->randomElement(WorkflowStatus::cases())->value,
            'version' => $this->faker->numberBetween(1, 10),
            'content' => [],
            'column_define' => [],
            'comments' => $this->faker->optional()->sentence,
            'completed_inspector_role_ids' => [],
            'completed_approver_role_ids' => [],
        ];
    }
}