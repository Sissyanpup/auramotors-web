# CLAUDE.md — Web Jual Beli Kendaraan

Dokumen ini adalah **master index** untuk proyek tugas mata kuliah _Pemrograman Web 2_: aplikasi web jual-beli kendaraan multi-role (admin/seller/buyer) dengan lapisan payment/escrow. Isinya dirangkum dari seluruh file `.txt`/`.md` eksplorasi yang sudah ada di root proyek, lalu dipecah jadi spesifikasi per topik di folder [`docs/`](./docs/).

## 1. Status Proyek

Proyek sudah menyelesaikan **Sprint 1–4** (Epic 1–4: auth/listing, payment collection, escrow state machine, disbursement ke seller — lihat `docs/sprint-log.md` untuk detail tiap sprint) dan sedang bersiap masuk **Sprint 5** (Epic 5 — polish & performa, lihat `docs/06-product-backlog.md`). Dokumentasi hasil Sprint 0 tetap jadi acuan:

| File sumber (root)          | Isi                                                                                                                             | Sudah dirangkum di                                                                 |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| `saran.txt`                 | Rekomendasi domain, role, tech stack, payment/escrow, urutan MVP, checklist performa                                            | `docs/01`, `docs/02`, `docs/03`, `docs/06`                                         |
| `referensi-umum.md`         | Pola activity diagram generik untuk CRUD (Add/Edit/Delete)                                                                      | `docs/04` (ringkasan + pointer, file asli tetap dipakai sebagai referensi lengkap) |
| `log-chat-konteks.txt`      | Log percakapan eksplorasi konsep desain "AuraMotors" (marketplace kendaraan mewah) — daftar layar yang sudah dirancang per role | `docs/05`                                                                          |
| `!-- Design System --.html` | Kode HTML/Tailwind mentah (~7000 baris) hasil generate desain visual AuraMotors (tema Obsidian & Champagne Gold)                | `docs/05` (token desain + inventori layar diekstrak)                               |

> `!-- Design System --.html` adalah **rujukan UI/UX** proyek ini (bukan sekadar draft) — dibuka langsung di browser untuk melihat tampilan tema AuraMotors. Sudah di-rename dari `.txt` ke `.html` agar bisa dirender.

## 2. Ringkasan Proyek

- **Domain**: marketplace kendaraan (bukan e-commerce barang kecil) → nilai transaksi tinggi, wajib ada verifikasi dokumen (STNK/BPKB) dan **escrow** (dana buyer ditahan sampai serah-terima terkonfirmasi).
- **4 peran/komponen minimum**: Admin, Seller, Buyer, Payment/Escrow layer.
- **Stack rekomendasi**: Next.js (frontend) + Laravel/NestJS (backend) + PostgreSQL + Sanctum/JWT + S3-compatible storage.
- **Payment**: Xendit direkomendasikan (butuh disbursement ke seller), pola escrow holding-then-release.
- **Konsep desain eksploratif**: tema "AuraMotors" (luxury automotive marketplace) — opsional dipakai, lihat `docs/05`.

Detail lengkap tiap topik ada di dokumen berikut:

1. [`docs/01-domain-dan-peran.md`](./docs/01-domain-dan-peran.md) — Domain modeling, 4 peran, prinsip desain domain.
2. [`docs/02-tech-stack-arsitektur.md`](./docs/02-tech-stack-arsitektur.md) — Stack per layer, checklist performa 20 poin, urutan build.
3. [`docs/03-payment-escrow.md`](./docs/03-payment-escrow.md) — Pola escrow, pilihan provider (Xendit vs Midtrans), state machine transaksi.
4. [`docs/04-activity-diagram-crud.md`](./docs/04-activity-diagram-crud.md) — Ringkasan pola CRUD (pointer ke `referensi-umum.md` untuk versi lengkap + diagram Mermaid).
5. [`docs/05-desain-ui-auramotors.md`](./docs/05-desain-ui-auramotors.md) — Ringkasan tema visual, design tokens, & inventori 11 layar yang sudah dirancang.
6. [`docs/06-product-backlog.md`](./docs/06-product-backlog.md) — Backlog per epic, siap dipetakan ke sprint.

## 3. Siklus Agile (Scrum) untuk Proyek Ini

Proyek dikerjakan dalam **5 sprint** mengikuti urutan _deployment-first_ dari `saran.txt` (backlog detail per epic ada di [`docs/06-product-backlog.md`](./docs/06-product-backlog.md)). Setiap sprint mengikuti siklus Scrum standar:

```
Sprint Planning → Daily Progress Check → Development & Self-Review → Sprint Review (Demo) → Retrospective → (ulang ke sprint berikutnya)
```

### Langkah dalam Satu Siklus Sprint

1. **Sprint Planning** — pilih item dari `docs/06-product-backlog.md` untuk sprint ini, pecah jadi task kecil, definisikan _Definition of Done_ (DoD) tiap task.
2. **Daily Progress Check** — cek singkat: apa yang sudah dikerjakan, apa yang dikerjakan berikutnya, ada blocker atau tidak (untuk tugas solo/kelompok kecil, ini bisa berupa catatan harian singkat, tidak harus meeting formal).
3. **Development** — implementasi task sesuai spesifikasi di `docs/`, ikuti pola CRUD (`docs/04`) & prinsip domain (`docs/01`) supaya konsisten antar modul.
4. **Self-Review / Code Review** — sebelum ditandai selesai, cek terhadap DoD: validasi client+server ada, confirm dialog hanya di aksi berisiko, audit trail untuk Edit/Delete, dsb.
5. **Sprint Review (Demo)** — jalankan/demokan fitur yang selesai di sprint ini terhadap acceptance criteria backlog.
6. **Retrospective** — catat apa yang berjalan baik/buruk, sesuaikan rencana sprint berikutnya (misalnya: apakah checklist performa di `docs/02` perlu dicicil lebih awal).

### Pemetaan Epic → Sprint

| Sprint                          | Fokus (Epic)                                                                     | Sumber Backlog                 |
| ------------------------------- | -------------------------------------------------------------------------------- | ------------------------------ |
| **Sprint 0** _(sudah berjalan)_ | Eksplorasi domain, stack, & desain visual (dokumen ini)                          | — (hasil: `docs/01`–`docs/05`) |
| **Sprint 1**                    | Epic 1 — Auth, role, KYC seller, CRUD listing kendaraan, gate verifikasi admin   | `docs/06` Epic 1               |
| **Sprint 2**                    | Epic 2 — Integrasi Xendit sandbox (collection/pembayaran masuk), checkout        | `docs/06` Epic 2               |
| **Sprint 3**                    | Epic 3 — State machine escrow, admin dashboard approval, tracking, dispute dasar | `docs/06` Epic 3               |
| **Sprint 4**                    | Epic 4 — Disbursement ke seller (manual → otomatis)                              | `docs/06` Epic 4               |
| **Sprint 5**                    | Epic 5 — Polish, checklist performa, (opsional) penerapan tema AuraMotors        | `docs/06` Epic 5               |

> Catatan: Epic 5 (performa) sebaiknya **tidak ditunda seluruhnya ke Sprint 5** — sisipkan item relevan (index database, pagination, image compression) di setiap sprint begitu modul terkait dibuat, jangan menumpuk semua optimisasi di akhir.

### Artefak Agile yang Dipakai

- **Product Backlog** → [`docs/06-product-backlog.md`](./docs/06-product-backlog.md) (checklist per epic, update centang saat task selesai).
- **Definition of Done** per task → validasi client+server lolos, audit trail ada (jika Edit/Delete/Approve), tidak ada regresi di checklist performa terkait.
- **Sprint Log** → [`docs/sprint-log.md`](./docs/sprint-log.md) — mencatat progres tiap sprint (tanggal mulai/selesai, daily check, demo, retro). Sprint 0 sudah diisi (hasil kerja dokumentasi ini); update entri Sprint 1 dst. saat sprint berjalan.

## 4. Panduan Pakai Dokumen Ini untuk Kerja Selanjutnya

- Kalau menambah fitur baru: tentukan dulu masuk epic mana di `docs/06`, lalu ikuti pola di `docs/04` untuk flow CRUD-nya.
- Kalau ada keputusan tech stack yang berubah dari rekomendasi di `docs/02`, update dokumen tersebut agar tidak menyesatkan pembaca berikutnya.
- File mentah (`saran.txt`, `log-chat-konteks.txt`, `referensi-umum.md`, `!-- Design System --.html`) **tidak dihapus** — tetap disimpan sebagai riwayat/raw reference, tapi untuk kerja sehari-hari cukup baca `docs/` + file ini.
