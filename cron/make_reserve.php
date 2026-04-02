<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Connectors\DbConnector;
use App\Requests\SimpleHttpRequest;
use TelegramBot\Api\BotApi;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Настройка логов
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/reserve_cron.log';

function writeLog($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [$level] $message" . PHP_EOL, FILE_APPEND);
}

$args = $_SERVER['argv'];
$arg1 = $args[1] ?? null;

if (!$arg1) {
    writeLog("Запуск без activity_id", "ERROR");
    die("Usage: php make_reserve.php <activity_id>\n");
}

writeLog("========== НАЧАЛО ЗАПУСКА ==========", "INFO");
writeLog("activity_id: $arg1", "INFO");

$botToken = $_ENV["BOT_TOKEN"];
$chatId = $_ENV["CHAT_ID"];
$mChatId = $_ENV["M_CHAT_ID"];
$success = false;
$maxAttempts = 1;

for ($attempt = 1; $attempt <= $maxAttempts && !$success; $attempt++) {
    writeLog("Попытка $attempt из $maxAttempts", "DEBUG");
    echo "Попытка $attempt из $maxAttempts\n";

    $header = [
        'Authorization: Bearer f8b76a15-42d6-42ec-a026-5fd17561db92', // мой токен
//        'Authorization: Bearer 91ef42dab154cc0f4567092f3205fb9bb5b1df7c', // мамин токен
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ];

    $data = [
        'scheduleId' => $arg1,
        'clubId' => $_ENV["CLUB_ID"]
    ];

    $client = new SimpleHttpRequest();
    $client->setHeader($header);

    try {
        $response = $client->post("https://mobifitness.ru/api/v8/account/reserve.json", $data);
        writeLog("Получен ответ: " . substr($response, 0, 200), "DEBUG");

        if (str_contains($response, "success")) {
            writeLog("Запись успешна!", "SUCCESS");
            $db = new DbConnector();
            $db->confirmReservation($arg1);
            $training = $db->getLastUserReservation($arg1, $chatId);
            $success = true;

            $bot = new BotApi($botToken);
            $message = "✅ Успешно записались на занятие " . $training['name'] . " - " . $training['date_info'];
            $bot->sendMessage($chatId, $message);
//            $bot->sendMessage($mChatId, $message);
            writeLog("Уведомление отправлено в Telegram", "INFO");
        } else {
            $decoded = json_decode($response);
            $errorText = $decoded->errors[0] ?? $decoded->details[0] ?? "Неизвестная ошибка";
            $errorCode = $decoded->code ?? 'N/A';

            // Запись ошибки в лог
            writeLog("Ошибка API | Код: $errorCode | Текст: $errorText | Попытка: $attempt", "ERROR");
            writeLog("Полный ответ: $response", "DEBUG");

            echo "API вернул ошибку: $errorText\n";

            if ($attempt < $maxAttempts) {
                sleep(5);
            }
        }
    } catch (Exception $e) {
        writeLog("Исключение: " . $e->getMessage(), "CRITICAL");
        echo "Ошибка запроса: " . $e->getMessage() . "\n";
        if ($attempt < $maxAttempts) {
            sleep(5);
        }
    }
}

if (!$success) {
    writeLog("Не удалось записаться после $maxAttempts попыток", "ERROR");
    $bot = new BotApi($botToken);
    $bot->sendMessage($chatId, "❌ Не удалось записаться после $maxAttempts попыток");
//    $bot->sendMessage($mChatId, "❌ Не удалось записаться после $maxAttempts попыток");
    exit(1);
}

writeLog("========== УСПЕШНОЕ ЗАВЕРШЕНИЕ ==========", "INFO");