<?php

namespace Database\Seeders;

use App\Enums\BuyerIdType;
use App\Enums\BuyerProfileStatus;
use App\Enums\SellerProfileStatus;
use App\Enums\UserRole;
use App\Models\BuyerProfile;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Akun demo untuk pemeriksaan dosen. Semua akun memakai password yang sama
 * (DatabaseSeeder::PASSWORD) dan data KTP sudah terisi, jadi langsung bisa
 * login tanpa melewati halaman /lengkapi-data.
 *
 * Aman dijalankan berulang: akun dicari berdasarkan email lalu di-update,
 * sehingga password selalu kembali ke default.
 */
class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->upsertUser('admin@auramotors.test', 'Admin AuraMotors', UserRole::Admin, '3171000000000001');

        // Seller utama: KYC approved + rekening bank, pemilik semua listing dummy.
        $seller = $this->upsertUser('seller@auramotors.test', 'Seller AuraMotors', UserRole::Seller, '3171000000000002');
        SellerProfile::updateOrCreate(['user_id' => $seller->id], [
            'ktp_path' => $this->placeholderDocument("kyc/{$seller->id}/ktp.png", 'KTP Seller AuraMotors'),
            'status' => SellerProfileStatus::Approved,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder_name' => 'Seller AuraMotors',
        ]);

        // Seller kedua: KYC masih pending, untuk demo admin approve/reject KYC.
        $sellerBaru = $this->upsertUser('seller2@auramotors.test', 'Seller Baru', UserRole::Seller, '3171000000000003');
        SellerProfile::updateOrCreate(['user_id' => $sellerBaru->id], [
            'ktp_path' => $this->placeholderDocument("kyc/{$sellerBaru->id}/ktp.png", 'KTP Seller Baru'),
            'status' => SellerProfileStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        // Buyer utama: KYC buyer approved, bisa checkout kendaraan di atas ambang nilai tinggi.
        $buyer = $this->upsertUser('buyer@auramotors.test', 'Buyer AuraMotors', UserRole::Buyer, '3171000000000004');
        BuyerProfile::updateOrCreate(['user_id' => $buyer->id], [
            'id_type' => BuyerIdType::Ktp,
            'id_number' => '3171000000000004',
            'id_document_path' => $this->placeholderDocument("buyer-kyc/{$buyer->id}/id.png", 'KTP Buyer AuraMotors'),
            'address_proof_path' => $this->placeholderDocument("buyer-kyc/{$buyer->id}/address.png", 'Bukti Alamat'),
            'proof_of_funds_path' => $this->placeholderDocument("buyer-kyc/{$buyer->id}/funds.png", 'Proof of Funds'),
            'status' => BuyerProfileStatus::Approved,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        // Buyer kedua: KYC buyer pending, untuk demo admin review KYC buyer.
        $buyerBaru = $this->upsertUser('buyer2@auramotors.test', 'Buyer Baru', UserRole::Buyer, '3171000000000005');
        BuyerProfile::updateOrCreate(['user_id' => $buyerBaru->id], [
            'id_type' => BuyerIdType::Ktp,
            'id_number' => '3171000000000005',
            'id_document_path' => $this->placeholderDocument("buyer-kyc/{$buyerBaru->id}/id.png", 'KTP Buyer Baru'),
            'address_proof_path' => $this->placeholderDocument("buyer-kyc/{$buyerBaru->id}/address.png", 'Bukti Alamat'),
            'proof_of_funds_path' => $this->placeholderDocument("buyer-kyc/{$buyerBaru->id}/funds.png", 'Proof of Funds'),
            'status' => BuyerProfileStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $this->command?->table(
            ['Role', 'Email', 'Password', 'Keterangan'],
            [
                ['admin', 'admin@auramotors.test', DatabaseSeeder::PASSWORD, 'Akses semua dashboard admin'],
                ['seller', 'seller@auramotors.test', DatabaseSeeder::PASSWORD, 'KYC approved, punya listing'],
                ['seller', 'seller2@auramotors.test', DatabaseSeeder::PASSWORD, 'KYC pending (demo approve)'],
                ['buyer', 'buyer@auramotors.test', DatabaseSeeder::PASSWORD, 'KYC buyer approved'],
                ['buyer', 'buyer2@auramotors.test', DatabaseSeeder::PASSWORD, 'KYC buyer pending (demo approve)'],
            ],
        );
    }

    private function upsertUser(string $email, string $name, UserRole $role, string $ktpNumber): User
    {
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => DatabaseSeeder::PASSWORD,
            'role' => $role,
            'ktp_number' => $ktpNumber,
            'ktp_name' => strtoupper($name),
            'ktp_verified_at' => now(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Tulis gambar placeholder ke disk privat supaya tombol "lihat dokumen"
     * di halaman review admin tidak 404.
     */
    private function placeholderDocument(string $path, string $label): string
    {
        if (Storage::disk('local')->exists($path)) {
            return $path;
        }

        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(640, 400);
            imagefill($image, 0, 0, imagecolorallocate($image, 30, 30, 34));
            $gold = imagecolorallocate($image, 212, 175, 55);
            imagerectangle($image, 10, 10, 629, 389, $gold);
            imagestring($image, 5, 40, 170, 'DOKUMEN DUMMY', $gold);
            imagestring($image, 4, 40, 200, $label, $gold);

            ob_start();
            imagepng($image);
            $contents = ob_get_clean();
            imagedestroy($image);
        } else {
            // Fallback tanpa ekstensi GD: PNG 1x1 piksel.
            $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=');
        }

        Storage::disk('local')->put($path, $contents);

        return $path;
    }
}
