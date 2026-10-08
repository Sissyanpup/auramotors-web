<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VehicleDocumentType;
use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehiclePhoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleDummySeeder extends Seeder
{
    private string $dataPath;

    public function __construct()
    {
        $this->dataPath = database_path('seeders/data');
    }

    public function run(): void
    {
        $seller = User::query()->where('email', 'seller@auramotors.test')->first();

        if (! $seller) {
            $this->command?->error('Akun seller dev tidak ditemukan. Jalankan DevUserSeeder terlebih dahulu.');
            return;
        }

        $admin = User::query()->where('role', UserRole::Admin)->first();

        $vehicleDirs = glob("{$this->dataPath}/vehicles/*/", GLOB_ONLYDIR);

        if (empty($vehicleDirs)) {
            $this->command?->warn('Tidak ada folder kendaraan di database/seeders/data/vehicles/');
            return;
        }

        foreach ($vehicleDirs as $dir) {
            $this->seedVehicle($dir, $seller->id, $admin?->id);
        }

        $this->updateSellerKtp($seller);
    }

    private function seedVehicle(string $dir, int $sellerId, ?int $adminId): void
    {
        $metaPath = $dir . 'meta.json';

        if (! file_exists($metaPath)) {
            $this->command?->warn("Lewati " . basename($dir) . ": meta.json tidak ditemukan.");
            return;
        }

        $meta = json_decode(file_get_contents($metaPath), true);

        if (! $meta || ! isset($meta['brand'], $meta['model'], $meta['year'])) {
            $this->command?->warn("Lewati " . basename($dir) . ": meta.json tidak valid.");
            return;
        }

        // Hindari duplikat
        $exists = Vehicle::query()
            ->where('seller_id', $sellerId)
            ->where('brand', $meta['brand'])
            ->where('model', $meta['model'])
            ->where('year', $meta['year'])
            ->exists();

        if ($exists) {
            $this->command?->line("Lewati {$meta['brand']} {$meta['model']} {$meta['year']}: sudah ada.");
            return;
        }

        $vehicle = Vehicle::create([
            'seller_id'   => $sellerId,
            'brand'       => $meta['brand'],
            'model'       => $meta['model'],
            'year'        => $meta['year'],
            'price'       => $meta['price'] ?? 0,
            'mileage'     => $meta['mileage'] ?? 0,
            'location'    => $meta['location'] ?? '-',
            'description' => $meta['description'] ?? null,
            'specs'       => $meta['specs'] ?? null,
            'status'      => VehicleStatus::Approved,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ]);

        $this->seedPhotos($dir . 'photos/', $vehicle->id);
        $this->seedDocuments($dir . 'documents/', $vehicle->id);

        $this->command?->info("Dibuat: {$meta['brand']} {$meta['model']} {$meta['year']} (ID: {$vehicle->id})");
    }

    private function seedPhotos(string $photosDir, int $vehicleId): void
    {
        $files = $this->listMediaFiles($photosDir);

        foreach ($files as $order => $file) {
            $filename  = Str::uuid() . '.' . pathinfo($file, PATHINFO_EXTENSION);
            $storagePath = "vehicles/{$vehicleId}/{$filename}";

            Storage::disk('public')->put($storagePath, file_get_contents($file));

            VehiclePhoto::create([
                'vehicle_id' => $vehicleId,
                'path'       => $storagePath,
                'sort_order' => $order,
            ]);
        }
    }

    private function seedDocuments(string $documentsDir, int $vehicleId): void
    {
        $files = $this->listMediaFiles($documentsDir);

        foreach ($files as $file) {
            $basename = strtolower(basename($file));

            if (Str::startsWith($basename, 'stnk')) {
                $type = VehicleDocumentType::Stnk;
            } elseif (Str::startsWith($basename, 'bpkb')) {
                $type = VehicleDocumentType::Bpkb;
            } else {
                $this->command?->warn("Lewati dokumen " . basename($file) . ": nama harus diawali 'stnk' atau 'bpkb'.");
                continue;
            }

            $filename    = Str::uuid() . '.' . pathinfo($file, PATHINFO_EXTENSION);
            $storagePath = "vehicles/{$vehicleId}/documents/{$filename}";

            Storage::disk('local')->put($storagePath, file_get_contents($file));

            VehicleDocument::create([
                'vehicle_id' => $vehicleId,
                'type'       => $type,
                'path'       => $storagePath,
            ]);
        }
    }

    private function updateSellerKtp(User $seller): void
    {
        $files = $this->listMediaFiles("{$this->dataPath}/seller/ktp/");

        if (empty($files)) {
            return;
        }

        $file        = $files[0];
        $filename    = Str::uuid() . '.' . pathinfo($file, PATHINFO_EXTENSION);
        $storagePath = "kyc/{$seller->id}/{$filename}";

        Storage::disk('local')->put($storagePath, file_get_contents($file));

        $seller->sellerProfile?->update(['ktp_path' => $storagePath]);

        $this->command?->info("KTP seller diperbarui dari file: " . basename($file));
    }

    /** @return string[] */
    private function listMediaFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $files      = [];

        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions, true)) {
                $files[] = $dir . $file;
            }
        }

        sort($files);
        return $files;
    }
}
