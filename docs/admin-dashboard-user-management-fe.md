# User Management API for FE

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

## 1. Get User List

### Endpoint

```http
GET /api/admin-dashboard/users
```

### Query Params

| Field | Type | Required | Notes |
|---|---|---:|---|
| `search` | string | no | search by `name`, `email` |
| `page` | number | no | default `1` |
| `per_page` | number | no | default `10`, max `10000` |
| `status` | string | no | `active` or `inactive` |
| `role` | string | no | exact role name |
| `sort_by` | string | no | `name`, `email`, `created_at`, `updated_at` |
| `sort_direction` | string | no | `asc` or `desc` |

### Example Request

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

- `total_user`
- `role_aktif`
- `user_terbaru`

### Query Param Error

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ],
    "sort_by": [
      "The selected sort by is invalid."
    ]
  },
  "data": null
}
```

## 2. Get User Detail

### Endpoint

```http
GET /api/admin-dashboard/users/{id}
```

`id` memakai UUID.

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

## 3. Create User

### Endpoint

```http
POST /api/admin-dashboard/users
```

### Request Body

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

### Validation

- `name` required
- `email` required, email, unique
- `password` required, confirmed, min 8
- `status` required: `active|inactive`
- `roles` required, array

## 4. Update User

### Endpoint

```http
PUT /api/admin-dashboard/users/{id}
```

### Request Body

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

Catatan:

- `password` optional saat update
- jika dikirim harus ada `password_confirmation`

## 5. Delete User

### Endpoint

```http
DELETE /api/admin-dashboard/users/{id}
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

Catatan:

- delete memakai soft delete

## 6. Helper for User Role Assignment

### Endpoint

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

Catatan:

- endpoint ini mengembalikan semua role
- tidak dibatasi pagination
- gunakan `name` sebagai value role yang dikirim saat create/update user
