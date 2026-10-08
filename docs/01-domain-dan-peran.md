# Domain Modeling & Peran Pengguna

> Sumber asli: `saran.txt` (bagian 1 & 4). Dirapikan sebagai spesifikasi domain untuk platform jual-beli kendaraan **AuraMotors**.

## 1. Kenapa Beda dari E-Commerce Biasa

Marketplace kendaraan menjual barang bernilai tinggi (puluhan-ratusan juta hingga miliaran rupiah), sehingga butuh **trust & verification layer** yang jauh lebih ketat dibanding jual baju/gadget:

- Verifikasi dokumen (STNK, BPKB, nomor rangka/mesin) **sebelum listing tayang**.
- Status kepemilikan/transfer harus jadi **state machine terpisah**, bukan status order biasa:
  `listing → deal → escrow hold → serah terima → payout release → selesai`
- Riwayat servis/inspeksi (opsional, tapi jadi differentiator besar — ini yang bikin platform seperti Carsome dipercaya).
- Perlu **dispute resolution flow** untuk admin, karena barang fisik besar rawan komplain kondisi tidak sesuai.

## 2. Empat Peran Minimum

| Peran                          | Tanggung Jawab Utama                                                                                                                                                       |
| ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Admin**                      | Approve listing kendaraan (cegah listing curang/duplikat), verifikasi dokumen (STNK/BPKB), moderasi dispute, kelola komisi.                                                |
| **Seller** (dealer/perorangan) | KYC wajib (KTP, kadang NPWP untuk dealer), upload listing + dokumen kendaraan, terima payout setelah transaksi _clear_.                                                    |
| **Buyer**                      | Browse/filter kendaraan, chat dengan seller, checkout, tracking status pembayaran & transfer kepemilikan.                                                                  |
| **Payment/Escrow layer**       | Uang buyer **tidak langsung cair** ke seller — ditahan sampai ada konfirmasi serah-terima (mirip rekening bersama), karena nilai transaksi besar & risiko penipuan tinggi. |

## 3. Prinsip Desain Domain

1. **Escrow bukan opsional** — ini bagian paling krusial dari domain, bukan fitur tambahan.
2. **State machine transaksi eksplisit**, jangan pakai kolom status generik (`pending/done`). Idealnya modelkan sebagai tabel/​enum state + histori transisi (audit trail).
3. **Verifikasi dokumen adalah gate**, bukan langkah opsional — listing tidak boleh tayang publik sebelum dokumen (STNK/BPKB) diverifikasi admin.
4. **Dispute resolution** perlu dirancang sejak awal (bukan ditambahkan belakangan) karena unit fisik besar → risiko komplain kondisi tinggi.

## 4. Terkait

- Alur pembayaran & escrow detail → [`03-payment-escrow.md`](./03-payment-escrow.md)
- Pola CRUD generik (Add/Edit/Delete) yang dipakai untuk modul kelola listing/user/dsb → [`04-activity-diagram-crud.md`](./04-activity-diagram-crud.md)
- Implementasi konkret alur (tema AuraMotors) → [`05-desain-ui-auramotors.md`](./05-desain-ui-auramotors.md)
- Urutan pembangunan fitur per sprint → [`06-product-backlog.md`](./06-product-backlog.md)
