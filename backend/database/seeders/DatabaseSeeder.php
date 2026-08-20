<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AgencySeeder::class);
        $this->call(SystemSettingSeeder::class);

        User::query()->firstOrCreate(
            ['email' => env('DG_EMAIL', 'dg@abt-lacolombe.test')],
            [
                'name' => env('DG_NAME', 'Direction Generale'),
                'password' => env('DG_PASSWORD', 'password'),
                'role' => User::ROLE_DG,
                'is_active' => true,
            ],
        );
    }
}
