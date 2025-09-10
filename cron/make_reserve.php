<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Connectors\DbConnector;
use App\Requests\SimpleHttpRequest;
use TelegramBot\Api\BotApi;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$args = $_SERVER['argv'];

$arg1 = $args[1] ?? null;
$botToken = $_ENV["BOT_TOKEN"];

$chatId = $_ENV["CHAT_ID"];
$mChatId = $_ENV["M_CHAT_ID"];
$success = false;

$params = [
    "activity_id" => $arg1,
    "user" => $mChatId
];
$sessionId = "";

while (!$success) {
    $header = [
        'Authorization: Bearer 91ef42dab154cc0f4567092f3205fb9bb5b1df7c',
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36' // Браузерный агент
    ];

    $data = [
        'scheduleId' => $arg1,
        'clubId' => $_ENV["CLUB_ID"]
    ];

    $client = new SimpleHttpRequest();
    $client->setHeader($header);
    $response = $client->post("https://mobifitness.ru/api/v8/account/reserve.json", $data);

    if (str_contains($response,"success")) {
        $db = new DbConnector();
        $db->connect();
        $db->update($arg1);
        $training = $db->select($params);

        $success = true;
        $bot = new BotApi($botToken);
        $message = "Успешно записались на занятие " . $training['name'] . " - " . $training['date_info'];

        try {
            $bot->sendMessage($chatId, $message);
            $bot->sendMessage($mChatId, $message);
        } catch (Exception $e) {
            echo "Ошибка отправки сообщения: " . $e->getMessage() . "\n";
        }
    }
}
die;