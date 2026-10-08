# Payment & Escrow

> Sumber asli: `saran.txt` (bagian 3). Ini bagian paling kritis dari seluruh sistem — kesalahan di sini = risiko finansial nyata.

## 1. Prinsip Dasar

Untuk marketplace multi-vendor, uang buyer harus **otomatis terpisah** antara komisi platform dan payout ke seller — bukan dipisah manual.

**Pola escrow untuk kendaraan** (beda dari marketplace barang kecil):

```
Buyer bayar → uang masuk akun platform (holding)
            → admin/buyer konfirmasi serah-terima kendaraan
            → baru di-disburse ke seller
```

Bukan auto-split real-time seperti marketplace barang kecil, karena risiko sengketa jauh lebih tinggi (nilai besar, barang fisik, legalitas kompleks).

## 2. Pilihan Provider (basis Indonesia)

| Provider     | Kapan Dipilih                                                             | Catatan                                                                                                                                                          |
| ------------ | ------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Xendit**   | Kalau butuh kirim uang keluar (bayar seller, refund massal)               | Disbursement adalah produk kelas satu: payout ke 450+ bank & e-wallet, single & batch payout. Satu provider untuk terima + kirim uang → rekonsiliasi lebih rapi. |
| **Midtrans** | Kalau fokus hanya menerima pembayaran & pakai Laravel (plugin siap pakai) | Historisnya tidak punya fitur disbursement native (ada produk terpisah "Iris", tapi bukan fokus utama).                                                          |

**Rekomendasi konkret**: pakai **Xendit** dari awal jika kemungkinan besar butuh disbursement (hampir pasti untuk marketplace kendaraan), supaya tidak perlu migrasi provider di tengah jalan.

## 3. Urutan Integrasi (selaras dengan MVP)

1. Xendit **sandbox** untuk collection (buyer bayar) dulu — tanpa disbursement.
2. State machine escrow hold + admin dashboard approve.
3. Disbursement ke seller — mulai dengan **admin trigger manual** sebelum diotomatisasi penuh.

## 4. State Machine Transaksi (disarankan)

```
listing → deal → escrow_hold → serah_terima → payout_release → selesai
                     │
                     └─(dispute)→ admin_review → (refund_buyer | lanjut payout)
```

Setiap transisi state sebaiknya:

- Dicatat sebagai histori (audit trail), bukan overwrite kolom status.
- Punya pemilik aksi yang jelas (siapa yang trigger: sistem, admin, buyer, atau seller).

**Implementasi (Epic 3, Sprint 3)**: state machine di atas diimplementasikan di `App\Escrow\EscrowStateMachine` (backend), satu-satunya tempat yang boleh mengubah `transactions.escrow_status`. Setiap transisi ditulis sebagai baris baru di tabel `transaction_status_histories` (from/to status, actor, catatan, timestamp) — bukan overwrite. Alurnya:

1. Pembayaran jadi Lunas (via mock/webhook/polling) → sistem otomatis pindah ke `escrow_hold` + vehicle jadi `sold`.
2. Buyer & seller masing-masing konfirmasi serah-terima lewat endpoint sendiri (`confirm-handover`) — ini baru catatan, bukan transisi state.
3. Admin approve transisi `escrow_hold → serah_terima` (setelah kedua pihak konfirmasi), lalu `serah_terima → payout_release`, lalu `payout_release → selesai` — tiga endpoint approval terpisah di dashboard admin (`/admin/transactions`), tiap approval tercatat siapa & kapan.
4. Buyer/seller bisa membuka dispute selama `escrow_hold`/`serah_terima` → escrow_status jadi `dispute` (state sebelumnya disimpan). Admin resolve lewat refund (vehicle balik ke `approved`) atau lanjutkan (kembali ke state sebelum dispute).

## 5. Driver Payment Gateway & Kendala Offline Kelas

Kelas ini berjalan **sepenuhnya tanpa akses internet** (kesepakatan dengan dosen supaya tidak perlu bayar hosting) — bukan cuma "tidak ada domain publik", tapi benar-benar tidak ada koneksi keluar sama sekali saat demo/dinilai. Itu berarti API Xendit manapun (termasuk sandbox) tidak bisa dipanggil di kelas.

**Solusi**: abstraksi `App\Payments\PaymentGateway` (backend Laravel) dengan dua implementasi, dipilih lewat `PAYMENT_GATEWAY_DRIVER` di `.env`:

- `MockGateway` (default) — 100% offline. "Invoice" hanya berupa halaman simulasi di frontend (`/checkout/mock/{reference}`) dengan tombol "Bayar Sekarang" / "Gagalkan" yang langsung mengubah status transaksi di DB sendiri, tanpa network call.
- `XenditGateway` — panggil API asli Xendit (`/v2/invoices`), dipakai kalau developer punya akses internet di luar kelas. Sudah didukung juga endpoint webhook (`POST /api/webhooks/xendit`) dan polling fallback (`POST /api/buyer/transactions/{id}/refresh-status`) untuk kasus webhook tidak bisa diakses (belum ada tunnel publik).

Konsekuensi: alur checkout, status pembayaran (pending/paid/failed/expired), dan escrow state machine (Epic 3) tetap bisa didemokan penuh secara offline lewat `MockGateway`; fitur webhook Xendit asli tidak pernah teruji di lingkungan kelas, hanya di luar kelas oleh developer sendiri.

## 6.1 Disbursement ke Seller (Epic 4, Sprint 4)

Setelah admin approve `serah_terima → payout_release` (Epic 3), dana **belum** berpindah — `payout_release` hanya berarti "disetujui untuk dicairkan". Pencairan aktual adalah aksi terpisah, sengaja dipisah dari approval supaya ada jejak yang jelas antara "admin setuju" vs "uang benar-benar sudah dikirim":

1. Admin klik **Cairkan Dana** di dashboard (`/admin/transactions`) → `App\Payouts\PayoutService::disburse()`.
2. Service menghitung komisi platform (`PLATFORM_COMMISSION_RATE` di `.env`, default 3%) dan sisa payout ke seller, lalu mencatat percobaan sebagai baris baru di `transaction_payouts` (bukan overwrite) — pola yang sama seperti `transaction_status_histories` di Epic 3.
3. Driver dipilih lewat `PAYOUT_GATEWAY_DRIVER` (sama pola dengan `PaymentGateway` di bagian 5):
   - `manual` (default, 100% offline) — admin sudah transfer manual di luar aplikasi; sistem hanya mencatat nomor referensi transfer yang diinput admin sebagai bukti.
   - `xendit` — panggil API Disbursement Xendit asli (`/disbursements`) ke rekening bank seller (`seller_profiles.bank_name/bank_account_number/bank_account_holder_name`, diisi seller sendiri dari halaman KYC — tidak perlu direview ulang admin karena bukan bagian verifikasi identitas).
4. `transactions.payout_status` (pending/paid/failed) adalah cache status payout terakhir untuk query cepat; `transaction_payouts` tetap sumber kebenaran penuh (termasuk percobaan yang gagal).
5. `escrow_status: payout_release → selesai` (`EscrowStateMachine::markCompleted`) sekarang **mensyaratkan** `payout_status = paid` — admin tidak bisa menandai transaksi selesai sebelum dana benar-benar dicairkan.
6. Rekonsiliasi (`/admin/payouts`, `GET /api/admin/payouts/reconciliation`) menjumlahkan `commission_amount` vs `payout_amount` dari `transaction_payouts` berstatus `paid` — bukan dihitung ulang dari `transactions.amount`, supaya laporan selalu mencerminkan apa yang benar-benar sudah cair.

## 7. Terkait

- Peran admin dalam approve/verifikasi → [`01-domain-dan-peran.md`](./01-domain-dan-peran.md)
- Alur konkret escrow di desain UI (checkout, tracking) → [`05-desain-ui-auramotors.md`](./05-desain-ui-auramotors.md)
- Pola konfirmasi (confirm dialog) untuk aksi berisiko seperti release dana → [`04-activity-diagram-crud.md`](./04-activity-diagram-crud.md) bagian Edit/Delete
