# Admin Dashboard Tutorial Playlist Management

Dokumentasi ini menjelaskan implementasi backend Tutorial Playlist Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar tutorial playlist
- mengambil detail tutorial playlist
- membuat tutorial playlist
- mengubah tutorial playlist
- menghapus tutorial playlist

Catatan identifier:

- tabel `tutorial_playlists` saat ini sudah memakai UUID pada kolom primary key `id`
- sehingga `id` pada API sudah merupakan UUID

Kolom utama tabel:

- `tutorial_playlists`: `id`, `title`, `slug`, `desc`, `url`, `thumbnail`, `created_at`, `updated_at`, `deleted_at`

## Middleware dan Akses

Route modul ini berada di prefix:

```text
/api/admin-dashboard
```

Permission per endpoint:

- `GET /tutorial-playlists` -> `read_admin_tutorial_playlists`
- `GET /tutorial-playlists/{id}` -> `read_admin_tutorial_playlists`
- `POST /tutorial-playlists` -> `create_admin_tutorial_playlists`
- `POST /tutorial-playlists/{id}` -> `update_admin_tutorial_playlists`
- `DELETE /tutorial-playlists/{id}` -> `delete_admin_tutorial_playlists`

## Catatan Upload Thumbnail

- upload thumbnail menggunakan disk `public`
- path penyimpanan: `tutorial-playlists/...`
- URL file dibentuk dengan pola yang sama seperti endpoint tutorial public sekarang melalui `Storage::url(...)`

## Response Contoh

```json
{
  "success": true,
  "message": "Tutorial playlist fetched successfully",
  "data": {
    "id": "uuid",
    "title": "Tutorial Dashboard",
    "slug": "tutorial-dashboard",
    "description": "Panduan menggunakan dashboard.",
    "url": "https://www.youtube.com/watch?v=123",
    "thumbnail": "tutorial-playlists/example.webp",
    "thumbnail_url": "http://127.0.0.1:8000/storage/tutorial-playlists/example.webp",
    "created_at": "2026-04-14T10:00:00Z",
    "updated_at": "2026-04-14T10:00:00Z"
  }
}
```
