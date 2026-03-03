# TVRI Ticketing System
### Sistem Manajemen Tiket Pelaporan — TVRI (Televisi Republik Indonesia)

---

## Deskripsi

Sistem ticketing berbasis web untuk TVRI yang memungkinkan karyawan melaporkan permasalahan teknis. Admin dapat menugaskan tiket ke divisi teknisi yang sesuai, dan teknisi dapat memperbarui status perbaikan lengkap dengan unggah foto bukti perbaikan. Seluruh aktivitas tiket tercatat dalam log riwayat, termasuk tiket yang telah dihapus. Leaderboard top teknisi ter-snapshot otomatis setiap bulan.

---

## Fitur Utama

### Multi-Role System
| Role | Kemampuan |
|------|-----------|
| **User / Pelapor** | Buat tiket, pantau status, hapus tiket milik sendiri, edit profil & foto |
| **Admin** | Kelola semua tiket, assign ke teknisi/divisi, kelola user, lihat activity log, cetak PDF |
| **Teknisi** | Lihat tiket yang ditugaskan, update status perbaikan, upload foto bukti, hapus tiket sendiri |

### Manajemen Tiket
- Buat tiket dengan judul, lokasi, deskripsi, dan lampiran foto
- Status tiket: `Open` → `Assigned` → `In Progress` → `Resolved`
- Priority: `Low`, `Medium`, `High`, `Urgent`
- 3 Divisi Teknisi: **IT**, **Teknik Kelistrikan**, **Studio (Audio Video)**
- Bulk action: hapus banyak tiket sekaligus (Admin & Teknisi)
- Filter tiket: divisi, prioritas, status, rentang tanggal (Admin)
- Pagination per halaman dengan navigasi lengkap

### Activity Log & Riwayat
- Setiap aktivitas tiket dicatat otomatis: buat, assign, update status, hapus
- Riwayat tetap tersimpan meskipun tiket sudah dihapus (judul & ID dipreservasi)
- Halaman **Activity Logs** (Admin) dengan filter: tab per aksi, user, tanggal, pencarian teks
- Auto-purge: log otomatis dihapus setelah 30 hari
- Detail tiket menampilkan timeline lengkap semua aktivitas

### Top Teknisi Bulanan (Auto-Reset & Snapshot)
- Leaderboard top 5 teknisi terbanyak resolved **bulan berjalan** ditampilkan di dashboard Admin
- Otomatis direset setiap awal bulan baru (scope query per bulan)
- Snapshot otomatis disimpan ke tabel `monthly_top_teknisi` saat admin buka dashboard
- Riwayat top teknisi semua bulan tersedia di halaman **Activity Logs** → tab "Top Teknisi Bulanan"
- Tampilan kartu per bulan dengan peringkat, foto, dan jumlah resolved

### Cetak PDF (Invoice Style)
- Generate PDF per tiket dalam layout invoice profesional menggunakan FPDF
- Header biru TVRI dengan logo resmi
- Tile metadata: nomor tiket, status, prioritas, tanggal
- Bagian tanda tangan: Pelapor, Approver, Teknisi

### Upload & Manajemen Foto
- User melampirkan foto saat membuat tiket → tersimpan di `uploads/tickets/`
- Teknisi mengupload foto bukti perbaikan → tersimpan di `uploads/perbaikan/`
- Foto resolved tiket → `uploads/resolved/`
- Foto profil semua user → `uploads/profile_photos/`
- Folder `uploads/` diproteksi dengan `.htaccess`

### Manajemen User (Admin)
- CRUD lengkap: tambah, edit, reset password, hapus akun user/teknisi
- Assign divisi untuk akun teknisi
- Email bersifat opsional

### Dashboard & Chart
- Dashboard Admin: statistik global (total, open, progress, resolved), chart status (Doughnut), chart tiket per bulan (Bar), top teknisi bulanan, filter & tabel tiket
- Dashboard Teknisi: statistik tiket pribadi, chart distribusi status (Doughnut), chart tiket per bulan, tabel tiket dengan pagination
- Dashboard User: daftar tiket milik sendiri, statistik ringkas
- Data chart diambil via endpoint `api/chart_data.php`

### Notifikasi Real-time
- Badge notifikasi tiket Open di sidebar (Admin)
- Badge tiket Assigned di sidebar (Teknisi)
- Toast notification untuk feedback aksi penting
- Polling `api/check_tickets.php` untuk cek tiket baru secara periodik

### Autentikasi & Keamanan
- Login username + password dengan hash bcrypt
- Session-based auth dengan validasi IP address
- RBAC: setiap halaman diproteksi per role via `config/auth.php`
- Ganti password mandiri di halaman profil
- Registrasi user baru dengan email opsional

---

## Teknologi

**Backend**
- PHP 8.1+
- MySQL 8.0+ / MariaDB 10+
- MySQLi Extension

**Frontend**
- Bootstrap 5.3
- Bootstrap Icons 1.11
- Chart.js (CDN) — visualisasi data
- SweetAlert2 v11 — dialog konfirmasi
- Vanilla JavaScript

**Libraries**
- FPDF 1.86 — PDF generation
- PHPMailer 6 — Email notifications (opsional)

---

## Struktur Folder

```
ticketing_tvri/
├── admin/                        # Halaman & proses khusus Admin
│   ├── dashboard.php             # Dashboard utama admin (statistik, tiket, top teknisi)
│   ├── activity_logs.php         # Log aktivitas + riwayat top teknisi bulanan
│   ├── assign.php                # Form assign tiket ke teknisi
│   ├── kelola_user.php           # CRUD manajemen user
│   ├── profile.php               # Profil admin
│   ├── delete_ticket.php         # Form konfirmasi hapus tiket (admin)
│   ├── proses_assign.php         # Handler proses assign
│   ├── proses_bulk.php           # Handler bulk action (delete/assign)
│   ├── proses_delete_ticket.php  # Handler hapus tiket (admin, dari dashboard)
│   ├── proses_hapus_tiket.php    # Handler hapus tiket (admin, dari detail)
│   └── proses_user.php           # Handler CRUD user
│
├── teknisi/                      # Halaman & proses Teknisi
│   ├── dashboard.php             # Dashboard teknisi (statistik, chart, tiket)
│   ├── profile.php               # Profil teknisi
│   ├── update_status.php         # Form update status tiket
│   ├── proses_update.php         # Handler proses update status + upload foto
│   └── proses_hapus_tiket.php    # Handler hapus tiket (teknisi)
│
├── user/                         # Halaman & proses User/Pelapor
│   ├── dashboard.php             # Dashboard user (daftar tiket milik sendiri)
│   ├── lapor.php                 # Form buat tiket baru
│   ├── profile.php               # Profil user
│   ├── proses_lapor.php          # Handler proses buat tiket + upload lampiran
│   └── delete_ticket.php         # Handler hapus tiket milik sendiri
│
├── api/                          # Endpoint API internal
│   ├── check_tickets.php         # Polling cek tiket baru (notifikasi real-time)
│   └── chart_data.php            # Data JSON untuk Chart.js (status & bulanan)
│
├── config/                       # Konfigurasi & helper sistem
│   ├── koneksi.php               # Koneksi database (env-based)
│   ├── auth.php                  # Autentikasi, session, RBAC
│   ├── bootstrap.php             # Inisialisasi session & koneksi
│   ├── logger.php                # Fungsi logTicketActivity() + getTicketLogs()
│   ├── upload_utils.php          # Helper validasi & simpan file upload
│   ├── mailer.php                # Konfigurasi PHPMailer
│   ├── email_config.php          # Pengaturan SMTP
│   └── email_helper.php          # Helper fungsi kirim email
│
├── fpdf/                         # Library FPDF (PDF generation)
│   └── fpdf.php
│
├── vendor/                       # Library eksternal
│   └── PHPMailer/
│       ├── PHPMailer.php
│       ├── SMTP.php
│       └── Exception.php
│
├── assets/                       # Aset statis
│   ├── Logo_TVRI.svg.png
│   └── TVRILogo2019.svg.png
│
├── uploads/                      # File yang diunggah (diproteksi .htaccess)
│   ├── tickets/                  # Lampiran foto tiket pelaporan
│   ├── perbaikan/                # Foto bukti perbaikan teknisi
│   ├── resolved/                 # Foto saat tiket diselesaikan
│   └── profile_photos/           # Foto profil semua user
│
├── scripts/                      # Skrip utilitas developer
│   └── reset_passwords.php       # Reset semua password ke "123456"
│
├── logs/                         # Log fallback sistem (error/debug)
│
├── .env                          # Konfigurasi environment (JANGAN di-commit)
├── .env.example                  # Template .env
├── .gitignore
│
├── index.php                     # Halaman login
├── cek_login.php                 # Handler proses login
├── logout.php                    # Proses logout & destroy session
├── register.php                  # Handler registrasi user baru
├── detail.php                    # Detail tiket + timeline aktivitas (semua role)
├── cetak.php                     # Generator PDF invoice per tiket
├── change_password.php           # Ganti password (semua role)
├── upload_photo.php              # Handler upload/update foto profil
│
├── db_ticketing.sql              # Skema database utama (tables + data awal)
├── update_db_logs.sql            # Migrasi: tambah tabel ticket_logs
├── monthly_top_teknisi.sql       # Migrasi: tambah tabel monthly_top_teknisi
├── create_db_user.sql            # Skrip buat user DB dengan privilege terbatas
└── README.md
```

---

## Instalasi & Setup

### 1. Prasyarat
- **Laragon** / XAMPP / WAMP (PHP 8.1+ dan MySQL 8.0+)
- Web browser modern (Chrome, Firefox, Edge)

### 2. Clone / Copy Project
```bash
git clone https://github.com/<username>/ticketing_tvri.git
# Letakkan di folder www Laragon atau htdocs XAMPP
# Contoh: D:\laragon\www\ticketing_tvri
```

### 3. Setup Database
```bash
# Buat database & import schema utama
mysql -u root db_ticketing < db_ticketing.sql

# Import migrasi tabel logging
mysql -u root db_ticketing < update_db_logs.sql

# Import tabel top teknisi bulanan
mysql -u root db_ticketing < monthly_top_teknisi.sql
```

Atau melalui phpMyAdmin / HeidiSQL:
1. Buat database `db_ticketing` (charset `utf8mb4`)
2. Import `db_ticketing.sql`
3. Import `update_db_logs.sql`
4. Import `monthly_top_teknisi.sql`

### 4. Konfigurasi Environment
Salin `.env.example` menjadi `.env` dan sesuaikan:
```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=db_ticketing
```

### 5. Akses Sistem
```
http://localhost/ticketing_tvri
```

> Untuk Laragon dengan port non-standar:
> `http://localhost:80/ticketing_tvri` atau sesuai konfigurasi Laragon.

---

## Akun Default

| Role | Username | Password | Keterangan |
|------|----------|----------|------------|
| Admin | `admin` | `123456` | Administrator sistem |
| Teknisi | `loudres_it` | `123456` | Divisi Teknik IT |
| Teknisi | `varel` | `123456` | Divisi Teknik Kelistrikan |
| Teknisi | `raya` | `123456` | Divisi Studio (Audio Video) |
| Teknisi | `joxra` | `123456` | Divisi Teknik Kelistrikan |
| User | `lelemboto` | `123456` | Pelapor biasa |

> **PENTING**: Ganti semua password default sebelum digunakan di jaringan internal!  
> Gunakan `scripts/reset_passwords.php` hanya di lingkungan development.

---

## Alur Kerja Sistem

### User (Pelapor)
```
Login → Dashboard → Lapor Tiket Baru → Isi Form + Upload Foto → Submit
→ Tiket masuk status "Open" → Menunggu Admin assign
→ Pantau status tiket dari dashboard
```

### Admin
```
Login → Dashboard → Lihat Tiket Open → Klik Assign
→ Pilih Divisi + Pilih Teknisi + Set Priority → Submit
→ Status "Assigned" → Teknisi menerima notifikasi
→ Pantau progress via dashboard & activity logs
→ Cetak PDF tiket jika diperlukan
```

### Teknisi
```
Login → Dashboard → Lihat Tiket Assigned (badge notifikasi)
→ Klik "Mulai" → Status "In Progress" → Kerjakan perbaikan
→ Upload foto bukti → Isi catatan → Klik "Selesai" → Status "Resolved"
```

---

## Skema Database

### `divisions`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `nama_divisi` | VARCHAR(100) | Teknik IT / Teknik Kelistrikan / Studio (Audio Video) |

### `users`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `nama` | VARCHAR(100) | Nama lengkap |
| `username` | VARCHAR(50) UNIQUE | |
| `password` | VARCHAR(255) | bcrypt hash |
| `email` | VARCHAR(150) NULL | Opsional |
| `role` | ENUM('admin','user','teknisi') | |
| `division_id` | INT FK NULL | NULL untuk admin & user; wajib untuk teknisi |
| `foto` | VARCHAR(255) NULL | Nama file foto profil |

### `tickets`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `judul` | VARCHAR(200) | |
| `lokasi` | VARCHAR(150) | |
| `deskripsi` | TEXT | |
| `attachment` | VARCHAR(255) NULL | Lampiran foto tiket (di `uploads/tickets/`) |
| `foto_perbaikan` | VARCHAR(255) NULL | Foto bukti perbaikan (di `uploads/perbaikan/`) |
| `user_id` | INT FK | Pelapor |
| `assigned_division_id` | INT FK NULL | Divisi yang ditugaskan |
| `handled_by` | INT FK NULL | ID teknisi yang menangani |
| `status` | ENUM('Open','Assigned','In Progress','Resolved') | |
| `priority` | ENUM('Low','Medium','High','Urgent') | |
| `catatan_teknisi` | TEXT NULL | Keterangan hasil perbaikan |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | auto-update |

### `ticket_logs`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `ticket_id` | INT FK NULL | NULL jika tiket sudah dihapus |
| `ticket_judul` | VARCHAR(255) NULL | Judul tiket saat log dibuat (backup) |
| `ticket_id_orig` | INT NULL | ID asli tiket sebelum dihapus |
| `user_id` | INT FK NULL | Pelaku aksi |
| `action` | VARCHAR(50) | `create` / `assign` / `status_update` / `ticket_deleted` |
| `old_value` | TEXT NULL | Nilai sebelum perubahan |
| `new_value` | TEXT NULL | Nilai setelah perubahan |
| `description` | TEXT NULL | Deskripsi aktivitas |
| `created_at` | TIMESTAMP | |

> Kolom `ticket_judul` dan `ticket_id_orig` memastikan riwayat aktivitas tetap
> tampil di Activity Logs meskipun tiket sudah dihapus.  
> Log otomatis dihapus setelah **30 hari** (auto-purge).

### `monthly_top_teknisi`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `teknisi_id` | INT NULL | FK ke `users.id` (bisa NULL jika user dihapus) |
| `teknisi_nama` | VARCHAR(100) | Nama teknisi saat snapshot |
| `teknisi_foto` | VARCHAR(255) NULL | Nama file foto saat snapshot |
| `bulan` | TINYINT | 1–12 |
| `tahun` | SMALLINT | Tahun 4 digit |
| `rank_position` | TINYINT | Peringkat (1 = terbaik) |
| `resolved_count` | INT | Jumlah tiket resolved bulan tersebut |
| `snapshotted_at` | TIMESTAMP | Waktu snapshot diambil |

> Snapshot diambil **otomatis secara lazy** saat admin membuka dashboard — bulan-bulan
> yang belum ada datanya akan di-snapshot sekaligus (hingga 24 bulan ke belakang).

---

## Keamanan

- Password di-hash dengan `password_hash()` (bcrypt, cost default)
- Session-based auth dengan validasi IP address (`config/auth.php`)
- RBAC: setiap halaman diproteksi via `checkRole()` sesuai role
- Input user di-escape dengan `mysqli_real_escape_string()` & prepared statements
- Folder `uploads/` diproteksi `.htaccess` (tidak bisa direct browse)
- File `.env` berisi kredensial — **jangan pernah di-commit ke Git** (ada di `.gitignore`)

---

## Troubleshooting

### Halaman Blank / Error 500
- Pastikan Apache & MySQL Laragon/XAMPP sudah berjalan
- Aktifkan error reporting sementara di `config/bootstrap.php`
- Cek log error Apache di Laragon

### Login Gagal
- Pastikan file `.env` sudah dibuat dan konfigurasi DB-nya benar
- Gunakan `scripts/reset_passwords.php` untuk reset semua password ke `123456`

### PDF Tidak Generate
- Pastikan `fpdf/fpdf.php` ada dan terbaca oleh webserver
- Akses langsung: `http://localhost/ticketing_tvri/cetak.php?id=1`
- Cek path logo di `cetak.php`

### Log Tidak Tercatat
- Verifikasi semua migrasi SQL sudah diimport (terutama kolom `ticket_judul` & `ticket_id_orig`)
- Pastikan `config/logger.php` di-`require` di semua file proses yang relevan

### Top Teknisi Tidak Muncul
- Pastikan `monthly_top_teknisi.sql` sudah diimport ke database
- Data muncul setelah ada tiket berstatus `Resolved` di bulan berjalan
- Riwayat bulanan muncul setelah setidaknya satu bulan berlalu sejak instalasi

---

## Pengembangan Lanjutan

Fitur yang dapat ditambahkan ke depan:
- [ ] Export laporan ke Excel (PhpSpreadsheet)
- [ ] REST API untuk integrasi aplikasi mobile
- [ ] Notifikasi email otomatis saat status tiket berubah
- [ ] Two-factor authentication (2FA)
- [ ] SLA tracking (batas waktu penyelesaian tiket)
- [ ] Filter & sorting DataTables server-side

---

## Kredit

**Dikembangkan untuk**: Proyek Akhir Magang — TVRI (Televisi Republik Indonesia)  
**Tahun**: 2025–2026

**Open Source Libraries:**
- [Bootstrap](https://getbootstrap.com) — MIT License
- [Bootstrap Icons](https://icons.getbootstrap.com) — MIT License
- [Chart.js](https://www.chartjs.org) — MIT License
- [FPDF](http://www.fpdf.org) — Freeware
- [SweetAlert2](https://sweetaleel2.github.io) — MIT License
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) — LGPL 2.1

---

*© 2025–2026 TVRI — Televisi Republik Indonesia*