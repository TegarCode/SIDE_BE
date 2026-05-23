<?php

namespace App\Repositories\ChatBot;

interface ChatBotRepositoryInterface
{
    public function processMessage(string $message, string $sector): array;
}
