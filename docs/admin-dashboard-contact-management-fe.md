# Contact Management API for FE

Dokumen ini khusus untuk kebutuhan integrasi frontend.

## Endpoint

1. `GET /api/admin-dashboard/contacts`
2. `GET /api/admin-dashboard/contacts/{id}`
3. `PUT /api/admin-dashboard/contacts/{id}`
4. `DELETE /api/admin-dashboard/contacts/{id}`

## Query Params List

- `search`
- `page`
- `per_page`
- `jenis`
- `sort_by`
- `sort_direction`

## Kolom Sort

- `nama`
- `email`
- `jenis`
- `created_at`
- `updated_at`

## Response Item

```json
{
  "id": "uuid",
  "nama": "Budi",
  "email": "budi@example.com",
  "jenis": "PERTANYAAN",
  "pesan": "Halo admin",
  "created_at": "2026-04-13T10:00:00Z",
  "updated_at": "2026-04-13T10:00:00Z"
}
```

## Summary Cards

- `total_contact`
- `jenis_aktif`
- `contact_terbaru`

## Update Payload

```json
{
  "nama": "Budi Santoso",
  "email": "budi@example.com",
  "jenis": "MASUKAN",
  "pesan": "Pesan yang sudah diperbarui."
}
```

## Catatan FE

- identifier API memakai UUID
- delete memakai soft delete
- tidak ada create endpoint di admin dashboard untuk contact management
