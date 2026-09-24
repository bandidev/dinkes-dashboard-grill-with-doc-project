# UAT Tabel Pelaporan 1

Dokumen ini digunakan untuk memutuskan apakah Tabel Pelaporan 1 layak dibekukan sebagai pola referensi sebelum pengembangan Dashboard Internal dan Tabel Pelaporan berikutnya.

## Identitas Pelaksanaan

| Informasi | Nilai |
|---|---|
| Tanggal | |
| Versi/commit aplikasi | |
| Lingkungan | UAT |
| Tahun Pelaporan | |
| Kabupaten/Kota | |
| Operator Kabupaten/Kota | |
| Administrator Sistem | |
| Pendamping/pencatat | |

## Prasyarat

- Satu akun Operator terikat hanya ke Kabupaten/Kota yang diuji.
- Satu akun Administrator Sistem tersedia.
- Tahun Pelaporan berstatus Terbuka.
- Tabel Pelaporan 1 memiliki lima Nilai Dasar dan tiga Nilai Turunan yang telah dipetakan.
- Dokumen sumber resmi dan hasil perhitungan pembanding tersedia.
- Browser dan perangkat yang lazim dipakai Operator tersedia.
- Backup database UAT telah dibuat sebelum pengujian.

## Data Pembanding

Catat nilai resmi yang akan digunakan. Jangan memakai data produksi tanpa persetujuan pemilik data.

| Indikator | Nilai Pembanding | Satuan |
|---|---:|---|
| Luas Wilayah | | km2 |
| Jumlah Desa | | desa |
| Jumlah Kelurahan | | kelurahan |
| Jumlah Penduduk | | jiwa |
| Jumlah Rumah Tangga | | rumah tangga |
| Jumlah Desa dan Kelurahan | | otomatis |
| Rata-rata Jiwa per Rumah Tangga | | otomatis |
| Kepadatan Penduduk | | otomatis |

## Skenario Operator

| No. | Langkah | Hasil yang Diharapkan | Lulus/Gagal | Catatan/Bukti |
|---:|---|---|---|---|
| 1 | Masuk sebagai Operator Kabupaten/Kota. | Operator melihat identitas Kabupaten/Kota yang benar. | | |
| 2 | Buka Tabel Pelaporan 1 dari Tahun Pelaporan yang dipilih. | Tahun Pelaporan dan Kabupaten/Kota pada halaman sesuai. | | |
| 3 | Periksa metode default. | Input Ringkas aktif dan ditandai Disarankan. | | |
| 4 | Ubah satu Nilai Dasar lalu pilih menu lain tanpa Simpan draft. | Peringatan perubahan belum disimpan muncul; pembatalan tetap menahan Operator di halaman. | | |
| 5 | Ubah satu Nilai Dasar lalu refresh atau tutup tab. | Browser menampilkan peringatan perubahan belum disimpan. | | |
| 6 | Simpan draft Input Ringkas. | Pesan berhasil muncul, peringatan hilang, dan nilai tetap ada setelah halaman dimuat ulang. | | |
| 7 | Tandai satu Indikator sebagai Tidak Berlaku tanpa alasan. | Data belum dianggap lengkap dan Selesai Input tidak dapat dilakukan. | | |
| 8 | Isi alasan Tidak Berlaku lalu Simpan draft. | Status Tidak Berlaku dan alasannya tersimpan. | | |
| 9 | Buka Tabel Lengkap. | Hanya baris Kabupaten/Kota Operator yang dapat diedit; wilayah lain hanya dapat dibaca. | | |
| 10 | Ubah dua sel secara cepat di Tabel Lengkap. | Kedua perubahan tersimpan; status akhirnya menampilkan Semua perubahan tersimpan. | | |
| 11 | Coba berpindah saat Tabel Lengkap masih menyimpan. | Peringatan muncul dan perpindahan dapat dibatalkan. | | |
| 12 | Kembali ke Input Ringkas setelah autosave selesai. | Nilai yang disimpan melalui Tabel Lengkap sama dengan Input Ringkas. | | |
| 13 | Cocokkan tiga Nilai Turunan dengan perhitungan resmi. | Jumlah, rata-rata, kepadatan, dan pembulatan sesuai aturan resmi. | | |
| 14 | Gunakan nilai penyebut nol pada data UAT yang diizinkan. | Nilai Turunan menampilkan Tidak Dapat Dihitung, bukan nol atau kosong. | | |
| 15 | Lengkapi seluruh Nilai Indikator wajib dan Simpan draft. | Kelengkapan 100% dan Selesai Input aktif. | | |
| 16 | Pilih Selesai Input. | Status menjadi Sudah Diinput dan perubahan oleh Operator terkunci. | | |

## Skenario Administrator Sistem

| No. | Langkah | Hasil yang Diharapkan | Lulus/Gagal | Catatan/Bukti |
|---:|---|---|---|---|
| 1 | Masuk sebagai Administrator Sistem dan buka Tabel Pelaporan 1 yang sudah selesai diinput. | Status Sudah Diinput dan nilai Kabupaten/Kota yang benar terlihat. | | |
| 2 | Cocokkan Nilai Dasar, Nilai Turunan, Tidak Berlaku, dan alasannya dengan bukti Operator. | Seluruh data sesuai dan tidak ada perubahan yang hilang. | | |
| 3 | Pilih Verifikasi. | Status menjadi Terverifikasi dan Tabel Pelaporan terkunci. | | |
| 4 | Batalkan Verifikasi tanpa alasan. | Sistem menolak tindakan. | | |
| 5 | Batalkan Verifikasi dengan alasan koreksi yang jelas. | Status kembali menjadi Sudah Diinput dan alasan tercatat. | | |

## Skenario Perbaikan dan Verifikasi Ulang

| No. | Langkah | Hasil yang Diharapkan | Lulus/Gagal | Catatan/Bukti |
|---:|---|---|---|---|
| 1 | Operator memilih Perbaiki Input tanpa alasan. | Sistem menolak tindakan. | | |
| 2 | Operator memilih Perbaiki Input dengan alasan. | Status kembali menjadi Belum Diinput dan perubahan kembali diizinkan. | | |
| 3 | Operator memperbaiki Nilai Dasar dan menyimpan draft. | Nilai baru tersimpan dan Riwayat Revisi memuat nilai sebelum, nilai sesudah, pengguna, waktu, dan alasan. | | |
| 4 | Operator memilih Selesai Input kembali. | Status kembali menjadi Sudah Diinput. | | |
| 5 | Administrator Sistem melakukan Verifikasi ulang. | Status kembali menjadi Terverifikasi dan data terkunci. | | |

## Pemeriksaan Nonfungsional

| No. | Pemeriksaan | Hasil yang Diharapkan | Lulus/Gagal | Catatan/Bukti |
|---:|---|---|---|---|
| 1 | Gunakan layar desktop target. | Tidak ada elemen penting yang terpotong atau sulit digunakan. | | |
| 2 | Gunakan layar ponsel sekitar 390 x 844. | Halaman tidak memiliki overflow horizontal global; Tabel Lengkap dapat digulir pada areanya. | | |
| 3 | Gunakan keyboard untuk berpindah kontrol. | Fokus terlihat dan urutan kontrol dapat dipahami. | | |
| 4 | Putuskan jaringan ketika menyimpan data UAT. | Kegagalan terlihat jelas, data tidak dinyatakan tersimpan, dan tersedia cara memuat ulang/mencoba kembali. | | |
| 5 | Biarkan sesi berakhir lalu lakukan tindakan. | Pengguna diarahkan untuk masuk kembali tanpa menyatakan perubahan gagal sebagai berhasil. | | |

## Temuan

| ID | Tingkat | Deskripsi | Penanggung Jawab | Status |
|---|---|---|---|---|
| | Kritis/Tinggi/Sedang/Rendah | | | |

## Kriteria Lulus

Tabel Pelaporan 1 dinyatakan lulus UAT apabila:

- Seluruh skenario wajib berstatus Lulus.
- Tidak ada temuan Kritis atau Tinggi yang masih terbuka.
- Nilai Dasar, Nilai Turunan, pembulatan, Tidak Berlaku, dan Tidak Dapat Dihitung telah disetujui pemilik proses.
- Operator Kabupaten/Kota dan Administrator Sistem menyetujui alur Input, Simpan draft, Selesai Input, Perbaiki Input, dan Verifikasi.
- Bukti pengujian dan daftar temuan tersimpan.

## Persetujuan

| Peran | Nama | Keputusan | Tanggal | Tanda Tangan/Paraf |
|---|---|---|---|---|
| Operator Kabupaten/Kota | | Lulus / Tidak Lulus | | |
| Administrator Sistem | | Lulus / Tidak Lulus | | |
| Pemilik Proses | | Lulus / Tidak Lulus | | |
