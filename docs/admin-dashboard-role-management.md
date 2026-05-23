# Admin Dashboard Role Management

Dokumentasi ini menjelaskan implementasi backend Role Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar role admin
- mengambil detail role admin
- membuat role admin
- mengubah role admin
- menghapus role admin
- mengambil daftar permission yang tersedia

Entity role dan permission memakai:

- primary key internal database: `id` bigint
- identifier API: `uuid`

Kolom utama tabel:

- `roles`: `id`, `uuid`, `name`, `slug`, `description`, `status`, `guard_name`, `created_at`, `updated_at`
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

- `GET /roles` -> `read_admin_roles`
- `GET /roles/{id}` -> `read_admin_roles`
- `POST /roles` -> `create_admin_roles`
- `PUT /roles/{id}` -> `update_admin_roles`
- `DELETE /roles/{id}` -> `delete_admin_roles`
- `GET /role-permissions` -> `read_admin_roles`

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

## 1. List Roles

### Request

```http
GET /api/admin-dashboard/roles
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan `name`, `slug`, atau `description` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `100` |
| `status` | string | no | Hanya `active` atau `inactive` |

### Contoh Request

```http
GET /api/admin-dashboard/roles?page=1&per_page=10&search=admin&status=active
```

### Response

```json
{
  "success": true,
  "message": "Roles fetched successfully",
  "data": {
    "items": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Super Admin",
        "slug": "super_admin",
        "description": "Role tertinggi untuk mengelola akses.",
        "status": "active",
        "user_count": 1,
        "permissions_count": 46,
        "permissions": [
          "create_admin_roles",
          "delete_admin_roles",
          "read_admin_roles",
          "update_admin_roles"
        ],
        "created_at": "2026-04-09T01:00:00Z",
        "updated_at": "2026-04-09T01:00:00Z"
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 10,
      "total": 1,
      "last_page": 1
    }
  }
}
```

### Catatan

- field `id` adalah UUID role
- `permissions` berisi array nama permission
- `user_count` dihitung dari tabel `model_has_roles` untuk model `App\Models\User`

## 2. Detail Role

### Request

```http
GET /api/admin-dashboard/roles/{uuid}
```

### Path Param

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `id` | uuid | yes | UUID role |

### Response

```json
{
  "success": true,
  "message": "Role fetched successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Super Admin",
    "slug": "super_admin",
    "description": "Role tertinggi untuk mengelola akses.",
    "status": "active",
    "user_count": 1,
    "permissions_count": 46,
    "permissions": [
      "create_admin_roles",
      "delete_admin_roles",
      "read_admin_roles",
      "update_admin_roles"
    ],
    "created_at": "2026-04-09T01:00:00Z",
    "updated_at": "2026-04-09T01:00:00Z"
  }
}
```

### Error Not Found

```json
{
  "success": false,
  "message": "Role not found",
  "data": null
}
```

## 3. Create Role

### Request

```http
POST /api/admin-dashboard/roles
Content-Type: application/json
```

### Body

```json
{
  "name": "Operator Internal",
  "slug": "operator_internal",
  "description": "Role untuk operasional harian.",
  "status": "active",
  "permissions": [
    "read_admin_roles",
    "update_admin_roles"
  ]
}
```

### Validasi

| Field | Rules |
|---|---|
| `name` | required, string, min:3, max:255, unique:`roles.name` |
| `slug` | required, string, max:255, unique:`roles.slug` |
| `description` | nullable, string |
| `status` | required, enum:`active,inactive` |
| `permissions` | required, array |
| `permissions.*` | string, distinct, harus ada di tabel `permissions.name` dengan `guard_name = web` |

### Response

```json
{
  "success": true,
  "message": "Role created successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440001",
    "name": "Operator Internal",
    "slug": "operator_internal",
    "description": "Role untuk operasional harian.",
    "status": "active",
    "user_count": 0,
    "permissions_count": 2,
    "permissions": [
      "read_admin_roles",
      "update_admin_roles"
    ],
    "created_at": "2026-04-09T02:00:00Z",
    "updated_at": "2026-04-09T02:00:00Z"
  }
}
```

## 4. Update Role

### Request

```http
PUT /api/admin-dashboard/roles/{uuid}
Content-Type: application/json
```

### Body

```json
{
  "name": "Operator Regional",
  "slug": "operator_regional",
  "description": "Role untuk operasional regional.",
  "status": "inactive",
  "permissions": [
    "read_admin_roles",
    "update_admin_roles"
  ]
}
```

### Validasi

Sama seperti create, tetapi `name` dan `slug` di-ignore untuk role yang sedang diupdate berdasarkan UUID path param.

### Response

```json
{
  "success": true,
  "message": "Role updated successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440001",
    "name": "Operator Regional",
    "slug": "operator_regional",
    "description": "Role untuk operasional regional.",
    "status": "inactive",
    "user_count": 0,
    "permissions_count": 2,
    "permissions": [
      "read_admin_roles",
      "update_admin_roles"
    ],
    "created_at": "2026-04-09T02:00:00Z",
    "updated_at": "2026-04-09T02:15:00Z"
  }
}
```

### Error Not Found

```json
{
  "success": false,
  "message": "Role not found",
  "data": null
}
```

## 5. Delete Role

### Request

```http
DELETE /api/admin-dashboard/roles/{uuid}
```

### Response Sukses

```json
{
  "success": true,
  "message": "Role deleted successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440001"
  }
}
```

### Error Role Dipakai User

```json
{
  "success": false,
  "message": "Role cannot be deleted because it is assigned to users.",
  "data": null
}
```

HTTP status: `422 Unprocessable Entity`

### Error Not Found

```json
{
  "success": false,
  "message": "Role not found",
  "data": null
}
```

HTTP status: `404 Not Found`

## 6. List Available Permissions for Role Form

### Request

```http
GET /api/admin-dashboard/role-permissions
```

### Response

```json
{
  "success": true,
  "message": "Permissions fetched successfully",
  "data": {
    "items": [
      {
        "id": "d87b5cc7-52b9-437a-b246-3d7b9fceab9a",
        "name": "view_diplomasi_ekonomi_indonesia",
        "category": "Modul Indonesia",
        "description": "Untuk menampilkan diplomasi ekonomi Indonesia"
      },
      {
        "id": "f28a1c97-4df0-4ff2-88e5-48514d55d5d5",
        "name": "read_admin_roles",
        "category": "Modul Role Admin",
        "description": null
      }
    ]
  }
}
```

### Catatan

- field `id` adalah UUID permission
- source data langsung dari tabel `permissions`
- response diurutkan berdasarkan `category`, lalu `name`
- endpoint ini dipakai sebagai helper untuk form create/update role

## Daftar Permission Saat Ini

### Modul Indonesia

- `view_diplomasi_ekonomi_indonesia`
- `view_kerjasama_bilateral_indonesia`
- `view_kinerja_ekonomi_indonesia`
- `view_infrastruktur_indonesia`

### Modul Mitra

- `view_overview_negara_mitra`
- `view_perdagangan_negara_mitra`
- `view_investasi_negara_mitra`
- `view_pariwisata_negara_mitra`
- `view_jasa_negara_mitra`

### Modul Analisis

- `view_produk_komoditas_analisis`
- `view_potensi_daya_saing_analisis`
- `view_ide_analisis`
- `view_operational_risk_analisis`
- `view_geopolitik_perdagangan_analisis`

### Modul Komoditas Utama

- `view_arus_tik_prioritas`
- `view_energi_prioritas`
- `view_mineral_kritis_prioritas`
- `view_farmasi_prioritas`
- `view_hilirisasi_prioritas`
- `view_pangan_prioritas`
- `view_pertahanan_prioritas`

### Modul Data Generator

- `view_perdagangan_data_generator`
- `view_turis_data_generator`
- `view_investasi_data_generator`
- `view_jasa_data_generator`
- `view_indikator_ekonomi_data_generator`

### Modul Report Generator

- `view_rca_cmsa_report_generator`
- `view_market_share_report_generator`
- `view_kerjasama_perdagangan_report_generator`

### Modul Parekraf

- `view_sektor_parekraf`
- `view_peta_persebaran_wisman`
- `view_peta_perjalanan_transportasi`
- `view_analisis_daya_saing_pariwisata_provinsi`
- `view_indeks_daya_saing_pariwisata_negara`
- `view_segmentasi_frekuensi_moneter`

### Modul Jasa

- `view_sektor_jasa`
- `view_data_pekerja_migran_indonesia`
- `view_insight_pasar_jasa`

### Modul Perdagangan

- `view_sektor_perdagangan`

### Modul Investasi

- `view_sektor_investasi`

### Modul KSPI

- `view_sektor_kspi`

### Modul Admin

- `view_admin_dashboard`

### Modul Role Admin

- `read_admin_roles`
- `create_admin_roles`
- `update_admin_roles`
- `delete_admin_roles`

### Modul Permission Admin

- `read_admin_permissions`
- `create_admin_permissions`
- `update_admin_permissions`
- `delete_admin_permissions`

### Modul User Admin

- `read_admin_users`
- `create_admin_users`
- `update_admin_users`
- `delete_admin_users`

## Seeder dan Migration

Struktur saat ini disiapkan untuk:

```bash
php artisan migrate:fresh --seed
```

Yang sudah masuk migration create table:

- `roles.uuid`
- `roles.slug`
- `roles.description`
- `roles.status`
- `permissions.uuid`
- `permissions.category`
- `permissions.description`
- `faq_topics.uuid`
- `faq_items.uuid`

Seeder yang terkait:

- [PermissionSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/PermissionSeeder.php)
- [RoleTableSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/RoleTableSeeder.php)
- [UserTableSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/UserTableSeeder.php)
- [FaqSeeder.php](/c:/laragon/www/SIDE_BE/database/seeders/FaqSeeder.php)

## Implementasi Teknis

Layer yang dipakai:

- request: [RoleManagementRequest.php](/c:/laragon/www/SIDE_BE/app/Http/Requests/AdminDashboard/RoleManagementRequest.php)
- controller: [RoleManagementController.php](/c:/laragon/www/SIDE_BE/app/Http/Controllers/Api/AdminDashboard/RoleManagementController.php)
- service: [RoleManagementService.php](/c:/laragon/www/SIDE_BE/app/Services/AdminDashboard/RoleManagementService.php)
- repository: [RoleManagementRepository.php](/c:/laragon/www/SIDE_BE/app/Repositories/AdminDashboard/RoleManagement/RoleManagementRepository.php)

Catatan implementasi:

- lookup role detail, update, dan delete menggunakan `roles.uuid`
- create/update role selalu `guard_name = web`
- available permissions diambil dari tabel `permissions` dengan filter `guard_name = web`
- delete role diblok kalau `users_count > 0`
- timestamp response dikonversi ke UTC format `Y-m-d\\TH:i:s\\Z`

## Contoh Integrasi FE

Alur FE yang disarankan:

1. Panggil `GET /api/admin-dashboard/role-permissions`
2. Simpan `items[].name` sebagai value checkbox permission
3. Saat create/update role, kirim `permissions` sebagai array string nama permission
4. Simpan `items[].id` dari role list sebagai UUID untuk detail/update/delete

Contoh payload submit dari FE:

```json
{
  "name": "Finance Reviewer",
  "slug": "finance_reviewer",
  "description": "Role untuk review data finance.",
  "status": "active",
  "permissions": [
    "view_admin_dashboard",
    "read_admin_roles"
  ]
}
```
