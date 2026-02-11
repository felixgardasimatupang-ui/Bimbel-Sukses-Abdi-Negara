# Panduan Setup Bimbel Abdi Negara

## 📋 Daftar Isi
1. [Persyaratan Sistem](#persyaratan-sistem)
2. [Instalasi Database](#instalasi-database)
3. [Konfigurasi](#konfigurasi)
4. [Akses dari Perangkat Lain](#akses-dari-perangkat-lain)
5. [Halaman Admin](#halaman-admin)

---

## Persyaratan Sistem

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau MariaDB 10.2+
- Apache dengan mod_rewrite (atau Nginx)
- XAMPP / WAMP / Laragon

---

## Instalasi Database

### Langkah 1: Buka phpMyAdmin
1. Buka browser dan akses `http://localhost/phpmyadmin`
2. Login dengan user: `root`, password: (kosong)

### Langkah 2: Import Database
1. Klik "New" untuk membuat database baru
2. Nama database: `bimbel_abdi_negara`
3. Collation: `utf8mb4_unicode_ci`
4. Klik tab "Import"
5. Pilih file `database/setup.sql`
6. Klik "Go" untuk import

### Atau Jalankan SQL Query
Copas isi file `database/setup.sql` ke tab SQL dan klik "Go"

---

## Konfigurasi

### 1. Edit Database Connection

Edit file `includes/db.php` jika perlu:

```php
private $host = 'localhost';
private $dbname = 'bimbel_abdi_negara';
private $username = 'root';
private $password = ''; // Sesuaikan dengan password MySQL Anda
```

### 2. Edit Email Configuration

Edit file `process-form.php` untuk mengubah email tujuan:

```php
$to = 'info@bimbelabdinegara.com'; // Email Anda
```

---

## Akses dari Perangkat Lain

### 🔗 Cara 1: Menggunakan IP Lokal (LAN)

#### Windows:
1. Buka Command Prompt
2. Ketik `ipconfig`
3. Lihat IPv4 Address (contoh: `192.168.1.100`)

#### Di perangkat lain (HP/Laptop):
1. Buka browser
2. Akses `http://IP_ANDA:8000`
   Contoh: `http://192.168.1.100:8000`

### 🔗 Cara 2: Port Forwarding (Untuk akses internet)

#### Di Router:
1. Login ke router (biasanya `192.168.1.1`)
2. Forward port 80/443 ke IP komputer Anda
3. Jika menggunakan XAMPP, port default Apache adalah 80

### 🔗 Cara 3: Ngrok (Untuk testing publik)

1. Download Ngrok dari https://ngrok.com
2. Jalankan:
```bash
ngrok http 80
```
3. Gunakan URL yang diberikan Ngrok

### 🔗 Cara 4: LocalTunnel

```bash
npx localtunnel --port 8000
```

---

## Halaman Admin

### Akses Admin
URL: `http://localhost:8000/admin/`

### Login Admin
- **Username:** `admin`
- **Password:** `admin123`

> ⚠️ **Ganti password default segera setelah setup!**

### Fitur Admin
- Dashboard dengan statistik
- Lihat pendaftaran terbaru
- Filter berdasarkan status
- Export data (fitur tambahan)

---

## Struktur Folder

```
bimbel-abdi-negara/
├── admin/
│   └── index.php          # Halaman admin
├── assets/
│   ├── css/               # Stylesheet
│   ├── js/                # JavaScript
│   ├── sass/              # SCSS
│   └── webfonts/          # Font Awesome
├── database/
│   └── setup.sql          # Skrip database
├── images/                # Gambar website
├── includes/
│   └── db.php             # Koneksi database
├── .htaccess              # Aturan Apache
├── index.html             # Halaman utama
├── process-form.php       # Form handler
├── security.php           # Keamanan
├── security.txt           # Security disclosure
└── SETUP-GUIDE.md         # Panduan ini
```

---

## Troubleshooting

### ❌ "Access Denied" ke Database
- Pastikan MySQL sudah berjalan
- Cek username/password di `includes/db.php`
- Buat user baru di MySQL:
```sql
CREATE USER 'bimbel'@'localhost' IDENTIFIED BY 'password123';
GRANT ALL PRIVILEGES ON bimbel_abdi_negara.* TO 'bimbel'@'localhost';
FLUSH PRIVILEGES;
```

### ❌ Tidak bisa akses dari HP
- Pastikan HP dan Laptop terhubung ke WiFi yang sama
- Disable firewall untuk testing:
```bash
# Windows
netsh advfirewall set allprofiles state off
```

### ❌ Port 8000 sudah terpakai
- Ganti port di XAMPP atau jalankan PHP lain port:
```bash
php -S localhost:8080
```

### ❌ Email tidak terkirim
- Pastikan SMTP server sudah diatur di `php.ini`
- Untuk testing, cek folder spam
- Gunakan library seperti PHPMailer untuk produksi

---

## Keamanan Production

1. **Ganti password database**
2. **Ganti password admin**
3. **Aktifkan HTTPS dengan SSL**
4. **Update `.htaccess` untuk production**
5. **Hapus file `setup.sql`** setelah instalasi
6. **Atur proper file permissions**

---

## Kontak Dukungan

- Email: info@bimbelabdinegara.com
- WhatsApp: +6281234567890

---

**© 2024 Bimbel Abdi Negara**
