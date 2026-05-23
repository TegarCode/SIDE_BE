# Admin Dashboard Cache Management

Dokumentasi ini menjelaskan implementasi backend Cache Management yang aktif saat ini.

## Ringkasan

Modul ini dipakai untuk:

- mengambil daftar cache dengan prefix `side_cache:`
- mengambil detail cache
- mengubah expiration cache
- menghapus cache

Kolom sumber data:

- tabel `cache`: `key`, `value`, `expiration`

Modul ini menampilkan:

- `key`
- `expiration`
- kategori hasil parsing key, misalnya `indonesia>infrastruktur`
- `value` hanya pada endpoint detail

Contoh key:

```text
side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1-amri2-asete-asten-astim-erop1-erop2-pasos-titen:kategori-bi-bumn-perbankan-bumn-iipc-kbri-kjri-kri-perwakilan-dagang-ptri
```

Contoh kategori hasil parsing:

- `category_parent`: `indonesia`
- `category_child`: `infrastruktur`
- `category`: `indonesia>infrastruktur`

## Middleware dan Akses

Permission per endpoint:

- `GET /caches` -> `read_admin_caches`
- `GET /caches/{id}` -> `read_admin_caches`
- `PUT /caches/{id}` -> `update_admin_caches`
- `DELETE /caches/{id}` -> `delete_admin_caches`

## Base Response Format

Sukses:

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

## 1. List Caches

### Request

```http
GET /api/admin-dashboard/caches
```

### Query Params

| Field | Type | Required | Keterangan |
|---|---|---:|---|
| `search` | string | no | Cari berdasarkan isi `key` |
| `page` | integer | no | Minimal `1` |
| `per_page` | integer | no | Minimal `1`, maksimal `10000` |
| `category` | string | no | Filter exact match, contoh `indonesia>infrastruktur` |
| `sort_by` | string | no | `key`, `expiration` |
| `sort_direction` | string | no | `asc` atau `desc` |

### Response

```json
{
  "success": true,
  "message": "Caches fetched successfully",
  "data": {
    "summary": {
      "total_cache": 20,
      "kategori_aktif": 5,
      "cache_terbaru": {
        "id": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
        "key": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
        "category": "indonesia>infrastruktur",
        "category_parent": "indonesia",
        "category_child": "infrastruktur",
        "expiration": "2026-04-21T00:00:00Z",
        "expiration_timestamp": 1776729600
      }
    },
    "items": [
      {
        "id": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
        "key": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
        "category": "indonesia>infrastruktur",
        "category_parent": "indonesia",
        "category_child": "infrastruktur",
        "expiration": "2026-04-21T00:00:00Z",
        "expiration_timestamp": 1776729600
      }
    ],
    "meta": {
      "page": 1,
      "per_page": 10,
      "total": 20,
      "last_page": 2,
      "sort_by": "expiration",
      "sort_direction": "desc"
    }
  }
}
```

### Summary Fields

- `total_cache`: total cache key dengan prefix `side_cache:`
- `kategori_aktif`: total kategori unik hasil parsing key
- `cache_terbaru`: cache dengan `expiration` terbaru

## 2. Detail Cache

### Request

```http
GET /api/admin-dashboard/caches/{id}
```

Catatan:

- `id` adalah raw cache key
- untuk client/FE, path param harus di-encode, misalnya `encodeURIComponent(key)`

### Response

```json
{
  "success": true,
  "message": "Cache fetched successfully",
  "data": {
    "id": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
    "key": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
    "category": "indonesia>infrastruktur",
    "category_parent": "indonesia",
    "category_child": "infrastruktur",
    "expiration": "2026-04-21T00:00:00Z",
    "expiration_timestamp": 1776729600,
    "value": {
      "items": []
    }
  }
}
```

### Bentuk Field `value`

Field `value` hanya dikirim di endpoint detail. Backend akan mencoba decode isi cache dengan urutan berikut:

1. decode JSON
2. unserialize PHP untuk payload yang aman
3. unserialize `Illuminate\Support\Collection` lalu mengubahnya menjadi array
4. jika semua gagal, fallback ke string mentah

Karena itu, `value` yang diterima client bisa berupa:

- object
- array
- scalar
- string mentah

Contoh serialized collection yang sekarang bisa di-decode:

```text
O:29:"Illuminate\Support\Collection":...
```

## 3. Update Expiration Cache

### Request

```http
PUT /api/admin-dashboard/caches/{id}
Content-Type: application/json
```

### Body

```json
{
  "expiration_at": "2026-04-25 00:00:00"
}
```

### Response

```json
{
  "success": true,
  "message": "Cache expiration updated successfully",
  "data": {
    "id": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
    "key": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
    "category": "indonesia>infrastruktur",
    "category_parent": "indonesia",
    "category_child": "infrastruktur",
    "expiration": "2026-04-25T00:00:00Z",
    "expiration_timestamp": 1777075200,
    "value": {
      "items": []
    }
  }
}
```

## 4. Delete Cache

### Request

```http
DELETE /api/admin-dashboard/caches/{id}
```

### Response

```json
{
  "success": true,
  "message": "Cache deleted successfully",
  "data": {
    "id": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1",
    "key": "side_cache:indonesia:infrastruktur:perwakilan:wilayah-afrik-amri1"
  }
}
```

## Catatan Update Expiration dan Rebuild Cache

- endpoint update hanya mengubah kolom `expiration`
- endpoint ini tidak menghitung ulang value cache
- jika ingin data cache dibangun ulang dari source query, hapus key cache lalu biarkan request aplikasi berikutnya membentuk cache baru
- endpoint detail mengirim `value` yang sudah dicoba decode dari payload cache
