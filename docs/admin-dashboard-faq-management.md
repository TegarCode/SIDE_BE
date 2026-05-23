# Admin Dashboard FAQ Management

Dokumentasi ini menjelaskan implementasi backend FAQ Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar FAQ topic
- mengambil detail FAQ topic
- membuat FAQ topic beserta items
- mengubah FAQ topic beserta items
- menghapus FAQ topic

Entity FAQ memakai:

- `faq_topics`
- `faq_items`

Identifier API:

- topic: `uuid`
- item: `uuid`

## Middleware dan Akses

Route modul ini berada di prefix:

```text
/api/admin-dashboard
```

Permission per endpoint:

- `GET /faqs` -> `read_admin_faqs`
- `GET /faqs/{id}` -> `read_admin_faqs`
- `POST /faqs` -> `create_admin_faqs`
- `PUT /faqs/{id}` -> `update_admin_faqs`
- `DELETE /faqs/{id}` -> `delete_admin_faqs`

## 1. List Faqs

### Request

```http
GET /api/admin-dashboard/faqs
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan `topic`, `summary`, `question`, `answer` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `10000` |
| `is_featured` | boolean | no | Filter topic featured |
| `sort_by` | string | no | `topic`, `order`, `created_at`, `updated_at` |
| `sort_direction` | string | no | `asc` atau `desc` |

### Response

```json
{
  "success": true,
  "message": "Faqs fetched successfully",
  "data": {
    "summary": {
      "total_faq_topic": 5,
      "total_faq_item": 9,
      "faq_featured": 3,
      "faq_terbaru": {
        "id": "uuid",
        "topic": "Penggunaan Platform",
        "summary": "Panduan dasar menggunakan fitur utama.",
        "is_featured": true,
        "order": 5,
        "items_count": 2,
        "items": [],
        "created_at": "2026-04-13T10:00:00Z",
        "updated_at": "2026-04-13T10:00:00Z"
      }
    },
    "items": [
      {
        "id": "uuid",
        "topic": "Akun dan Akses",
        "summary": "Panduan ringkas untuk masuk dan mengelola akun.",
        "is_featured": true,
        "order": 1,
        "items_count": 2,
        "items": [
          {
            "id": "uuid",
            "question": "Bagaimana cara login ke sistem?",
            "answer": "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
            "order": 1,
            "created_at": "2026-04-13T10:00:00Z",
            "updated_at": "2026-04-13T10:00:00Z"
          }
        ],
        "created_at": "2026-04-13T10:00:00Z",
        "updated_at": "2026-04-13T10:00:00Z"
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 10,
      "total": 5,
      "last_page": 1,
      "sort_by": "order",
      "sort_direction": "desc"
    }
  }
}
```

### Summary Fields

- `total_faq_topic`: total topic FAQ aktif
- `total_faq_item`: total item FAQ aktif
- `faq_featured`: total topic featured
- `faq_terbaru`: topic FAQ terbaru

## 2. Detail Faq

```http
GET /api/admin-dashboard/faqs/{uuid}
```

## 3. Create Faq

```http
POST /api/admin-dashboard/faqs
```

```json
{
  "topic": "Akun dan Akses",
  "summary": "Panduan ringkas untuk masuk dan mengelola akun.",
  "is_featured": true,
  "order": 1,
  "items": [
    {
      "question": "Bagaimana cara login ke sistem?",
      "answer": "Masuk menggunakan email dan password.",
      "order": 1
    },
    {
      "question": "Apa yang harus dilakukan jika lupa kata sandi?",
      "answer": "Gunakan fitur reset password.",
      "order": 2
    }
  ]
}
```

## 4. Update Faq

```http
PUT /api/admin-dashboard/faqs/{uuid}
```

Payload sama seperti create. Update akan mengganti seluruh item aktif pada topic tersebut.

## 5. Delete Faq

```http
DELETE /api/admin-dashboard/faqs/{uuid}
```

Delete memakai soft delete pada topic dan seluruh item di bawahnya.

## Validasi

- `topic` required, min 3
- `summary` nullable string
- `is_featured` required boolean
- `order` required integer min 0
- `items` required array min 1
- `items.*.question` required, min 3
- `items.*.answer` required
- `items.*.order` nullable integer min 0

## Implementasi

- request: [FaqManagementRequest.php](/c:/laragon/www/BSKLN/databank/databank-be/app/Http/Requests/AdminDashboard/FaqManagementRequest.php)
- controller: [FaqManagementController.php](/c:/laragon/www/BSKLN/databank/databank-be/app/Http/Controllers/Api/AdminDashboard/FaqManagementController.php)
- service: [FaqManagementService.php](/c:/laragon/www/BSKLN/databank/databank-be/app/Services/AdminDashboard/FaqManagementService.php)
- repository: [FaqManagementRepository.php](/c:/laragon/www/BSKLN/databank/databank-be/app/Repositories/AdminDashboard/FaqManagement/FaqManagementRepository.php)
