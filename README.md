# SIPROKES Babel

Sistem Informasi Profil Kesehatan Kepulauan Bangka Belitung untuk pengisian, pemantauan, dan Verifikasi Tabel Pelaporan tahunan Kabupaten/Kota.

## Struktur

- `backend/`: Laravel 13 REST API.
- `frontend/`: React 19, Vite, Tailwind CSS, dan komponen bergaya shadcn/ui.
- `CONTEXT.md`: glossary domain resmi proyek.
- `PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx`: sumber migrasi awal.

## Menjalankan Lokal

Backend menggunakan PostgreSQL. Buat database dan role aplikasi lokal terlebih dahulu:

```sql
CREATE ROLE profil_kesehatan_app LOGIN PASSWORD '<password-lokal>';
CREATE DATABASE "dinkes-dashboard-app-gpt" OWNER profil_kesehatan_app;
```

Salin `backend/.env.example` menjadi `backend/.env`, isi `DB_PASSWORD`, lalu jalankan:

```bash
cd backend
composer install
php artisan migrate --seed
php artisan profile:import-catalog ../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx --year=2024 --replace
php artisan profile:map-table-one --year=2024 --without-values
php artisan profile:map-table-59 --year=2024
php artisan serve
```

Perintah tersebut membuat data master Tahun Pelaporan 2024 tanpa membuat Nilai Indikator, submission, Riwayat Revisi, atau event status.

Pada terminal lain:

```bash
cd frontend
npm install
npm run dev
```

Buka `http://127.0.0.1:5173`.

## Akun Demo

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Administrator Sistem | `admin@example.com` | `password` |
| Operator Kabupaten Bangka | `operator.bangka@example.com` | `password` |
| Operator Kabupaten Belitung | `operator.belitung@example.com` | `password` |
| Operator Kabupaten Bangka Barat | `operator.bangka.barat@example.com` | `password` |
| Operator Kabupaten Bangka Tengah | `operator.bangka.tengah@example.com` | `password` |
| Operator Kabupaten Bangka Selatan | `operator.bangka.selatan@example.com` | `password` |
| Operator Kabupaten Belitung Timur | `operator.belitung.timur@example.com` | `password` |
| Operator Kota Pangkalpinang | `operator.pangkalpinang@example.com` | `password` |

## Verifikasi

```bash
cd backend
php artisan test
vendor/bin/pint --test

cd ../frontend
npm run lint
npm run build
npm run test:e2e
```

## Cakupan Saat Ini

MVP sudah mencakup autentikasi, pembatasan data Kabupaten/Kota, daftar dan detail Tabel Pelaporan, Simpan Draft, Selesai Input, Perbaiki Input, Verifikasi, pembatalan Verifikasi, Tidak Berlaku beserta alasan, Riwayat Revisi backend, Katalog Indikator, pengguna, dan dashboard status.

Seeder menyediakan tujuh Kabupaten/Kota dan akun demo. Perintah `php artisan profile:import-catalog ../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx --year=2024` mengimpor 86 Tabel Pelaporan dari 87 lembar workbook; `Resume` dikecualikan karena merupakan rekap otomatis. Administrator kemudian memetakan kandidat header menjadi Nilai Dasar per tabel. Rumus workbook tidak diimpor sebagai sumber nilai resmi.
