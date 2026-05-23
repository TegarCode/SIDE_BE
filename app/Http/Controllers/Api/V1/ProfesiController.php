<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProfesiController extends Controller
{
  protected $cacheKey = 'side_cache:master:profesi:list';
  protected $cacheDuration = 86400;

  public function index()
  {
    $data = Cache::remember($this->cacheKey, $this->cacheDuration, function () {
      return DB::connection('server_mysql')
        ->table('tbprofesi')
        ->select('ID_Profesi as id', 'Profesi as nama', 'Kategori as kategori')
        ->whereNotNull('Profesi')
        ->orderBy('nama', 'asc')
        ->get();
    });

    return response()->json([
      'success' => true,
      'message' => 'Data profesi v1',
      'data' => $data
    ]);
  }
}
