# API Client Management API for FE

## Endpoint

- `GET /api/admin-dashboard/api-clients`
- `GET /api/admin-dashboard/api-clients/{id}`
- `POST /api/admin-dashboard/api-clients`
- `POST /api/admin-dashboard/api-clients/{id}/regenerate-key`
- `PUT /api/admin-dashboard/api-clients/{id}`
- `DELETE /api/admin-dashboard/api-clients/{id}`
- `GET /api/admin-dashboard/api-client-permissions`

## Request Create

```json
{
  "name": "Mobile Service",
  "description": "Client untuk integrasi mobile service.",
  "abilities": [
    "view_admin_dashboard",
    "view_perdagangan_negara_mitra"
  ],
  "allowed_domains": [
    "https://mobile.example.com"
  ],
  "active": true
}
```

## Response Create

```json
{
  "success": true,
  "message": "API client created successfully",
  "data": {
    "id": "uuid",
    "name": "Mobile Service",
    "description": "Client untuk integrasi mobile service.",
    "abilities": [
      "view_admin_dashboard",
      "view_perdagangan_negara_mitra"
    ],
    "allowed_domains": [
      "https://mobile.example.com"
    ],
    "active": true,
    "created_at": "2026-04-14T09:00:00Z",
    "updated_at": "2026-04-14T09:00:00Z"
  },
  "metadata": {
    "plain_text_api_key": "bskln_xxxxx",
    "api_key_notice": "API key hanya ditampilkan sekali. Simpan dengan aman."
  }
}
```

## Catatan FE

- tampilkan `metadata.plain_text_api_key` hanya di modal sukses create
- jangan berharap API key mentah tersedia lagi di endpoint lain
- untuk opsi abilities, ambil dari `GET /api/admin-dashboard/api-client-permissions`
- untuk lihat ulang token lama: tidak didukung backend
- jika user butuh token baru, panggil endpoint regenerate dengan `current_password`

## Request Regenerate API Key

```json
{
  "current_password": "your-current-password"
}
```
