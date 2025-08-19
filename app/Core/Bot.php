<?php

namespace App\Core;

use TelegramBot\Api\Client;
use TelegramBot\Api\Types\Message;

class Bot
{
    private $client;

    public function __construct(string $token)
    {
        $this->client = new Client($token);
    }

    /**
     * Запускает обработку входящих сообщений
     */
    public function run(callable $messageHandler)
    {
        // Перед этим добавь проверку, чтобы убедиться, что $messageHandler верно инициализирован
        if ($messageHandler) {
            $this->client->on($messageHandler);
            $this->client->run();
        } else {
            throw new \Exception("Message handler is not set properly.");
        }
    }


    /**
     * Отправляет сообщение в чат
     */
    public function sendMessage(int $chatId, string $message)
    {
        $this->client->sendMessage($chatId, $message);
    }
}
