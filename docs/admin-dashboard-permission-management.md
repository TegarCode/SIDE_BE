# Admin Dashboard Permission Management

Dokumentasi ini menjelaskan implementasi backend Permission Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar permission admin
- mengambil detail permission admin
- membuat permission admin
- mengubah permission admin
- menghapus permission admin

Entity permission memakai:

- primary key internal database: `id` bigint
- identifier API: `uuid`

Kolom utama tabel:

- `permissions`: `id`, `uuid`, `name`, `category`, `description`, `guard_name`, `created_at`, `updated_at`

## Middleware dan Akses

Route modul ini berada di prefix:

```text
/api/admin-dashboard
```

Middleware route group:

- `throttle:60,1`
- `auth_or_api`

Permission per endpoint:

- `GET /permissions` -> `read_admin_permissions`
- `GET /permissions/{id}` -> `read_admin_permissions`
- `POST /permissions` -> `create_admin_permissions`
- `PUT /permissions/{id}` -> `update_admin_permissions`
- `DELETE /permissions/{id}` -> `delete_admin_permissions`

## Base Response Format

Semua endpoint memakai struktur dasar berikut:

```json
{
  "success": true,
  "message": "Message text",
  "data": {}
}
```

Untuk error yang ditangani controller:

```json
{
  "success": false,
  "message": "Error message",
  "data": null
}
```

## 1. List Permissions

### Request

```http
GET /api/admin-dashboard/permissions
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan `name`, `category`, atau `description` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `10000` |
| `category` | string | no | Filter category exact match |
| `sort_by` | string | no | `name`, `category`, `created_at`, `updated_at` |
| `sort_direction` | string | no | `asc` atau `desc` |

### Contoh Request

```http
GET /api/admin-dashboard/permissions?page=1&per_page=10&search=admin&category=Modul Admin&sort_by=updated_at&sort_direction=desc
```

### Response

```json
{
  "success": true,
  "message": "Permissions fetched successfully",
  "data": {
    "summary": {
      "total_permission": 50,
      "kategori_aktif": 14,
      "permission_terbaru": {
        "id": "uuid",
        "name": "delete_admin_permissions",
        "category": "Modul Permission Admin",
        "description": null,
        "created_at": "2026-04-09T10:00:00Z",
        "updated_at": "2026-04-09T10:00:00Z"
      }
    },
    "items": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "read_admin_roles",
        "category": "Modul Role Admin",
        "description": "Melihat daftar dan detail role admin.",
        "created_at": "2026-04-09T10:00:00Z",
        "updated_at": "2026-04-09T10:00:00Z"
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

- `total_permission`: total permission `guard_name = web`
- `kategori_aktif`: total category unik yang terpakai
- `permission_terbaru`: permission terbaru berdasarkan `created_at`

### Sorting

Sorting table didukung lewat query param:

- `sort_by=name`
- `sort_by=category`
- `sort_by=created_at`
- `sort_by=updated_at`
- `sort_direction=asc|desc`

### Validation Error for Query Params

Jika query param tidak valid, backend mengirim semua error validasi:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "page": [
      "The page field must be at least 1."
    ],
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

## 2. Detail Permission

### Request

```http
GET /api/admin-dashboard/permissions/{uuid}
```

### Response

```json
{
  "success": true,
  "message": "Permission fetched successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "read_admin_roles",
    "category": "Modul Role Admin",
    "description": "Melihat daftar dan detail role admin.",
    "created_at": "2026-04-09T10:00:00Z",
    "updated_at": "2026-04-09T10:00:00Z"
  }
}
```

### Error Not Found

```json
{
  "success": false,
  "message": "Permission not found",
  "data": null
}
```

## 3. Create Permission

### Request

```http
POST /api/admin-dashboard/permissions
Content-Type: application/json
```

### Body

```json
{
  "name": "approve_admin_permissions",
  "category": "Modul Permission Admin",
  "description": "Menyetujui perubahan permission admin."
}
```

### Validasi

| Field | Rules |
|---|---|
| `name` | required, string, min:3, max:255, unique:`permissions.name` |
| `category` | required, string, max:255 |
| `description` | nullable, string |

### Response

```json
{
  "success": true,
  "message": "Permission created successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440001",
    "name": "approve_admin_permissions",
    "category": "Modul Permission Admin",
    "description": "Menyetujui perubahan permission admin.",
    "created_at": "2026-04-09T10:00:00Z",
    "updated_at": "2026-04-09T10:00:00Z"
  }
}
```

## 4. Update Permission

### Request

```http
PUT /api/admin-dashboard/permissions/{uuid}
Content-Type: application/json
```

### Body

```json
{
  "name": "approve_admin_permissions",
  "category": "Modul Permission Admin",
  "description": "Menyetujui dan mengubah permission admin."
}
```

### Response

```json
{
  "success": true,
  "message": "Permission updated successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440001",
    "name": "approve_admin_permissions",
    "category": "Modul Permission Admin",
    "description": "Menyetujui dan mengubah permission admin.",
    "created_at": "2026-04-09T10:00:00Z",
    "updated_at": "2026-04-09T10:20:00Z"
  }
}
```

### Error Not Found

```json
{
  "success": false,
  "message": "Permission not found",
  "data": null
}
```

## 5. Delete Permission

### Request

```http
DELETE /api/admin-dashboard/permissions/{uuid}
```

### Response

```json
{
  "success": true,
  "message": "Permission deleted successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440001"
  }
}
```

### Catatan Delete

- delete permission tidak diblok walaupun permission masih dipakai role
- relasi pada tabel `role_has_permissions` ikut terhapus melalui foreign key cascade Spatie

### Error Not Found

```json
{
  "success": false,
  "message": "Permission not found",
  "data": null
}
```

## Seeder dan Migration

Struktur saat ini disiapkan untuk:

```bash
php artisan migrate:fresh --seed
```

Yang sudah masuk migration create table:

- `permissions.uuid`
- `permissions.category`
- `permissions.description`

Seeder yang terkait:

- [PermissionSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/PermissionSeeder.php)
- [RoleTableSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/RoleTableSeeder.php)

## Implementasi Teknis

Layer yang dipakai:

- request: [PermissionManagementRequest.php](/c:/laragon/www/SIDE_BE/app/Http/Requests/AdminDashboard/PermissionManagementRequest.php)
- controller: [PermissionManagementController.php](/c:/laragon/www/SIDE_BE/app/Http/Controllers/Api/AdminDashboard/PermissionManagementController.php)
- service: [PermissionManagementService.php](/c:/laragon/www/SIDE_BE/app/Services/AdminDashboard/PermissionManagementService.php)
- repository: [PermissionManagementRepository.php](/c:/laragon/www/SIDE_BE/app/Repositories/AdminDashboard/PermissionManagement/PermissionManagementRepository.php)

Catatan implementasi:

- lookup detail, update, dan delete menggunakan `permissions.uuid`
- create/update permission selalu `guard_name = web`
- list permission diurutkan berdasarkan `category`, lalu `name`
- timestamp response dikonversi ke UTC format `Y-m-d\\TH:i:s\\Z`
- endpoint helper untuk form role tersedia di `GET /api/admin-dashboard/role-permissions`
