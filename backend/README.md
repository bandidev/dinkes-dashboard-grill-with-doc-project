# Profil Kesehatan API

Laravel 13 JSON REST API for annual Kabupaten/Kota health profile reporting.

## Setup

```bash
composer install
php artisan migrate:fresh --seed
php artisan serve
```

Use `Accept: application/json`. Login at `POST /api/login`, then send the returned token as `Authorization: Bearer <token>`. Sanctum stores only the SHA-256 token hash in `personal_access_tokens`; the plaintext token is returned once at login.

## Demo Accounts

| Role | Email | Password | Kabupaten/Kota |
| --- | --- | --- | --- |
| Administrator Sistem | `admin@example.com` | `password` | All |
| Operator Kabupaten/Kota | `operator.bangka@example.com` | `password` | Kabupaten Bangka |

The seeder creates all 7 Kabupaten/Kota in Kepulauan Bangka Belitung, a closed 2023 reporting year, and an open 2024 reporting year with three representative reporting tables and numeric, text, and date indicators.

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
