# Data Dummy Seed

Folder ini berisi data dummy untuk keperluan demo dan presentasi.
Semua kendaraan di sini otomatis masuk ke database saat `composer demo-setup` (lihat README utama), dimiliki akun `seller@auramotors.test` dengan status approved.

---

## Struktur Folder

```
data/
├── vehicles/
│   ├── kendaraan-01/
│   │   ├── meta.json       ← data teks kendaraan (wajib ada)
│   │   ├── photos/         ← foto kendaraan (JPG/PNG, boleh lebih dari 1; kalau kosong, gambar di root folder dipakai)
│   │   └── documents/      ← dokumen STNK/BPKB (JPG/PNG/PDF)
│   ├── kendaraan-02/
│   │   └── ...
│   └── kendaraan-03/
│       └── ...
└── seller/
    └── ktp/                ← foto KTP untuk akun seller dev (1 file saja)
```

> Untuk menambah kendaraan baru, cukup **buat folder baru** (misal `kendaraan-04/`)
> dengan `meta.json` dan subfolder `photos/` & `documents/`.

---

## Format `meta.json`

```json
{
  "brand": "Nama Merek",
  "model": "Nama Model",
  "year": 2022,
  "vin": "WP0AF2A97R0195421",
  "price": 195000000,
  "mileage": 18000,
  "location": "Kota, Provinsi",
  "description": "Deskripsi panjang kendaraan...",
  "specs": {
    "transmisi": "Manual / CVT Otomatis",
    "bahan_bakar": "Bensin / Diesel / Listrik",
    "warna": "Nama Warna",
    "mesin": "1500cc",
    "kapasitas_penumpang": 5,
    "tipe": "MPV / Sedan / SUV / Hatchback / Motor"
  }
}
```

| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|-----------|
| `brand` | string | Ya | Merek kendaraan |
| `model` | string | Ya | Nama model |
| `year` | integer | Ya | Tahun produksi |
| `vin` | string | Tidak | VIN 17 karakter |
| `price` | integer | Ya | Harga dalam Rupiah (tanpa titik/koma) |
| `mileage` | integer | Ya | Odometer dalam km |
| `location` | string | Ya | Kota tempat kendaraan berada |
| `description` | string | Tidak | Deskripsi bebas |
| `specs` | object | Tidak | Spesifikasi tambahan (isi bebas) |

---

## Aturan Penamaan File

### Foto (`photos/`)
- Format yang didukung: `.jpg`, `.jpeg`, `.png`, `.webp`
- Urutan tampil sesuai nama file secara alfabet: `01.jpg`, `02.jpg`, dst.
- Foto pertama otomatis jadi **cover/thumbnail** di katalog
- Tidak ada batas jumlah foto

### Dokumen (`documents/`)
- Format yang didukung: `.jpg`, `.jpeg`, `.png`, `.pdf`
- Nama file **harus diawali** `stnk` atau `bpkb` agar tipe terdeteksi otomatis
  - `stnk.jpg` → tipe `stnk`
  - `bpkb.pdf` → tipe `bpkb`
  - `stnk-depan.jpg` → tipe `stnk`

### KTP Seller (`seller/ktp/`)
- Taruh **satu file** saja di folder ini (format JPG/PNG)
- Dipakai sebagai KTP akun `seller@auramotors.test`

---

## Cara Menjalankan Seeder

Dari folder `backend/`:

```bash
# Tambah kendaraan baru saja (data lain tidak berubah)
php artisan db:seed --class=VehicleDummySeeder

# Reset semua data ke kondisi demo awal
composer demo-setup
```

> Seeder aman dijalankan berulang. Kendaraan yang sudah ada (berdasarkan
> kombinasi brand + model + year) tidak akan dibuat duplikat.
