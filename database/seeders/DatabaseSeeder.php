<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        /*        $this->call([
                    UsersSeeder::class,
                    LedgerDefineSeeder::class,
                    LedgersSeeder::class,
                    FolderSeeder::class,
                    TagSeeder::class,
                ]);*/

        $users = User::factory(10)->create();
        $this->call([
            UsersSeeder::class,
            FolderSeeder::class,
        ]);

        $ledgerDefines = LedgerDefine::factory(50)->recycle($users)
//            ->hasTag(random_int(0,5))
            ->create();

        $tags = Tag::factory(100)->recycle($users)->recycle($ledgerDefines)->create();

        Ledger::factory(1000)->recycle($users)->recycle($ledgerDefines)->create();

    }
}
