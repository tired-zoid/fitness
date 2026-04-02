<?php

namespace App\LogicManagers;

use App\Connectors\DbConnector;
use App\Services\ScheduleService;
use App\Services\TaskScheduler;
use DateTime;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\ReplyKeyboardMarkup;

class TelegramLogicManager
{
    private $bot;
    private $chatId;
    private $callbackData;
    private $messageText;
    private $lastMessageId = 0;
    private $schedule;
    private $taskScheduler;
    private $dbConnector;
    private $scheduleService;

    public function __construct(BotApi $bot, DbConnector $dbConnector)
    {
        $this->bot = $bot;

        $this->dbConnector = $dbConnector;
        $this->taskScheduler = new TaskScheduler();
        $this->scheduleService = new ScheduleService();


        date_default_timezone_set('Europe/Moscow');
    }

    /**
     * Преобразование команды в метод
     */
    private function getCommandMethod(string $command): string
    {
        return lcfirst(str_replace('_', '', ucwords(ltrim($command, '/'), '_')));
    }

    /**
     * Отправка ответа в бот
     */
    private function sendResponse(string $message, $keyboard = null, $deletePrev = false, $parseMode = null): void
    {
       if ($this->lastMessageId && $deletePrev) {
           $this->bot->deleteMessage($this->chatId, $this->lastMessageId);
       }

        $msg = $this->bot->sendMessage(
            $this->chatId,
            $message,
            $parseMode,
            false,
            null,
            $keyboard
        );
        $this->lastMessageId = $msg->getMessageId();

    }

    /**
     * Обрабатывает входящее сообщение
     */
    public function handleMessage(array $message): void
    {
        $this->chatId = $message['chat']['id'];
        $this->messageText = trim($message['text']);
        $parts = explode(' ', $this->messageText);

        $command = $parts[0];

        $this->params = array_slice($parts, 1);
        $methodName = $this->getCommandMethod($command);

        if (method_exists($this, $methodName)) {
            $this->$methodName();
        } else {
            $this->sendResponse("Неизвестная команда: {$command}");
        }
    }

    /**
     * Обрабатывает входящий колбэк
     */
    public function handleCallback($callbackQuery): void
    {
        $this->callbackData = $callbackQuery['data'];
        $this->chatId = $callbackQuery['message']['chat']['id'];

        $command = explode(":", $this->callbackData)[0];
        $methodName = $this->getCommandMethod($command);

        if (method_exists($this, $methodName)) {
            $this->$methodName();
        } else {
            $this->sendResponse("Неизвестная команда: {$command}");
        }
    }


    function generateInlineWeekButtons($offset = 0): InlineKeyboardMarkup
    {
        // Вычисляем начало недели с учетом сдвига
        $startOfWeek = strtotime('last monday', strtotime('tomorrow')) + $offset * 7 * 24 * 60 * 60;
        $buttons = [];

        // Генерация кнопок для дней недели
        for ($i = 0; $i < 7; $i++) {
            $date = date("Y-m-d", strtotime("+$i day", $startOfWeek));
            $dayOfWeek = date("l", strtotime($date));
            $buttons[] = [
                [
                    'text' => "$dayOfWeek ($date)",
                    'callback_data' => "day_selection:$date"
                ]
            ];
        }


        $buttons[] = [
            [
                'text' => "← Предыдущая неделя",
                'callback_data' => "prev_week:" . ($offset - 1)
            ],
            [
                'text' => "Следующая неделя →",
                'callback_data' => "next_week:" . ($offset + 1)
            ]
        ];

        return new InlineKeyboardMarkup($buttons);
    }



    private function start(): void
    {
        // Регистрируем пользователя
        $username = $this->message['from']['username'] ?? null;
        $firstName = $this->message['from']['first_name'] ?? null;
        $lastName = $this->message['from']['last_name'] ?? null;
        $fullName = trim($firstName . ' ' . $lastName);

        $this->dbConnector->createUserIfNotExists($this->chatId, $username, $fullName);

        // Проверяем, есть ли уже токен у пользователя
        $hasToken = $this->dbConnector->getUserAuthToken($this->chatId);

        $buttons = [
            ["/get_week", "/my_reserves"],
            ["/set_auth_token"]
        ];

        $keyboard = new ReplyKeyboardMarkup($buttons, false, true);

        $message = "Привет, $firstName! 👋\n\nЯ помогу тебе записаться на занятие.\n\n";

        if ($hasToken) {
            $message .= "✅ У вас уже установлен auth токен.\n";
        } else {
            $message .= "⚠️ *ВАЖНО:* Сначала установите auth токен командой /set_auth_token\n\n";
        }

        $message .= "\n📋 Доступные команды:\n";
        $message .= "🔹 /get_week - выбрать занятие\n";
        $message .= "🔹 /my_reserves - мои записи\n";
        $message .= "🔹 /set_auth_token - установить токен авторизации";

        $this->sendResponse($message, $keyboard, false, 'Markdown');
    }

    private function getWeek(): void
    {
        $this->sendResponse("Выберите день недели:", $this->generateInlineWeekButtons());
    }

    public function myReserves()
    {
        $reserves = $this->dbConnector->getUserReservations($this->chatId);
        $message = "";
        foreach ($reserves as $index => $reserve) {
            $status = $reserve['status'] === 'confirmed' ? '🟢 confirmed' : '🔴 waiting';

            $message .= "<b>" . ($index + 1) . ".</b> 🔹" . $reserve['date_info'] . "\n";
            $message .= $reserve['name'] . "\n";
            $message .= "👤 " . $reserve['user'] . " | " . $status . "\n\n";
        }
        $this->sendResponse("Ваши записи: \n" . $message, null, false, 'HTML');


    }

    public function reserve()
    {
        $exploded = explode(":", $this->callbackData);
        $id = $exploded[1];
        $date = $exploded[2];

        $training = $this->scheduleService->getTraining($id, $date);

        if (!$training) {
            $this->sendResponse("Ошибка записи" );
        }
        $datetime = new DateTime($training['beginDate']);
        $result = $this->taskScheduler->setTask($datetime, $id, $this->chatId);

        if ($result) {
            $this->insertReservationToDb($training);
            $this->sendResponse("Задача успешно создана.");
        } else {
            $this->sendResponse("Ошибка при создании задачи. Попробуйте еще раз.");
        }
    }

    private function insertReservationToDb(array $training): void
    {
        $data = [
            'activity_id' => $training['id'],
            'date_info' => $training['datetime'],
            'user' => $this->chatId,
            'name' => $training['activity']['title'],
            'status' => 'waiting',
        ];
        $this->dbConnector->insertReservation($data);
    }


    private function nextWeek(): void
    {
        $offset = (int)str_replace('next_week:', '', $this->callbackData);
        $keyboard = $this->generateInlineWeekButtons($offset);
        $this->sendResponse("Выберите день недели для следующей недели:", $keyboard, true);
    }

    private function prevWeek(): void
    {
        $offset = (int)str_replace('prev_week:', '', $this->callbackData);
        $keyboard = $this->generateInlineWeekButtons($offset);
        $this->sendResponse("Выберите день недели для предыдущей недели:", $keyboard, true);
    }

    private function daySelection(): void
    {
        $selectedDate = str_replace('day_selection:', '', $this->callbackData);
        $timestamp = strtotime($selectedDate);

        $year = date("Y", $timestamp);
        $week = date("W", $timestamp);

        if ($this->schedule = $this->scheduleService->getSchedule($week, $year)) {
            $buttons = [];
            $this->selectedDayTrainings = [];
            foreach ($this->schedule as $item) {
                if (str_contains($item['datetime'], $selectedDate)) {
                    $this->selectedDayTrainings[] = $item;

                    $timestamp = strtotime($item['datetime']);
                    $dateTime = date('H:i', $timestamp);
                    $date = date('j/m', $timestamp);

                    // Переносим время на новую строку
                    $title = $item['activity']['title'];
                    if (mb_strlen($title) > 16) {
                        $title = mb_substr($title, 0, 16) . '...';
                    }

                    $buttons[] = [[
                        'text' => "$title | 🕐$dateTime",
                        'callback_data' => "reserve:" . $item['id'] . ":" . $date
                    ]];
                }
            }
            $keyboard = new InlineKeyboardMarkup($buttons);

            $this->sendResponse("Расписание на $selectedDate:", $keyboard);
        } else {
            $this->sendResponse("Проблемы с получением расписания на выбранную дату. Попробуйте еще раз.");
        }
    }

    /**
     * Установка auth_token пользователя
     * Команда: /set_auth_token
     */
    private function setAuthToken(): void
    {
        // Проверяем, есть ли параметр
        if (empty($this->params)) {
            $message = "🔐 *Как получить auth_token:*\n\n";
            $message .= "1. Зайдите в приложение или на сайт фитнес-клуба\n";
            $message .= "2. Откройте инструменты разработчика (F12)\n";
            $message .= "3. Перейдите во вкладку Network (Сеть)\n";
            $message .= "4. Найдите любой запрос к API\n";
            $message .= "5. В заголовках найдите `Authorization: Bearer`\n";
            $message .= "6. Скопируйте токен после `Bearer `\n\n";
            $message .= "📝 *Отправьте токен командой:*\n";
            $message .= "`/set_auth_token ВАШ_ТОКЕН`";

            $this->sendResponse($message, null, false, 'Markdown');
            return;
        }

        $authToken = $this->params[0];

        // Простая валидация токена (проверяем, что он не пустой и похож на токен)
        if (strlen($authToken) < 10) {
            $this->sendResponse("❌ Токен слишком короткий. Убедитесь, что вы скопировали полный токен.");
            return;
        }

        // Сохраняем токен в БД
        $result = $this->dbConnector->updateUserAuthToken($this->chatId, $authToken);

        if ($result) {
            $this->sendResponse("✅ Auth токен успешно сохранен! Теперь вы можете записываться на занятия.");
        } else {
            $this->sendResponse("❌ Ошибка при сохранении токена. Попробуйте еще раз.");
        }
    }

}
