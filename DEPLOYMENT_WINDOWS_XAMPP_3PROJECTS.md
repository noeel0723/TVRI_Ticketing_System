# SOP Deployment 3 Project PHP+MySQL di Windows Server (XAMPP/Apache)

Dokumen ini menjelaskan deployment **3 project** (termasuk `ticketing_tvri`) ke **Windows Server internal** menggunakan **XAMPP (Apache + MySQL/MariaDB)**, dengan **port berbeda per project** (sekitar 10000) dan **nama domain internal bisa dicustom**.

> Contoh mapping yang dipakai di dokumen ini:
>
> - Project 1: `ticketing.tvri.internal` di port `10000`
> - Project 2: `app2.tvri.internal` di port `10001`
> - Project 3: `app3.tvri.internal` di port `10002`
>
> Anda bebas ganti domain dan port sesuai kebutuhan.

---

## 1) Prasyarat & keputusan awal

### 1.1 Prasyarat server
- Windows Server (2016/2019/2022)
- Akses administrator lokal
- Static IP server (contoh: `10.x.x.x`)
- DNS internal aktif (AD DNS atau DNS internal lain)

### 1.2 Prasyarat aplikasi
- Ketiga project adalah **PHP + MySQL/MariaDB**
- Untuk `ticketing_tvri`:
  - Konfigurasi DB pakai file `.env`
  - Key yang digunakan untuk password DB adalah `DB_PASSWORD` (bukan `DB_PASS`)
  - Folder yang butuh write untuk upload: `uploads/` (dan subfoldernya)

### 1.3 Rekomendasi struktur folder di server
Gunakan folder terpisah dari folder XAMPP agar upgrade XAMPP tidak mengganggu aplikasi:

- `D:\WebApps\project1\`  (contoh: `D:\WebApps\ticketing_tvri\`)
- `D:\WebApps\project2\`
- `D:\WebApps\project3\`
- `D:\WebData\backups\`   (backup DB & file upload)

---

## 2) Instal XAMPP dan jadikan service

### 2.1 Install XAMPP
1. Download XAMPP versi yang sesuai PHP project (mis. PHP 7.4+ untuk `ticketing_tvri`).
2. Install ke path sederhana, contoh: `C:\xampp`.
3. Pastikan komponen minimal terpasang:
   - Apache
   - MySQL (atau MariaDB bila Anda mengganti engine)

### 2.2 Jalankan Apache & MySQL sebagai Windows Service
Tujuan: service auto-start setelah reboot server.

1. Buka **XAMPP Control Panel** sebagai Administrator.
2. Klik `Svc` di baris **Apache** (hingga muncul tanda centang service).
3. Klik `Svc` di baris **MySQL**.
4. Klik `Start` untuk Apache dan MySQL.

> Jika port `80/443` bentrok (sering karena IIS/Skype), itu tidak masalah bila Anda hanya pakai port `10000+`.

---

## 3) Siapkan port & firewall (10000/10001/10002)

### 3.1 Tentukan port per project
Contoh yang konsisten:
- Project 1 → `10000`
- Project 2 → `10001`
- Project 3 → `10002`

### 3.2 Buka firewall Windows
Buka inbound TCP untuk port yang dipakai.

PowerShell (Run as Admin), contoh:
- `New-NetFirewallRule -DisplayName "TVRI Web Project1 10000" -Direction Inbound -Protocol TCP -LocalPort 10000 -Action Allow`
- `New-NetFirewallRule -DisplayName "TVRI Web Project2 10001" -Direction Inbound -Protocol TCP -LocalPort 10001 -Action Allow`
- `New-NetFirewallRule -DisplayName "TVRI Web Project3 10002" -Direction Inbound -Protocol TCP -LocalPort 10002 -Action Allow`

> Jika policy internal mewajibkan rule dibuat via GPO/Firewall terpusat, ikuti prosedur internal.

---

## 4) Konfigurasi Apache untuk listen di port 10000+ dan VirtualHost per project

### 4.1 Set Apache listen ke port 10000/10001/10002
Edit file:
- `C:\xampp\apache\conf\httpd.conf`

Cari baris `Listen 80`. Anda punya 2 opsi:

**Opsi A (disarankan untuk requirement ini): nonaktifkan port 80 dan pakai 10000+ saja**
- Comment `Listen 80` (tambahkan `#` di depannya)
- Tambahkan:
  - `Listen 10000`
  - `Listen 10001`
  - `Listen 10002`

**Opsi B: tetap listen 80 + tambah 10000+**
- Biarkan `Listen 80`
- Tambahkan `Listen 10000/10001/10002`

Pastikan modul vhost include aktif (biasanya sudah):
- `Include conf/extra/httpd-vhosts.conf`

### 4.2 Buat VirtualHost per port
Edit file:
- `C:\xampp\apache\conf\extra\httpd-vhosts.conf`

Isi contoh (sesuaikan `DocumentRoot` dan `ServerName`):

```apache
# Project 1 (ticketing) - port 10000
<VirtualHost *:10000>
    ServerName ticketing.tvri.internal
    DocumentRoot "D:/WebApps/ticketing_tvri"

    <Directory "D:/WebApps/ticketing_tvri">
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    ErrorLog "logs/ticketing_error.log"
    CustomLog "logs/ticketing_access.log" combined
</VirtualHost>

# Project 2 - port 10001
<VirtualHost *:10001>
    ServerName app2.tvri.internal
    DocumentRoot "D:/WebApps/project2"

    <Directory "D:/WebApps/project2">
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    ErrorLog "logs/project2_error.log"
    CustomLog "logs/project2_access.log" combined
</VirtualHost>

# Project 3 - port 10002
<VirtualHost *:10002>
    ServerName app3.tvri.internal
    DocumentRoot "D:/WebApps/project3"

    <Directory "D:/WebApps/project3">
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    ErrorLog "logs/project3_error.log"
    CustomLog "logs/project3_access.log" combined
</VirtualHost>
```

### 4.3 Restart Apache
- Di XAMPP Control Panel: `Stop` Apache → `Start` Apache

Verifikasi port terbuka:
- Dari server: buka `http://localhost:10000/` (harus mengarah ke project 1)

---

## 5) Siapkan DNS internal (domain bisa dicustom)

### 5.1 Buat A record di DNS internal
Di DNS internal, buat record:
- `ticketing.tvri.internal` → `10.x.x.x` (IP server)
- `app2.tvri.internal` → `10.x.x.x`
- `app3.tvri.internal` → `10.x.x.x`

> Karena port berbeda per project, user akan akses:
> - `http://ticketing.tvri.internal:10000/`
> - `http://app2.tvri.internal:10001/`
> - `http://app3.tvri.internal:10002/`

### 5.2 Testing cepat tanpa DNS (opsional)
Untuk uji coba dari PC tertentu, Anda bisa tambah ke file hosts:
- `C:\Windows\System32\drivers\etc\hosts`

Contoh:
- `10.x.x.x ticketing.tvri.internal`
- `10.x.x.x app2.tvri.internal`
- `10.x.x.x app3.tvri.internal`

---

## 6) Deploy kode 3 project

### 6.1 Copy source code
- Copy Project 1 ke: `D:\WebApps\ticketing_tvri\`
- Copy Project 2 ke: `D:\WebApps\project2\`
- Copy Project 3 ke: `D:\WebApps\project3\`

Pastikan:
- Tidak ada nested folder yang membuat `index.php` tidak berada di DocumentRoot yang benar.

### 6.2 Pastikan folder writeable
Untuk aplikasi yang punya upload/log file:
- Pastikan folder upload ada dan dapat ditulis.

Khusus `ticketing_tvri`:
- `D:\WebApps\ticketing_tvri\uploads\`
  - `tickets\`
  - `perbaikan\`
  - `profile_photos\`

Catatan permission Windows:
- Jika Apache service berjalan sebagai `LocalSystem`, biasanya tetap bisa menulis.
- Untuk praktik yang lebih aman, Anda bisa jalankan service Apache dengan account service khusus dan berikan Modify hanya ke folder upload/log.

---

## 7) Setup database per project (lebih aman: 1 DB + 1 user per project)

### 7.1 Buat database dan user
Gunakan MySQL CLI dari XAMPP Shell atau phpMyAdmin.

Contoh pola:
- DB: `db_project1`, User: `app_project1`
- DB: `db_project2`, User: `app_project2`
- DB: `db_project3`, User: `app_project3`

Khusus `ticketing_tvri`:
- DB: `db_ticketing`
- User: `tvri`

Anda bisa gunakan skrip `create_db_user.sql` sebagai template (ganti password dengan kuat).

### 7.2 Import schema
Khusus `ticketing_tvri` (urutannya penting):
1. Import `db_ticketing.sql`
2. Import `update_db_logs.sql`

Jika pakai CLI:
- `mysql -u root -p db_ticketing < D:\WebApps\ticketing_tvri\db_ticketing.sql`
- `mysql -u root -p db_ticketing < D:\WebApps\ticketing_tvri\update_db_logs.sql`

---

## 8) Konfigurasi aplikasi (DB, email, base URL)

### 8.1 Konfigurasi `.env` untuk `ticketing_tvri`
Di `D:\WebApps\ticketing_tvri\`, copy `.env.example` menjadi `.env`, lalu isi:

```dotenv
DB_HOST=127.0.0.1
DB_USER=tvri
DB_PASSWORD=GANTI_PASSWORD_DB
DB_NAME=db_ticketing
```

Catatan penting:
- File `config/koneksi.php` membaca `DB_PASSWORD`.
- Pastikan tidak ada spasi aneh setelah nilai.

### 8.2 Konfigurasi email (opsional)
Jika email tidak dipakai di internal, nonaktifkan di konfigurasi aplikasi.

Untuk `ticketing_tvri`:
- Edit `config/email_config.php`
- Set `SMTP_ENABLED` menjadi `false` jika tidak ada SMTP server internal.

Jika email dipakai:
- Isi `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, dan `SMTP_FROM_EMAIL` sesuai SMTP internal TVRI.

### 8.3 (Opsional) Set base URL untuk link absolut
Kalau ada project lain yang butuh base URL (mis. untuk link email), pastikan dia menggunakan:
- Domain + port yang benar (contoh: `http://ticketing.tvri.internal:10000`).

---

## 9) Proteksi file sensitif (.env) dan hardening minimal

### 9.1 Blok akses file `.env` via Apache
Di root tiap project, buat `.htaccess` (jika belum ada) dan pastikan `AllowOverride All` sudah aktif pada VirtualHost.

Contoh `.htaccess` yang aman untuk blok dotfiles:

```apache
# Block .env and other dotfiles
<FilesMatch "^\\.">
    Require all denied
</FilesMatch>

# Disable directory listing
Options -Indexes
```

> Jika suatu saat Anda butuh `/.well-known/` (untuk TLS/ACME), jangan blok semuanya. Saat itu Anda bisa refine rule.

### 9.2 Ubah password default aplikasi
Khusus `ticketing_tvri`, akun default ada di README, contoh `admin / 123456`.
- Wajib ganti password setelah deploy.
- Hindari men-deploy `scripts/reset_passwords.php` ke produksi, atau blok akses folder `scripts/`.

---

## 10) Verifikasi end-to-end (checklist)

Untuk tiap project lakukan:
1. Akses dari client:
   - Project1: `http://ticketing.tvri.internal:10000/`
   - Project2: `http://app2.tvri.internal:10001/`
   - Project3: `http://app3.tvri.internal:10002/`
2. Test koneksi DB (login/fitur utama)
3. Test upload (jika ada)
4. Cek log Apache:
   - `C:\xampp\apache\logs\error.log`
   - atau log custom yang Anda set di vhost (mis. `ticketing_error.log`)

Khusus `ticketing_tvri`:
- Test role Admin/User/Teknisi
- Test pembuatan tiket + upload
- Test assign tiket
- Test update status + upload bukti
- Test generate PDF (`cetak.php`)

---

## 11) Backup & maintenance (minimum yang disarankan)

### 11.1 Backup database (harian)
Gunakan `mysqldump` terjadwal (Task Scheduler), contoh output ke `D:\WebData\backups\db\`.

### 11.2 Backup folder upload
Backup `uploads/` di tiap project, karena ini data operasional.

### 11.3 Catat inventaris
Simpan dokumen internal berisi:
- Domain, port, path DocumentRoot
- Nama DB dan user DB
- Jadwal backup dan lokasi backup

---

## 12) Troubleshooting cepat

### 12.1 `ERR_CONNECTION_REFUSED`
- Apache belum listen port itu, atau firewall belum membuka port.
- Cek `httpd.conf` (Listen) dan firewall rules.

### 12.2 HTTP 403 / tidak bisa akses folder
- Pastikan di vhost ada:
  - `Require all granted`
  - `AllowOverride All` (jika Anda pakai `.htaccess`)

### 12.3 Upload gagal
- Cek permission folder `uploads/`
- Cek `php.ini`: `upload_max_filesize` dan `post_max_size`

### 12.4 Koneksi DB gagal
- Pastikan `.env` terisi benar
- Pastikan user DB punya grant ke database
- Pastikan key password: `DB_PASSWORD`

---

## Lampiran A: Template isian untuk 3 project

Isi tabel ini sebelum go-live:

| Project | Folder | Domain | Port | DB Name | DB User |
|---|---|---|---:|---|---|
| Project 1 | `D:\WebApps\ticketing_tvri` | `ticketing.tvri.internal` | 10000 | `db_ticketing` | `tvri` |
| Project 2 | `D:\WebApps\project2` | `app2.tvri.internal` | 10001 | `db_project2` | `app_project2` |
| Project 3 | `D:\WebApps\project3` | `app3.tvri.internal` | 10002 | `db_project3` | `app_project3` |
