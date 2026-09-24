# Pengelolaan Profil Kesehatan

Konteks ini mengelola data resmi Profil Kesehatan tahunan untuk setiap Kabupaten/Kota dan menyajikannya dalam dashboard internal tingkat provinsi.

## Language

**Profil Kesehatan**:
Kumpulan data kesehatan resmi untuk satu Tahun Pelaporan yang dihimpun dari seluruh Kabupaten/Kota.
_Avoid_: Laporan kesehatan, workbook

**Kabupaten/Kota**:
Wilayah pelapor yang memiliki dan mengelola data Profil Kesehatannya sendiri.
_Avoid_: Cabang, tenant

**Tahun Pelaporan**:
Tahun yang menjadi periode acuan pengisian dan pelaporan data Profil Kesehatan.
_Avoid_: Tahun data, periode

**Katalog Indikator**:
Daftar definisi data kesehatan yang harus dilaporkan pada suatu Tahun Pelaporan.
_Avoid_: Kolom Excel, field dinamis

**Nilai Indikator**:
Nilai aktif yang dilaporkan untuk satu kombinasi Kabupaten/Kota, Tahun Pelaporan, dan Indikator, beserta riwayat revisinya.
_Avoid_: Isian, angka Excel

**Fasilitas Kesehatan**:
Fasilitas atau unit pelayanan kesehatan yang terdaftar pada satu Kabupaten/Kota dan dapat menjadi rincian dalam Tabel Pelaporan.
_Avoid_: Baris fasilitas, nama unit bebas

**Kategori Indikator**:
Dimensi yang merinci Nilai Indikator, seperti jenis kelamin, kelompok umur, atau jenis fasilitas, dengan pilihan yang ditetapkan per Tabel Pelaporan.
_Avoid_: Subkolom, label tambahan

**Tabel Pelaporan**:
Kelompok Indikator yang diisi dan dipantau status kelengkapannya sebagai satu kesatuan.
_Avoid_: Sheet, form

**Status Tabel Pelaporan**:
Tahap penyelesaian satu Tabel Pelaporan untuk satu Kabupaten/Kota dan Tahun Pelaporan: Belum Diinput, Sudah Diinput, atau Terverifikasi. Belum Diinput juga mencakup data draft yang belum dinyatakan selesai.
_Avoid_: Status data, progres sheet

**Selesai Input**:
Pernyataan Operator Kabupaten/Kota bahwa seluruh Nilai Indikator wajib dalam suatu Tabel Pelaporan telah diisi dan valid.
_Avoid_: Submit, kirim laporan

**Verifikasi**:
Pengesahan oleh Administrator Sistem atas Tabel Pelaporan yang sudah selesai diinput, yang sekaligus mengunci perubahan oleh Operator Kabupaten/Kota.
_Avoid_: Approval, publikasi

**Nilai Dasar**:
Nilai Indikator yang dimasukkan langsung oleh Operator Kabupaten/Kota dan menjadi masukan bagi perhitungan nilai turunan.
_Avoid_: Input mentah, raw value

**Nilai Turunan**:
Nilai Indikator yang dihitung otomatis dari Nilai Dasar, seperti jumlah, persentase, rasio, cakupan, atau CFR.
_Avoid_: Formula Excel, nilai input

**Tidak Dapat Dihitung**:
Keadaan Nilai Turunan yang tidak memiliki hasil sah, misalnya karena penyebut bernilai nol; keadaan ini berbeda dari nilai nol.
_Avoid_: Nol, kosong

**Tidak Berlaku**:
Keadaan ketika suatu Indikator memang tidak relevan bagi Kabupaten/Kota tertentu, disertai alasan yang wajib dicatat.
_Avoid_: Kosong, tidak dapat dihitung

**Riwayat Revisi**:
Catatan perubahan Nilai Indikator yang menyimpan nilai sebelum dan sesudah perubahan, pengguna, waktu, dan alasannya.
_Avoid_: Log teknis

**Tahun Pelaporan Terbuka**:
Tahun Pelaporan yang masih mengizinkan Operator Kabupaten/Kota mengisi atau memperbaiki data yang belum diverifikasi.
_Avoid_: Tahun aktif

**Tahun Pelaporan Ditutup**:
Tahun Pelaporan yang tidak lagi mengizinkan perubahan data oleh Operator Kabupaten/Kota.
_Avoid_: Tahun arsip

**Administrator Sistem**:
Pengguna yang mengelola pengguna, Kabupaten/Kota, Tahun Pelaporan, Katalog Indikator, konfigurasi aplikasi, dan Verifikasi Tabel Pelaporan.
_Avoid_: Superadmin, admin provinsi

**Operator Kabupaten/Kota**:
Pengguna yang mengisi dan memperbaiki data Profil Kesehatan milik Kabupaten/Kotanya.
_Avoid_: Admin daerah, petugas input

**Dashboard Internal**:
Penyajian data Profil Kesehatan untuk pengguna terautentikasi, dengan filter Tahun Pelaporan, Kabupaten/Kota, kategori, dan Indikator.
_Avoid_: Dashboard publik
