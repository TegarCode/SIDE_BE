Buat module Permission Management untuk Admin Dashboard dengan endpoint dan struktur mengikuti module Role Management yang sudah ada.

Gunakan pattern:
- request
- service
- repository
- controller

Gunakan UUID sebagai identifier API.
Response format harus konsisten seperti Role Management:
{
  "success": true,
  "message": "...",
  "data": ...
}

Middleware dan akses:
- route prefix: /api/admin-dashboard
- middleware route group mengikuti module role yang sekarang
- gunakan permission per endpoint

Permission yang dibutuhkan:
read_admin_permissions
create_admin_permissions
update_admin_permissions
delete_admin_permissions

Endpoint yang dibutuhkan:

GET /api/admin-dashboard/permissions
permission: read_admin_permissions
query:
{
  "search": "admin",
  "page": 1,
  "per_page": 10,
  "category": "Modul Admin"
}
response:
{
  "success": true,
  "message": "Permissions fetched successfully",
  "data": {
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
      "last_page": 1
    }
  }
}

GET /api/admin-dashboard/permissions/{id}
permission: read_admin_permissions
response:
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

POST /api/admin-dashboard/permissions
permission: create_admin_permissions
request:
{
  "name": "approve_admin_permissions",
  "category": "Modul Permission Admin",
  "description": "Menyetujui perubahan permission admin."
}
response:
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

PUT /api/admin-dashboard/permissions/{id}
permission: update_admin_permissions
request:
{
  "name": "approve_admin_permissions",
  "category": "Modul Permission Admin",
  "description": "Menyetujui dan mengubah permission admin."
}
response:
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

DELETE /api/admin-dashboard/permissions/{id}
permission: delete_admin_permissions
response:
{
  "success": true,
  "message": "Permission deleted successfully",
  "data": {
    "id": "uuid"
  }
}

Validasi backend:
- name required, min 3, unique
- category required
- description nullable string

Ketentuan implementasi:
- source table gunakan table permissions Spatie yang sekarang
- kolom yang digunakan: id internal, uuid, name, category, description, guard_name
- id pada API harus uuid
- detail, update, delete harus menerima uuid
- gunakan guard_name = web
- list harus support search, pagination, dan filter category
- ketika permission dihapus, relasi pada table role_has_permissions ikut terhapus
- tidak perlu blok delete jika permission masih dipakai role
- tetap pastikan delete aman dan sinkron dengan relasi Spatie
- buat documentation markdown seperti module role management
- buat seeder permission management mengikuti data existing bila perlu
