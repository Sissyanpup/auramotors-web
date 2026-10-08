# Akses Proyek dari Perangkat Lain (WiFi yang Sama)

Panduan ini menjelaskan cara membuka proyek AuraMotors dari HP/laptop lain selama terhubung ke WiFi yang sama.

---

## Konsep Singkat

Secara default, server Next.js dan Laravel hanya bisa diakses dari komputer itu sendiri (`localhost`). Agar perangkat lain bisa mengaksesnya, server perlu "dibuka" ke seluruh jaringan lokal dengan flag `--host=0.0.0.0`.

---

## Konfigurasi yang Sudah Dilakukan (Sekali Saja)

Perubahan berikut sudah diterapkan ke proyek dan **tidak perlu diulangi**:

### 1. `backend/.env`
```
APP_URL=http://192.168.88.253:8000
SESSION_DOMAIN=
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:3001,192.168.88.253:3000,192.168.88.253:3001
FRONTEND_URL=http://localhost:3000,http://localhost:3001,http://192.168.88.253:3000,http://192.168.88.253:3001
```

### 2. `frontend/.env.local`
```
NEXT_PUBLIC_API_URL=http://192.168.88.253:8000
```

### 3. `frontend/next.config.ts`
```ts
allowedDevOrigins: ["192.168.88.253"],
```
> Baris ini wajib ada agar Next.js 16 mengizinkan perangkat lain mengambil file JavaScript dari server. Tanpa ini, halaman ter-render tapi semua tombol tidak muncul (React tidak aktif).

---

## Cara Menjalankan (Setiap Sesi)

Butuh **2 terminal** yang berjalan bersamaan.

### Terminal 1 — Backend
```bash
cd backend
php artisan serve --host=0.0.0.0
```

### Terminal 2 — Frontend
```bash
cd frontend
npm run dev -- -H 0.0.0.0
```

Perhatikan port yang muncul di output Terminal 2. Biasanya `3000`, tapi bisa `3001` kalau port 3000 sedang dipakai proses lain.

---

## Akses dari Perangkat Lain

Dari HP/laptop lain yang terhubung WiFi yang sama, buka browser ke:

```
http://192.168.88.253:3000
```
atau (jika port 3001):
```
http://192.168.88.253:3001
```

> IP `192.168.88.253` adalah IP lokal komputer host di jaringan ini. Cek ulang IP dengan perintah di bawah jika tidak bisa diakses.

---

## Jika IP Berubah

Router kadang memberikan IP baru (DHCP). Jika tiba-tiba tidak bisa diakses lagi, lakukan ini:

**1. Cek IP lokal saat ini:**
```bash
ip addr show | grep 'inet ' | grep -v '127.0.0.1'
```
Cari baris yang berawalan angka seperti `192.168.x.x` (bukan `172.x.x.x` karena itu Docker).

**2. Update 3 file dengan IP baru:**

`backend/.env`:
```
APP_URL=http://<IP-BARU>:8000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:3001,<IP-BARU>:3000,<IP-BARU>:3001
FRONTEND_URL=http://localhost:3000,http://localhost:3001,http://<IP-BARU>:3000,http://<IP-BARU>:3001
```

`frontend/.env.local`:
```
NEXT_PUBLIC_API_URL=http://<IP-BARU>:8000
```

`frontend/next.config.ts`:
```ts
allowedDevOrigins: ["<IP-BARU>"],
```

**3. Restart kedua server.**

---

## Troubleshooting

| Gejala | Kemungkinan Penyebab | Solusi |
|--------|----------------------|--------|
| "Can't be reached" | Server belum jalan atau tanpa flag `--host=0.0.0.0` | Jalankan ulang dengan flag yang benar |
| Halaman muncul tapi tombol tidak ada | `allowedDevOrigins` belum dikonfigurasi | Pastikan `next.config.ts` sudah diupdate, restart frontend |
| Login/API gagal | CORS tidak mengizinkan origin dari IP tersebut | Cek `FRONTEND_URL` di `backend/.env`, pastikan IP dan port sesuai |
| Port tiba-tiba 3001 bukan 3000 | Port 3000 dipakai proses lain | Akses dengan port yang benar, atau kill proses di port 3000: `kill -9 $(lsof -t -i:3000)` |
