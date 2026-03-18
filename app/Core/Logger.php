<?php

namespace App\Core;

class Logger
{
    private string $logDir;
    private string $defaultChannel;
    private array $channels = [];

    /**
     * @param string $logDir Директория для логов (относительно корня проекта)
     * @param string $defaultChannel Канал по умолчанию
     */
    public function __construct(string $logDir = 'logs', string $defaultChannel = 'app')
    {
        $this->logDir = rtrim($logDir, '/\\');
        $this->defaultChannel = $defaultChannel;
        $this->init();
    }

    /**
     * Создаёт директорию для логов, если её нет
     */
    private function init(): void
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    /**
     * Основной метод записи логов
     */
    public function log(string $message, string $level = 'INFO', string $channel = null): void
    {
        $channel = $channel ?? $this->defaultChannel;
        $date = date('Y-m-d H:i:s');
        $pid = getmypid(); // ID процесса — полезно для отладки

        // Формат: [2024-03-18 16:30:45] [INFO] [bot] Сообщение (PID: 12345)
        $logMessage = sprintf(
            "[%s] [%s] [%s] %s (PID: %s)%s",
            $date,
            strtoupper($level),
            $channel,
            $message,
            $pid,
            PHP_EOL
        );

        $filename = $this->logDir . '/' . $channel . '_' . date('Y-m-d') . '.log';
        file_put_contents($filename, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Удобные методы для разных уровней логирования
     */
    public function info(string $message, string $channel = null): void
    {
        $this->log($message, 'INFO', $channel);
    }

    public function error(string $message, string $channel = null): void
    {
        $this->log($message, 'ERROR', $channel);
    }

    public function warning(string $message, string $channel = null): void
    {
        $this->log($message, 'WARNING', $channel);
    }

    public function debug(string $message, string $channel = null): void
    {
//        if (defined('DEBUG_MODE') && DEBUG_MODE) {
//            $this->log($message, 'DEBUG', $channel);
//        }
    }

    /**
     * Логирование массива или объекта (удобно для отладки вебхуков)
     */
    public function dump($data, string $title = '', string $channel = null): void
    {
        $output = $title ? $title . ': ' : '';
        $output .= print_r($data, true);
        $this->debug($output, $channel);
    }

    /**
     * Очистка старых логов (например, старше 30 дней)
     */
    public function clean(int $days = 30): void
    {
        $files = glob($this->logDir . '/*.log');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $days * 24 * 60 * 60) {
                unlink($file);
            }
        }
    }
}