<?php

namespace App\Services;

class TaskScheduler
{
    public function setTask($dateTime, $id): bool
    {
        $month = $dateTime->format('m');
        $month = str_replace("0", "", $month);
        $day = $dateTime->format('d');
        $hour = $dateTime->format('H');
        $minute = $dateTime->format('i');

        $command = "/opt/php/8.3/bin/php /var/www/u3042487/data/fitness_scheduler/cron/make_reserve.php $id";
        $cronJob = "$minute $hour $day $month * $command";

        $taskAdded = false;
        exec("(crontab -l 2>/dev/null; echo '$cronJob') | crontab -", $output, $returnVar);

        if ($returnVar === 0) {
            $taskAdded = true;
        }
        return $taskAdded;
    }
}