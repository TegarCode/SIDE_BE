<?php

namespace App\Services;

use App\Repositories\ChatBot\ChatBotRepositoryInterface;

class ChatBotService
{
    protected $repo;

    public function __construct(ChatBotRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function handle(string $message): array
    {
        return $this->repo->processMessage($message);
    }
}
