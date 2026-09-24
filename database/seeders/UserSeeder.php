<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'System Admin', 'email' => 'admin@example.com', 'is_admin' => true],
            ['name' => 'Maria Santos', 'email' => 'maria@example.com', 'is_admin' => false],
            ['name' => 'James Reyes', 'email' => 'james@example.com', 'is_admin' => false],
        ];

        foreach ($accounts as $account) {
            if (User::where('email', $account['email'])->exists()) {
                continue;
            }

            User::factory()
                ->when($account['is_admin'], fn ($factory) => $factory->admin())
                ->create(['name' => $account['name'], 'email' => $account['email']]);
        }
    }
}
