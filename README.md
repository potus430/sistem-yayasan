# Administrasi Yayasan Multi-Cabang

Aplikasi Laravel 12, Livewire 3 class-based, Tailwind CSS, dan MariaDB. Entitas aplikasi memakai UUID v7; data siswa, gaji, absensi, konfigurasi insentif, dan guru pengganti dapat di-push ke server pusat melalui API bearer-token.

## Menjalankan

1. Siapkan PHP 8.2+, ekstensi `pdo_mysql`, MariaDB, Composer, dan Node.js. Salin `.env.example` menjadi `.env`, isi koneksi MariaDB, lalu jalankan `composer install`, `php artisan key:generate`, dan `npm install`.
2. Buat database MariaDB sesuai `DB_DATABASE`. Atur `SEED_ADMIN_EMAIL` dan `SEED_ADMIN_PASSWORD` dengan kredensial kuat untuk membuat akun admin pusat. Jalankan `php artisan migrate --seed`; seeder membuat 5 cabang, 150 pegawai, 600 siswa, dua tahun tagihan SPP, enam bulan payroll dan absensi, insentif, roster, serta akun demo. Seeder aman dijalankan ulang dan tidak menghapus data.
3. Akun demo hanya untuk local/testing. Password awalnya `Sekolah123!` dan dapat diganti dengan `SEED_SAMPLE_PASSWORD`. Production melewatkan data demo kecuali `SEED_SAMPLE_DATA=true` diatur secara eksplisit; jangan memakai kredensial demo di production.
4. Jalankan `npm run build` lalu `php artisan serve`.
5. Untuk sinkronisasi cabang, isi `SYNC_CENTRAL_URL` dengan URL aplikasi pusat dan `SYNC_API_TOKEN` dengan token acak yang sama pada kedua sisi. Jalankan scheduler Laravel dan worker antrean (misalnya `php artisan schedule:work` serta `php artisan queue:work`). Endpoint pusat menerima upsert untuk konfigurasi insentif cabang, nilai insentif tahunan, siswa, gaji, absensi, dan penugasan guru pengganti.

Role yang didukung: `admin_pusat`, `admin_cabang`, `keuangan`, `guru`, dan `staff`. `admin_pusat` mengelola lintas cabang dan memiliki akses administrasi serta keuangan; `admin_cabang` mengelola absensi dan roster cabangnya; `keuangan` dibatasi pada payroll dan SPP cabangnya. Guru dan staff dapat melihat roster. Semua akun selain `admin_pusat` wajib memiliki `branch_id`. Dashboard menampilkan ringkasan dan grafik sesuai role serta batas cabang pengguna. Admin dapat membuat roster terpisah untuk setiap cabang dan tahun ajaran. Setelah roster terbentuk, jadwal tidak di-generate ulang agar perubahan manual tidak hilang; admin dapat menukar jam dua pelajaran, dengan validasi bentrok guru, kelas, dan ruangan.

Pada halaman penggajian, admin cabang atau pusat dapat membuat jenis insentif `Tetap per tahun ajaran` atau `Manual saat payroll`. Nilai insentif tahunan disimpan per pegawai dan tahun ajaran; insentif manual diisi saat payroll. Total dan rincian nama-nilai kedua jenis insentif tersimpan sebagai snapshot payroll dan disinkronkan ke pusat. Halaman Absensi menyediakan status hadir, izin, alfa, dan sakit; izin/alfa mengurangi gaji harian, sedangkan sakit tidak. Untuk jam roster guru yang absen, sistem menampilkan guru pengganti yang tidak sedang mengajar atau sudah ditugaskan pada jam tersebut.

Admin pusat dan admin cabang dapat menambah, mengubah, dan menghapus data pegawai serta siswa. Penghapusan ditolak jika pegawai memiliki riwayat transaksi/roster/absensi/insentif atau siswa memiliki tagihan SPP. Master kelas dan ruangan dikelola per cabang pada menu `Kelas & ruangan`; mengganti nama akan memperbarui referensi siswa dan roster, sedangkan master yang masih digunakan tidak dapat dihapus. Roster mengambil pilihan kelas dan ruangan dari master cabang agar jadwal tetap konsisten.

Daftar pegawai, siswa, tagihan SPP, payroll, absensi, dan roster memakai pagination dengan 25 baris per halaman secara default; pengguna dapat memilih 10, 25, 50, atau 100 baris. Pencarian dan filter mengembalikan daftar ke halaman pertama.

## Validasi

Jalankan `php artisan test` dan `npm run build`.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
