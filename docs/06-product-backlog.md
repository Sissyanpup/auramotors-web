# Product Backlog & Roadmap MVP

> Diturunkan dari urutan build di `saran.txt` bagian 5, dipecah jadi backlog per epic supaya bisa langsung dipetakan ke sprint Agile di [`../CLAUDE.md`](../CLAUDE.md).

## Epic 1 — Auth, Role & Listing CRUD (tanpa payment)

- [x] Setup auth (Sanctum/JWT) untuk 3 role: admin, seller, buyer.
- [x] KYC seller (upload KTP, NPWP opsional untuk dealer) sebelum bisa listing.
- [x] CRUD listing kendaraan (foto, spesifikasi, dokumen STNK/BPKB) — pakai pola di [`04-activity-diagram-crud.md`](./04-activity-diagram-crud.md).
- [x] Gate: listing tidak tayang publik sebelum diverifikasi admin.
- [x] Halaman katalog + filter (merek, tahun, harga, lokasi) untuk buyer.

## Epic 2 — Payment Collection (Xendit Sandbox)

> Kelas berjalan **tanpa akses internet** (kesepakatan dengan dosen agar tidak perlu hosting) — lihat keputusan driver abstraction di [`03-payment-escrow.md`](./03-payment-escrow.md#6-driver-payment-gateway--offline-classroom-constraint).

- [x] Integrasi Xendit sandbox untuk terima pembayaran (collection saja, belum disbursement) — via `PaymentGateway` interface + `XenditGateway` (dipakai kalau ada internet).
- [x] Halaman checkout (deposit/DP alokasi unit).
- [x] Webhook handler status pembayaran (pending/success/failed) — plus endpoint polling (`refresh-status`) sebagai fallback saat webhook tidak bisa diakses (offline).

## Epic 3 — Escrow State Machine & Admin Approval

- [x] Definisikan state machine transaksi: `listing → deal → escrow_hold → serah_terima → payout_release → selesai` (lihat [`03-payment-escrow.md`](./03-payment-escrow.md)) — via `App\Escrow\EscrowStateMachine`.
- [x] Admin dashboard untuk approve transisi state (dengan audit trail: siapa & kapan) — `transaction_status_histories` mencatat setiap transisi.
- [x] Halaman tracking status transaksi untuk buyer & seller — termasuk konfirmasi serah-terima per pihak.
- [x] Dispute flow dasar (buyer/seller lapor ketidaksesuaian → admin review) — admin resolve via refund atau lanjutkan transaksi.

## Epic 4 — Disbursement ke Seller

- [x] Payout manual oleh admin dulu (trigger manual, bukan otomatis) — via `App\Payouts\PayoutService` + `ManualDisbursementGateway`.
- [x] Setelah stabil: otomatisasi disbursement via Xendit ke rekening seller — `XenditDisbursementGateway` (dipakai kalau ada internet, sama seperti pola `PaymentGateway` di Epic 2).
- [x] Rekonsiliasi laporan komisi platform vs payout seller — dashboard `/admin/payouts`, sumber data tabel `transaction_payouts` (bukan dihitung ulang dari `transactions`).

## Epic 5 — Polish, Performa & Hardening

- [x] Jalankan checklist performa 20 poin — lihat status per poin di [`02-tech-stack-arsitektur.md`](./02-tech-stack-arsitektur.md#2a-status-per-poin-sprint-5-diisi-2026-09-17) (18/20 selesai atau N/A untuk setup kelas ini, 2 poin lain — Compress API Payloads & Unnecessary Re-renders — ditandai sebagian/direview dengan alasan eksplisit, bukan diklaim selesai penuh).
- [x] Lighthouse audit halaman katalog & detail kendaraan — lihat hasil & catatan di [`02-tech-stack-arsitektur.md`](./02-tech-stack-arsitektur.md#2a-status-per-poin-sprint-5-diisi-2026-09-17) poin #13 (Performance 97/89, Accessibility 100/100, Best Practices 96/96, SEO 100/100).
- [x] Review UX konsisten dengan pola CRUD & confirm-dialog di [`04-activity-diagram-crud.md`](./04-activity-diagram-crud.md) — sisa 5 halaman (`admin/kyc`, `admin/vehicles`, `seller/vehicles`, `seller/transactions/[id]`, `buyer/transactions/[id]`) sudah diganti ke `ConfirmModal` in-app, tidak ada lagi `window.confirm`/`prompt` di codebase.
- [x] (Opsional) Terapkan tema visual AuraMotors — lihat [`05-desain-ui-auramotors.md`](./05-desain-ui-auramotors.md). Token warna/font/radius "Obsidian & Champagne Gold" diterapkan lewat `globals.css` + komponen bersama (`Badge`, `Header`, `ConfirmModal`), lalu disebarkan otomatis ke seluruh 17 halaman via script reskin — bukan rename istilah domain ke flavor mewah (sesuai catatan di `docs/05` §1).

## Fase Lanjutan — Dokumen Standar Internasional

> Diturunkan dari requirement Standar Internasional Automotive Sales / Cross-Border Dealership (KYC/AML, legal, vehicle docs, insurance, cross-border logistics). Pendekatan: **mock/simulasi** (bukan integrasi API sungguhan, sesuai constraint kelas offline yang sama dengan `PaymentGateway` di Epic 2). Cross-border **termasuk** dalam scope.

### Epic 6 — Extended KYC/AML & Virtual Data Room Foundation

- [x] Buyer KYC: upload ID resmi (KTP/Paspor), Bukti Alamat (utility bill / rekening koran < 3 bulan), Proof of Funds (PoF: surat referensi bank / mutasi rekening).
- [x] Seller entity type: `individu` vs `perusahaan` — jika `perusahaan`, wajib upload Certificate of Incorporation & Articles of Association + UBO (Ultimate Beneficial Owner).
- [x] Admin review buyer KYC (approve/reject dengan alasan, pola sama dengan seller KYC di Epic 1).
- [x] Gate: buyer tidak bisa checkout kendaraan di atas threshold (mis. Rp500jt) sebelum KYC + PoF approved.
- [ ] Virtual Data Room per-transaksi: folder virtual yang mengelompokkan dokumen (Identitas, Legal, Vehicle, Insurance, Financial, Logistics) — akses hanya untuk buyer/seller/admin yang terlibat. _(Dipindah ke Epic 7 bersama auto-generated PDF: mengelompokkan dokumen jadi lebih bermakna setelah auto-generated invoice/BoS/SPA sudah tersedia untuk masuk ke folder Financial/Legal.)_

### Epic 7 — Auto-Generated PDF (Financial & Legal Docs)

- [x] Generator PDF (server-side, `barryvdh/laravel-dompdf` — offline-friendly, tidak butuh Chromium) untuk:
  - [x] Commercial Invoice (tagihan resmi dari Seller ke Buyer, auto-generate saat checkout).
  - [x] Deposit Receipt / Booking Fee Note (nota DP + fund locked, auto-generate saat payment_status=paid via mock/webhook/polling).
  - [x] Bill of Sale (bukti pemindahan kepemilikan, auto-generate saat admin approve serah-terima).
  - [x] Sales Purchase Agreement (SPA) — kontrak utama dengan 4 pasal (Objek, Harga, Ketentuan Umum, Berlaku Efektif). Auto-generate saat checkout, regenerate saat ada tanda tangan baru.
  - [x] Escrow Disbursement Note (nota pelepasan dana escrow ke seller — komisi platform + payout). Auto-generate setelah `disburse` berhasil.
  - [x] Tax Invoice (PPnBM / VAT dummy 11% + 20% dengan breakdown DPP) untuk pelaporan pajak buyer. Auto-generate saat `payment_status = paid`.
- [x] E-signature mock: buyer & seller menggambar tanda tangan di canvas signature pad; disimpan sebagai PNG di storage + timestamp; SPA & Bill of Sale auto-regenerate dengan tanda tangan yang baru masuk. Idempotent: satu tanda tangan per pihak, tidak bisa diganti.
- [x] Virtual Data Room per-transaksi: folder virtual yang mengelompokkan dokumen (Financial/Legal) — akses hanya untuk buyer/seller/admin yang terlibat. _(Dipindah dari Epic 6, selesai di Sprint 7A)_

### Epic 8 — Vehicle Extended Docs & Insurance Module

- [x] Dokumen kendaraan tambahan (di luar BPKB/STNK Epic 1): Service History Log (buku servis), Third-party Inspection Report (upload PDF laporan inspektor independen), Certificate of Authenticity / Build Sheet (untuk kendaraan rare/limited edition). Upload/replace/delete lewat `POST/DELETE /api/seller/vehicles/{v}/documents`.
- [x] VIN Check mock: `App\VinCheck\VinCheckService` (deterministic response berdasarkan pola VIN yang VIN-safe: `STLN`=blocked, `CRSH`=warning, dst). Trigger manual dari seller edit page; hasil disimpan sebagai audit history di `vehicle_vin_checks`. VIN status "clean" ditampilkan sebagai trust signal di katalog publik.
- [x] Insurance module — polis kendaraan: enum `VehiclePolicyType` (All Risk / TLO / Agreed Value), tabel `vehicle_insurance_policies` dengan policy_number, insurer_name, coverage_amount, valid_from/until, agreed_value_amount (conditional untuk Agreed Value), certificate upload. CRUD lewat `/api/seller/vehicles/{v}/insurance`. Stream certificate ke owner/admin. Katalog publik menampilkan ringkasan polis aktif (insurer + type + valid_until) tanpa expose policy_number/coverage_amount.
- [ ] Cargo / Transit Insurance: polis terpisah wajib untuk fase pengiriman (Epic 9); sistem memblokir transisi state `logistics` sebelum polis transit terverifikasi. _(Dipindah ke Epic 9 karena tightly coupled dengan state machine logistics yang belum dibangun.)_
- [ ] Insurance Claim History / No-Claims Bonus (NCB) record — upload opsional untuk meningkatkan trust score kendaraan. _(Stretch goal, tidak diprioritaskan Sprint 8.)_

### Epic 9 — Cross-Border Logistics, Customs & Delivery

- [x] State machine shipment terpisah (`draft → logistics_prep → in_transit → customs_clearance → delivered`, plus `delayed`) — dibangun paralel dengan escrow state machine (bukan refactor `EscrowStateMachine`) supaya siklus escrow tetap murni; shipment 1:1 dengan transaction, opsional (hanya diinisiasi untuk cross-border).
- [x] Cargo / Transit Insurance (dipindah dari Epic 8): fields inline di `shipments` (insurer_name, policy_number, coverage_amount, certificate). Guard `moveToLogisticsPrep` menolak transisi kalau cargo insurance belum lengkap.
- [x] Seller upload: Export Declaration. Guard uploader role di controller (seller-only, admin override).
- [x] Logistics: Bill of Lading (B/L) untuk mode `sea` / Air Waybill (AWB) untuk mode `air`; guard `moveToInTransit` menolak transisi tanpa dokumen yang sesuai mode + tracking_number. Field carrier_name + estimated_arrival + actual_arrival (otomatis di-set saat `markDelivered`).
- [x] Customs (admin-only upload): Import Declaration, Customs Duty Receipt, Certificate of Conformity. Guard `moveToCustomsClearance` menolak tanpa Import Declaration + Customs Duty Receipt.
- [x] Delivery (buyer-only upload): Proof of Delivery (PoD) + Letter of Acceptance. Guard `markDelivered` menolak tanpa keduanya.
- [ ] Transport & Delivery Receipt auto-generated (Epic 7 style PDF). _(Stretch goal — tidak diprioritaskan Sprint 9; arsitektur `DocumentGenerator` sudah siap kalau perlu ditambah.)_

### Epic 10 — Asuransi Detail, Surat Jual Beli Bertanda Tangan, Portal Bank & Rincian Harga

- [x] Kalkulator rincian harga tunggal (`App\Support\PriceBreakdownCalculator`, tarif di `config/pricing.php`): komposisi DPP/PPN/PPnBM dalam harga, tagihan DP + asuransi, estimasi biaya balik nama Samsat (BBN-KB, PKB, SWDKLLJ, PNBP STNK/TNKB/BPKB), hak bersih penjual, total biaya kepemilikan, simulasi kredit 12–60 bulan. Di-snapshot ke `transactions.price_breakdown` saat checkout.
- [x] Asuransi detail: perluasan jaminan (banjir, gempa, huru-hara, terorisme, TJH pihak ketiga, PA, derek + bengkel + mobil pengganti khusus All Risk), biaya polis & meterai, risiko sendiri, ambang total loss 75%, ganti rugi kehilangan 80%, pengecualian (taksi online/rental, tanpa SIM sah). Nomor polis terbit setelah premi dibayar.
- [x] Surat yang ditandatangani buyer/seller per dokumen (`transaction_document_signatures`): SPA 3 pihak (penjual, pembeli, badan hukum PT AuraMotors Escrow Indonesia), SPPA, Surat Pernyataan Keabsahan Kendaraan, Surat Kuasa Balik Nama, BAST, Kuitansi bermeterai. Tanda tangan sekali untuk banyak dokumen.
- [x] Surat bank: Bukti Pembayaran (Payment Advice) & Surat Konfirmasi Penempatan Dana Escrow, berkop bank kustodian fiktif.
- [x] Portal bank simulasi `/bank-portal/[reference]` (tema putih, terpisah dari tema AuraMotors): kartu kredit/debit + 3-D Secure, PayPal, Virtual Account/internet banking, OTP 6 digit apa saja, jeda proses realistis. Nomor kartu & CVV tidak disimpan.
- [x] Premi asuransi dikeluarkan dari basis komisi & payout seller (diteruskan ke asuradur).
- [x] Metode bayar **kredit/leasing** sungguhan (pengajuan, approval bank, angsuran). _(Selesai di Epic 11 / Sprint 11.)_
- [x] Gate approval serah-terima admin menunggu BAST ditandatangani kedua pihak. _(Selesai di Sprint 11: payout release butuh BAST bertanda tangan.)_

### Epic 11 — Revisi Dosen 16 Okt: Kredit, Cicilan, Laporan, Bonus & Promo

Sprint 11 (alur & keuangan):

- [x] Menu admin pindah ke sidebar kiri (`AdminSidebar`), header cukup satu tautan "Panel Admin".
- [x] Serah terima dua tahap: seller mengajukan → admin memeriksa checklist & menyetujui → seller menyerahkan/mengirim → buyer konfirmasi terima. Shipment & tracker GPS terkunci sampai disetujui. Payout butuh BAST ditandatangani kedua pihak.
- [x] Biaya balik nama ditagih bersama DP (masuk `due_now`, tidak dicicil), diteruskan escrow ke Samsat; tidak masuk basis komisi/payout seller.
- [x] Revisi bunga kredit: flat 8/9/10/11/12% per tahun untuk tenor 12–60 bulan, +1% (umur ≥ 6 th) / +2% (≥ 11 th), bunga efektif, provisi 1%, admin, fidusia (notaris + PNBP berjenjang), asuransi tahun ke-2 dst. dicicil. DP minimal kredit 20%.
- [x] Kredit sebagai metode bayar: leasing fiktif, pengajuan → approval otomatis setelah jeda (atau manual admin) → leasing melunasi sisa harga ke seller → jadwal angsuran buyer ke leasing via portal bank (`?installment=`). Rincian dana seller: DP escrow − komisi + pelunasan leasing. Serah terima butuh kredit disetujui.
- [x] Modul cicilan: tabel jadwal angsuran, status lunas/jatuh tempo/telat + denda 0,2%/hari, halaman admin `/admin/loans` (statistik, filter, setujui, catat setoran manual, "majukan 1 bulan").

Sprint 12 (bisnis & pemantauan):

- [x] Log aktivitas global (`activity_logs`, observer `ModelActivityObserver` di 16 model + login/logout): verifikasi KYC/listing, transaksi, pembayaran, serah terima, dokumen & tanda tangan, pengiriman, kredit & angsuran, payout, bonus, promo. Halaman `/admin/activity` (filter kategori/aktor/tanggal/kata kunci/transaksi/subjek) + timeline per transaksi. Tabel verifikasi (KYC penjual, KYC pembeli, listing) kini menampilkan **semua** data termasuk yang sudah terverifikasi, dengan peninjau, waktu, alasan, dan tautan jejak.
- [x] Bonus seller: 0,5% per unit + bonus tier (3 → Rp2,5 jt, 5 → Rp5 jt, 10 → Rp15 jt), dibayar bersama payout (`transaction_payouts.bonus_amount`), halaman `/seller/bonus`.
- [x] Promo buyer: harga coret + badge + hitung mundur di katalog, banner promo, voucher di checkout (potongan persen/nominal, gratis perluasan asuransi, bunga kredit spesial), kuota & periode, dikelola di `/admin/promos`. Subsidi ditanggung platform — hak penjual tidak berkurang.
- [x] Halaman laporan admin `/admin/reports`: KPI, grafik bulanan & komposisi (SVG ringan tanpa dependensi baru, warna data divalidasi), tabel penjualan/pencairan/bonus/promo/kredit, filter tanggal, ekspor CSV, cetak/simpan PDF.

## Prioritas & Dependensi

```
Epic 1 (Auth+Listing) ──▶ Epic 2 (Payment Collection) ──▶ Epic 3 (Escrow+Approval) ──▶ Epic 4 (Disbursement) ──▶ Epic 5 (Polish)
                                                                                                                        │
                                                                                                                        ▼
                                              Epic 6 (Extended KYC + VDR) ──▶ Epic 7 (Auto-PDF + E-sign) ──▶ Epic 8 (Vehicle Docs + Insurance) ──▶ Epic 9 (Cross-Border Logistics)
```

- Epic 5 (performa) bisa dicicil paralel di setiap sprint — lihat catatan di `CLAUDE.md` bagian 3.
- Epic 6 adalah fondasi VDR & extended KYC — **wajib** sebelum Epic 7–9 karena semua dokumen di epic berikutnya masuk ke VDR yang didefinisikan di sini.
- Epic 7 (auto-PDF) & Epic 8 (vehicle docs + insurance) bisa dikerjakan paralel setelah Epic 6 selesai, karena tidak saling bergantung.
- Epic 9 (cross-border) bergantung pada state machine yang harus diperluas — sebaiknya kerjakan setelah Epic 8 supaya insurance transit tersedia sebagai prasyarat.
