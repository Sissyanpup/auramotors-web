# AuraMotors — Web Jual Beli Kendaraan

Aplikasi web marketplace kendaraan multi-role (**Admin, Seller, Buyer**) dengan lapisan **pembayaran & escrow**. Dana buyer ditahan platform sampai serah-terima kendaraan terkonfirmasi, baru kemudian dicairkan ke seller. Dikerjakan sebagai tugas kelompok mata kuliah **Pemrograman Web 2** dengan tema visual _AuraMotors_ (luxury automotive, palet Obsidian & Champagne Gold).

> Proyek ini dirancang untuk **berjalan sepenuhnya offline** (kelas tanpa akses internet). Payment gateway, disbursement, VIN check, dan GPS pengiriman memakai driver **mock/simulasi** secara default. Integrasi Xendit asli tetap tersedia sebagai driver opsional.

> **Login cepat:** `admin@auramotors.test` · `seller@auramotors.test` · `buyer@auramotors.test`, semuanya dengan password **`password123`**. Daftar lengkap akun ada di [Akun demo](#akun-demo), cara menjalankan di [Instalasi & Menjalankan](#instalasi--menjalankan).

---

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Tech Stack](#tech-stack)
- [Struktur Repositori](#struktur-repositori)
- [Arsitektur & Alur Transaksi](#arsitektur--alur-transaksi)
- [Instalasi & Menjalankan](#instalasi--menjalankan)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Testing](#testing)
- [Ringkasan API](#ringkasan-api)
- [Halaman Frontend](#halaman-frontend)
- [Dokumentasi & Progres Sprint](#dokumentasi--progres-sprint)
- [Tim Pengembang](#tim-pengembang)

---

## Fitur Utama

### Autentikasi & Peran

- Registrasi/login dengan **Laravel Sanctum (SPA cookie session)**, tiga role: `admin`, `seller`, `buyer`.
- Otorisasi berbasis **Policy** per resource (bukan pengecekan ad-hoc).
- Gate data KTP/NIK: pengguna wajib melengkapi data identitas sebelum menjelajah katalog (`/lengkapi-data`).
- Profil pengguna: edit data, ganti password, avatar.

### Seller

- **KYC seller** untuk individu atau perusahaan (perusahaan wajib unggah Certificate of Incorporation, Articles of Association, dan data UBO).
- **CRUD listing kendaraan** dengan foto (otomatis dikompres), dokumen STNK/BPKB, lalu diajukan ke admin untuk direview.
- Dokumen tambahan: service history, inspection report, certificate of authenticity.
- **VIN check** (mock deterministik, misalnya pola `STLN` = diblokir, `CRSH` = peringatan).
- **Polis asuransi** kendaraan: All Risk, TLO, dan Agreed Value.
- Rekening bank untuk pencairan dana.

### Buyer

- Katalog publik dengan filter dan halaman detail kendaraan.
- **Checkout** dengan skema pembayaran **DP (5/10/15/20%/custom) atau bayar penuh**.
- **KYC buyer + Proof of Funds**, wajib untuk transaksi di atas ambang nilai (default Rp500 juta).
- Halaman pembayaran simulasi (kartu, bank, OTP dummy) untuk demo offline.
- Tracking transaksi, konfirmasi serah-terima, pelaporan dispute, dan nota transaksi.

### Admin

- Review **KYC seller & buyer** serta **listing kendaraan** (approve/reject disertai alasan, tercatat di audit trail).
- Dashboard escrow: approve serah-terima, approve pelepasan dana, **pencairan (disbursement)**, tandai selesai, dan resolusi dispute (refund atau lanjutkan).
- **Rekonsiliasi payout**: komisi platform (default 3%) vs dana yang dicairkan ke seller.
- Mengubah status pengiriman lintas negara.

### Escrow, Dokumen & Logistik

- **State machine escrow**: `escrow_hold → serah_terima → payout_release → selesai`, dengan cabang `dispute → refunded`. Setiap transisi tercatat di `transaction_status_histories` (append-only).
- **Auto-generated PDF** (dompdf): Commercial Invoice, Sales Purchase Agreement, Deposit Receipt, Tax Invoice (PPN/PPnBM dummy), Bill of Sale, dan Escrow Disbursement Note.
- **E-signature** buyer/seller melalui canvas signature pad. SPA otomatis dibuat ulang dengan tanda tangan terbaru.
- **Virtual Data Room (VDR)** per transaksi, hanya bisa diakses pihak yang terlibat.
- **State machine shipment** lintas negara: `draft → logistics_prep → in_transit → customs_clearance → delivered` (plus `delayed`). Ada guard dokumen di tiap tahap: cargo insurance, Export Declaration, B/L atau AWB, Import Declaration, Customs Duty Receipt, Proof of Delivery.
- **Peta pengiriman dummy** (Leaflet) untuk visualisasi lokasi kendaraan.
- Dokumen privat dilayani melalui **temporary signed URL** (HMAC + expiry), tidak ada URL publik permanen.

### UX & Performa

- Konfirmasi berlapis melalui modal in-app (`ConfirmModal`, `DangerConfirmModal`) untuk setiap aksi transaksi berisiko.
- Badge navbar (keranjang, notifikasi, pesan, chat).
- Cache katalog, kompresi gambar, index database, pagination, dan `next/image`.
- Lighthouse: Performance 97 (katalog) / 89 (detail); Accessibility & SEO 100.

---

## Tech Stack

| Layer    | Teknologi                                                               |
| -------- | ----------------------------------------------------------------------- |
| Backend  | **Laravel 13** (PHP 8.3+), Laravel Sanctum 4, barryvdh/laravel-dompdf 3 |
| Frontend | **Next.js 16** (App Router), React 19, TypeScript 5, Tailwind CSS 4     |
| Peta     | Leaflet + react-leaflet                                                 |
| Database | SQLite (lokal/demo). Rekomendasi produksi: PostgreSQL                   |
| Payment  | Abstraksi `PaymentGateway`: driver `mock` (default) / `xendit`          |
| Payout   | Abstraksi `DisbursementGateway`: driver `manual` (default) / `xendit`   |
| Testing  | PHPUnit (feature & unit test), `tsc`, ESLint, `next build`              |

---

## Struktur Repositori

```
web-jual-beli-kendaraan/
├── backend/                  # Laravel 13 API (port 8000)
│   ├── app/
│   │   ├── Documents/        # DocumentGenerator — generator PDF transaksi
│   │   ├── Enums/            # Status escrow, pembayaran, shipment, role, dsb.
│   │   ├── Escrow/           # EscrowStateMachine — satu-satunya tempat logika transisi escrow
│   │   ├── Http/             # Controllers (Admin/Auth/Buyer/Seller/Catalog/Payments), Requests, Resources, Middleware
│   │   ├── Models/           # User, Vehicle, Transaction, Shipment, *Profile, *Document, dsb.
│   │   ├── Payments/         # PaymentGateway + MockGateway + XenditGateway
│   │   ├── Payouts/          # DisbursementGateway + Manual/Xendit + PayoutService
│   │   ├── Policies/         # Otorisasi per resource
│   │   ├── Shipping/         # ShipmentStateMachine
│   │   ├── Support/          # CatalogCache, ImageOptimizer, InvoiceNumber, SignedDocumentUrl
│   │   └── VinCheck/         # VinCheckService (mock)
│   ├── config/               # kyc.php, payment.php, payout.php, dsb.
│   ├── database/             # migrations, seeders (+ data dummy kendaraan), factories, database.sqlite
│   ├── routes/api.php        # Seluruh endpoint REST
│   └── tests/                # Feature & Unit test PHPUnit
├── frontend/                 # Next.js 16 (port 3000)
│   └── src/
│       ├── app/              # Halaman (admin/, seller/, buyer/, checkout/, kendaraan/, ...)
│       ├── components/       # Komponen UI bersama (modal, badge, VDR, signature pad, peta, ...)
│       ├── contexts/         # Auth context
│       └── lib/              # api.ts, types.ts, format.ts
├── docs/                     # Spesifikasi per topik + sprint log + referensi desain
├── CLAUDE.md                 # Master index dokumentasi & siklus Scrum
└── !-- Design System --.html # Rujukan UI/UX AuraMotors (buka di browser)
```

---

## Arsitektur & Alur Transaksi

```
 Next.js (3000) ──cookie Sanctum + CSRF──▶ Laravel API (8000) ──▶ SQLite
                                               │
                     ┌─────────────────────────┼──────────────────────────┐
                     ▼                         ▼                          ▼
              PaymentGateway          EscrowStateMachine         DisbursementGateway
              (mock | xendit)         + status history           (manual | xendit)
                                               │
                                     DocumentGenerator (PDF) ──▶ Virtual Data Room
```

**Alur end-to-end:**

1. Seller mendaftar dan mengisi KYC, lalu **admin approve KYC**.
2. Seller membuat listing dan mengajukan review, lalu **admin approve listing**. Kendaraan tampil di katalog.
3. Buyer checkout (DP atau penuh). Invoice dan SPA dibuat otomatis.
4. Buyer membayar (mock/Xendit). Status menjadi **`escrow_hold`**, kendaraan berstatus `sold`, dan Deposit Receipt serta Tax Invoice dibuat.
5. Buyer & seller menandatangani SPA, lalu masing-masing mengonfirmasi serah-terima (**`serah_terima`**).
6. Admin approve serah-terima (Bill of Sale dibuat), lalu approve pelepasan dana (**`payout_release`**).
7. Admin mencairkan dana ke rekening seller (dikurangi komisi). Escrow Disbursement Note dibuat, status menjadi **`selesai`**.
8. _(Opsional)_ Pengiriman lintas negara berjalan melalui state machine shipment terpisah.

Dispute bisa dibuka buyer/seller selama escrow berjalan dan diselesaikan admin dengan **refund** atau **lanjutkan transaksi**.

---

## Instalasi & Menjalankan

### Prasyarat

| Tool     | Versi                                                           |
| -------- | --------------------------------------------------------------- |
| PHP      | 8.3+ (ekstensi `pdo_sqlite`, `sqlite3`, `gd`, `fileinfo` aktif) |
| Composer | 2.x                                                             |
| Node.js  | 18+ (npm 9+)                                                    |

Tidak perlu MySQL/PostgreSQL. Database memakai file SQLite dan semuanya jalan offline.

### Langkah 1: Setup backend (sekali saja)

```bash
cd backend
composer install
composer demo-setup
```

`composer demo-setup` membuat `.env`, generate `APP_KEY`, membuat `database/database.sqlite`, menjalankan migrasi + **seeder** (akun demo dan 6 listing kendaraan), lalu `storage:link`. Perintah ini bisa dijalankan ulang kapan saja untuk **mereset database** ke kondisi demo awal.

### Langkah 2: Setup frontend (sekali saja)

```bash
cd frontend
npm install
cp .env.example .env.local      # di CMD Windows: copy .env.example .env.local
```

### Langkah 3: Jalankan (2 terminal)

```bash
# Terminal 1
cd backend && php artisan serve          # http://localhost:8000

# Terminal 2
cd frontend && npm run dev               # http://localhost:3000
```

Buka **http://localhost:3000** lalu login dengan salah satu akun di bawah.

### Akun demo

Semua akun memakai password **`password123`**. Data KTP sudah terisi, jadi tidak perlu melewati halaman `/lengkapi-data`.

| Role   | Email                     | Password      | Kondisi awal                                                   |
| ------ | ------------------------- | ------------- | -------------------------------------------------------------- |
| Admin  | `admin@auramotors.test`   | `password123` | Akses semua dashboard admin                                    |
| Seller | `seller@auramotors.test`  | `password123` | KYC approved, rekening bank terisi, pemilik 6 listing approved |
| Seller | `seller2@auramotors.test` | `password123` | KYC **pending** (untuk demo admin approve/reject KYC seller)   |
| Buyer  | `buyer@auramotors.test`   | `password123` | KYC buyer approved, bisa checkout kendaraan > Rp500 juta       |
| Buyer  | `buyer2@auramotors.test`  | `password123` | KYC buyer **pending** (untuk demo admin review KYC buyer)      |

Akun baru tetap bisa dibuat melalui halaman **Register**.

### Urutan demo yang disarankan

1. Login `buyer@auramotors.test`, pilih kendaraan di katalog, lalu checkout (DP atau penuh).
2. Bayar di halaman pembayaran simulasi (isi nomor kartu/OTP bebas). Status menjadi `escrow_hold`.
3. Buyer dan `seller@auramotors.test` masing-masing tanda tangan SPA dan konfirmasi serah-terima.
4. Login `admin@auramotors.test`: approve serah-terima, approve pelepasan dana, lalu cairkan dana ke seller. Status menjadi `selesai`.
5. Di dashboard admin, review KYC `seller2` dan `buyer2` yang masih pending.

### Akses dari HP/laptop lain (WiFi yang sama, opsional)

1. Cek IP lokal komputer host (`ipconfig` di Windows, `ip addr` di Linux/macOS), misalnya `192.168.1.10`.
2. Ganti `localhost` dengan IP tersebut di:
   - `backend/.env`: `APP_URL=http://192.168.1.10:8000`, `SANCTUM_STATEFUL_DOMAINS=localhost:3000,192.168.1.10:3000`, `FRONTEND_URL=http://localhost:3000,http://192.168.1.10:3000`, `SESSION_DOMAIN=` (kosongkan)
   - `frontend/.env.local`: `NEXT_PUBLIC_API_URL=http://192.168.1.10:8000`
   - `frontend/next.config.ts`: `allowedDevOrigins: ["192.168.1.10"]` (tanpa ini halaman tampil tetapi tombol tidak berfungsi)
3. Jalankan server dengan `php artisan serve --host=0.0.0.0` dan `npm run dev -- -H 0.0.0.0`, lalu buka `http://192.168.1.10:3000` dari perangkat lain.

### Troubleshooting

| Gejala                                             | Solusi                                                                                                                             |
| -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `No application encryption key has been specified` | `cd backend && php artisan key:generate`                                                                                           |
| `no such table` / data berantakan                  | `cd backend && composer demo-setup` (reset database ke kondisi demo)                                                               |
| Foto kendaraan tidak muncul                        | `cd backend && composer demo-setup`. Penyebabnya biasanya `public/storage` berupa folder biasa, bukan link ke `storage/app/public` |
| `Cannot find module` di frontend                   | `cd frontend && npm install`                                                                                                       |
| Login gagal / error CORS                           | Pastikan frontend dibuka di `http://localhost:3000` dan `FRONTEND_URL` di `backend/.env` sesuai                                    |
| Port 8000/3000 sudah dipakai                       | Tutup proses lain, atau jalankan `php artisan serve --port=8001` lalu sesuaikan `NEXT_PUBLIC_API_URL`                              |

---

## Konfigurasi Environment

**`backend/.env`** (variabel penting):

| Variabel                             | Default                 | Keterangan                                     |
| ------------------------------------ | ----------------------- | ---------------------------------------------- |
| `DB_CONNECTION`                      | `sqlite`                | Database                                       |
| `FRONTEND_URL`                       | `http://localhost:3000` | Origin CORS (boleh beberapa, pisahkan koma)    |
| `SANCTUM_STATEFUL_DOMAINS`           | `localhost:3000`        | Domain SPA yang memakai cookie session         |
| `PAYMENT_GATEWAY_DRIVER`             | `mock`                  | `mock` (offline) atau `xendit`                 |
| `PAYOUT_GATEWAY_DRIVER`              | `manual`                | `manual` (offline) atau `xendit`               |
| `XENDIT_SECRET_KEY`                  | kosong                  | Diisi hanya jika memakai driver `xendit`       |
| `XENDIT_CALLBACK_VERIFICATION_TOKEN` | kosong                  | Verifikasi webhook `POST /api/webhooks/xendit` |
| `PLATFORM_COMMISSION_RATE`           | `0.03`                  | Komisi platform saat disbursement (3%)         |
| `KYC_HIGH_VALUE_THRESHOLD`           | `500000000`             | Ambang nilai (Rp) yang mewajibkan KYC buyer    |

**`frontend/.env.local`**:

| Variabel              | Default                 |
| --------------------- | ----------------------- |
| `NEXT_PUBLIC_API_URL` | `http://localhost:8000` |

---

## Testing

```bash
# Backend — PHPUnit (feature + unit)
cd backend
php artisan test

# Frontend — type check, lint, build
cd frontend
npx tsc --noEmit
npm run lint
npm run build
```

Cakupan test backend meliputi autentikasi, KYC seller/buyer, listing & katalog, checkout & gate nilai tinggi, mock payment & webhook Xendit (`Http::fake`), state machine escrow, payout & rekonsiliasi, generator PDF, shipment, dan image optimizer.

---

## Ringkasan API

Semua endpoint berada di prefix `/api`. Rute bertanda 🔒 memerlukan sesi Sanctum.

| Grup                 | Endpoint utama                                                                                                                                                                                                           |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Auth                 | `POST /auth/register`, `POST /auth/login`, 🔒 `POST /auth/logout`, 🔒 `GET /auth/me`, 🔒 `PUT /auth/profile[/password\|/ktp]`                                                                                            |
| Katalog (publik)     | `GET /vehicles`, `GET /vehicles/{id}`                                                                                                                                                                                    |
| Seller 🔒            | `/seller/kyc`, `/seller/bank-account`, `/seller/vehicles` (CRUD + `submit-for-review`), `/documents`, `/insurance`, `/vin-checks`, `/seller/transactions` (+ `confirm-handover`, `dispute`)                              |
| Buyer 🔒             | `/buyer/kyc`, `POST /buyer/vehicles/{id}/checkout`, `/buyer/transactions` (+ `refresh-status`, `cancel`, `confirm-handover`, `dispute`)                                                                                  |
| Admin 🔒             | `/admin/kyc`, `/admin/buyer-kyc`, `/admin/vehicles`, `/admin/transactions` (+ `approve-handover`, `approve-payout`, `disburse`, `mark-completed`, `resolve-dispute`), `/admin/payouts/reconciliation`, transisi shipment |
| Pembayaran           | 🔒 `/payments/mock/{reference}` (+ `pay`, `fail`), `POST /webhooks/xendit`                                                                                                                                               |
| Transaksi bersama 🔒 | `POST /transactions/{id}/sign`, `/transactions/{id}/shipment` (+ `update`, `documents`)                                                                                                                                  |
| Dokumen privat       | Rute `signed` untuk dokumen KYC, kendaraan, polis, transaksi, dan shipment                                                                                                                                               |

Daftar lengkap: [`backend/routes/api.php`](./backend/routes/api.php) atau `php artisan route:list --path=api`.

---

## Halaman Frontend

| Area   | Rute                                                                                                                             |
| ------ | -------------------------------------------------------------------------------------------------------------------------------- |
| Umum   | `/` (katalog), `/kendaraan/[id]`, `/login`, `/register`, `/lengkapi-data`, `/profil`, `/pesan`, `/chat`                          |
| Seller | `/seller/kyc`, `/seller/vehicles`, `/seller/vehicles/new`, `/seller/vehicles/[id]/edit`, `/seller/transactions[/id]`             |
| Buyer  | `/buyer/kyc`, `/checkout/[vehicleId]`, `/checkout/mock/[reference]`, `/buyer/transactions[/id]`, `/buyer/transactions/[id]/nota` |
| Admin  | `/admin/kyc`, `/admin/buyer-kyc`, `/admin/vehicles`, `/admin/transactions`, `/admin/payouts`                                     |

---

## Dokumentasi & Progres Sprint

Proyek dikerjakan dengan siklus **Scrum**. Dokumentasi detail ada di folder [`docs/`](./docs/):

| Dokumen                                                             | Isi                                                      |
| ------------------------------------------------------------------- | -------------------------------------------------------- |
| [`01-domain-dan-peran.md`](./docs/01-domain-dan-peran.md)           | Domain modeling & 4 peran                                |
| [`02-tech-stack-arsitektur.md`](./docs/02-tech-stack-arsitektur.md) | Stack, checklist performa 20 poin                        |
| [`03-payment-escrow.md`](./docs/03-payment-escrow.md)               | Pola escrow, provider, state machine                     |
| [`04-activity-diagram-crud.md`](./docs/04-activity-diagram-crud.md) | Pola activity diagram CRUD                               |
| [`05-desain-ui-auramotors.md`](./docs/05-desain-ui-auramotors.md)   | Tema visual & design tokens                              |
| [`06-product-backlog.md`](./docs/06-product-backlog.md)             | Backlog per epic                                         |
| [`sprint-log.md`](./docs/sprint-log.md)                             | Log planning, daily check, review, dan retro tiap sprint |

| Sprint | Epic                                             | Status                                   |
| ------ | ------------------------------------------------ | ---------------------------------------- |
| 0      | Eksplorasi domain, stack, desain                 | ✅                                       |
| 1      | Auth, role, KYC seller, CRUD listing, gate admin | ✅                                       |
| 2      | Payment collection (mock + Xendit sandbox)       | ✅                                       |
| 3      | State machine escrow, approval admin, dispute    | ✅                                       |
| 4      | Disbursement ke seller + rekonsiliasi            | ✅                                       |
| 5      | Polish, performa, tema AuraMotors                | ✅                                       |
| 6      | Extended KYC/AML buyer & seller perusahaan       | ✅                                       |
| 7 / 7B | Auto-generated PDF, VDR, e-signature             | ✅                                       |
| 8      | Dokumen kendaraan tambahan, VIN check, asuransi  | ✅ (stretch: NCB belum)                  |
| 9      | Logistik lintas negara, bea cukai, delivery      | ✅ (stretch: PDF delivery receipt belum) |

**Pengembangan berikutnya** (dari catatan revisi dosen): rincian asuransi/kompri/pajak/cicilan yang lebih detail, serta metode pembayaran **kredit**.

---

## Tim Pengembang

| Nama                   | NIM          |
| ---------------------- | ------------ |
| Muhammad Zirlda Prairi | 231011402293 |
| _(anggota 2)_          | —            |
| _(anggota 3)_          | —            |

---

> **Catatan:** Proyek ini dibuat untuk keperluan akademik. Seluruh data (KTP, kartu, rekening, VIN, dll.) adalah data dummy, dan pembayaran tidak memproses uang sungguhan.
