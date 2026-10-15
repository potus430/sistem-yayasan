<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CentralFinanceAccountSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_CENTRAL_FINANCE_EMAIL');
        $password = env('SEED_CENTRAL_FINANCE_PASSWORD');

        if (! $email || ! $password) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Keuangan Pusat',
                'branch_id' => null,
                'role' => 'keuangan_pusat',
                'password' => $password,
                'is_active' => true,
            ]
        );
    }
}
