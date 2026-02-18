<<<<<<< HEAD
﻿# TVRI Ticketing System
## Sistem Manajemen Tiket Pelaporan TVRI

###  Deskripsi
Sistem ticketing ini merupakan project akhir magang untuk TVRI. Sistem ini memungkinkan karyawan untuk melaporkan permasalahan teknis, dan admin dapat menugaskan tiket tersebut ke divisi teknisi yang sesuai. Teknisi dapat memperbarui status perbaikan dan memberikan catatan.

###  Fitur Utama

#### 1. **Multi-Role System**
- **User/Pelapor**: Membuat laporan tiket masalah teknis
- **Admin**: Mengelola dan menugaskan tiket ke divisi teknisi
- **Teknisi**: Mengupdate status perbaikan dan memberikan laporan

#### 2. **Manajemen Tiket**
- Buat tiket baru dengan judul, lokasi, dan deskripsi masalah
- Status tiket: Open  Assigned  In Progress  Resolved
- Priority level: Low, Medium, High, Urgent
- 3 Divisi Teknisi: IT, Listrik, Studio (Audio Video)

#### 3. **Logging & Riwayat**
- Setiap aktivitas tiket tercatat dalam log
- Timeline riwayat aktivitas lengkap dengan user dan waktu
- Halaman detail tiket dengan semua informasi dan riwayat

#### 4. **Laporan PDF**
- Generate PDF untuk setiap tiket
- Tampilan profesional dengan badge status dan priority
- Informasi lengkap termasuk catatan teknisi

#### 5. **Notifikasi Real-time**
- SweetAlert2 notifications untuk feedback user
- Badge notifikasi untuk pending tasks (Admin & Teknisi)
- Success/error messages yang informatif

#### 6. **Dashboard Interaktif**
- Statistik real-time untuk setiap role
- Card statistics dengan hover effects
- Tabel responsive dengan Bootstrap 5
- Gradient purple theme yang modern

###  Struktur Folder
```
ticketing_tvri/
 admin/              # Halaman admin
    dashboard.php
    assign.php
    proses_assign.php
 user/               # Halaman user/pelapor
    dashboard.php
    lapor.php
    proses_lapor.php
 teknisi/            # Halaman teknisi
    dashboard.php
    update_status.php
    proses_update.php
 config/             # Konfigurasi sistem
    koneksi.php     # Database connection
    auth.php        # Authentication & RBAC
    logger.php      # Activity logging functions
 fpdf/               # Library PDF
    fpdf.php
 scripts/            # Utility scripts
    reset_passwords.php
 index.php           # Login page
 cek_login.php       # Login process
 logout.php          # Logout process
 cetak.php           # PDF generator
 detail.php          # Ticket detail & history
 db_ticketing.sql    # Database schema
 update_db_logs.sql  # Ticket logs table
 README.md
```

###  Teknologi yang Digunakan

**Backend:**
- PHP 7.4+
- MySQL 5.7+
- MySQLi Extension

**Frontend:**
- Bootstrap 5.3.0
- Bootstrap Icons 1.11.0
- SweetAlert2 v11
- Vanilla JavaScript

**Libraries:**
- FPDF 1.86 (Custom simplified version)

**Security:**
- Password hashing with `password_hash()`
- Session management with IP validation
- RBAC (Role-Based Access Control)
- SQL injection prevention with `mysqli_real_escape_string()`

###  Instalasi

#### 1. Prerequisites
- XAMPP/WAMP (PHP 7.4+ dan MySQL)
- Web browser modern (Chrome, Firefox, Edge)

#### 2. Setup Database
```sql
-- 1. Buat database
CREATE DATABASE db_ticketing;

-- 2. Import schema utama
mysql -u root db_ticketing < db_ticketing.sql

-- 3. Import tabel logging
mysql -u root db_ticketing < update_db_logs.sql
```

Atau melalui phpMyAdmin:
1. Buka `http://localhost/phpmyadmin`
2. Buat database baru dengan nama `db_ticketing`
3. Import file `db_ticketing.sql`
4. Import file `update_db_logs.sql`

#### 3. Konfigurasi Koneksi
Buka `config/koneksi.php` dan sesuaikan jika perlu:
```php
$host = ''localhost'';
$username = ''root'';
$password = '''';        // Sesuaikan dengan password MySQL Anda
$database = ''db_ticketing'';
```

#### 4. Deployment
Copy folder `ticketing_tvri` ke:
- **XAMPP**: `C:\xampp\htdocs\ticketing_tvri`
- **WAMP**: `C:\wamp\www\ticketing_tvri`

#### 5. Akses Sistem
Buka browser dan akses: `http://localhost/ticketing_tvri`

###  Default Akun Login

| Role     | Username   | Password | Keterangan                |
|----------|------------|----------|---------------------------|
| Admin    | admin      | 123456   | Administrator sistem      |
| User     | budi       | 123456   | Pelapor (User biasa)      |
| User     | siti       | 123456   | Pelapor (User biasa)      |
| Teknisi  | andi_it    | 123456   | Teknisi Divisi IT         |
| Teknisi  | joko_listrik | 123456 | Teknisi Divisi Listrik    |
| Teknisi  | dedi_studio | 123456  | Teknisi Divisi Studio     |

** PENTING**: Ganti semua password default setelah deployment!

###  Keamanan

#### Authentication
- Session-based login dengan session ID regeneration
- IP address validation untuk mencegah session hijacking
- Role-based access control pada setiap halaman

#### Password Management
Untuk development, gunakan `scripts/reset_passwords.php` untuk reset semua password ke default (123456).
Untuk production, **HARUS** mengganti semua password melalui database atau buat halaman change password.

#### SQL Security
- Semua user input di-escape dengan `mysqli_real_escape_string()`
- Prepared statements untuk query yang kompleks (direkomendasikan untuk upgrade)

###  Workflow Sistem

#### 1. User (Pelapor) Workflow
```
Login  Dashboard  Lapor Tiket Baru  Isi Form  Submit
 Tiket tercatat dengan status "Open"  Menunggu Admin assign
```

#### 2. Admin Workflow
```
Login  Dashboard  Lihat Tiket Open  Klik Assign  
 Pilih Divisi Teknisi + Set Priority  Submit
 Status berubah "Assigned"  Teknisi dapat melihat tiket
```

#### 3. Teknisi Workflow
```
Login  Dashboard  Lihat Tiket Assigned/In Progress
 Klik "Mulai" (Assigned)  Status "In Progress"  Kerjakan perbaikan
 Klik "Selesai"  Isi Catatan Teknisi  Submit  Status "Resolved"
```

#### 4. Activity Logging
Setiap aktivitas penting tercatat:
- **Create**: User membuat tiket baru
- **Assign**: Admin menugaskan ke divisi
- **Status Update**: Teknisi mengubah status tiket
- Log dapat dilihat di halaman detail tiket

###  Database Schema

#### Tabel `users`
- `id`: Primary key
- `nama`: Nama lengkap user
- `username`: Username login (unique)
- `password`: Hashed password
- `role`: enum(''admin'', ''user'', ''teknisi'')
- `division_id`: Foreign key ke `divisions` (untuk teknisi)

#### Tabel `divisions`
- `id`: Primary key
- `nama_divisi`: Nama divisi (IT, Listrik, Studio)

#### Tabel `tickets`
- `id`: Primary key
- `user_id`: FK ke users (pelapor)
- `judul`: Judul tiket
- `lokasi`: Lokasi masalah
- `deskripsi`: Deskripsi detail
- `assigned_division_id`: FK ke divisions
- `status`: enum(''Open'', ''Assigned'', ''In Progress'', ''Resolved'')
- `priority`: enum(''Low'', ''Medium'', ''High'', ''Urgent'')
- `catatan_teknisi`: Catatan dari teknisi
- `created_at`, `updated_at`: Timestamps

#### Tabel `ticket_logs`
- `id`: Primary key
- `ticket_id`: FK ke tickets
- `user_id`: FK ke users (yang melakukan aksi)
- `action`: Jenis aktivitas
- `old_value`, `new_value`: Nilai sebelum/sesudah
- `description`: Deskripsi aktivitas
- `created_at`: Timestamp

###  Customization

#### Theme Colors
Edit di setiap file dashboard untuk mengubah theme:
```css
/* Gradient Sidebar */
background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);

/* Ganti dengan warna TVRI jika ada brand guideline */
```

#### Logo & Branding
Tambahkan logo TVRI di bagian sidebar:
```html
<div class="text-center mb-4">
    <img src="assets/img/logo-tvri.png" alt="TVRI" width="80">
    <h5 class="mt-2">TVRI Ticketing</h5>
</div>
```

###  Troubleshooting

#### 1. Halaman Blank/Error
- Pastikan XAMPP Apache & MySQL sudah running
- Cek error di `C:\xampp\apache\logs\error.log`
- Enable error reporting:
  ```php
  error_reporting(E_ALL);
  ini_set(''display_errors'', 1);
  ```

#### 2. Login Gagal
- Cek koneksi database di `config/koneksi.php`
- Jalankan `scripts/reset_passwords.php` untuk reset password
- Pastikan session berjalan (cek `php.ini` untuk `session.save_path`)

#### 3. PDF Tidak Generate
- Pastikan `fpdf/fpdf.php` exist dan readable
- Cek permission folder (harus writable jika ada cache)
- Test manual: `http://localhost/ticketing_tvri/cetak.php?id=1`

#### 4. Log Tidak Tercatat
- Pastikan tabel `ticket_logs` sudah dibuat
- Cek `update_db_logs.sql` sudah diimport
- Verify `config/logger.php` di-require di file proses

###  Future Enhancements

Fitur yang bisa ditambahkan:
- [ ] DataTables untuk sorting & pagination advanced
- [ ] Chart.js dashboard visualizations
- [ ] Filter & pencarian tiket lanjutan
- [ ] Email notification system
- [ ] Upload attachment/foto masalah
- [ ] Mobile responsive optimization
- [ ] API REST untuk integrasi mobile app
- [ ] Change password feature
- [ ] User management (CRUD users)
- [ ] Export Excel reports

###  License & Credits

**Dikembangkan untuk**: Proyek Akhir Magang TVRI  
**Developer**: [Nama Anda]  
**Tahun**: 2024  

**Open Source Components:**
- Bootstrap (MIT License)
- FPDF (Freeware)
- SweetAlert2 (MIT License)

###  Support

Untuk pertanyaan atau issue, silakan hubungi:
- Email: [email@tvri.co.id]
- Internal: [Nomor Ext.]

---
** 2024 TVRI - Televisi Republik Indonesia**
=======
# TVRI_Ticketing_System
>>>>>>> 6aff431cca2acc85656e77cda34fc409ca0e39f6
