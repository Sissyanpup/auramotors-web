# Cara Menjalankan Proyek Web Jual Beli Kendaraan

Stack: **Laravel 13** (backend, port 8000) + **Next.js 16** (frontend, port 3000) + SQLite

---

## Prasyarat

Pastikan sudah terinstall di perangkat:

| Tool | Versi minimum |
|------|--------------|
| PHP | 8.3+ |
| Composer | 2.x |
| Node.js | 18+ |
| npm | 9+ |

Cek dengan:
```bash
php --version && composer --version && node --version && npm --version
```

---

## SETUP PERTAMA KALI (hanya dilakukan sekali)

### 1. Setup Backend (Laravel)

```bash
cd backend

# Install dependensi PHP
composer install

# Buat file .env dari template
cp .env.example .env

# Generate APP_KEY (wajib, sekali saja)
php artisan key:generate

# Buat file database SQLite
touch database/database.sqlite

# Jalankan migrasi & seeder (membuat tabel + akun admin)
php artisan migrate --seed
```

> **Catat password admin** yang muncul di output seeder — formatnya:
> `Admin dev account: admin@auramotors.test / <password_random>`
> Password ini **tidak bisa dilihat lagi** kecuali reset database. Simpan di tempat aman.

### 2. Setup Frontend (Next.js)

```bash
cd frontend

# Install dependensi Node
npm install

# Buat file .env dari template
cp .env.example .env.local
```

---

## MENJALANKAN PROYEK (setiap sesi)

Butuh **2 terminal** yang berjalan bersamaan.

### Terminal 1 — Backend

```bash
cd backend
php artisan serve
```

Backend berjalan di: http://localhost:8000

### Terminal 2 — Frontend

```bash
cd frontend
npm run dev
```

Frontend berjalan di: http://localhost:3000

---

## Akun Login Default

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@auramotors.test` | *(lihat output seeder saat setup pertama)* |
| Seller | daftar via halaman registrasi | — |
| Buyer | daftar via halaman registrasi | — |

> Jika lupa password admin, reset database dengan:
> ```bash
> cd backend
> php artisan migrate:fresh --seed
> ```
> Password baru akan muncul di output.

---

## Langkah Presentasi (Checklist)

Sebelum presentasi, lakukan ini secara berurutan:

- [ ] **Buka 2 terminal** di folder root proyek
- [ ] **Terminal 1**: `cd backend && php artisan serve`
- [ ] **Terminal 2**: `cd frontend && npm run dev`
- [ ] **Buka browser** ke http://localhost:3000
- [ ] Pastikan tidak ada pesan error di kedua terminal
- [ ] Login sebagai admin dengan kredensial yang sudah dicatat

### Urutan Demo yang Direkomendasikan

1. **Login sebagai Admin** → tunjukkan dashboard admin
2. **Daftar akun Seller baru** → isi profil seller (KYC)
3. **Admin approve seller** → verifikasi akun seller
4. **Login sebagai Seller** → tambah listing kendaraan
5. **Admin approve listing** → kendaraan tampil di marketplace
6. **Daftar akun Buyer** → lihat listing, lakukan checkout
7. **Lihat flow escrow** → transaksi pending → admin confirm → disbursement

---

## Troubleshooting

### Error: `No application encryption key has been specified`
```bash
cd backend && php artisan key:generate
```

### Error: `SQLSTATE[HY000]: no such table`
```bash
cd backend && php artisan migrate
```

### Error: `Cannot find module` (frontend)
```bash
cd frontend && npm install
```

### Port sudah dipakai (EADDRINUSE / address already in use)
```bash
# Cari proses yang memakai port 8000 atau 3000
lsof -i :8000
lsof -i :3000
# Kill proses tersebut, lalu jalankan ulang
kill -9 <PID>
```

### Reset database (data hilang semua, akun baru)
```bash
cd backend && php artisan migrate:fresh --seed
```

---

## Akses dari Perangkat Lain (WiFi yang Sama)

IP lokal mesin host: **`192.168.88.253`**

Config sudah diatur. Jalankan server dengan flag berikut:

| Terminal | Perintah |
|----------|---------|
| Backend  | `cd backend && php artisan serve --host=0.0.0.0` |
| Frontend | `cd frontend && npm run dev -- -H 0.0.0.0` |

Dari perangkat lain, buka browser ke:
```
http://192.168.88.253:3000
```

> **Catatan IP berubah**: Jika IP host berubah (karena DHCP router), update 2 file berikut dengan IP baru:
> - `backend/.env` → `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`
> - `frontend/.env.local` → `NEXT_PUBLIC_API_URL`
>
> Cek IP lokal saat ini dengan: `ip addr show | grep 'inet ' | grep -v '127.0.0.1'`
