# Tech Stack & Arsitektur

> Sumber asli: `saran.txt` (bagian 2 & poin performa di akhir file).

## 1. Stack Final (Keputusan)

> Dosen tidak mewajibkan framework tertentu. Stack di bawah **sudah final** (dikonfirmasi 2026-09-17), dipilih atas dasar prioritas _selesai tepat waktu dengan fitur lengkap_ untuk pengerjaan solo — bukan lagi opsi terbuka.

| Layer       | Keputusan                                                                        | Alasan                                                                                                                                                                                                                                                                                                    |
| ----------- | -------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Frontend    | **Next.js 16 (App Router)**                                                      | Listing kendaraan butuh SEO bagus (orang cari "jual mobil Avanza 2020 Tangerang" di Google) + dashboard dinamis untuk 3 role berbeda.                                                                                                                                                                     |
| Backend/API | **Laravel 13**                                                                   | Dipilih di atas NestJS karena kecepatan bangun untuk solo dev: Eloquent ORM, Sanctum, migration, form request, policy sudah built-in — mengurangi boilerplate dibanding setup DI/module manual di NestJS. Cocok untuk target selesai tepat waktu dengan scope fitur lengkap (auth, KYC, escrow, payment). |
| Database    | **SQLite** (revisi Sprint 5, lihat catatan di bawah)                             | Awalnya direkomendasikan PostgreSQL untuk ACID kuat + JSONB, tapi tugas ini jalan solo & offline tanpa deployment sungguhan — SQLite sudah terbukti stabil dari Sprint 1 (75 test PHPUnit lolos) dan tidak ada fitur yang benar-benar butuh JSONB (spesifikasi kendaraan disimpan sebagai kolom biasa).            |
| Auth        | **Laravel Sanctum** + verifikasi dokumen manual/semi-otomatis (OCR KTP opsional) | Buyer auth ringan, seller wajib KYC sebelum bisa listing.                                                                                                                                                                                                                                                 |
| Storage     | **S3-compatible**                                                                | Foto kendaraan banyak (10–20 per listing) + dokumen sensitif harus terpisah dari bucket publik.                                                                                                                                                                                                           |

> Kalau nanti ada keputusan yang berubah dari yang tercatat di atas, update tabel ini agar tidak menyesatkan pembaca berikutnya (lihat `CLAUDE.md` bagian 4).

### 1b. Keputusan Final: SQLite vs PostgreSQL (ditutup Sprint 5, 2026-09-18)

Sejak Sprint 1 keputusan ini digantung ("dites dulu pakai SQLite, putuskan nanti") — ditutup di Sprint 5 dengan keputusan **tetap SQLite**, alasan:

- Tugas dikerjakan solo & dinilai secara lokal/offline (tidak ada deployment ke server produksi sungguhan), jadi argumen utama PostgreSQL (concurrency tinggi multi-instance, butuh connection pooling) tidak relevan di konteks ini.
- Tidak ada kolom yang benar-benar butuh tipe `JSONB` — spesifikasi kendaraan disimpan sebagai kolom relasional biasa, bukan dokumen semi-terstruktur.
- Migrasi ke PostgreSQL menjelang akhir pengerjaan berisiko regresi baru (query yang kebetulan SQLite-specific, perbedaan driver) tanpa manfaat yang terukur untuk scope tugas ini, dibanding 75 test PHPUnit yang sudah stabil di atas SQLite.
- Kalau proyek ini pernah dilanjutkan ke deployment produksi sungguhan, poin performa #20 (`Database Connection Pooling`, lihat §2a) tetap dicatat sebagai hal yang perlu direvisit saat itu terjadi.

## 1a. Struktur Repo

Monorepo — satu repo Git ini berisi dua project terpisah:

```
/backend   → Laravel 13 (API)
/frontend  → Next.js 16 (App Router)
```

Masing-masing punya `.gitignore` sendiri (`vendor/`, `node_modules/`, `.env` sudah dikecualikan). Dijalankan sebagai dua proses terpisah saat development (`php artisan serve` + `npm run dev`), berkomunikasi lewat REST API.

## 2. Checklist Performa (wajib dipertimbangkan sebelum deployment)

Daftar berikut adalah 20 poin optimisasi yang harus dicek satu per satu sebelum rilis / demo tugas:

1. Cache API response
2. Load Balancer
3. Index the Database
4. Compress Image
5. Loading Skeletons
6. Cache Expensive Queries
7. N+1 Database Queries (hindari)
8. Debounce Input Handlers
9. Split Code into Chunk
10. Add CDN
11. Server-side Caching
12. Paginate Large Lists
13. Lighthouse Audit
14. Compress API Payloads
15. Unnecessary Re-renders (hindari)
16. Minify JS and CSS
17. Add Lazy Loading
18. Defer Non-Critical Scripts
19. Unused Dependencies (bersihkan)
20. Database Connection Pooling

### 2a. Status per Poin (Sprint 5, diisi 2026-09-17)

| # | Poin | Status | Catatan |
| - | --- | --- | --- |
| 1 | Cache API response | ✅ Selesai | `App\Support\CatalogCache` — katalog publik (`GET /api/vehicles`) di-cache 30s per kombinasi filter, key pakai version counter yang otomatis bump di setiap `Vehicle::saved()`/`deleted()` supaya approve/reject/sold tidak pernah kelihatan stale. |
| 2 | Load Balancer | ➖ N/A | Kelas berjalan offline, satu instance lokal (`docs/03` §6) — tidak ada multi-server untuk di-load-balance. Relevan lagi kalau nanti benar-benar deploy ke Laravel Cloud/hosting. |
| 3 | Index the Database | ✅ Selesai | `vehicles.status`, `vehicles.brand` sudah ada dari Sprint 1; ditambah `vehicles.seller_id` & `transactions.seller_id` (dipakai query dashboard seller) di migration `2026_09_17_182633_...`. |
| 4 | Compress Image | ✅ Selesai | `App\Support\ImageOptimizer` (GD, tanpa dependency baru) — resize foto kendaraan ke maks lebar 1600px + re-encode kualitas 78 saat upload (`Seller\VehicleController`). |
| 5 | Loading Skeletons | ✅ Selesai | Katalog (`/`) pakai `<Suspense>` scoped di sekitar grid hasil (bukan `app/loading.tsx` di root — itu akan ikut membungkus semua route lain seperti `/login`/`/admin/*`); detail kendaraan (`/kendaraan/[id]`) pakai `loading.tsx` khusus folder tersebut (aman karena route daun tanpa child). |
| 6 | Cache Expensive Queries | ✅ Selesai | Sama seperti #1 — query katalog dengan filter (brand/tahun/harga/lokasi) adalah yang paling sering dipanggil publik tanpa auth. |
| 7 | N+1 Database Queries | ✅ Selesai | Endpoint list (`VehicleCatalogController@index`, `Admin\VehicleReviewController@index`, `Seller\VehicleController@index`, `TransactionController@index`) sebelumnya eager-load **semua** foto per kendaraan padahal `VehicleResource` cuma butuh 1 cover — diganti relasi `Vehicle::coverPhoto()` (HasOne, `ORDER BY sort_order`). Endpoint detail tetap load semua foto (`photos`) karena memang dipakai penuh. |
| 8 | Debounce Input Handlers | ➖ N/A | Form filter katalog pakai submit biasa (`<form method="GET">`, tombol "Filter"), bukan live-search per keystroke — tidak ada handler yang perlu di-debounce. |
| 9 | Split Code into Chunk | ✅ Otomatis | Next.js App Router sudah route-based code splitting per default (tiap `page.tsx` jadi chunk terpisah) — tidak perlu setup manual. |
| 10 | Add CDN | ➖ N/A | Tidak ada hosting/CDN di kelas ini. Foto kendaraan disarankan lewat S3-compatible + CDN kalau production (`docs/02` §1, baris Storage). |
| 11 | Server-side Caching | ✅ Selesai | Sama seperti #1/#6 (Laravel `Cache` facade, driver `database` dari `.env`). |
| 12 | Paginate Large Lists | ✅ Selesai (sejak Sprint 1-4) | Semua endpoint index (`vehicles`, `admin/vehicles`, `admin/kyc`, `seller/vehicles`, transaksi, payout) sudah `paginate()`, tidak ada `->get()` tanpa limit di controller. |
| 13 | Lighthouse Audit | ✅ Selesai (2026-09-18) | Dijalankan via `npx lighthouse` (CLI, headless Chrome) terhadap production build (`npm start`, bukan `next dev`) di `/` dan `/kendaraan/1` — bukan lewat panel DevTools manual (di luar jangkauan otomasi Claude in Chrome), tapi mesin & metodologi penilaiannya identik. Hasil: `/` — Performance 97, Accessibility 100, Best Practices 96, SEO 100 (LCP 2.6s, TBT 20ms, CLS 0.005); `/kendaraan/1` — Performance 89, Accessibility 100, Best Practices 96, SEO 100 (LCP 3.8s, TBT 20ms, CLS 0.005). Catatan: (a) skor Best Practices tertahan di 96 karena `GET /api/auth/me` mengembalikan 401 untuk pengunjung anonim (tercatat sebagai "console error" oleh Lighthouse) — ini perilaku benar (cek sesi guest), bukan bug; (b) skor "back/forward cache" gagal karena halaman dinamis (harga/status kendaraan bisa berubah, ada state auth) memakai `Cache-Control: no-store` — trade-off keamanan/kesegaran data yang disengaja untuk marketplace, bukan oversight; (c) LCP `/kendaraan/1` (3.8s) lebih tinggi dari `/` (2.6s) karena breakdown metrik menunjukan elemen LCP adalah teks deskripsi (bukan foto), TTFB cuma ~8ms — dominan dari simulasi throttling Lighthouse terhadap request jaringan tambahan di halaman detail (foto, dsb.), bukan indikasi query lambat (endpoint API sudah dioptimasi di poin #7). Laporan HTML lengkap tidak disertakan di repo (dihasilkan sekali sebagai artefak audit, bukan bagian aplikasi). |
| 14 | Compress API Payloads | 🟡 Sebagian | Payload katalog sudah lebih kecil karena fix #7 (tidak lagi kirim semua foto per kendaraan di list). Kompresi transport (gzip/brotli) sendiri biasanya tanggung jawab web server/reverse proxy (nginx) di production, bukan `artisan serve` lokal — jadi belum relevan untuk setup kelas ini. |
| 15 | Unnecessary Re-renders | 🟡 Direview | Ditinjau seluruh komponen `"use client"` — skala data & interaksi masih kecil (list terpaginasi 20/halaman, tidak ada list ribuan baris), belum ditemukan kasus re-render bermasalah. Revisit kalau ada halaman dengan list besar/tanpa paginasi di masa depan. |
| 16 | Minify JS and CSS | ✅ Otomatis | `next build` (Turbopack, production mode) minify JS/CSS secara default — tidak perlu konfigurasi tambahan. |
| 17 | Add Lazy Loading | ✅ Selesai | Foto kendaraan di katalog & detail diganti dari `<img>` ke `next/image` (lazy-load native + `priority` cuma untuk foto pertama di atas fold pada halaman detail). |
| 18 | Defer Non-Critical Scripts | ➖ N/A | Tidak ada script pihak ketiga (analytics/tracking/dsb) di aplikasi ini yang perlu di-defer. |
| 19 | Unused Dependencies | ✅ Diverifikasi bersih | `composer.json` (Laravel, Sanctum, Tinker saja) & `package.json` (Next, React, Tailwind + type packages) dicek — semua importnya benar-benar dipakai di kode, tidak ada yang menganggur. |
| 20 | Database Connection Pooling | ➖ N/A (lokal) | SQLite lokal tidak punya konsep connection pool. Keputusan final tetap SQLite (§1b) — kalau di masa depan proyek ini benar-benar dideploy produksi dan pindah ke PostgreSQL, pertimbangkan PgBouncer saat itu. |

## 3. Urutan Build (Deployment-First)

1. Auth + role (admin/seller/buyer) + listing CRUD dulu, **tanpa payment**.
2. Integrasi Xendit sandbox untuk _collection_ (buyer bayar) — test dulu tanpa disbursement.
3. Tambah state machine transaksi (escrow hold) + admin dashboard approve.
4. Baru integrasi _disbursement_ ke seller setelah flow-nya jalan manual dulu (admin trigger payout manual sebelum diotomatisasi).

Urutan ini menjadi dasar pembagian sprint — lihat [`06-product-backlog.md`](./06-product-backlog.md) dan bagian **Agile Cycle** di `CLAUDE.md`.

## 4. Terkait

- Detail payment/escrow provider → [`03-payment-escrow.md`](./03-payment-escrow.md)
