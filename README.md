# SIDE Backend

Backend Laravel untuk aplikasi `SIDE` (`Sistem Informasi Diplomasi Ekonomi`). Repo ini menangani autentikasi, otorisasi, endpoint master data, modul analisis, data generator, report generator, contact form, FAQ, tutorial playlist, dan beberapa area admin dashboard.

README ini ditulis untuk versi portfolio/sanitized copy, jadi beberapa branding, kontak, dan aset institusi sudah dinetralkan tanpa mengubah struktur teknis utama aplikasi.

## Fitur Utama

- Autentikasi login, logout, session user, dan endpoint `me`
- Otorisasi berbasis permission dengan `spatie/laravel-permission`
- Proteksi akses gabungan:
  - user login `Sanctum`
  - atau API client melalui header `X-API-KEY`
- Modul analisis:
  - RCA-CMSA
  - RSCA-TBI
  - RCA-EPD
  - Komoditas ekspor utama
  - Geopolitik perdagangan
  - Operational risk
- Modul negara mitra:
  - overview
  - perdagangan
  - investasi
  - pariwisata
  - jasa
- Modul Indonesia:
  - diplomasi ekonomi
  - kerja sama bilateral
  - infrastruktur
  - indikator ekonomi
- Sektor prioritas:
  - ekonomi digital
  - energi
  - farmasi
  - hilirisasi
  - mineral kritis
  - pangan
  - pertahanan
- Data generator:
  - perdagangan
  - investasi
  - jasa
  - pariwisata
  - indikator ekonomi
- Report generator:
  - RCA-CMSA
  - market share
  - kerja sama perdagangan
- Admin dashboard:
  - user, role, permission
  - API client
  - cache
  - contact
  - FAQ
  - authentication log
  - side page views
  - tutorial playlist
  - manajemen data indikator ekonomi

## Stack

- `php` 8.2+
- `laravel` 12
- `laravel/sanctum`
- `spatie/laravel-permission`
- `rappasoft/laravel-authentication-log`
- `barryvdh/laravel-dompdf`
- `phpoffice/phpspreadsheet`
- `phpoffice/phpword`
- `vite`
- `tailwindcss` v4
- `axios`
- `phpunit`
- `laravel/pint`

## Pola Arsitektur

Project ini banyak memakai pemisahan layer:

- `Controller`: menerima request dan mengembalikan response API
- `Service`: memegang orchestration/business flow
- `Repository`: menangani query dan akses data
- `Request`: validasi input
- `Middleware`: auth, API client verification, CORS, access control

Di beberapa modul analisis, pola ini dipakai konsisten:

```text
Controller -> Service -> Repository
```

Struktur seperti ini memudahkan penambahan modul baru seperti `RSCA-TBI` dan `RCA-EPD` tanpa mengacak flow modul lain.

## Prasyarat

- PHP 8.2 atau lebih baru
- Composer 2
- Node.js 20 atau lebih baru
- npm 10 atau lebih baru
- MySQL/MariaDB atau SQLite untuk pengembangan lokal

## Instalasi

Install dependency backend:

```bash
composer install
```

Install dependency frontend asset/Vite:

```bash
npm install
```

Siapkan file environment:

```bash
copy .env.example .env
```

Generate app key:

```bash
php artisan key:generate
```

Jika memakai database lokal, sesuaikan `.env`, lalu jalankan migration:

```bash
php artisan migrate
```

Jika diperlukan, jalankan seeder:

```bash
php artisan db:seed
```

## Menjalankan Project

Menjalankan backend saja:

```bash
php artisan serve
```

Menjalankan Vite saja:

```bash
npm run dev
```

Menjalankan mode development lengkap dari Composer:

```bash
composer run dev
```

Script ini akan menjalankan:

- Laravel server
- queue listener
- log tail
- Vite dev server

## Environment

Project ini memakai `.env.example` sebagai acuan. Variabel yang paling penting biasanya:

```env
APP_NAME=SIDE
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Catatan:

- jangan commit file `.env`
- untuk portfolio copy ini, dump database produksi/internal tidak disertakan
- konfigurasi origin/domain di middleware sudah dinetralkan untuk copy repo pribadi

## Akses dan Proteksi API

Ada dua pola akses utama:

1. `auth:sanctum`
   Untuk endpoint yang membutuhkan user login.

2. `auth_or_api`
   Middleware gabungan untuk menerima:
   - user login lewat Sanctum
   - atau API client valid lewat `X-API-KEY`

Beberapa endpoint juga dibatasi lagi dengan permission:

```text
ability_or_permission:<permission_name>
```

Contohnya dipakai luas di:

- `routes/api.php`
- area `admin-dashboard`
- area `api/v1`

## Ringkasan Route

Route utama ada di [routes/api.php](C:/laragon/www/SIDE_BE/routes/api.php).

Kelompok besarnya:

- public:
  - `/captcha`
  - `/login`
  - `/contact`
  - `/tutorial-playlists`
  - `/faqs`
- authenticated utility:
  - `/me`
  - `/logout`
- admin dashboard:
  - `/api/admin-dashboard/...`
- API v1:
  - `/api/v1/negara`
  - `/api/v1/indonesia/...`
  - `/api/v1/negara-mitra/...`
  - `/api/v1/sektor-prioritas/...`
  - `/api/v1/analisis/...`
  - `/api/v1/data-generator/...`
  - `/api/v1/report-generator/...`

## Modul Analisis

Folder analisis utama ada di:

- [app/Repositories/Analisis](C:/laragon/www/SIDE_BE/app/Repositories/Analisis)
- [app/Services/Analisis](C:/laragon/www/SIDE_BE/app/Services/Analisis)
- [app/Http/Controllers/Api/V1/Analisis](C:/laragon/www/SIDE_BE/app/Http/Controllers/Api/V1/Analisis)

Modul yang sudah ada:

- `AnalisisRCACMSA`
- `AnalisisRSCATBI`
- `AnalisisRCAEPD`
- `EksporUtama`
- `GeopolitikPerdagangan`
- `OperationalRisk`

Untuk modul seperti `RSCA-TBI` dan `RCA-EPD`, backend ini sudah memisahkan endpoint utama, kalkulasi, dan comparison agar frontend bisa membangun UI tabular dan chart dengan normalizer yang konsisten.

## Report Generator

Report generator memanfaatkan beberapa format output:

- PDF dengan `barryvdh/laravel-dompdf`
- DOCX dengan `phpoffice/phpword`
- spreadsheet/export data dengan `phpoffice/phpspreadsheet`

Contoh implementasi ada di:

- [RCACMSAController.php](C:/laragon/www/SIDE_BE/app/Http/Controllers/Api/V1/ReportGenerator/RCACMSA/RCACMSAController.php)
- [resources/views/exports](C:/laragon/www/SIDE_BE/resources/views/exports)
- [resources/views/templates](C:/laragon/www/SIDE_BE/resources/views/templates)

## Script

Composer:

- `composer run dev`: jalankan server, queue, log, dan Vite
- `composer test`: clear config lalu jalankan test

Node:

- `npm run dev`: jalankan Vite
- `npm run build`: build asset frontend/backend integration

## Struktur Folder

```text
app/
  Helpers/
  Http/
    Controllers/
    Middleware/
    Requests/
  Jobs/
  Models/
  Providers/
  Repositories/
  Services/
  Support/
bootstrap/
config/
database/
docs/
public/
resources/
  css/
  js/
  templates/
  views/
routes/
storage/
tests/
```

Ringkasan:

- `app/Repositories`: query layer per modul
- `app/Services`: business/service layer
- `app/Http/Controllers`: endpoint entrypoint
- `app/Http/Requests`: validasi request
- `resources/views/exports`: template PDF/report
- `database/migrations`: skema database
- `database/seeders`: data awal pengembangan
- `docs`: dokumentasi teknis tambahan

## Pengujian dan Quality Check

Jalankan test:

```bash
composer test
```

Atau langsung:

```bash
php artisan test
```

Format/lint PHP dengan Pint:

```bash
./vendor/bin/pint
```

## Catatan Portfolio

- Repo ini adalah copy portfolio yang sudah disanitasi dari konfigurasi sensitif dasar
- file `.env` tidak disertakan
- dump database internal dihapus dari copy ini
- beberapa branding, email, domain, dan aset institusi telah dinetralkan
- masih ada istilah domain bisnis seperti `SIDE`, `diplomasi ekonomi`, `RCA`, `CMSA`, `RSCA`, `EPD`, karena itu bagian dari konteks teknis dan fungsional aplikasi

## Catatan Tambahan

- Jika ingin menjalankan modul secara penuh, pastikan frontend memakai base URL backend yang sesuai
- Untuk pengembangan lokal, cek juga file:
  - [config/app.php](C:/laragon/www/SIDE_BE/config/app.php)
  - [config/database.php](C:/laragon/www/SIDE_BE/config/database.php)
  - [config/permission.php](C:/laragon/www/SIDE_BE/config/permission.php)
  - [routes/api.php](C:/laragon/www/SIDE_BE/routes/api.php)
