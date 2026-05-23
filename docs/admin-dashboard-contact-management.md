# Admin Dashboard Contact Management

Dokumentasi ini menjelaskan implementasi backend Contact Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar contact message
- mengambil detail contact message
- mengubah contact message
- menghapus contact message

Entity contact memakai:

- primary key internal database: `id` bigint
- identifier API: `uuid`

Kolom utama tabel:

- `contacts`: `id`, `uuid`, `nama`, `email`, `jenis`, `pesan`, `ip_hash`, `user_agent`, `created_at`, `updated_at`, `deleted_at`

## Middleware dan Akses

Permission per endpoint:

- `GET /contacts` -> `read_admin_contacts`
- `GET /contacts/{id}` -> `read_admin_contacts`
- `PUT /contacts/{id}` -> `update_admin_contacts`
- `DELETE /contacts/{id}` -> `delete_admin_contacts`

## 1. List Contacts

### Request

```http
GET /api/admin-dashboard/contacts
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan `nama`, `email`, `jenis`, `pesan` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `10000` |
| `jenis` | string | no | `PERTANYAAN`, `MASUKAN`, `SARAN` |
| `sort_by` | string | no | `nama`, `email`, `jenis`, `created_at`, `updated_at` |
| `sort_direction` | string | no | `asc` atau `desc` |

### Response

```json
{
  "success": true,
  "message": "Contacts fetched successfully",
  "data": {
    "summary": {
      "total_contact": 10,
      "jenis_aktif": 3,
      "contact_terbaru": {
        "id": "uuid",
        "nama": "Budi",
        "email": "budi@example.com",
        "jenis": "PERTANYAAN",
        "pesan": "Halo admin",
        "created_at": "2026-04-13T10:00:00Z",
        "updated_at": "2026-04-13T10:00:00Z"
      }
    },
    "items": [
      {
        "id": "uuid",
        "nama": "Budi",
        "email": "budi@example.com",
        "jenis": "PERTANYAAN",
        "pesan": "Halo admin",
        "created_at": "2026-04-13T10:00:00Z",
        "updated_at": "2026-04-13T10:00:00Z"
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 10,
      "total": 10,
      "last_page": 1,
      "sort_by": "created_at",
      "sort_direction": "desc"
    }
  }
}
```

## 2. Detail Contact

```http
GET /api/admin-dashboard/contacts/{uuid}
```

## 3. Update Contact

```http
PUT /api/admin-dashboard/contacts/{uuid}
```

```json
{
  "nama": "Budi Santoso",
  "email": "budi@example.com",
  "jenis": "MASUKAN",
  "pesan": "Pesan yang sudah diperbarui."
}
```

## 4. Delete Contact

```http
DELETE /api/admin-dashboard/contacts/{uuid}
```

Delete memakai soft delete.

## Validasi

- `nama` required
- `email` required, email
- `jenis` required: `PERTANYAAN|MASUKAN|SARAN`
- `pesan` required, min 6

## Implementasi

- request: [ContactManagementRequest.php](/c:/laragon/www/SIDE_BE/app/Http/Requests/AdminDashboard/ContactManagementRequest.php)
- controller: [ContactManagementController.php](/c:/laragon/www/SIDE_BE/app/Http/Controllers/Api/AdminDashboard/ContactManagementController.php)
- service: [ContactManagementService.php](/c:/laragon/www/SIDE_BE/app/Services/AdminDashboard/ContactManagementService.php)
- repository: [ContactManagementRepository.php](/c:/laragon/www/SIDE_BE/app/Repositories/AdminDashboard/ContactManagement/ContactManagementRepository.php)
