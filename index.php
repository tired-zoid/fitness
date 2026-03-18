<?php

use App\Connectors\DbConnector;
use App\LogicManagers\TelegramLogicManager;
use Dotenv\Dotenv;
use TelegramBot\Api\BotApi;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
//require_once __DIR__ . '/app/Core/Logger.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();

$bot = new BotApi($_ENV["BOT_TOKEN"]);
$dbConnector = new DbConnector();
$TgLM = new TelegramLogicManager($bot, $dbConnector);

$data = file_get_contents("php://input");

if ($data) {

    $update = json_decode($data, true);

    try {
        if (isset($update['message'])) {
            $message = $update['message'];
            $TgLM->handleMessage($message);
        }

        if (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];

            $TgLM->handleCallback($callbackQuery);
        }
    } catch (Exception $e) {
        file_put_contents( __DIR__ . '/logs/logfile.log', 'Ошибка: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
}

echo "OK";
