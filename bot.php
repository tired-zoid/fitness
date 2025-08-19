<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;
use App\LogicManagers\TelegramLogicManager;
use Dotenv\Dotenv;
use TelegramBot\Api\BotApi;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$bot = new BotApi($_ENV["BOT_TOKEN"]);
$TgLM = new TelegramLogicManager($bot);

// Получаем данные, которые Telegram отправляет через вебхук
$data = file_get_contents("php://input");

if ($data) {
    // Преобразуем JSON в объект
    $update = json_decode($data, true);

    try {
        // Обработка обычного сообщения
        if (isset($update['message'])) {
            $message = $update['message'];
            $TgLM->handleMessage($message);
        }

        // Обработка нажатия на callback-кнопки
        if (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $TgLM->handleCallback($callbackQuery);
        }
    } catch (Exception $e) {
        error_log('Ошибка: ' . $e->getMessage());
    }
}

echo "OK";
