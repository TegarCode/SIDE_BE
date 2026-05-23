# Admin Dashboard Authentication Log Management

Dokumentasi ini menjelaskan implementasi backend Authentication Log Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar authentication log
- mengambil detail authentication log
- menghapus authentication log

Catatan:

- tidak ada endpoint create manual dari admin dashboard
- log dibuat otomatis oleh sistem authentication

Catatan identifier:

- tabel `authentication_log` memakai primary key internal `id`
- API memakai `uuid` sebagai identifier

Kolom utama tabel:

- `authentication_log`: `id`, `uuid`, `authenticatable_type`, `authenticatable_id`, `ip_address`, `user_agent`, `login_at`, `login_successful`, `logout_at`, `cleared_by_user`, `location`

## Middleware dan Akses

Route modul ini berada di prefix:

```text
/api/admin-dashboard
```

Middleware route group:

- `throttle:60,1`
- `auth_or_api`

Permission per endpoint:

- `GET /authentication-logs` -> `read_admin_authentication_logs`
- `GET /authentication-logs/{id}` -> `read_admin_authentication_logs`
- `DELETE /authentication-logs/{id}` -> `delete_admin_authentication_logs`

## Base Response Format

Semua endpoint memakai struktur dasar berikut:

```json
{
  "success": true,
  "message": "Message text",
  "data": {}
}
```

Validation error:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "field": [
      "Validation message"
    ]
  },
  "data": null
}
```

## 1. List Authentication Logs

### Request

```http
GET /api/admin-dashboard/authentication-logs
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan `ip_address`, `user_agent`, `user.name`, atau `user.email` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `10000` |
| `login_successful` | boolean | no | Filter status login berhasil atau tidak |
| `sort_by` | string | no | `login_at` atau `logout_at` |
| `sort_direction` | string | no | `asc` atau `desc` |

### Contoh Request

```http
GET /api/admin-dashboard/authentication-logs?page=1&per_page=10&search=admin@login.com&login_successful=true&sort_by=login_at&sort_direction=desc
```

### Response

```json
{
  "success": true,
  "message": "Authentication logs fetched successfully",
  "data": {
    "summary": {
      "total_log": 120,
      "login_berhasil": 100,
      "log_terbaru": {
        "id": "uuid",
        "user": {
          "id": "uuid-user",
          "name": "Super Admin",
          "email": "superadmin@side.com"
        },
        "ip_address": "127.0.0.1",
        "user_agent": "Mozilla/5.0",
        "login_at": "2026-04-20T10:00:00Z",
        "login_successful": true,
        "logout_at": null,
        "location": null
      }
    },
    "items": [
      {
        "id": "uuid",
        "user": {
          "id": "uuid-user",
          "name": "Super Admin",
          "email": "superadmin@side.com"
        },
        "ip_address": "127.0.0.1",
        "user_agent": "Mozilla/5.0",
        "login_at": "2026-04-20T10:00:00Z",
        "login_successful": true,
        "logout_at": null,
        "location": null
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 10,
      "total": 120,
      "last_page": 12,
      "sort_by": "login_at",
      "sort_direction": "desc"
    }
  }
}
```

### Summary Fields

- `total_log`: total seluruh authentication log
- `login_berhasil`: total log dengan `login_successful = true`
- `log_terbaru`: log terbaru berdasarkan `login_at`

### Validation Error for Query Params

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "sort_by": [
      "The selected sort by is invalid."
    ],
    "sort_direction": [
      "The selected sort direction is invalid."
    ]
  },
  "data": null
}
```

## 2. Detail Authentication Log

### Request

```http
GET /api/admin-dashboard/authentication-logs/{uuid}
```

### Response

```json
{
  "success": true,
  "message": "Authentication log fetched successfully",
  "data": {
    "id": "uuid",
    "user": {
      "id": "uuid-user",
      "name": "Super Admin",
      "email": "superadmin@side.com"
    },
    "ip_address": "127.0.0.1",
    "user_agent": "Mozilla/5.0",
    "login_at": "2026-04-20T10:00:00Z",
    "login_successful": true,
    "logout_at": null,
    "location": null
  }
}
```

### Error Not Found

```json
{
  "success": false,
  "message": "Authentication log not found",
  "data": null
}
```

## 3. Delete Authentication Log

### Request

```http
DELETE /api/admin-dashboard/authentication-logs/{uuid}
```

### Response

```json
{
  "success": true,
  "message": "Authentication log deleted successfully",
  "data": {
    "id": "uuid"
  }
}
```

## Seeder dan Migration

Struktur saat ini disiapkan untuk:

```bash
php artisan migrate:fresh --seed
```

Yang sudah masuk migration create table:

- `authentication_log.uuid`

Seeder yang terkait:

- [PermissionSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/PermissionSeeder.php)
- [RoleTableSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/RoleTableSeeder.php)

## Implementasi Teknis

Layer yang dipakai:

- request: [AuthenticationLogManagementRequest.php](/c:/laragon/www/SIDE_BE/app/Http/Requests/AdminDashboard/AuthenticationLogManagementRequest.php)
- controller: [AuthenticationLogManagementController.php](/c:/laragon/www/SIDE_BE/app/Http/Controllers/Api/AdminDashboard/AuthenticationLogManagementController.php)
- service: [AuthenticationLogManagementService.php](/c:/laragon/www/SIDE_BE/app/Services/AdminDashboard/AuthenticationLogManagementService.php)
- repository: [AuthenticationLogManagementRepository.php](/c:/laragon/www/SIDE_BE/app/Repositories/AdminDashboard/AuthenticationLogManagement/AuthenticationLogManagementRepository.php)

Catatan implementasi:

- lookup detail dan delete menggunakan `authentication_log.uuid`
- create log tidak diekspos ke admin dashboard karena sumber data berasal dari activity login sistem
- field histori login seperti `login_at`, `logout_at`, `ip_address`, dan `user_agent` tidak diubah lewat endpoint admin
- UUID authentication log diisi otomatis lewat listener pada [AppServiceProvider.php](/c:/laragon/www/SIDE_BE/app/Providers/AppServiceProvider.php)
