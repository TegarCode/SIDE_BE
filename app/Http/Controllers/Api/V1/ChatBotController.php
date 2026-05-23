<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatBotRequest;
use App\Repositories\ChatBot\ChatBotRepositoryInterface;
use Illuminate\Support\Facades\Log;

class ChatBotController extends Controller
{
  protected $chatbot;

  public function __construct(ChatBotRepositoryInterface $chatbot)
  {
    $this->chatbot = $chatbot;
  }

  public function handle(ChatBotRequest $request)
  {
    $message = $request->input('message');
    $sector  = $request->input('sector');

    try {
      $result = $this->chatbot->processMessage($message, $sector);
      return ApiResponse::success($result);
    } catch (\Exception $e) {
      return ApiResponse::error('Terjadi kesalahan sistem.');
    }
  }
}
