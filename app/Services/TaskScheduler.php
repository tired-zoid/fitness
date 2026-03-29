<?php

namespace App\Services;

use Dotenv\Dotenv;

class TaskScheduler
{
    public function setTask($dateTime, $id): bool
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $month = $dateTime->format('m');
        $month = str_replace("0", "", $month);
        $day = $dateTime->format('d');
        $hour = $dateTime->format('H');
        $minute = $dateTime->format('i');

        $command = $_ENV["PHP_PATH"] . ' ' . $_ENV['MAKE_RESERVE_PATH'] . ' ' . $id;
        $cronJob = "$minute $hour $day $month * $command";

        $taskAdded = false;
        exec("(crontab -l 2>/dev/null; echo '$cronJob') | crontab -", $output, $returnVar);

        if ($returnVar === 0) {
            $taskAdded = true;
        }
        return $taskAdded;
    }
    // todo добавить функционал удаления крон задач после выполнения
}