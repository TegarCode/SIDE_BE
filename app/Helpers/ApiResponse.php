<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
  /**
   * Response sukses standar.
   */
  public static function success($data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
  {
    return response()->json([
      'success' => true,
      'message' => $message,
      'data'    => $data ?? [],
      'meta'    => $meta,
      'errors'  => null,
    ], $status);
  }

  /**
   * Response error umum.
   */
  public static function error(string $message = 'Terjadi kesalahan', $errors = null, int $status = 500): JsonResponse
  {
    // Sembunyikan detail error jika bukan local
    if (!app()->environment('local') && is_array($errors)) {
      $errors = null;
    }

    return response()->json([
      'success' => false,
      'message' => $message,
      'data'    => null,
      'meta'    => [],
      'errors'  => $errors,
    ], $status);
  }

  /**
   * Response error 404.
   */
  public static function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
  {
    return self::error($message, null, 404);
  }

  /**
   * Response error 401.
   */
  public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
  {
    return self::error($message, null, 401);
  }

  /**
   * Response error 403.
   */
  public static function forbidden(string $message = 'Forbidden'): JsonResponse
  {
    return self::error($message, null, 403);
  }

  /**
   * Response validasi gagal (422).
   */
  public static function validation($errors, string $message = 'Validasi gagal'): JsonResponse
  {
    return self::error($message, $errors, 422);
  }
}
