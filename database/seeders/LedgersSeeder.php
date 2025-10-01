<?php

namespace Database\Seeders;

use App\Models\Ledger;
use Illuminate\Database\Seeder;

// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Str;

class LedgersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /*        DB::table('ledgers')->insert([
                    'content' => json_encode(
                        [ Str::random(10),Str::random(10),Str::random(10),Str::random(10), Str::random(10),]
                    ),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);*/

        /*        DB::enableQueryLog();
                Ledger::factory()->count(10)->create();
                dd(DB::getQueryLog());*/
        Ledger::factory()->count(1000)->create();
    }
}
