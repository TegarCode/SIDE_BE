<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProdukHSController extends Controller
{
  public function index(Request $request)
  {
    $level = $request->input('level', 4);

    $query = DB::connection('server_mysql')
      ->table('tbharmonized')
      ->select(
        'hscode as value',
        DB::raw("CONCAT(hscode, ' - ', description) as label")
      )
      ->orderBy('hscode');

    if (in_array((string)$level, ['2', '4', '6'])) {
      $query->whereRaw('CHAR_LENGTH(hscode) = ?', [(int)$level]);
    }

    $data = $query->get();

    return response()->json([
      'success' => true,
      'message' => 'Data HSCode level ' . $level,
      'data' => $data
    ]);
  }
}
