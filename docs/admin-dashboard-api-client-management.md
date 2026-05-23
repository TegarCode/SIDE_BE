# Admin Dashboard API Client Management

Dokumentasi ini menjelaskan implementasi backend API Client Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar API client
- mengambil detail API client
- membuat API client
- mengubah API client
- menghapus API client
- mengambil daftar permission untuk pengisian `abilities`

Entity API client memakai:

- primary key internal database: `id` bigint
- identifier API: `uuid`

Kolom utama tabel:

- `api_clients`: `id`, `uuid`, `name`, `description`, `api_key`, `abilities`, `allowed_domains`, `active`, `created_at`, `updated_at`, `deleted_at`

## Catatan Keamanan API Key

- database hanya menyimpan hash `api_key`
- API key mentah hanya dikirim sekali saat create
- endpoint list, detail, dan update tidak pernah mengembalikan API key mentah
- jika API key hilang atau perlu rotasi, buat client baru lalu hapus client lama
- melihat ulang API key lama tidak didukung karena token disimpan dalam bentuk hash
- gunakan fitur regenerate key dengan verifikasi password user admin

## Abilities pada API Client

- field `abilities` berisi daftar nama permission
- middleware `ability_or_permission` akan mengecek `abilities` saat request memakai `X-API-KEY`
- jika permission endpoint tidak ada di `abilities`, request ditolak `403 Forbidden`
- wildcard `*` didukung untuk akses penuh

## Endpoint Regenerate API Key

```http
POST /api/admin-dashboard/api-clients/{uuid}/regenerate-key
```

Request body:

```json
{
  "current_password": "your-current-password"
}
```

Response:

```json
{
  "success": true,
  "message": "API key regenerated successfully",
  "data": {
    "id": "uuid",
    "name": "Mobile Service",
    "description": "Client untuk integrasi mobile service.",
    "abilities": [
      "view_admin_dashboard"
    ],
    "allowed_domains": [
      "https://mobile.example.com"
    ],
    "active": true,
    "created_at": "2026-04-14T09:00:00Z",
    "updated_at": "2026-04-14T10:00:00Z"
  },
  "metadata": {
    "plain_text_api_key": "bskln_xxxxx",
    "api_key_notice": "API key baru hanya ditampilkan sekali. Simpan dengan aman."
  }
}
```
