# Profil Kesehatan API

Laravel 13 JSON REST API for annual Kabupaten/Kota health profile reporting.

## Setup

```bash
composer install
php artisan migrate:fresh --seed
php artisan profile:import-catalog ../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx --year=2024 --replace
php artisan profile:map-table-one --year=2024 --without-values
php artisan profile:map-table-59 --year=2024
php artisan serve
```

Konfigurasi database mengikuti `backend/.env.example` dan menggunakan PostgreSQL. Setup di atas membuat data master Tahun Pelaporan 2024 tanpa Nilai Indikator atau Riwayat Revisi.

Use `Accept: application/json`. Login at `POST /api/login`, then send the returned token as `Authorization: Bearer <token>`. Sanctum stores only the SHA-256 token hash in `personal_access_tokens`; the plaintext token is returned once at login.

## Demo Accounts

| Role | Email | Password | Kabupaten/Kota |
| --- | --- | --- | --- |
| Administrator Sistem | `admin@example.com` | `password` | All |
| Operator Kabupaten Bangka | `operator.bangka@example.com` | `password` | Kabupaten Bangka |
| Operator Kabupaten Belitung | `operator.belitung@example.com` | `password` | Kabupaten Belitung |
| Operator Kabupaten Bangka Barat | `operator.bangka.barat@example.com` | `password` | Kabupaten Bangka Barat |
| Operator Kabupaten Bangka Tengah | `operator.bangka.tengah@example.com` | `password` | Kabupaten Bangka Tengah |
| Operator Kabupaten Bangka Selatan | `operator.bangka.selatan@example.com` | `password` | Kabupaten Bangka Selatan |
| Operator Kabupaten Belitung Timur | `operator.belitung.timur@example.com` | `password` | Kabupaten Belitung Timur |
| Operator Kota Pangkalpinang | `operator.pangkalpinang@example.com` | `password` | Kota Pangkalpinang |

Seeder membuat tujuh Kabupaten/Kota, delapan akun lokal, dan Tahun Pelaporan 2024 berstatus Terbuka. Importer dan perintah pemetaan melengkapi 86 Tabel Pelaporan dan Katalog Indikator tanpa data pelaporan operasional.

## Main Endpoints

- `POST /api/login`, `POST /api/logout`, `GET /api/me`
- `GET /api/dashboard`
- `GET /api/reporting-years`, `GET /api/reporting-years/{id}/tables`
- `GET /api/submissions`, `GET /api/submissions/{id}`
- Operator: `POST /api/submissions/draft`, `POST /api/submissions/{id}/complete`, `POST /api/submissions/{id}/reopen`
- Administrator: `POST /api/submissions/{id}/verify`, `POST /api/submissions/{id}/unverify`
- Administrator CRUD: `/api/users`, regions, reporting years, reporting tables, and indicators

Draft saves require the current integer `version`; a stale version returns HTTP 409. Draft data remains `not_started`. Completed and verified submissions are locked from value edits.

## Tests

```bash
php artisan test
```
