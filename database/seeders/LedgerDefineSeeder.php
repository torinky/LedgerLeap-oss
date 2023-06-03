<?php

namespace Database\Seeders;

use App\Models\LedgerDefine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LedgerDefineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        LedgerDefine::factory()->count(10)->create();
    }
}
