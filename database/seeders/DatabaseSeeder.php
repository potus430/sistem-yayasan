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
        $this->call(SampleDataSeeder::class);

        $adminEmail = env('SEED_ADMIN_EMAIL');
        $adminPassword = env('SEED_ADMIN_PASSWORD');
        if ($adminEmail && $adminPassword) {
            User::query()->updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Administrator Pusat',
                    'branch_id' => null,
                    'role' => 'admin_pusat',
                    'password' => $adminPassword,
                    'is_active' => true,
                ]
            );
        }

        $this->call(CentralFinanceAccountSeeder::class);
    }
}
