<?php

namespace App\Services;

use App\Requests\SimpleHttpRequest;
use DateTime;

class ScheduleService
{
    private string $cacheDir = __DIR__ . '/../../cache';
    private int $cacheTTL = 3600;

    public function getSchedule(int $week, int $year): array
    {
        $cacheFile = $this->cacheDir . "/schedule_{$year}_{$week}.json";

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheTTL)) {
            $json = file_get_contents($cacheFile);
            $decoded = json_decode($json, true);
            return $decoded['schedule'] ?? [];
        }

        $header = [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Authorization: Bearer 91ef42dab154cc0f4567092f3205fb9bb5b1df7c',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'X-requested-with: XMLHttpRequest'
        ];

        $client = new SimpleHttpRequest();
        $client->setHeader($header);

        $response = $client->get("https://mobifitness.ru/api/v8/club/407/schedule.json?year=$year&week=$week");
        $result = trim($response);
        $json = str_replace(["\r", "\n"], '', $result);
        $json = mb_convert_encoding($json, 'UTF-8', 'auto');
        $decoded = json_decode($json, true);

        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        file_put_contents($cacheFile, json_encode($decoded));
        return $decoded['schedule'];
    }

    public function getTraining(string $id, string $date): ?array
    {
        $year = date('Y');
        list($day, $month) = explode('/', $date);
        $day = str_pad($day, 2, '0', STR_PAD_LEFT);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $dateString = "$year-$month-$day";

        $dateTime = DateTime::createFromFormat('Y-m-d', $dateString);
        $week = $dateTime->format('W');

        $schedule = $this->getSchedule($week, $year);

        foreach ($schedule as $training) {
            if (str_contains($training['id'], $id)) {
                if (strtotime($training['datetime']) > time()) {
                    $date1 = explode("T", $training['datetime'])[0];
                    if ($date1 == $dateString) {
                        return $training;
                    }

                }
            }
        }
        return null;
    }
}