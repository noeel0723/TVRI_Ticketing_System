# TVRI Ticketing System
### Sistem Manajemen Tiket Pelaporan — TVRI (Televisi Republik Indonesia)

---

## Deskripsi

Sistem ticketing berbasis web untuk TVRI yang memungkinkan karyawan melaporkan permasalahan teknis. Admin dapat menugaskan tiket ke divisi teknisi yang sesuai, dan teknisi dapat memperbarui status perbaikan lengkap dengan unggah foto bukti perbaikan. Seluruh aktivitas tiket tercatat dalam log riwayat, termasuk tiket yang telah dihapus.

---

## Fitur Utama

### Multi-Role System
| Role | Kemampuan |
|------|-----------|
| **User / Pelapor** | Buat tiket, pantau status, hapus tiket milik sendiri |
| **Admin** | Kelola semua tiket, assign ke teknisi, kelola user, lihat activity log |
| **Teknisi** | Lihat tiket yang ditugaskan, update status perbaikan, upload foto hasil kerja |

### Manajemen Tiket
- Buat tiket baru dengan judul, lokasi, deskripsi, dan lampiran foto
- Status tiket: `Open` → `Assigned` → `In Progress` → `Resolved`
- Priority: `Low`, `Medium`, `High`, `Urgent`
- 3 Divisi Teknisi: **IT**, **Listrik**, **Studio (Audio Video)**
- Bulk action (delete/assign) untuk Admin

### Activity Log & Riwayat
- Setiap aktivitas tiket tercatat secara otomatis
- Riwayat tetap tersimpan meskipun tiket sudah dihapus (judul tiket dipreservasi)
- Halaman Activity Logs khusus Admin dengan filter per tab
- Halaman detail tiket menampilkan timeline lengkap

### Cetak PDF (Invoice Style)
- Generate PDF per tiket dalam layout invoice profesional
- Header berwarna biru TVRI dengan logo resmi
- Metadata tiles: nomor tiket, status, prioritas, tanggal
- Bagian tanda tangan: Pelapor, Approver, Teknisi

### Upload Foto
- User dapat melampirkan foto saat membuat tiket
- Teknisi dapat mengunggah foto bukti perbaikan
- Foto tersimpan terorganisir di folder `uploads/`

### Manajemen User (Admin)
- CRUD lengkap untuk akun user dan teknisi
- Tambah user baru, edit data, reset password, nonaktifkan akun

### Notifikasi Real-time
- Badge notifikasi di sidebar untuk pending tasks (Admin & Teknisi)
- SweetAlert2 feedback untuk setiap aksi penting
- Polling otomatis untuk cek tiket baru (API)

### Autentikasi & Keamanan
- Login dengan username + password (hash bcrypt)
- Session-based auth dengan IP validation
- Ganti password mandiri di halaman profil
- Email bersifat **opsional** saat registrasi

---

## Teknologi

**Backend**
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10+
- MySQLi Extension

**Frontend**
- Bootstrap 5.3
- Bootstrap Icons 1.11
- SweetAlert2 v11
- Vanilla JavaScript

**Libraries**
- FPDF 1.86 — PDF generation
- PHPMailer 6 — Email notifications (opsional)

---

## Struktur Folder

```
ticketing_tvri/
├── admin/                    # Halaman admin
│   ├── dashboard.php
│   ├── assign.php
│   ├── activity_logs.php
│   ├── kelola_user.php
│   ├── profile.php
│   ├── proses_assign.php
│   ├── proses_bulk.php
│   ├── proses_user.php
│   └── proses_hapus_tiket.php
├── teknisi/                  # Halaman teknisi
│   ├── dashboard.php
│   ├── profile.php
│   ├── update_status.php
│   ├── proses_update.php
│   └── proses_hapus_tiket.php
├── user/                     # Halaman user/pelapor
│   ├── dashboard.php
│   ├── lapor.php
│   ├── profile.php
│   ├── proses_lapor.php
│   └── delete_ticket.php
├── api/                      # Endpoint API internal
│   ├── check_tickets.php
│   └── chart_data.php
├── config/                   # Konfigurasi sistem
│   ├── koneksi.php           # Koneksi database (env-based)
│   ├── auth.php              # Autentikasi & RBAC
│   ├── bootstrap.php         # Inisialisasi session & koneksi
│   ├── logger.php            # Fungsi activity logging
│   ├── upload_utils.php      # Helper upload file
│   ├── mailer.php            # Konfigurasi PHPMailer
│   ├── email_config.php      # Pengaturan SMTP
│   └── email_helper.php      # Helper kirim email
├── fpdf/                     # Library FPDF
│   └── fpdf.php
├── vendor/                   # Library PHPMailer
│   └── PHPMailer/
├── assets/                   # Gambar & aset statis
│   ├── Logo_TVRI.svg.png
│   └── TVRILogo2019.svg.png
├── uploads/                  # File yang diunggah user
│   ├── tickets/              # Lampiran tiket
│   ├── perbaikan/            # Foto bukti perbaikan teknisi
│   └── profile_photos/       # Foto profil user
├── scripts/                  # Skrip utilitas developer
│   └── reset_passwords.php   # Reset semua password ke default
├── logs/                     # Log fallback sistem
├── .env                      # Konfigurasi environment (JANGAN di-commit)
├── .env.example              # Template konfigurasi
├── index.php                 # Halaman login & registrasi
├── cek_login.php             # Proses login
├── logout.php                # Proses logout
├── register.php              # Proses registrasi
├── detail.php                # Detail & riwayat tiket
├── cetak.php                 # Generator PDF invoice
├── change_password.php       # Ganti password
├── upload_photo.php          # Handle upload foto profil
├── db_ticketing.sql          # Skema database utama
├── update_db_logs.sql        # Migrasi tabel ticket_logs
├── create_db_user.sql        # Skrip buat user DB terbatas
└── README.md
```

---

## Instalasi & Setup

### 1. Prasyarat
- XAMPP / WAMP (PHP 7.4+ dan MySQL/MariaDB)
- Web browser modern (Chrome, Firefox, Edge)

### 2. Clone / Copy Project
```bash
git clone https://github.com/<username>/ticketing_tvri.git
# Letakkan di htdocs XAMPP
# Contoh: D:\xamp\htdocs\ticketing_tvri
```

### 3. Setup Database
```sql
-- Buat database
CREATE DATABASE db_ticketing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Import schema utama
mysql -u root db_ticketing < db_ticketing.sql

-- Import migrasi tabel logging
mysql -u root db_ticketing < update_db_logs.sql
```

Atau melalui phpMyAdmin:
1. Buat database `db_ticketing`
2. Import `db_ticketing.sql`
3. Import `update_db_logs.sql`

### 4. Konfigurasi Environment
Salin `.env.example` menjadi `.env` dan sesuaikan:
```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=db_ticketing
```

### 5. Akses Sistem
```
http://localhost/ticketing_tvri
```

> Untuk deployment port non-standar, akses `http://localhost:10080/ticketing_tvri`
> (sesuaikan port Apache di `httpd.conf`)

---

## Akun Default

| Role | Username | Password | Keterangan |
|------|----------|----------|------------|
| Admin | `admin` | `123456` | Administrator sistem |
| User | `budi` | `123456` | Pelapor biasa |
| Teknisi | `andi_it` | `123456` | Divisi IT |
| Teknisi | `joko_listrik` | `123456` | Divisi Listrik |
| Teknisi | `dedi_studio` | `123456` | Divisi Studio |

> **PENTING**: Ganti semua password default sebelum digunakan di jaringan internal!  
> Gunakan `scripts/reset_passwords.php` hanya di lingkungan development.

---

## Alur Kerja Sistem

### User (Pelapor)
```
Login → Dashboard → Lapor Tiket Baru → Isi Form + Upload Foto → Submit
→ Tiket masuk status "Open" → Menunggu Admin assign
```

### Admin
```
Login → Dashboard → Lihat Tiket Open → Klik Assign
→ Pilih Divisi + Pilih Teknisi + Set Priority → Submit
→ Status "Assigned" → Teknisi menerima notifikasi
```

### Teknisi
```
Login → Dashboard → Lihat Tiket Assigned
→ Klik "Mulai" → Status "In Progress" → Kerjakan perbaikan
→ Upload foto bukti → Klik "Selesai" + Isi Catatan → Status "Resolved"
```

---

## Skema Database

### `users`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `nama` | VARCHAR(100) | Nama lengkap |
| `username` | VARCHAR(50) UNIQUE | |
| `password` | VARCHAR(255) | bcrypt hash |
| `email` | VARCHAR(100) NULL | Opsional |
| `role` | ENUM('admin','user','teknisi') | |
| `division_id` | INT FK | NULL untuk non-teknisi |
| `profile_photo` | VARCHAR(255) NULL | Path foto profil |

### `divisions`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `nama_divisi` | VARCHAR(100) | IT / Listrik / Studio |

### `tickets`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `user_id` | INT FK | Pelapor |
| `judul` | VARCHAR(255) | |
| `lokasi` | VARCHAR(255) | |
| `deskripsi` | TEXT | |
| `foto_masalah` | VARCHAR(255) NULL | Lampiran foto tiket |
| `assigned_division_id` | INT FK NULL | |
| `handled_by` | INT FK NULL | ID teknisi yang menangani |
| `status` | ENUM('Open','Assigned','In Progress','Resolved') | |
| `priority` | ENUM('Low','Medium','High','Urgent') | |
| `catatan_teknisi` | TEXT NULL | |
| `foto_perbaikan` | VARCHAR(500) NULL | Foto bukti perbaikan |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

### `ticket_logs`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | INT PK | |
| `ticket_id` | INT FK NULL | NULL jika tiket dihapus |
| `ticket_judul` | VARCHAR(255) NULL | Judul tiket (dipreservasi saat hapus) |
| `ticket_id_orig` | INT NULL | ID asli tiket sebelum dihapus |
| `user_id` | INT FK | Pelaku aksi |
| `action` | VARCHAR(50) | create / assign / status_update / delete, dll. |
| `old_value` | VARCHAR(255) NULL | |
| `new_value` | VARCHAR(255) NULL | |
| `description` | TEXT NULL | |
| `created_at` | DATETIME | |

> Kolom `ticket_judul` dan `ticket_id_orig` memungkinkan riwayat aktivitas tetap
> tampil di halaman Activity Logs meskipun tiket sudah dihapus.

---

## Keamanan

- Password di-hash dengan `password_hash()` (bcrypt)
- Session-based auth dengan validasi IP address
- RBAC: setiap halaman diproteksi sesuai role via `config/auth.php`
- User input di-escape dengan `mysqli_real_escape_string()`
- Folder `uploads/` dan `scripts/` diproteksi dengan `.htaccess`
- File `.env` berisi kredensial — **jangan pernah di-commit ke Git**

---

## Troubleshooting

### Halaman Blank / Error 500
- Pastikan Apache & MySQL XAMPP sudah running
- Cek `D:\xamp\apache\logs\error.log`
- Aktifkan error reporting sementara di `config/bootstrap.php`

### Login Gagal
- Pastikan `.env` sudah dikonfigurasi dengan benar
- Jalankan `scripts/reset_passwords.php` untuk reset password ke `123456`

### PDF Tidak Generate
- Pastikan `fpdf/fpdf.php` ada dan terbaca
- Test: `http://localhost/ticketing_tvri/cetak.php?id=1`
- Cek path logo di `cetak.php` sudah sesuai lokasi XAMPP

### Log Tidak Tercatat
- Pastikan `update_db_logs.sql` sudah diimport
- Verifikasi kolom `ticket_judul` dan `ticket_id_orig` sudah ada di tabel `ticket_logs`
- Cek `config/logger.php` di-require di file proses yang relevan

---

## Pengembangan Lanjutan

Fitur yang dapat ditambahkan ke depan:
- [ ] Export laporan ke Excel (PhpSpreadsheet)
- [ ] DataTables dengan sorting & pagination server-side
- [ ] REST API untuk integrasi aplikasi mobile
- [ ] Dashboard chart interaktif (Chart.js)
- [ ] Notifikasi email otomatis saat status tiket berubah
- [ ] Two-factor authentication (2FA)

---

## Kredit

**Dikembangkan untuk**: Proyek Akhir Magang — TVRI (Televisi Republik Indonesia)  
**Tahun**: 2025–2026

**Open Source Libraries:**
- [Bootstrap](https://getbootstrap.com) — MIT License
- [FPDF](http://www.fpdf.org) — Freeware
- [SweetAlert2](https://sweetalert2.github.io) — MIT License
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) — LGPL 2.1

---

*© 2025–2026 TVRI — Televisi Republik Indonesia*