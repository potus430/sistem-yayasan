# Administrasi Yayasan Multi-Cabang

Aplikasi Laravel 12, Livewire 3 class-based, Tailwind CSS, dan MariaDB. Entitas aplikasi memakai UUID v7; data siswa, gaji, absensi, konfigurasi insentif, dan guru pengganti dapat di-push ke server pusat melalui API bearer-token.

## Menjalankan

1. Siapkan PHP 8.2+, ekstensi `pdo_mysql`, MariaDB, Composer, dan Node.js. Salin `.env.example` menjadi `.env`, isi koneksi MariaDB, lalu jalankan `composer install`, `php artisan key:generate`, dan `npm install`.
2. Buat database MariaDB sesuai `DB_DATABASE`. Atur `SEED_ADMIN_EMAIL` dan `SEED_ADMIN_PASSWORD` untuk membuat akun admin pusat, serta `SEED_CENTRAL_FINANCE_EMAIL` dan `SEED_CENTRAL_FINANCE_PASSWORD` dengan kredensial kuat untuk membuat akun keuangan pusat. Jalankan `php artisan migrate --seed`; seeder membuat akun awal bila kedua kredensial untuk role tersebut tersedia, selain data demo yang diaktifkan. Seeder aman dijalankan ulang dan tidak menghapus data.
3. Akun demo hanya untuk local/testing. Password awalnya `Sekolah123!` dan dapat diganti dengan `SEED_SAMPLE_PASSWORD`. Production melewatkan data demo kecuali `SEED_SAMPLE_DATA=true` diatur secara eksplisit; jangan memakai kredensial demo di production.
4. Jalankan `npm run build` lalu `php artisan serve`.
5. Untuk sinkronisasi cabang, isi `SYNC_CENTRAL_URL` dengan URL aplikasi pusat dan `SYNC_API_TOKEN` dengan token acak yang sama pada kedua sisi. Jalankan scheduler Laravel dan worker antrean (misalnya `php artisan schedule:work` serta `php artisan queue:work`). Endpoint pusat menerima upsert untuk konfigurasi insentif cabang, nilai insentif tahunan, siswa, gaji, absensi, dan penugasan guru pengganti.

Role yang didukung: `admin_pusat`, `keuangan_pusat`, `admin_cabang`, `keuangan`, `guru`, dan `staff`. `admin_pusat` mengelola akun serta profil staff dan dapat melihat data operasional lintas cabang tanpa mengubah data siswa, guru, absensi, kelas/ruangan, roster, pembayaran, atau payroll. Admin pusat dapat menambahkan jabatan dinamis dan definisi insentif per cabang serta menetapkan tarif SPP per kelas/tahun ajaran, tarif otomatis cabang (transport serta invalen), dan tarif gaji guru per jam untuk setiap cabang/tahun ajaran. `keuangan_pusat` mengelola gaji pokok staff, nilai kompensasi, konfigurasi payroll selain tarif otomatis cabang, pembuatan tagihan SPP, dan proses payroll lintas cabang. `admin_cabang` mengelola data operasional sekolahnya; `keuangan` cabang mencatat pembayaran SPP serta melihat dan mencetak rincian payroll tanpa mengubahnya. Guru dan staff dapat melihat roster. Akun lintas cabang seperti `admin_pusat` dan `keuangan_pusat` tidak memerlukan `branch_id`; akun cabang wajib terkait dengan cabangnya. Dashboard menampilkan ringkasan dan grafik sesuai role serta batas cabang pengguna. Admin cabang dapat membuat roster per cabang dan tahun ajaran; setelah terbentuk, jadwal tidak di-generate ulang agar perubahan manual tidak hilang.

Jenis insentif `Tetap per tahun ajaran` atau `Manual saat payroll` ditetapkan admin pusat untuk tiap cabang; admin pusat juga menetapkan tarif otomatis cabang serta tarif gaji guru per jam mengajar untuk tiap cabang dan tahun ajaran. Gaji pokok bulanan guru dihitung dari tarif per jam dikalikan jumlah jam roster guru pada hari-hari sekolah dalam periode payroll dan tahun ajaran yang dipilih; tarif dan jamnya disimpan sebagai snapshot payroll. Gaji pokok staff tetap ditetapkan oleh keuangan pusat. Keuangan pusat mengelola nilai tahunan per pegawai serta menghitung dan menandai payroll lunas. Rincian nilai insentif disimpan sebagai snapshot payroll. Admin pusat hanya dapat melihat payroll, sedangkan keuangan cabang dapat melihat rincian, mengekspor data payroll Excel yang dibatasi ke cabangnya, dan mencetak slip. Slip gaji bisa diunduh per pegawai atau keseluruhan dengan pilihan 1, 2, atau 4 slip per lembar. Tarif SPP ditetapkan di awal tahun ajaran per cabang dan kelas oleh admin pusat atau keuangan pusat; tagihan baru mengambil tarif sebagai snapshot. Keuangan pusat membuat tagihan bulanan, sementara hanya keuangan cabang yang dapat mencatat pembayaran. Daftar data siswa, pegawai, absensi, roster, penggajian, dan SPP menyediakan ekspor PDF dan Excel sesuai kewenangan role. Halaman Absensi menyediakan status hadir, izin, alfa, dan sakit; izin/alfa mengurangi gaji harian, sedangkan sakit tidak. Untuk jam roster guru yang absen, sistem menampilkan guru pengganti yang tidak sedang mengajar atau sudah ditugaskan pada jam tersebut.

Admin pusat dapat melihat seluruh pegawai lintas unit dan mengelola profil staff di Yayasan Pusat maupun sekolah cabang; profil guru dan siswa hanya dapat dikelola admin cabang. Gaji pokok pegawai baru yang dibuat admin cabang dimulai dari nol dan tidak dapat diubah oleh admin cabang. Unit payroll `Yayasan Pusat` dibuat otomatis dan terpisah dari cabang sekolah. Pegawai kantor pusat dapat menerima gaji, tunjangan, dan insentif melalui alur payroll yang sama. Akun login `Staff Pusat Yayasan` tetap tidak ditautkan ke cabang dan hanya dapat membuka profil. Di menu `Jabatan & kompensasi`, admin pusat menambahkan jabatan dinamis per cabang, keuangan pusat menetapkan nominal kompensasinya, dan admin cabang mengelola nama/jenis jabatan yang tersedia serta penugasan. Di menu `Insentif & tunjangan`, admin pusat menambahkan definisi jenis insentif beserta nilai awal untuk setiap cabang; keuangan pusat mengelola nilai tahunan per pegawai serta tarif payroll cabang. Penghapusan pegawai/siswa ditolak jika masih memiliki riwayat transaksi/roster/absensi/insentif atau tagihan SPP. Master kelas dan ruangan dikelola per cabang pada menu `Kelas & ruangan`; mengganti nama akan memperbarui referensi siswa dan roster, sedangkan master yang masih digunakan tidak dapat dihapus. Roster mengambil pilihan kelas dan ruangan dari master cabang agar jadwal tetap konsisten.

Log aktivitas dikelola admin pusat melalui menu `Log aktivitas`. Catatan mencakup identitas pengguna, waktu (WIB), IP, user-agent browser/perangkat, jenis data, serta nilai sebelum/sesudah; field kredensial seperti password dan token tidak direkam. Sinkronisasi API dan pembuatan tagihan massal juga dicatat. User-agent hanya memberi petunjuk browser/OS dan tidak dapat mengidentifikasi nama host atau PC fisik secara tepercaya. Riwayat dimulai setelah migrasi audit dijalankan; perubahan sebelum itu tidak dapat dipulihkan.

Daftar pegawai, siswa, tagihan SPP, payroll, absensi, dan roster memakai pagination dengan 25 baris per halaman secara default; pengguna dapat memilih 10, 25, 50, atau 100 baris. Tabel data bertanggal menyediakan filter tanggal tertentu, bulan, tahun, atau rentang tanggal; filter mengikuti tanggal pendaftaran siswa/pegawai, tanggal penerbitan tagihan, periode payroll, atau waktu aktivitas sesuai jenis datanya. Pencarian dan filter mengembalikan daftar ke halaman pertama.

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
