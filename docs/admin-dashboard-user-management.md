# Admin Dashboard User Management

Dokumentasi ini menjelaskan implementasi backend User Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar user admin
- mengambil detail user admin
- membuat user admin
- mengubah user admin
- menghapus user admin
- mengambil daftar role untuk assignment user

Entity user memakai:

- primary key internal database: `id` bigint
- identifier API: `uuid`

Kolom utama tabel:

- `users`: `id`, `uuid`, `name`, `email`, `password`, `status`, `remember_token`, `created_at`, `updated_at`, `deleted_at`

## Middleware dan Akses

Route modul ini berada di prefix:

```text
/api/admin-dashboard
```

Middleware route group:

- `throttle:60,1`
- `auth_or_api`

Permission per endpoint:

- `GET /users` -> `read_admin_users`
- `GET /users/{id}` -> `read_admin_users`
- `POST /users` -> `create_admin_users`
- `PUT /users/{id}` -> `update_admin_users`
- `DELETE /users/{id}` -> `delete_admin_users`
- `GET /user-roles` -> `read_admin_users`

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

## 1. List Users

### Request

```http
GET /api/admin-dashboard/users
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan `name` atau `email` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `10000` |
| `status` | string | no | `active` atau `inactive` |
| `role` | string | no | Nama role exact match |
| `sort_by` | string | no | `name`, `email`, `created_at`, `updated_at` |
| `sort_direction` | string | no | `asc` atau `desc` |

### Contoh Request

```http
GET /api/admin-dashboard/users?page=1&per_page=10&search=admin&status=active&role=super_admin&sort_by=updated_at&sort_direction=desc
```

### Response

```json
{
  "success": true,
  "message": "Users fetched successfully",
  "data": {
    "summary": {
      "total_user": 6,
      "role_aktif": 5,
      "user_terbaru": {
        "id": "uuid",
        "name": "Super Admin",
        "email": "superadmin@side.com",
        "status": "active",
        "roles": [
          "super_admin"
        ],
        "created_at": "2026-04-13T10:00:00Z",
        "updated_at": "2026-04-13T10:00:00Z"
      }
    },
    "items": [
      {
        "id": "uuid",
        "name": "Super Admin",
        "email": "superadmin@side.com",
        "status": "active",
        "roles": [
          "super_admin"
        ],
        "created_at": "2026-04-13T10:00:00Z",
        "updated_at": "2026-04-13T10:00:00Z"
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 10,
      "total": 1,
      "last_page": 1,
      "sort_by": "updated_at",
      "sort_direction": "desc"
    }
  }
}
```

### Summary Fields

- `total_user`: total user aktif non-soft-deleted
- `role_aktif`: total role unik yang sedang terpakai oleh user aktif
- `user_terbaru`: user terbaru berdasarkan `created_at`

## 2. Detail User

### Request

```http
GET /api/admin-dashboard/users/{uuid}
```

### Response

```json
{
  "success": true,
  "message": "User fetched successfully",
  "data": {
    "id": "uuid",
    "name": "Super Admin",
    "email": "superadmin@side.com",
    "status": "active",
    "roles": [
      "super_admin"
    ],
    "created_at": "2026-04-13T10:00:00Z",
    "updated_at": "2026-04-13T10:00:00Z"
  }
}
```

### Error Not Found

```json
{
  "success": false,
  "message": "User not found",
  "data": null
}
```

## 3. Create User

### Request

```http
POST /api/admin-dashboard/users
Content-Type: application/json
```

### Body

```json
{
  "name": "Operator Internal",
  "email": "operator@company.com",
  "password": "password123",
  "password_confirmation": "password123",
  "status": "active",
  "roles": [
    "admin"
  ]
}
```

### Validasi

| Field | Rules |
|---|---|
| `name` | required, string, min:3, max:255 |
| `email` | required, email, unique:`users.email` |
| `password` | required, string, min:8, confirmed |
| `status` | required, enum:`active,inactive` |
| `roles` | required, array, min:1 |
| `roles.*` | string, distinct, harus ada di tabel `roles.name` dengan `guard_name = web` |

### Response

```json
{
  "success": true,
  "message": "User created successfully",
  "data": {
    "id": "uuid",
    "name": "Operator Internal",
    "email": "operator@company.com",
    "status": "active",
    "roles": [
      "admin"
    ],
    "created_at": "2026-04-13T10:00:00Z",
    "updated_at": "2026-04-13T10:00:00Z"
  }
}
```

## 4. Update User

### Request

```http
PUT /api/admin-dashboard/users/{uuid}
Content-Type: application/json
```

### Body

```json
{
  "name": "Operator Regional",
  "email": "operator.regional@company.com",
  "status": "active",
  "roles": [
    "admin",
    "user"
  ]
}
```

### Catatan

- `password` optional saat update
- jika dikirim, harus `confirmed` dan minimal `8`

### Response

```json
{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "id": "uuid",
    "name": "Operator Regional",
    "email": "operator.regional@company.com",
    "status": "active",
    "roles": [
      "admin",
      "user"
    ],
    "created_at": "2026-04-13T10:00:00Z",
    "updated_at": "2026-04-13T10:20:00Z"
  }
}
```

## 5. Delete User

### Request

```http
DELETE /api/admin-dashboard/users/{uuid}
```

### Response

```json
{
  "success": true,
  "message": "User deleted successfully",
  "data": {
    "id": "uuid"
  }
}
```

### Catatan

- delete user menggunakan soft delete
- user yang sudah dihapus tidak muncul di list dan detail

## 6. Get User Roles Helper

### Request

```http
GET /api/admin-dashboard/user-roles
```

### Response

```json
{
  "success": true,
  "message": "Roles fetched successfully",
  "data": {
    "items": [
      {
        "id": "uuid",
        "name": "super_admin",
        "slug": "super_admin",
        "description": null
      }
    ]
  }
}
```

### Catatan

- endpoint ini mengembalikan semua role
- tidak dibatasi pagination
- digunakan untuk helper assignment role pada form user

## Seeder dan Migration

Struktur saat ini disiapkan untuk:

```bash
php artisan migrate:fresh --seed
```

Yang sudah masuk migration create table users:

- `users.uuid`
- `users.status`
- `users.deleted_at`

Seeder yang terkait:

- [PermissionSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/PermissionSeeder.php)
- [RoleTableSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/RoleTableSeeder.php)
- [UserTableSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/UserTableSeeder.php)

## Implementasi Teknis

Layer yang dipakai:

- request: [UserManagementRequest.php](/c:/laragon/www/SIDE_BE/app/Http/Requests/AdminDashboard/UserManagementRequest.php)
- controller: [UserManagementController.php](/c:/laragon/www/SIDE_BE/app/Http/Controllers/Api/AdminDashboard/UserManagementController.php)
- service: [UserManagementService.php](/c:/laragon/www/SIDE_BE/app/Services/AdminDashboard/UserManagementService.php)
- repository: [UserManagementRepository.php](/c:/laragon/www/SIDE_BE/app/Repositories/AdminDashboard/UserManagement/UserManagementRepository.php)
