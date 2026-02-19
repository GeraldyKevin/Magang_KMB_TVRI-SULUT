# Dashboard KMB TVRI Sulawesi Utara (PHP + MySQL)

Project ini sudah disiapkan sesuai target pengerjaan cepat 1-2 hari.

## Struktur Folder

- `config/` → koneksi dan proteksi autentikasi
- `css/` → styling dashboard
- `js/` → script Chart.js (data dummy)
- `database/` → file SQL pembuatan database dan tabel
- `index.php` → login
- `dashboard.php` → beranda monitoring
- `kalender.php` → CRUD event kalender konten
- `rencana_konten.php` → CRUD rencana konten KMB
- `logout.php` → keluar dari sesi

## Sesi 1 - Fondasi & Database

1. Aktifkan Apache + MySQL di XAMPP/Laragon.
2. Copy folder project ini ke `htdocs` (XAMPP) atau `www` (Laragon).
3. Buka phpMyAdmin.
4. Import file `database/db_kmb_tvri.sql`.
5. Pastikan database `db_kmb_tvri` dan 3 tabel terbentuk:
   - `users`
   - `kalender_event`
   - `rencana_konten`

> Akun awal yang terbuat otomatis:
>
> - Username: `admin`
> - Password: `admin123`

## Sesi 2 - UI & Layout

Sudah tersedia:

- Login page (`index.php`) dengan form di tengah layar.
- Dashboard (`dashboard.php`) dengan layout 3 bagian:
  - Sidebar: Beranda, Kalender Konten, Rencana Konten, Logout
  - Header: judul + sapaan admin
  - Main content: kartu ringkas + area grafik

## Sesi 3 - Logic PHP & CRUD Kalender

Sudah tersedia di `kalender.php`:

- **Create**: input event baru
- **Read**: tampil daftar event
- **Update**: edit event
- **Delete**: hapus event
- Tampilan **kalender bulanan** dengan kontrol bar **tahun, bulan, tanggal**
- Integrasi hari besar/libur nasional dari `https://hari-libur-api.vercel.app/`
- Klik tanggal di kalender untuk otomatis mengisi form tambah event

Sudah tersedia di `rencana_konten.php`:

- **Create**: input rencana konten
- **Read**: tampil daftar rencana
- **Update**: edit rencana
- **Delete**: hapus rencana

Sistem login/session juga sudah aktif:

- `index.php` memvalidasi username/password dari tabel `users`
- `dashboard.php` dan `kalender.php` diproteksi session

## Sesi 4 - Visualisasi Data & Mocking API

Sudah tersedia:

- Integrasi CDN Chart.js di `dashboard.php`
- `<canvas id="insightChart">` sebagai area grafik
- Data dummy followers/reach di `js/script.js`

Nanti saat izin API Instagram sudah turun, data dummy bisa diganti hasil fetch API.

## Sesi 5 - Finishing & Debugging

Checklist cepat:

1. Uji login dengan akun admin.
2. Pastikan navigasi sidebar berjalan.
3. Tambahkan event di kalender, lalu edit dan hapus.
4. Cek tidak ada error PHP di browser.
5. Sesuaikan warna/spacing di `css/style.css` bila perlu.

## Catatan Koneksi Database

File koneksi ada di `config/koneksi.php`:

- Host default: `localhost`
- User default: `root`
- Password default: kosong (`''`)
- Database: `db_kmb_tvri`

Jika kredensial MySQL berbeda, ubah nilai di file tersebut.
