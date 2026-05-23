# Admin Dashboard Side Page View Management

Dokumentasi ini menjelaskan implementasi backend Side Page View Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar side page view
- mengambil detail side page view

Modul ini read-only dan hanya untuk menampilkan data tracking page view.

Entity side page view memakai:

- primary key internal database: `id` bigint
- identifier API: `id`

Kolom utama tabel:

- `side_page_views`: `id`, `user_id`, `session_id`, `path`, `module`, `user_agent`, `ip_hash`, `created_at`, `updated_at`
- `side_page_views`: `id`, `user_id`, `session_id`, `path`, `module`, `user_agent`, `ip_hash`, `created_at`, `updated_at`

## Middleware dan Akses

Route modul ini berada di prefix:

```text
/api/admin-dashboard
```

Permission per endpoint:

- `GET /side-page-views` -> `read_admin_side_page_views`
- `GET /side-page-views/{id}` -> `read_admin_side_page_views`
- `GET /side-page-view-modules` -> `read_admin_side_page_views`

## 1. List Side Page Views

### Request

```http
GET /api/admin-dashboard/side-page-views
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan `path`, `module`, `user_agent`, `user.name`, `user.email` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `10000` |
| `module` | string | no | Filter module exact match |
| `sort_by` | string | no | `path`, `module`, `created_at` |
| `sort_direction` | string | no | `asc` atau `desc` |

### Response

```json
{
  "success": true,
  "message": "Side page views fetched successfully",
  "data": {
    "summary": {
      "total_view": 120,
      "module_aktif": 5,
      "view_terbaru": {
        "id": 100,
        "path": "/",
        "module": "home",
        "user": {
          "id": "uuid-user",
          "name": "Super Admin",
          "email": "superadmin@side.com"
        },
        "user_agent": "Mozilla/5.0",
        "created_at": "2026-04-14T10:00:00Z",
        "updated_at": "2026-04-14T10:00:00Z"
      }
    },
    "items": [
      {
        "id": 100,
        "path": "/",
        "module": "home",
        "user": {
          "id": "uuid-user",
          "name": "Super Admin",
          "email": "superadmin@side.com"
        },
        "user_agent": "Mozilla/5.0",
        "created_at": "2026-04-14T10:00:00Z",
        "updated_at": "2026-04-14T10:00:00Z"
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 10,
      "total": 120,
      "last_page": 12,
      "sort_by": "created_at",
      "sort_direction": "desc"
    }
  }
}
```

## 2. Detail Side Page View

### Request

```http
GET /api/admin-dashboard/side-page-views/{id}
```

### Response

```json
{
  "success": true,
  "message": "Side page view fetched successfully",
  "data": {
    "id": 100,
    "path": "/",
    "module": "home",
    "user": {
      "id": "uuid-user",
      "name": "Super Admin",
      "email": "superadmin@side.com"
    },
    "user_agent": "Mozilla/5.0",
    "ip_hash": "hashed-ip",
    "created_at": "2026-04-14T10:00:00Z",
    "updated_at": "2026-04-14T10:00:00Z"
  }
}
```

## 3. Get Module Options

### Request

```http
GET /api/admin-dashboard/side-page-view-modules
```

### Response

```json
{
  "success": true,
  "message": "Modules fetched successfully",
  "data": {
    "items": [
      {
        "name": "home"
      },
      {
        "name": "analytics"
      }
    ]
  }
}
```
