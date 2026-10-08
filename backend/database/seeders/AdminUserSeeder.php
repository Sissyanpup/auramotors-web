<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::query()->where('role', UserRole::Admin)->exists()) {
            return;
        }

        $password = Str::password(16);

        User::factory()->create([
            'name' => 'Admin AuraMotors',
            'email' => 'admin@auramotors.test',
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $this->command?->warn("Admin dev account: admin@auramotors.test / {$password}");
    }
}
