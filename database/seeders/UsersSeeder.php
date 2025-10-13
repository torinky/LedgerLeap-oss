<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //        User::factory(10)->create();
        User::updateOrCreate([
            'email' => 'super_admin@ll.com',
        ], [
            'name' => 'super_admin',
            'email_verified_at' => now(),
            'password' => '$2y$10$O4kUm3TSL9gIhws1xNQcJ.d3kbis37b.aZo5we665Z.wYV.L.QaEi', // aaaaaaaa
            'remember_token' => Str::random(10),
        ]);

        User::updateOrCreate([
            'email' => 'general_user@ll.com',
        ], [
            'name' => 'general_user',
            'email_verified_at' => now(),
            'password' => '$2y$10$O4kUm3TSL9gIhws1xNQcJ.d3kbis37b.aZo5we665Z.wYV.L.QaEi', // aaaaaaaa
            'remember_token' => Str::random(10),
        ]);

    }
}
