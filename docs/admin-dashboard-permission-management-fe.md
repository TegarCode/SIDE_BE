# Permission Management API for FE

Dokumen ini khusus untuk kebutuhan integrasi frontend.

## Base URL

```text
/api/admin-dashboard
```

## Headers

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

## Response Format

Sukses:

```json
{
  "success": true,
  "message": "Message text",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Error message",
  "data": null
}
```

## 1. Get Permission List

### Endpoint

```http
GET /api/admin-dashboard/permissions
```

### Query Params

| Field | Type | Required | Notes |
|---|---|---:|---|
| `search` | string | no | search by `name`, `category`, `description` |
| `page` | number | no | default `1` |
| `per_page` | number | no | default `10`, max `10000` |
| `category` | string | no | exact filter |
| `sort_by` | string | no | `name`, `category`, `created_at`, `updated_at` |
| `sort_direction` | string | no | `asc` or `desc` |

### Example Request

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
        "id": "uuid",
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

- `total_permission`: total permission
- `kategori_aktif`: total kategori unik yang dipakai
- `permission_terbaru`: data permission terbaru

### Sorting Table

Gunakan query:

- `sort_by=name`
- `sort_by=category`
- `sort_by=created_at`
- `sort_by=updated_at`
- `sort_direction=asc|desc`

Contoh:

```http
GET /api/admin-dashboard/permissions?sort_by=name&sort_direction=asc
```

### Query Param Error

Jika query invalid, backend kirim semua error:

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

## 2. Get Permission Detail

### Endpoint

```http
GET /api/admin-dashboard/permissions/{id}
```

`id` memakai UUID.

### Response

```json
{
  "success": true,
  "message": "Permission fetched successfully",
  "data": {
    "id": "uuid",
    "name": "read_admin_roles",
    "category": "Modul Role Admin",
    "description": "Melihat daftar dan detail role admin.",
    "created_at": "2026-04-09T10:00:00Z",
    "updated_at": "2026-04-09T10:00:00Z"
  }
}
```

## 3. Create Permission

### Endpoint

```http
POST /api/admin-dashboard/permissions
```

### Request Body

```json
{
  "name": "approve_admin_permissions",
  "category": "Modul Permission Admin",
  "description": "Menyetujui perubahan permission admin."
}
```

### Validation

- `name` required, min 3, unique
- `category` required
- `description` optional

### Response

```json
{
  "success": true,
  "message": "Permission created successfully",
  "data": {
    "id": "uuid",
    "name": "approve_admin_permissions",
    "category": "Modul Permission Admin",
    "description": "Menyetujui perubahan permission admin.",
    "created_at": "2026-04-09T10:00:00Z",
    "updated_at": "2026-04-09T10:00:00Z"
  }
}
```

## 4. Update Permission

### Endpoint

```http
PUT /api/admin-dashboard/permissions/{id}
```

### Request Body

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
    "id": "uuid",
    "name": "approve_admin_permissions",
    "category": "Modul Permission Admin",
    "description": "Menyetujui dan mengubah permission admin.",
    "created_at": "2026-04-09T10:00:00Z",
    "updated_at": "2026-04-09T10:20:00Z"
  }
}
```

## 5. Delete Permission

### Endpoint

```http
DELETE /api/admin-dashboard/permissions/{id}
```

### Response

```json
{
  "success": true,
  "message": "Permission deleted successfully",
  "data": {
    "id": "uuid"
  }
}
```

### Notes

- jika permission dihapus, relasi ke role ikut terhapus
- FE tidak perlu handle `role_has_permissions`

## 6. Helper for Role Form

Untuk form create/update role:

```http
GET /api/admin-dashboard/role-permissions
```

Response:

```json
{
  "success": true,
  "message": "Permissions fetched successfully",
  "data": {
    "items": [
      {
        "id": "uuid",
        "name": "read_admin_roles",
        "category": "Modul Role Admin",
        "description": null
      }
    ]
  }
}
```

Catatan:

- endpoint ini mengembalikan semua permission
- tidak dibatasi pagination
- gunakan `name` sebagai value yang dikirim ke endpoint role
