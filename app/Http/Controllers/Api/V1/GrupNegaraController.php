<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GrupNegaraController extends Controller
{
  protected $cacheKey = 'side_cache:master:grup-negara:list';
  protected $cacheDuration = 86400; // 1 hari (dalam detik)

  public function index()
  {
    $groupnegaras = Cache::remember($this->cacheKey, $this->cacheDuration, function () {
      $conn = DB::connection('server_mysql');

      $tbkelompoks = $conn
        ->table('tborgjenis')
        ->select('ID_Org as id', 'Abbreviation as nama', 'Organization')
        ->whereNotNull('Abbreviation')
        ->where('Abbreviation', '!=', '')
        ->orderBy('Abbreviation', 'asc')
        ->get();

      $tbbenua = $conn
        ->table('tbbenua')
        ->select('ID_benua as id', 'Benua as nama')
        ->orderBy('Benua', 'asc')
        ->get();

      // --- Countries per organisasi (tborgnegara) ---
      $orgIds = $tbkelompoks->pluck('id')->values();
      $orgNegaraMap = $orgIds->isEmpty() ? collect() : $conn
        ->table('tborgnegara as on')
        ->join('tbnegara as n', 'n.Kode_Alpha3', '=', 'on.Kode_Alpha3')
        ->whereIn('on.ID_Org', $orgIds->all())
        ->where('n.Kode_Alpha3', '!=', '0')
        ->select('on.ID_Org as id', 'n.Kode_Alpha3 as kode', 'n.Negara_IDN as nama')
        ->orderBy('n.Negara_IDN', 'asc')
        ->get()
        ->groupBy('id');

      // --- Countries per benua (tbkawasan -> tbnegara) ---
      $benuaIds = $tbbenua->pluck('id')->values();
      $benuaNegaraMap = $benuaIds->isEmpty() ? collect() : $conn
        ->table('tbkawasan as w')
        ->join('tbnegara as n', 'n.ID_Wil', '=', 'w.ID_Wil')
        ->whereIn('w.ID_Benua', $benuaIds->all())
        ->where('n.Kode_Alpha3', '!=', '0')
        ->select('w.ID_Benua as id', 'n.Kode_Alpha3 as kode', 'n.Negara_IDN as nama')
        ->orderBy('n.Negara_IDN', 'asc')
        ->get()
        ->groupBy('id');

      $tbkelompoks = $tbkelompoks
        ->map(function ($item) {
          return [
            'id' => $item->id,
            'nama' => "{$item->nama} ({$item->Organization})",
            'tipe' => 'ID_Org',
          ];
        })
        ->toArray();

      $tbbenua = $tbbenua
        ->map(function ($item) {
          return [
            'id' => $item->id,
            'nama' => $item->nama,
            'tipe' => 'ID_benua',
          ];
        })
        ->toArray();

      return array_merge($tbbenua, $tbkelompoks);
    });

    return response()->json([
      'success' => true,
      'message' => 'Data kelompok negara v1',
      'data' => $groupnegaras
    ]);
  }

  public function negaraByGroup()
  {
    $cacheKey = $this->cacheKey . ':with-negara';

    $groupnegaras = Cache::remember($cacheKey, $this->cacheDuration, function () {
      $conn = DB::connection('server_mysql');

      $tbkelompoks = $conn
        ->table('tborgjenis')
        ->select('ID_Org as id', 'Abbreviation as nama', 'Organization')
        ->whereNotNull('Abbreviation')
        ->where('Abbreviation', '!=', '')
        ->orderBy('Abbreviation', 'asc')
        ->get();

      $tbbenua = $conn
        ->table('tbbenua')
        ->select('ID_benua as id', 'Benua as nama')
        ->orderBy('Benua', 'asc')
        ->get();

      // --- Countries per organisasi (tborgnegara) ---
      $orgIds = $tbkelompoks->pluck('id')->values();
      $orgNegaraMap = $orgIds->isEmpty() ? collect() : $conn
        ->table('tborgnegara as on')
        ->join('tbnegara as n', 'n.Kode_Alpha3', '=', 'on.Kode_Alpha3')
        ->whereIn('on.ID_Org', $orgIds->all())
        ->where('n.Kode_Alpha3', '!=', '0')
        ->select('on.ID_Org as id', 'n.Kode_Alpha3 as kode', 'n.Negara_IDN as nama')
        ->orderBy('n.Negara_IDN', 'asc')
        ->get()
        ->groupBy('id');

      // --- Countries per benua (tbkawasan -> tbnegara) ---
      $benuaIds = $tbbenua->pluck('id')->values();
      $benuaNegaraMap = $benuaIds->isEmpty() ? collect() : $conn
        ->table('tbkawasan as w')
        ->join('tbnegara as n', 'n.ID_Wil', '=', 'w.ID_Wil')
        ->whereIn('w.ID_Benua', $benuaIds->all())
        ->where('n.Kode_Alpha3', '!=', '0')
        ->select('w.ID_Benua as id', 'n.Kode_Alpha3 as kode', 'n.Negara_IDN as nama')
        ->orderBy('n.Negara_IDN', 'asc')
        ->get()
        ->groupBy('id');

      $tbkelompoks = $tbkelompoks
        ->map(function ($item) use ($orgNegaraMap) {
          $negara = $orgNegaraMap->get($item->id, collect())
            ->map(fn($n) => ['kode' => $n->kode, 'nama' => $n->nama])
            ->values();

          return [
            'id' => $item->id,
            'nama' => "{$item->nama} ({$item->Organization})",
            'tipe' => 'ID_Org',
            'negara' => $negara,
          ];
        })
        ->toArray();

      $tbbenua = $tbbenua
        ->map(function ($item) use ($benuaNegaraMap) {
          $negara = $benuaNegaraMap->get($item->id, collect())
            ->map(fn($n) => ['kode' => $n->kode, 'nama' => $n->nama])
            ->values();

          return [
            'id' => $item->id,
            'nama' => $item->nama,
            'tipe' => 'ID_benua',
            'negara' => $negara,
          ];
        })
        ->toArray();

      return array_merge($tbbenua, $tbkelompoks);
    });

    return response()->json([
      'success' => true,
      'message' => 'Data kelompok negara dengan list negara v1',
      'data' => $groupnegaras
    ]);
  }
}
