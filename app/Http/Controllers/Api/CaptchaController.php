<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;

class CaptchaController extends Controller
{
  public function get(Request $request)
  {
    // 1. Buat ID unik (UUID) dan pastikan tidak ada di cache
    do {
      $id = Str::uuid()->toString();
    } while (Cache::has("captcha_{$id}"));

    // 2. Buat kode CAPTCHA acak
    $code = Str::upper(Str::random(6));

    // 3. Simpan ke cache selama 5 menit
    Cache::put("captcha:{$id}", $code, now()->addMinutes(5));

    // 4. Render gambar
    $w = 120;
    $h = 30;
    $img = imagecreate($w, $h);
    $bg  = imagecolorallocate($img, 255, 255, 255);
    $fg  = imagecolorallocate($img,   0,  0,  0);
    imagestring($img, 5, 15, 8, $code, $fg);
    ob_start();
    imagepng($img);
    $bin = ob_get_clean();
    imagedestroy($img);

    // 5. Kembalikan ID + image base64
    return response()->json([
      'id'    => $id,
      'image' => 'data:image/png;base64,' . base64_encode($bin),
    ]);
  }
}
