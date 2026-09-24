# Handoff SIPROKES Babel

## Repository

- Repository: https://github.com/bandidev/dinkes-dashboard-grill-with-doc-project
- Branch fitur: `feature/table-one-input-modes`
- Base branch: `main`
- Setup dan perintah verifikasi: `README.md`
- Istilah domain resmi: `CONTEXT.md`

## Kondisi Saat Ini

- Backend menggunakan Laravel 13 dan frontend menggunakan React 19/Vite.
- Autentikasi, pembatasan Kabupaten/Kota, Simpan Draft, Selesai Input, Verifikasi, optimistic locking, Tidak Berlaku, dan Riwayat Revisi tersedia.
- Importer membuat 86 Tabel Pelaporan dari 87 lembar workbook; `Resume` dikecualikan.
- Tabel Pelaporan 1 memiliki 5 Nilai Dasar dan 3 Nilai Turunan.
- Tabel Pelaporan 59 memiliki 12 Nilai Dasar dan 15 Nilai Turunan berdasarkan kelompok umur dan jenis kelamin.
- Formula aman menggunakan operasi JSON terbatas: `add`, `divide`, dan `percent`.

## Metode Input Tabel 1

Tabel Pelaporan 1 menyediakan dua metode input yang memakai data dan Riwayat Revisi yang sama:

- **Form Indikator**: metode default, satu Indikator per baris dengan tombol Simpan draft.
- **Worksheet Excel**: tampilan menyerupai worksheet sumber, hanya baris wilayah Operator yang editable, dan tersimpan saat blur atau Enter.

File utama:

- `frontend/src/pages/reporting-detail.tsx`
- `frontend/src/components/table-one-worksheet.tsx`
- `frontend/tests/e2e/table-one.spec.ts`

## Verifikasi Terakhir

- Backend: 11 tests, 50 assertions.
- Playwright: 2 tests.
- Frontend lint dan build lulus.
- Pint lulus.

Gunakan perintah dalam `README.md` untuk menjalankan ulang seluruh pemeriksaan.

## Keputusan dan Batasan

- Seluruh pengujian browser harus menggunakan Playwright, bukan `agent-browser`.
- Workbook sumber, `.env`, database SQLite lokal, `vendor`, `node_modules`, build output, dan artefak Playwright tidak masuk Git.
- Form Indikator tetap menjadi metode default. Preferensi metode belum disimpan per pengguna.
- Renderer worksheet khusus baru tersedia untuk Tabel Pelaporan 1.
- Autosave worksheet menggunakan satu request per blur atau Enter untuk mencegah revisi tertimpa snapshot lama.
- Jangan menggeneralisasi renderer Tabel 1 atau Tabel 59 ke tabel lain sebelum struktur workbook tabel tersebut diperiksa.

## Langkah Lanjutan

1. Review dan merge branch `feature/table-one-input-modes` ke `main` jika kedua metode sudah disetujui client.
2. Putuskan apakah pilihan metode input perlu disimpan per pengguna atau perangkat.
3. Sinkronkan ringkasan laporan impor dengan status aktual `reporting_tables`.
4. Petakan Tabel Pelaporan berikutnya berdasarkan pola workbook yang berbeda, terutama tabel dengan Fasilitas Kesehatan dinamis.
5. Pertahankan Playwright coverage untuk payload penyimpanan dan sinkronisasi nilai ketika berpindah metode.

## Suggested Skills

- `diagnosing-bugs` untuk autosave, optimistic locking, dan sinkronisasi data.
- `interface-design` untuk perubahan selector metode atau worksheet.
- `playwright-best-practices` untuk E2E tests.
- `domain-modeling` untuk pola Fasilitas Kesehatan atau Kategori Indikator baru.
