# FAQ Management API for FE

Dokumen ini khusus untuk kebutuhan integrasi frontend.

## Base URL

```text
/api/admin-dashboard
```

## Endpoint

1. `GET /api/admin-dashboard/faqs`
2. `GET /api/admin-dashboard/faqs/{id}`
3. `POST /api/admin-dashboard/faqs`
4. `PUT /api/admin-dashboard/faqs/{id}`
5. `DELETE /api/admin-dashboard/faqs/{id}`

## Summary Cards

Gunakan data summary dari response list:

- `total_faq_topic`
- `total_faq_item`
- `faq_featured`
- `faq_terbaru`

## Query Params List

- `search`
- `page`
- `per_page`
- `is_featured`
- `sort_by`
- `sort_direction`

## Kolom Sort

- `topic`
- `order`
- `created_at`
- `updated_at`

## Request Body Create / Update

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

## Response Item

```json
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
      "answer": "Masuk menggunakan email dan password.",
      "order": 1,
      "created_at": "2026-04-13T10:00:00Z",
      "updated_at": "2026-04-13T10:00:00Z"
    }
  ],
  "created_at": "2026-04-13T10:00:00Z",
  "updated_at": "2026-04-13T10:00:00Z"
}
```

## Contoh Response List

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
    "items": [],
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

## Catatan FE

- `id` topic dan item memakai UUID
- create/update mengirim seluruh `items`
- update akan mengganti daftar item aktif yang ada sekarang
- delete memakai soft delete
