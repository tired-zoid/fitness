<?php

namespace App\Services;

use Dotenv\Dotenv;

class TaskScheduler
{
    public function setTask($dateTime, $id, $userId): bool
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $month = $dateTime->format('m');
        $month = ltrim($month, '0');
        $day = $dateTime->format('d');
        $hour = $dateTime->format('H');
        $minute = $dateTime->format('i');

        // Формируем команду
        $command = $_ENV["PHP_PATH"] . ' ' . $_ENV['MAKE_RESERVE_PATH'] . ' ' . $id . ' ' . $userId;
        $cronJob = "$minute $hour $day $month * $command";

        // Проверяем, нет ли ТОЧНО ТАКОЙ ЖЕ задачи (с таким же временем)
        $existingCron = shell_exec("crontab -l 2>/dev/null | grep -F '$cronJob'");

        if (trim($existingCron)) {
            // Такая же задача уже существует
            $this->logTask("SKIPPED (already exists)", $id, $userId, $dateTime->format('Y-m-d H:i:s'));
            return false;
        }

        // Добавляем задачу
        $taskAdded = false;
        $crontab = shell_exec("crontab -l 2>/dev/null");

        if ($crontab === null) {
            $crontab = "";
        }

        $newCrontab = $crontab . $cronJob . "\n";

        // Записываем новый crontab через временный файл (более надежно)
        $tempFile = tempnam(sys_get_temp_dir(), 'cron_');
        file_put_contents($tempFile, $newCrontab);

        exec("crontab $tempFile 2>&1", $output, $returnVar);
        unlink($tempFile);

        if ($returnVar === 0) {
            $taskAdded = true;
            $this->logTask("ADDED", $id, $userId, $dateTime->format('Y-m-d H:i:s'));
        } else {
            $this->logTask("FAILED", $id, $userId, "Error: " . implode(", ", $output));
        }

        return $taskAdded;
    }


    private function logTask($action, $id, $userId, $time)
    {
        $logFile = __DIR__ . '/../../logs/taskscheduler.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logMessage = date('Y-m-d H:i:s') . " | $action | activity_id: $id | user_id: $userId | scheduled: $time\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}