<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NegaraController extends Controller
{
  protected $cacheKey = 'side_cache:master:negara:list-v2';
  protected $cacheKeyCommonNegara = 'side_cache:master:negara:list-common';
  protected $cacheKeyCommonWilayah = 'side_cache:master:wilayah:list-common';
  protected $cacheDuration = 86400;

  public function index(Request $request)
  {
    $idOrg = $request->input('ID_Org');
    $idBenua = $request->input('ID_Benua', $request->input('ID_benua'));

    if ($idOrg || $idBenua) {
      $query = DB::connection('server_mysql')
        ->table('tbnegara as n')
        ->select('n.Kode_Alpha3 as id', 'n.Kode_Alpha2 as kode_alpha2', 'n.Negara_IDN as nama')
        ->where('n.Kode_Alpha3', '!=', '0');

      if ($idOrg) {
        $query
          ->join('tborgnegara as on', 'n.Kode_Alpha3', '=', 'on.Kode_Alpha3')
          ->where('on.ID_Org', $idOrg);
      }

      if ($idBenua) {
        $query
          ->join('tbkawasan as w', 'n.ID_Wil', '=', 'w.ID_Wil')
          ->where('w.ID_Benua', $idBenua);
      }

      $data = $query->orderBy('n.Negara_IDN', 'asc')->get();
    } else {
      $data = Cache::remember($this->cacheKey, $this->cacheDuration, function () {
        return DB::connection('server_mysql')
          ->table('tbnegara')
          ->select('Kode_Alpha3 as id', 'Kode_Alpha2 as kode_alpha2', 'Negara_IDN as nama')
          ->where('Kode_Alpha3', '!=', '0')
          ->orderBy('Negara_IDN', 'asc')
          ->get();
      });
    }

    return response()->json([
      'success' => true,
      'message' => 'Data negara v1',
      'data' => $data,
    ]);
  }

  public function commonNegara()
  {
    $data = Cache::remember($this->cacheKeyCommonNegara, $this->cacheDuration, function () {
      return DB::connection('server_mysql')
        ->table('tbnegara as n')
        ->join('tbkawasan_satker as ks', 'n.ID_Wil_Kemlu', '=', 'ks.ID_Wil_Kemlu')
        ->join('tbdirjen as d', 'ks.ID_Dirjen', '=', 'd.ID_Dirjen')
        ->select('n.Kode_Alpha3 as id', 'n.Kode_Alpha2 as kode_alpha2', 'n.Negara_IDN as nama', 'ks.ID_Wil_Kemlu as id_wilayah', 'ks.Nama_Wil_Kemlu as wilayah', 'd.ID_Dirjen as id_dirjen', 'd.Nama_Dirjen as dirjen')
        ->where('n.Kode_Alpha3', '!=', '0')
        ->orderBy('n.Negara_IDN', 'asc')
        ->get()
        ->map(function ($item) {
          return [
            'id' => $item->id,
            'kode_alpha2' => $item->kode_alpha2,
            'nama' => $item->nama,
            'wilayah' => [
              'id' => $item->id_wilayah,
              'nama' => $item->wilayah,
              'dirjen' => [
                'id' => $item->id_dirjen,
                'nama' => $item->dirjen,
              ],
            ],
          ];
        });
    });

    return response()->json([
      'success' => true,
      'message' => 'Data negara v1',
      'data' => $data,
    ]);
  }

  public function commonWilayah()
  {
    $data = Cache::remember($this->cacheKeyCommonWilayah, $this->cacheDuration, function () {
      $rows = DB::connection('server_mysql')
        ->table('tbkawasan_satker as ks')
        ->join('tbdirjen as d', 'ks.ID_Dirjen', '=', 'd.ID_Dirjen')
        ->select([
          'd.ID_Dirjen as id_dirjen',
          'd.Nama_Dirjen as nama_dirjen',
          'ks.ID_Wil_Kemlu as id_wilayah',
          'ks.Nama_Wil_Kemlu as nama_wilayah',
        ])
        ->orderBy('d.ID_Dirjen', 'desc')
        ->orderBy('ks.Nama_Wil_Kemlu', 'asc')
        ->get();

      return $rows
        ->groupBy('id_dirjen')
        ->map(function ($items, $idDirjen) {
          $namaDirjen = $items->first()->nama_dirjen;

          return [
            'id' => (int) $idDirjen,
            'nama' => $namaDirjen,
            'wilayah' => $items->map(function ($r) {
              return [
                'id' => $r->id_wilayah,
                'nama' => $r->nama_wilayah,
              ];
            })->values(),
          ];
        })
        ->values();
    });

    return response()->json([
      'success' => true,
      'message' => 'Data wilayah v1',
      'data' => $data,
    ]);
  }
}
