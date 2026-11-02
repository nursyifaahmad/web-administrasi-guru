# Sistem Administrasi Guru Terpadu (Web Administrasi Guru)

Aplikasi web **Administrasi Guru** berbasis **Laravel 11, Alpine.js, Tailwind CSS, MySQL/MariaDB, DomPDF, dan PhpSpreadsheet** untuk mempermudah guru mengelola **Jurnal Harian, Absensi Bulanan & Semester, serta Daftar Nilai Dinamis** hingga **15+ kelas per guru**, lengkap dengan **Panel Monitoring Admin**, pengaturan **KKM/KKTP terpusat**, serta ekspor **Cetak PDF (F4/A4)** dan **Download Excel (`.xlsx`)** resmi bertanda tangan.

---

## Fitur Utama

### 1. Portofolio Multi-Kelas Guru (15+ Kelas Mandiri)
- Satu akun guru dapat membuat dan mengelola **15+ Sistem Administrasi Kelas** yang terpisah (per kelas, mata pelajaran, tahun ajaran, dan semester).
- Dilengkapi **Quick Class Switcher** di *topbar* & *sidebar* untuk berpindah antar kelas dalam 1 klik.
- Manajemen **Daftar Murid** mendukung tambah manual, pengurutan abjad otomatis (`A-Z`), **Import dari Excel**, dan **Salin Murid dari Kelas Lain**.

### 2. Jurnal Harian Mengajar
- Kolom lengkap: **Nomor, Tanggal, Jam Pelajaran Ke, Kegiatan/Materi, Jumlah Murid (Hadir & Tidak Hadir), Catatan, dan Paraf**.
- **Sinkronisasi Otomatis**: Jumlah murid Hadir & Tidak Hadir otomatis terisi dari menu Absensi pada tanggal yang sama (dengan opsi *override* manual).
- Mendukung **Paraf Digital** langsung di kanvas tanda tangan maupun mode tanda tangan basah saat cetak.

### 3. Absensi Bulanan (`1..31`) & Rekapitulasi Semester
- Kolom: **Nomor, Nama Lengkap, Nama Panggilan, Jenis Kelamin, Bulan & Tanggal (`1..31`), Jumlah Hadir, serta Jumlah Ketidakhadiran (Sakit, Izin, Alfa)**.
- Kode kehadiran sesuai standar buku absensi:
  - `.` = **Hadir**
  - `d` = **Dispensasi** *(tetap dihitung ke dalam **Jumlah Hadir**, namun dibedakan tampilannya pada kolom tanggal)*
  - `s` = **Sakit**
  - `i` = **Izin**
  - `a` = **Alfa / Tanpa Keterangan**
- Dilengkapi **Mode Sentuh Cepat HP (`Tap-to-Fill`)** agar guru dapat mengisi absensi lewat layar sentuh HP tanpa memunculkan keyboard virtual, serta tombol **Set Semua Hadir (`.`)** per tanggal.

### 4. Daftar Nilai Dinamis & Rumus Kontrak Belajar Fleksibel
- Kolom: **Nomor, Nama Lengkap, Jenis Kelamin, Nilai Harian/Keaktifan/Karakter (`PH 1, PH 2, ...` dinamis), Nilai Kehadiran, Nilai Tugas (`NT 1, NT 2, ...` dinamis), Nilai STS, Nilai SAS, dan Nilai Akhir (`NA`)**.
- Kolom `PH` dan `NT` dapat **ditambah, diberi label topik, dan dihapus secara dinamis** kapan saja.
- **Rumus Persentase Nilai Akhir (`NA`) Kustom per Kelas**: Setiap guru dapat mengatur bobot persentase (`%PH + %Kehadiran + %NT + %STS + %SAS = 100%`) sesuai kesepakatan dengan murid, serta menyalin rumus ke kelas lain.
- Indikator ketuntasan otomatis berdasarkan standar **KKM / KKTP** yang ditetapkan oleh Admin.

### 5. Panel Kontrol & Monitoring Admin
- Memantau seluruh kelas, progres Jurnal Harian, Absensi, dan Daftar Nilai semua guru secara *real-time*.
- Membuat dan mengelola **Akun Guru** (login menggunakan **Email** atau **NIP**).
- Mengatur dan mengunci standar **KKM / KKTP** per Mata Pelajaran dan Tingkat Kelas.
- Mengelola **Kop Surat Resmi Sekolah**, Logo, Kota Titimangsa, Kepala Sekolah + NIP, serta Tahun Ajaran & Semester aktif.

### 6. Cetak PDF (F4 / A4) & Download Excel Resmi di Setiap Menu
- Tersedia pada **Jurnal Harian, Absensi Bulanan, Rekap Absensi Semester, Daftar Nilai**, serta **Cetak Bundel Lengkap 1 Kelas** (PDF siap jilid & Excel Multi-Sheet).
- Setiap cetakan dilengkapi **Kop Surat**, **Titimangsa**, **Kolom Tanda Tangan + NIP Wali Kelas**, **Kolom Tanda Tangan + NIP Guru Mata Pelajaran**, dan opsi **Mengetahui Kepala Sekolah + NIP**.

---

## Teknologi

- **Backend**: PHP 8.2+, Laravel 11
- **Frontend**: Blade, Alpine.js, Tailwind CSS, Vite
- **Database**: MySQL / MariaDB (`utf8mb4_unicode_ci`)
- **Export / Import**: `barryvdh/laravel-dompdf` (PDF F4/A4) & `phpoffice/phpspreadsheet` (Excel `.xlsx`)

---

## Deploy ke Server / Mini PC dengan Docker (Port `4010`)

Repository ini sudah dilengkapi `Dockerfile` *multi-stage* (Node 20 Vite Build + PHP 8.2-FPM + Nginx + Supervisor) dan `docker-compose.yml` (bersama MariaDB 10.11) yang siap jalan pada **port `4010`**:

```bash
git clone https://github.com/nursyifaahmad/web-administrasi-guru.git
cd web-administrasi-guru
cp .env.docker.example .env
# Opsional: sesuaikan APP_URL, DB_PASSWORD, dan DB_ROOT_PASSWORD di file .env
docker compose up -d --build
```

Container akan otomatis membuat `APP_KEY`, menunggu MariaDB siap, menjalankan `migrate --force`, mengisi akun/data awal (`DatabaseSeeder`) saat database pertama kali kosong, mengoptimalkan *cache* produksi, dan melayani website di **`http://<IP-SERVER>:4010`**.

---

## Cara Instalasi Manual (Tanpa Docker)

1. **Clone repository & masuk ke direktori proyek**
   ```bash
   git clone https://github.com/nursyifaahmad/web-administrasi-guru.git
   cd web-administrasi-guru
   ```

2. **Instal dependensi PHP & Node.js**
   ```bash
   composer install
   npm install
   ```

3. **Konfigurasi file `.env` & kunci aplikasi**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Pastikan database MySQL/MariaDB `db_administrasi_guru` sudah dibuat dan sesuai dengan konfigurasi di `.env`.

4. **Jalankan migrasi, seeder data demo, storage link, dan build aset**
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   npm run build
   ```

5. **Jalankan server**
   - Akses lokal di komputer saja:
     ```bash
     php artisan serve --port=8080
     ```
   - Akses dari **Laptop + HP di jaringan Wi-Fi yang sama** (Windows):
     Klik dua kali `jalankan-akses-hp.bat` atau jalankan:
     ```bash
     php artisan serve --host=0.0.0.0 --port=8080
     ```

---

## Akun Demo Bawaan (`DatabaseSeeder`)

| Peran | Email | NIP | Password |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@sekolah.sch.id` | `19800315 200604 1 004` | `password` |
| **Guru 1 (5 Kelas)** | `guru@sekolah.sch.id` | `19900412 201903 1 008` | `password` |
| **Guru 2** | `siti@sekolah.sch.id` | `19880721 201504 2 005` | `password` |

---

## Pengujian Otomatis

```bash
php artisan test
```
