<?php

namespace Database\Seeders;

use App\Enums\SellerProfileStatus;
use App\Enums\UserRole;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->createSeller();
        $this->createBuyer();
    }

    private function createSeller(): void
    {
        if (User::query()->where('email', 'seller@auramotors.test')->exists()) {
            return;
        }

        $seller = User::factory()->create([
            'name'     => 'Seller Dev',
            'email'    => 'seller@auramotors.test',
            'password' => Hash::make('password'),
            'role'     => UserRole::Seller,
        ]);

        SellerProfile::create([
            'user_id'     => $seller->id,
            'ktp_path'    => 'dev/placeholder-ktp.jpg',
            'status'      => SellerProfileStatus::Approved,
            'reviewed_by' => User::query()->where('role', UserRole::Admin)->value('id'),
            'reviewed_at' => now(),
        ]);

        $this->command?->info('Seller dev account: seller@auramotors.test / password');
    }

    private function createBuyer(): void
    {
        if (User::query()->where('email', 'buyer@auramotors.test')->exists()) {
            return;
        }

        User::factory()->create([
            'name'     => 'Buyer Dev',
            'email'    => 'buyer@auramotors.test',
            'password' => Hash::make('password'),
            'role'     => UserRole::Buyer,
        ]);

        $this->command?->info('Buyer dev account: buyer@auramotors.test / password');
    }
}
