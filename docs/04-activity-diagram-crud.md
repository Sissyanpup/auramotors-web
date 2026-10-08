# Pola Activity Diagram — CRUD (Add / Edit / Delete)

> Dokumen lengkap (legenda simbol, diagram Mermaid penuh untuk Add/Edit/Delete, tabel flow tambahan) ada di **[`../referensi-umum.md`](../referensi-umum.md)** — file ini adalah ringkasan cepat + pointer, dipakai sebagai checklist saat membuat modul CRUD baru (listing kendaraan, user, dokumen, dll).

## Pola Generik

```
Start → Table Data → Branch aksi (Add/Edit/Delete)
      → (Form opsional) → (Confirm Dialog opsional)
      → Decision (proses berhasil?)
          Yes → Popup sukses → Update Table → End
          No  → Warning → kembali ke Form/Table
```

## Ringkasan Perbedaan per Aksi

| Aksi                          | Form?                     | Confirm Dialog? | Catatan                                                                                                                 |
| ----------------------------- | ------------------------- | --------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **Add**                       | Ya (kosong)               | Tidak           | Risiko rendah — salah input tinggal diedit/dihapus lagi.                                                                |
| **Edit**                      | Ya (terisi data existing) | **Ya**          | Menimpa data existing → butuh _safety net_ konfirmasi. Pertimbangkan tampilkan _diff_ untuk field sensitif (finansial). |
| **Delete**                    | Tidak                     | **Ya**          | Kegagalan paling sering karena FK constraint. Pertimbangkan **soft delete** untuk data transaksional.                   |
| **View/Detail**               | Tidak (read-only)         | Tidak           | Tanpa Decision (tidak ada proses tulis).                                                                                |
| **Approve/Reject** (workflow) | Kadang (form alasan)      | Ya              | Decision branching ke status berbeda, bukan cuma yes/no — relevan untuk approval listing & escrow release oleh admin.   |

## Prinsip yang Wajib Dipegang di Semua Modul CRUD Proyek Ini

1. Selalu ada jalan keluar dari Form (Cancel = benar-benar tidak ada side effect).
2. Decision = validasi konkret (field wajib, format, duplikasi, constraint relasi) — bukan cuma "berhasil/gagal" generik.
3. Ada state loading antara Save → Decision (cegah double-submit / duplicate data).
4. Strategi update tabel (full refresh vs optimistic update) **konsisten** di semua modul.
5. Audit trail untuk Edit & Delete: catat siapa & kapan (`created_by/updated_by/deleted_by` + timestamp) — **wajib** untuk modul escrow/verifikasi dokumen karena multi-role (admin/seller/buyer).
6. Confirm dialog **hanya** untuk aksi berisiko (Edit, Delete, Approve, Release Dana) — jangan taruh di aksi Add.

## Penerapan di Proyek Ini

- **Approve listing oleh Admin** → pakai pola _Approve/Reject workflow_ (bukan Edit biasa) karena ada branching status (approved/rejected) + wajib audit trail.
- **Release dana escrow** → pola paling mendekati _Delete_ (irreversible, butuh konfirmasi eksplisit + kemungkinan gagal karena constraint, misal dokumen belum lengkap).
- **Listing kendaraan (Seller)** → Add/Edit standar, tapi status listing tidak langsung "tayang" — ada gate verifikasi admin sebelum publik (lihat [`01-domain-dan-peran.md`](./01-domain-dan-peran.md)).

## Terkait

- State machine transaksi & escrow → [`03-payment-escrow.md`](./03-payment-escrow.md)
- Peran & gate verifikasi → [`01-domain-dan-peran.md`](./01-domain-dan-peran.md)
