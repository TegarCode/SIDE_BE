# Admin Dashboard Kinerja Ekonomi Management

Dokumentasi ini menjelaskan implementasi backend Manajemen Kinerja Ekonomi yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar batch staging kinerja ekonomi
- mengambil ringkasan summary batch
- mengambil detail batch dan baris staging
- membuat batch manual
- menerima upload file CSV atau Excel
- memproses import file ke staging melalui queue
- memvalidasi batch melalui queue
- approve dan reject batch
- publish batch ke tabel utama melalui queue
- menghapus batch atau baris staging
- membersihkan staging setelah publish
- mengambil data aktif dari tabel utama
- mengubah dan menghapus data aktif, termasuk bulk delete

Tabel yang dipakai:

- `data_import_batches`
- `tbkin_ekonomi_staging`
- `tbkin_ekonomi_testing`
- `tbindikator_kinek`
- `tbnegara`
- `tbsumber`

Catatan:

- repository saat ini memakai connection `server_mysql`
- lookup nama pengunggah diambil dari model `App\Models\User`
- file upload dihapus dari storage setelah import ke staging selesai

## Middleware dan Permission

Route modul ini berada di prefix:

```text
/api/admin-dashboard
```

Middleware route group:

- `throttle:60,1`
- `auth_or_api`

Permission utama:

- `read_admin_kinerja_ekonomi`
- `read_admin_kinerja_ekonomi_current`
- `read_all_admin_kinerja_ekonomi`
- `create_admin_kinerja_ekonomi`
- `update_admin_kinerja_ekonomi`
- `approve_admin_kinerja_ekonomi`
- `publish_admin_kinerja_ekonomi`
- `delete_admin_kinerja_ekonomi`

Aturan akses data:

- jika user punya `read_all_admin_kinerja_ekonomi`, user dapat melihat semua batch
- jika tidak punya, list, summary, detail, dan aksi batch dibatasi ke `uploaded_by = user.id`
- data aktif di tabel utama tidak memakai scope pengunggah

## Base Response Format

Semua endpoint memakai struktur dasar berikut:

```json
{
  "success": true,
  "message": "Message text",
  "data": {}
}
```

Validation error:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "field": [
      "Validation message"
    ]
  },
  "data": null
}
```

## 1. List Batch Kinerja Ekonomi

### Request

```http
GET /api/admin-dashboard/kinerja-ekonomi
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan UUID batch, nama file, atau catatan |
| `status` | string | no | `draft`, `validating`, `valid`, `invalid`, `approved`, `rejected`, `published`, `failed` |
| `source_type` | string | no | `manual` atau `upload` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `100` |
| `sort_by` | string | no | `created_at`, `updated_at`, `uploaded_at`, `validated_at`, `approved_at`, `published_at`, `source_type`, `original_filename`, `status`, `total_rows`, `valid_rows`, `invalid_rows` |
| `sort_direction` | string | no | `asc` atau `desc` |

### Response

```json
{
  "success": true,
  "message": "Kinerja ekonomi batches fetched successfully",
  "data": {
    "summary": {
      "total_batch": 12,
      "pending_batch": 4,
      "approved_batch": 2,
      "published_batch": 5,
      "invalid_batch": 1
    },
    "items": [
      {
        "id": "uuid-batch",
        "source_type": "upload",
        "original_filename": "indikator-ekonomi-april.xlsx",
        "status": "approved",
        "total_rows": 120,
        "valid_rows": 118,
        "invalid_rows": 2,
        "uploaded_by": 1,
        "uploaded_by_name": "Super Admin",
        "uploaded_at": "2026-04-27T01:00:00Z",
        "validated_at": "2026-04-27T01:05:00Z",
        "approved_at": "2026-04-27T01:08:00Z",
        "published_at": null,
        "note": "Upload April 2026"
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 10,
      "total": 12,
      "last_page": 2,
      "sort_by": "uploaded_at",
      "sort_direction": "desc"
    }
  }
}
```

## 2. Detail Batch dan Baris Staging

### Request

```http
GET /api/admin-dashboard/kinerja-ekonomi/{uuid}
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `100` |
| `sort_by` | string | no | `id`, `Kode_Alpha3`, `Bulan`, `Tahun`, `Nilai`, `Unit`, `Satuan`, `ID_Indikator`, `Komponen_Indikator`, `KodeSumber`, `row_status`, `created_at`, `updated_at` |
| `sort_direction` | string | no | `asc` atau `desc` |

Response memuat:

- informasi batch
- daftar `rows.items`
- metadata `rows.meta`
- `validation_errors` per baris jika ada

## 3. Create Batch Manual

### Request

```http
POST /api/admin-dashboard/kinerja-ekonomi
```

### Payload

```json
{
  "source_type": "manual",
  "note": "Input manual indikator ekonomi",
  "rows": [
    {
      "Kode_Alpha3": "IDN",
      "Bulan": 4,
      "Tahun": 2026,
      "Nilai": 4.52,
      "Unit": "persen",
      "Satuan": "yoy",
      "ID_Indikator": "INF01",
      "Komponen_Indikator": "Inflasi Umum",
      "KodeSumber": "BI"
    }
  ]
}
```

## 4. Upload File dan Background Import

### Request

```http
POST /api/admin-dashboard/kinerja-ekonomi
```

Payload multipart:

- `source_type=upload`
- `file`
- `original_filename`
- `note`
- `column_mapping[...]`

Alur proses:

1. backend membuat batch dengan status `validating`
2. file disimpan sementara ke `storage/app/imports/kinerja-ekonomi`
3. job `ImportKinerjaEkonomiCsvJob` dipush ke queue
4. job membaca file CSV/Excel dan mengisi staging
5. setelah selesai, file input dihapus dari storage lokal
6. batch di-refresh ke status `draft`

## 5. Preview Upload

### Request

```http
POST /api/admin-dashboard/kinerja-ekonomi/preview
```

Payload:

- `file`
- `sample_size` opsional

Response memuat:

- `original_filename`
- `headers`
- `sample_rows`
- `sample_size`

## 6. Validasi Batch

### Request

```http
POST /api/admin-dashboard/kinerja-ekonomi/{uuid}/validate
```

Alur:

1. batch ditandai `validating`
2. job `ValidateKinerjaEkonomiBatchJob` dipush ke queue
3. staging row dihitung `valid` atau `invalid`
4. batch status menjadi `valid` atau `invalid`

Catatan:

- batch `approved` dan `published` tidak bisa divalidasi ulang

## 7. Approve, Reject, dan Publish

### Approve

```http
POST /api/admin-dashboard/kinerja-ekonomi/{uuid}/approve
```

Syarat:

- batch harus `valid`

### Reject

```http
POST /api/admin-dashboard/kinerja-ekonomi/{uuid}/reject
```

Payload opsional:

```json
{
  "note": "Perlu perbaikan data sebelum publish"
}
```

### Publish

```http
POST /api/admin-dashboard/kinerja-ekonomi/{uuid}/publish
```

Alur publish:

1. batch ditandai `publishing`
2. job `PublishKinerjaEkonomiBatchJob` dipush ke queue
3. row `valid` di staging di-insert ke tabel utama per chunk
4. row staging terkait diubah menjadi `published`
5. batch diubah menjadi `published`

Catatan:

- publish memakai bulk `insert`, bukan `updateOrInsert`
- batch boleh dipublish jika `approved_at` sudah ada dan `published_at` masih kosong

## 8. Update dan Delete Data Staging

### Update Satu Row Staging

```http
PUT /api/admin-dashboard/kinerja-ekonomi/{uuid}/rows/{rowId}
```

### Delete Satu Row Staging

```http
DELETE /api/admin-dashboard/kinerja-ekonomi/{uuid}/rows/{rowId}
```

### Bulk Delete Row Staging

```http
POST /api/admin-dashboard/kinerja-ekonomi/{uuid}/rows/bulk-delete
```

Payload:

```json
{
  "row_ids": [12, 13, 14]
}
```

## 9. Hapus Batch dan Bersihkan Staging Publish

### Delete Batch

```http
DELETE /api/admin-dashboard/kinerja-ekonomi/{uuid}
```

### Clear Published Staging Rows

```http
DELETE /api/admin-dashboard/kinerja-ekonomi/{uuid}/staging
```

Aturan:

- hanya membersihkan row staging batch yang sudah `published`
- tidak menghapus data yang sudah masuk ke tabel utama

## 10. Data Aktif Kinerja Ekonomi

### List Data Aktif

```http
GET /api/admin-dashboard/kinerja-ekonomi/current
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `country_code` | string | no | Kode negara 3 huruf |
| `indicator_id` | string | no | ID indikator |
| `source_code` | string | no | Kode sumber |
| `year` | integer | no | Tahun data |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `100` |
| `sort_by` | string | no | `ID`, `Kode_Alpha3`, `Bulan`, `Tahun`, `Nilai`, `Unit`, `Satuan`, `ID_Indikator`, `Komponen_Indikator`, `KodeSumber` |
| `sort_direction` | string | no | `asc` atau `desc` |

Catatan:

- default sort backend saat ini `Tahun desc`
- filter aktif mendukung `negara`, `indikator`, `sumber`, dan `tahun`

### Update Satu Row Data Aktif

```http
PUT /api/admin-dashboard/kinerja-ekonomi/current/{rowId}
```

### Delete Satu Row Data Aktif

```http
DELETE /api/admin-dashboard/kinerja-ekonomi/current/{rowId}
```

### Bulk Delete Data Aktif

```http
POST /api/admin-dashboard/kinerja-ekonomi/current/bulk-delete
```

Payload:

```json
{
  "row_ids": [101, 102, 103]
}
```

Response:

```json
{
  "success": true,
  "message": "Kinerja ekonomi current rows deleted successfully",
  "data": {
    "deleted_count": 3,
    "row_ids": [101, 102, 103]
  }
}
```

## 11. Options Lookup

### Request

```http
GET /api/admin-dashboard/kinerja-ekonomi/options
```

Response memuat lookup:

- `countries`
- `indicators`
- `sources`

Endpoint ini dipakai untuk:

- input manual
- pemetaan unggahan
- edit row staging
- edit row data aktif
- filter data aktif

## Seeder dan Implementasi

Seeder permission yang terkait:

- `read_admin_kinerja_ekonomi`
- `read_admin_kinerja_ekonomi_current`
- `read_all_admin_kinerja_ekonomi`
- `create_admin_kinerja_ekonomi`
- `update_admin_kinerja_ekonomi`
- `approve_admin_kinerja_ekonomi`
- `publish_admin_kinerja_ekonomi`
- `delete_admin_kinerja_ekonomi`

Layer implementasi utama:

- request: `app/Http/Requests/AdminDashboard/ManajemenData/KinerjaEkonomiManagementRequest.php`
- controller: `app/Http/Controllers/Api/AdminDashboard/ManajemenData/KinerjaEkonomiManagementController.php`
- service: `app/Services/AdminDashboard/ManajemenData/KinerjaEkonomiManagementService.php`
- repository: `app/Repositories/AdminDashboard/ManajemenData/KinerjaEkonomiManagement/KinerjaEkonomiManagementRepository.php`
- jobs:
  - `app/Jobs/AdminDashboard/ImportKinerjaEkonomiCsvJob.php`
  - `app/Jobs/AdminDashboard/ValidateKinerjaEkonomiBatchJob.php`
  - `app/Jobs/AdminDashboard/PublishKinerjaEkonomiBatchJob.php`

## Status

- list batch aktif
- detail batch aktif
- summary aktif
- own-data vs all-data scope aktif
- manual create aktif
- upload preview aktif
- import upload via queue aktif
- validasi via queue aktif
- approve aktif
- reject aktif
- publish via queue aktif
- clear staging aktif
- data aktif list aktif
- data aktif filter `negara`, `indikator`, `sumber`, `tahun` aktif
- data aktif update aktif
- data aktif single delete aktif
- data aktif bulk delete aktif
