<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Password tunggal untuk semua akun demo (admin, seller, buyer). */
    public const PASSWORD = 'password123';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DevUserSeeder::class);
        $this->call(VehicleDummySeeder::class);
    }
}
