# Data Dummy Seed

Folder ini berisi data dummy untuk keperluan demo dan presentasi.
Isi dengan file nyata, lalu jalankan `VehicleDummySeeder` untuk memasukkan ke database.

---

## Struktur Folder

```
data/
├── vehicles/
│   ├── kendaraan-01/
│   │   ├── meta.json       ← data teks kendaraan (wajib ada)
│   │   ├── photos/         ← foto kendaraan (JPG/PNG, boleh lebih dari 1)
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

Setelah mengisi file di folder ini, jalankan dari folder `backend/`:

```bash
# Hanya seeder kendaraan (tidak reset database)
php artisan db:seed --class=VehicleDummySeeder

# Atau reset semua + jalankan ulang dari awal
php artisan migrate:fresh --seed
```

> Seeder aman dijalankan berulang — kendaraan yang sudah ada (berdasarkan
> kombinasi brand + model + year) tidak akan dibuat duplikat.
