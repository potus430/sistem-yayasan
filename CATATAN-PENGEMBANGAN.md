# Catatan Pengembangan Aplikasi

Dokumen ini mencatat fitur yang telah diselesaikan pada pengembangan aplikasi administrasi yayasan multi-cabang, keputusan implementasi penting, serta cara memverifikasi dan menjalankan fitur terkait.

## Ringkasan rilis terakhir

- **Commit:** `20553d4` — `Lengkapi PPDB dan profil siswa multi-cabang`
- **Branch:** `main`
- **Remote:** `origin/main`
- **Stack:** Laravel 12, Livewire 3, Tailwind CSS, MariaDB, dan UUID v7.
- Perubahan pada commit tersebut sudah didorong ke GitHub. Catatan ini ditambahkan sesudah commit itu.

## Pendaftaran dan penerimaan siswa

Menu **Pendaftaran siswa** tersedia untuk admin cabang. Setiap pendaftaran dikelola dalam lingkup cabang pengguna dan memuat informasi calon siswa, orang tua/wali, kontak, alamat, sekolah asal, tahun ajaran aktif, serta kelas tujuan.

Admin cabang dapat memproses status calon siswa, termasuk menandai lolos atau tidak lolos dan menerima calon yang lolos. Penerimaan membuat atau memperbarui data siswa dan master kelas secara konsisten. NISN wajib tersedia saat penerimaan.

Pendaftaran siswa pindahan memakai jenis penerimaan tersendiri. Data sekolah sebelumnya dicatat dan siswa dapat diterima langsung jika kapasitas kelas tujuan masih tersedia.

## Kapasitas kelas dan sinkronisasi

- Kapasitas ditentukan per cabang, kelas, dan tahun ajaran.
- Daftar pendaftaran menampilkan kapasitas, jumlah siswa terdaftar, serta sisa kursi.
- Penerimaan ditolak bila kapasitas belum diatur atau kelas sudah penuh.
- Ketersediaan kursi diperiksa kembali saat proses penerimaan untuk mencegah kapasitas terlampaui.
- Data kelas/kapasitas disinkronkan sebelum data siswa yang bergantung padanya.
- Sinkronisasi antaraplikasi menggunakan `SYNC_CENTRAL_URL` dan `SYNC_API_TOKEN`, dengan scheduler Laravel serta worker antrean aktif.

## Direktori dan profil siswa

Menu **Data siswa** dapat digunakan admin pusat untuk melihat lintas cabang dan admin cabang untuk melihat data cabangnya sendiri. Direktori mendukung pencarian, filter cabang/kelas/tanggal, pagination, dan ekspor sesuai kewenangan.

Setiap baris menampilkan pas foto bila tersedia; bila tidak ada, direktori memakai inisial. Tombol detail membuka modal profil dengan informasi:

- Nama, NISN, jenis kelamin, tempat dan tanggal lahir.
- Cabang, jenjang, kelas, tahun ajaran masuk, dan jenis penerimaan.
- Sekolah asal dan alamat lengkap.
- Nama serta nomor telepon orang tua/wali.
- Daftar dokumen siswa dan tautan unduh yang diizinkan.

Pada header modal, foto profil ditampilkan di sisi kanan nama. Foto pasfoto yang diunggah selalu diprioritaskan. Jika tidak ada foto, aplikasi memakai ikon bawaan berdasarkan jenis kelamin:

- `public/pasphoto/icon-laki.png` untuk data laki-laki.
- `public/pasphoto/icon-wanita.png` untuk data perempuan.

Nilai jenis kelamin `L`, `LAKI_LAKI`, `LAKI-LAKI` dan padanannya dikenali sebagai laki-laki; `P`, `PEREMPUAN`, `WANITA`, dan `FEMALE` dikenali sebagai perempuan. Nilai kosong atau tidak dikenal memakai ikon laki-laki sebagai fallback.

Pasfoto disimpan di disk privat bersama dokumen siswa. Endpoint pratinjau hanya melayani tipe dokumen pasfoto dan gambar JPEG/PNG/WebP kepada admin pusat atau admin cabang pemilik siswa. File lain tidak dipublikasikan sebagai gambar. Dokumen diunduh melalui endpoint terautentikasi; data lintas cabang tetap dibatasi.

## Arsip dokumen siswa

Admin cabang dapat mengelola dokumen melalui halaman dokumen siswa:

- Pasfoto: JPG, PNG, atau WebP, maksimal 5 MB.
- Kartu keluarga dan ijazah terakhir: PDF/JPG/PNG sesuai validasi unggahan aplikasi, maksimal 10 MB per dokumen.
- Berkas berada di penyimpanan privat, bukan URL publik.
- Penghapusan siswa juga membersihkan berkas privat terkait; penghapusan siswa tetap ditolak bila ada riwayat tagihan SPP.

Pastikan direktori penyimpanan privat dapat ditulis oleh proses aplikasi dan masuk kebijakan backup terenkripsi. Atur batas PHP unggahan agar dapat menampung ukuran maksimum berkas.

## Panel login dinamis dan tampilan

Admin pusat dapat mengubah konten panel kiri login lewat **Identitas aplikasi**, tanpa mengedit kode:

- Label atau badge.
- Judul utama.
- Deskripsi.
- Gambar latar opsional.

Gambar panel disimpan di disk publik dengan validasi JPEG/PNG/WebP maksimal 5 MB. Admin pusat dapat mengganti atau menghapusnya. Bila belum memilih gambar, panel memakai latar gradien bawaan. Nilai awal kolom pengaturan disediakan melalui migrasi agar instalasi yang sudah ada tetap mempunyai konten login.

Kerangka navigasi, halaman login, tabel siswa, formulir, serta komponen terkait juga telah diperbarui dengan gaya visual yang lebih modern dan responsif.

## Data contoh siswa

Seeder `SampleStudentProfileSeeder` mengisi kolom biodata yang kosong pada data siswa demo yang cocok dengan pola NISN seeder, tanpa menghapus siswa dan tanpa menimpa nilai yang sudah ada. Seeder mengabaikan data produksi kecuali `SEED_SAMPLE_DATA=true` diatur secara eksplisit.

Seeder ini menyediakan data contoh untuk biodata dasar, wali, telepon, alamat, dan sekolah asal. Seeder tidak membuat foto pasfoto siswa. Sampai foto aktual diunggah admin cabang, modal dan direktori menggunakan fallback masing-masing.

## Perubahan database

Migrasi yang ditambahkan untuk fitur-fitur ini:

- `2026_11_08_000001_create_admission_applications_table`
- `2026_11_08_000002_add_synced_at_to_school_classes`
- `2026_11_08_000003_add_admission_details_to_students`
- `2026_11_08_000004_add_class_capacity_and_transfer_support`
- `2026_11_09_000001_create_student_documents_table`
- `2026_11_09_000002_add_login_panel_settings`

Jalankan `php artisan migrate` pada lingkungan yang akan diperbarui. Migrasi bersifat penambahan skema dan tidak ditujukan untuk menghapus data siswa yang sudah ada.

## Verifikasi terakhir

Pada penyelesaian fitur pendaftaran, dokumen, pengaturan panel login, dan data contoh, suite lengkap lulus **50 tes dan 481 assertions**. Setelah itu, perubahan ikon profil diuji dengan suite terarah `StudentDocumentManagerTest` (**4 tes, 41 assertions**), validasi Blade, pemeriksaan format PHP, dan verifikasi ikon dapat diakses melalui web sebagai `image/png`.

Perintah pemeriksaan yang umum:

```powershell
php artisan test
npm run build
php artisan view:cache
vendor/bin/pint --test
```

## Pengembangan terdahulu yang tetap berlaku

- Payroll guru dihitung berdasarkan jam roster terjadwal; izin dan alfa mengurangi upah, sakit tidak, dan guru pengganti menerima upah invalen.
- Aplikasi mendukung data cabang, pegawai, kelas/ruangan, absensi, roster, insentif, tagihan/pembayaran SPP, payroll, laporan, dan audit aktivitas sesuai role.
- Batas akses cabang dan role tetap harus dipertahankan pada setiap halaman, aksi Livewire, endpoint berkas, dan operasi sinkronisasi baru.
