# Desain UI/UX — Konsep "AuraMotors" (Luxury Automotive Marketplace)

> Sumber asli: `log-chat-konteks.txt` (log permintaan & ringkasan hasil desain) + `!-- Design System --.html` (kode HTML/Tailwind mentah hasil generate, ~7000 baris, satu halaman panjang berisi banyak section). Dokumen ini merangkum **konsep tema visual** dan **inventori layar** yang sudah dirancang, supaya tidak perlu membaca ulang file mentahnya.
>
> Tambahan (2026-09-18): dua file `stitch_luxury_vehicle_marketplace_platform*.zip` di root `docs/` (hasil export Google Stitch, AI UI generator) sudah diekstrak ke [`docs/design-reference/`](./design-reference/) — masing-masing folder berisi `code.html` (markup Tailwind mentah) + `screen.png` (screenshot render). Isinya versi standalone & lebih detail dari beberapa layar yang sudah masuk inventori §3 di bawah, ditambah satu layar baru. Zip aslinya tetap disimpan di `docs/` sebagai riwayat (tidak dihapus, sesuai konvensi `CLAUDE.md`).

## 1. Konsep & Tema

Nama proyek/brand konsep: **AuraMotors** — marketplace kendaraan mewah (hypercar/supercar) dengan 3 peran: **VIP Collector** (buyer), **Dealer Rekanan/Mitra** (seller), **Admin Sanctuary/Compliance** (admin).

Tema visual: **Obsidian & Champagne Gold** (dark mode premium).

> Catatan penting: istilah "VIP Collector / Sanctuary / Escrow Custodian / Dossier Forensik" adalah **flavor/istilah tema mewah** untuk peran generik **buyer/seller/admin/escrow** yang sudah didefinisikan di [`01-domain-dan-peran.md`](./01-domain-dan-peran.md). Saat implementasi, boleh dipakai sebagai copywriting, tapi jangan biarkan istilah tema ini menggantikan penamaan domain/database yang seharusnya generik (`users`, `role`, `escrow_status`, dst.).

## 2. Design Tokens (diekstrak dari `!-- Design System --.html`)

**Font:**

- Display/Headline → `Bodoni Moda` (serif, mewah)
- Body → `Manrope`
- Label/UI kecil → `Geist`

**Warna kunci (dark theme, dari `tailwind.config`):**

| Token                       | Hex                   | Kegunaan                                    |
| --------------------------- | --------------------- | ------------------------------------------- |
| `primary`                   | `#f2ca50`             | Aksen emas/champagne utama (CTA, highlight) |
| `primary-container`         | `#d4af37`             | Tombol utama                                |
| `tertiary`                  | `#eec98f`             | Aksen sekunder (badge, teks emas muda)      |
| `background` / `surface`    | `#121315`             | Latar dasar (obsidian/dark)                 |
| `surface-container`         | `#1f2022`             | Card/panel                                  |
| `on-surface`                | `#e3e2e5`             | Teks utama di atas dasar gelap              |
| `error` / `error-container` | `#ffb4ab` / `#93000a` | Status gagal/warning                        |

**Spacing scale:** `space-xs (0.375rem)`, `space-sm (0.75rem)`, `space-md (1.25rem)`, `space-lg (2rem)`, `space-xl (3.5rem)`, plus `margin`/`gutter` khusus mobile vs desktop.

**Border radius:** kecil & tajam (`DEFAULT 0.125rem` s.d. `full 0.75rem`) — kesan mewah "precision-cut", bukan rounded-friendly seperti UI konsumer biasa.

> Tim boleh reuse token ini kalau memang mau lanjutkan tema mewah, atau treat sebagai **referensi desain saja** dan pakai design system yang lebih sesuai kebutuhan tugas kampus (lebih sederhana/generik) — lihat catatan di [`CLAUDE.md`](../CLAUDE.md) bagian rekomendasi.

## 3. Inventori Layar yang Sudah Dirancang

### Portal Buyer / VIP Collector

1. **Landing/Public Page** — hero, team showcase, penjelasan platform (3 pillars), live stats, filter cepat, gallery preview, CTA login/register. Versi standalone team showcase (3 kartu kurator/broker dengan badge & foto) ada di [`design-reference/team-showcase/`](./design-reference/team-showcase/).
2. **Autentikasi & Registrasi** (`AuraMotors - Autentikasi & Registrasi VIP Kolektor`) — tab Login/Register, dukungan biometrik/FIDO2, showcase eksklusif.
3. **Dashboard Belanja Pasca-Login** (`AuraMotors - Dashboard Belanja Unit Otomotif Mewah`) — header user + saldo escrow, filter mendalam, grid katalog unit, widget layanan (financing, konsultasi, private viewing). Versi mockup terbaru: [`design-reference/dashboard-belanja/`](./design-reference/dashboard-belanja/).
4. **Halaman Detail Kendaraan** (`AuraMotors - Detail Unit ...`) — galeri multi-angle, panel transaksi cepat, tab dossier (spesifikasi teknis, laporan inspeksi forensik, riwayat dokumen/provenance, paket opsi), rekomendasi unit sejenis.
5. **Keranjang & Checkout Escrow** (`AuraMotors - Keranjang Alokasi & Transaksi Escrow`) — stepper 4 tahap, add-on services, breakdown biaya, pilihan metode settlement, diagram alur escrow, konfirmasi. Versi mockup terbaru: [`design-reference/keranjang-escrow/`](./design-reference/keranjang-escrow/).
6. **Pelacakan Transaksi & Handover** (`AuraMotors - Pelacakan Transaksi & Handover Escrow`) — stepper 5 fase (deposit terverifikasi → forensik & Samsat → pelunasan escrow → towing → handover kunci), live tracking, akses dokumen legalitas, tombol verifikasi rilis dana.
7. **Akun & Garasi Digital** (`AuraMotors - Garasi Digital & Pengelolaan Akun Kolektor`) — profil, metrik portofolio, koleksi unit dimiliki, riwayat transaksi, dokumen/sertifikat, keamanan vault (2FA, bank terhubung).

### Portal Seller / Dealer Mitra

8. **Dashboard Penjualan & Portofolio Mitra** (`AuraMotors - Dashboard Penjualan & Portofolio Mitra Dealer`) — KPI penjualan, feed tawaran/bidding masuk, armada konsinyasi aktif, status pencairan escrow.
9. **Form Listing & Kurasi Unit Baru** — stepper 4 tahap (identitas & spesifikasi unit → kliring legalitas/BPKB → upload foto/berkas → penetapan nilai & escrow).

### Portal Admin / Super Admin

10. **Master Admin & Escrow Oversight Console** — indikator konektivitas institusional (OJK, Korlantas, bank kustodian), metrik makro (volume escrow, antrean kurasi, integritas forensik), otorisasi rilis escrow, live audit log.
11. **Admin Kurasi & Verifikasi Forensik Unit** — profil unit dalam audit, dossier forensik (integritas fisik, kliring dokumen/pajak, studio visual), protokol otorisasi multi-signature sebelum unit terbit ke showroom.

> Catatan: layar #10 dan #11 baru terdokumentasi di `log-chat-konteks.txt` (deskripsi naratif), belum ada kode HTML lengkapnya di `!-- Design System --.html` (file itu berhenti di layar #8). Perlu digenerate ulang atau dibangun langsung dalam kode aplikasi jika mau dipakai.

### Layar Tambahan (opsional, di luar flow inti jual-beli)

12. **Direktori Download Aset Visual Resmi** (`AuraMotors - Direktori & Download Aset Visual Resmi`) — halaman media kit/press kit: daftar aset brand (logo, foto unit resmi, dsb.) yang bisa diunduh. Bukan bagian dari flow buyer/seller/admin inti — mockup ada di [`design-reference/direktori-aset-visual/`](./design-reference/direktori-aset-visual/), dipakai hanya kalau butuh halaman brand asset/media kit.

## 4. Alur Utama (End-to-End)

```
Buyer  : Auth VIP → Marketplace Catalog → Vehicle Detail → Escrow Cart/Checkout → Live Tracking → Digital Garage
Seller : Dashboard Portofolio → Form Listing (kurasi 4 tahap) → menunggu approval admin → terima bidding/tawaran
Admin  : Console Compliance (monitor makro) → Kurasi & Verifikasi Forensik (per unit) → Otorisasi Rilis Escrow
```

## 5. Status Implementasi (Sprint 5, 2026-09-18)

Token warna/font/radius di §2 sudah diterapkan ke seluruh aplikasi lewat `frontend/src/app/globals.css` (Tailwind v4 `@theme`), font Bodoni Moda & Manrope di-self-host lewat `next/font/local` (file di `frontend/src/app/fonts/`, bukan `next/font/google`, supaya build tetap jalan tanpa internet — lihat `docs/02` §1b), dan disebarkan ke komponen bersama (`Header`, `ConfirmModal`, `Badge` + turunannya) serta seluruh 17 halaman lain lewat script reskin satu kali (menukar kelas Tailwind `zinc/white` → token semantik `surface/on-surface/primary`, bukan menulis ulang tiap halaman dari nol). Istilah flavor mewah ("VIP Collector", "Dossier Forensik", dst.) **tidak** dipakai menggantikan penamaan domain/database — sesuai catatan di §1. Layar-layar detail di inventori §3 (stepper checkout 4 tahap, tab dossier, live tracking 5 fase, dll.) tetap versi generik yang sudah dibangun di Sprint 1-4, bukan rebuild identik dengan mockup HTML — itu di luar scope polish visual.

## 6. Rekomendasi Pemakaian untuk Implementasi

- File `!-- Design System --.html` adalah **rujukan UI/UX resmi** proyek ini — buka langsung di browser untuk melihat tampilan tiap layar. Perlakukan sebagai acuan visual/markup, bukan kode aplikasi final: tetap perlu dipecah jadi komponen di framework yang dipakai (lihat [`02-tech-stack-arsitektur.md`](./02-tech-stack-arsitektur.md)), potong per section sesuai marker HTML comment di dalamnya.
- File-file di `design-reference/*/code.html` punya perlakuan sama: buka `screen.png` dulu utk lihat hasil render, lalu comot markup/kelas Tailwind dari `code.html` yang relevan kalau mau merapikan halaman `page.tsx` (dashboard) atau `/checkout` (keranjang escrow) yang sudah jalan sejak Sprint 1-4 — bukan bikin halaman baru dari nol, karena flow-nya sudah ada.
- Sinkronkan setiap layar di atas dengan flow CRUD generik di [`04-activity-diagram-crud.md`](./04-activity-diagram-crud.md) agar konsisten dari sisi UX (confirm dialog, validasi, loading state).

## 7. Terkait

- Domain & peran → [`01-domain-dan-peran.md`](./01-domain-dan-peran.md)
- Escrow flow teknis → [`03-payment-escrow.md`](./03-payment-escrow.md)
- Backlog & sprint pembangunan layar-layar ini → [`06-product-backlog.md`](./06-product-backlog.md)
