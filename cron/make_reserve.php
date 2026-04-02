<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Connectors\DbConnector;
use App\Requests\SimpleHttpRequest;
use TelegramBot\Api\BotApi;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$args = $_SERVER['argv'];
$arg1 = $args[1] ?? null;      // activity_id
$userId = $args[2] ?? null;    // user_id (chat_id)

if (!$arg1) {
    die("Usage: php make_reserve.php <activity_id> [user_id]\n");
}

$botToken = $_ENV["BOT_TOKEN"];
$chatId = $_ENV["CHAT_ID"];
$mChatId = $userId;

// Логирование
$logFile = __DIR__ . '/../logs/reserve_cron.log';
function writeLog($message, $level = 'INFO'): void {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [$level] $message" . PHP_EOL, FILE_APPEND);
}

writeLog("========== ЗАПУСК ==========");
writeLog("activity_id: $arg1, user_id: $mChatId");

// Получаем auth_token пользователя из БД
$db = new DbConnector();
$authToken = $db->getUserAuthToken($mChatId);

if (!$authToken) {
    $errorMsg = "У пользователя $mChatId нет auth_token в базе данных";
    writeLog($errorMsg, "ERROR");
    echo $errorMsg . "\n";

    // Отправляем уведомление админу
    $bot = new BotApi($botToken);
    $bot->sendMessage($chatId, "❌ $errorMsg");
    exit(1);
}

writeLog("Auth token получен из БД", "DEBUG");

$success = false;
$maxAttempts = 5;

for ($attempt = 1; $attempt <= $maxAttempts && !$success; $attempt++) {
    echo "Попытка $attempt из $maxAttempts\n";
    writeLog("Попытка $attempt из $maxAttempts", "DEBUG");

    // Используем auth_token из БД
    $header = [
        'Authorization: Bearer ' . $authToken,
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
        writeLog("Ответ API: " . substr($response, 0, 200), "DEBUG");

        if (str_contains($response, "success")) {
            $db->confirmReservation($arg1);
            $training = $db->getLastUserReservation($arg1, $mChatId);

            $success = true;

            $bot = new BotApi($botToken);
            $message = "✅ Успешно записались на занятие " . $training['name'] . " - " . $training['date_info'];

            // Отправляем уведомление конкретному пользователю
            $bot->sendMessage($mChatId, $message);
            // Также отправляем в основной чат для контроля
            $bot->sendMessage($chatId, "✅ Пользователь $mChatId записался на " . $training['name']);

            writeLog("Успешная запись! Уведомление отправлено пользователю $mChatId", "SUCCESS");

        } else {
            $decoded = json_decode($response);
            $errorText = $decoded->errors[0] ?? $decoded->details[0] ?? "Неизвестная ошибка";
            $errorCode = $decoded->code ?? 'N/A';

            writeLog("Ошибка API | Код: $errorCode | Текст: $errorText", "ERROR");
            echo "API вернул ошибку: $errorText\n";

            // Отправляем пользователю уведомление об ошибке
            if ($attempt == $maxAttempts) {
                $bot = new BotApi($botToken);
                $bot->sendMessage($mChatId, "❌ Не удалось записаться: $errorText");
            }

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
    $bot = new BotApi($botToken);
    $bot->sendMessage($chatId, "❌ Не удалось записаться после $maxAttempts попыток для пользователя $mChatId на занятие $arg1");
    writeLog("Не удалось записаться после $maxAttempts попыток", "ERROR");
    exit(1);
}

writeLog("========== ЗАВЕРШЕНИЕ ==========");