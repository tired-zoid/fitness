<?php

namespace App\Connectors;

require __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use DateTime;
use PDO;
use PDOException;
use RuntimeException;

class DbConnector
{
    private PDO $pdo;
    private string $logFile;

    public function __construct()
    {
        $this->loadEnvironment();
        $this->logFile = __DIR__ . '/../../logs/logfile.log';
        $this->connect();
    }

    private function loadEnvironment(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
    }

    private function connect(): void
    {
        $host = $_ENV['DB_HOST'] ?? '';
        $dbname = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_LOGIN'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';

        $this->validateCredentials($host, $dbname, $user);

        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $host, $dbname);
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $this->log('Database connection established successfully');
        } catch (PDOException $e) {
            $this->log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Unable to connect to database: ' . $e->getMessage());
        }
    }

    private function validateCredentials(string $host, string $dbname, string $user): void
    {
        if (empty($host) || empty($dbname) || empty($user)) {
            throw new RuntimeException('Database credentials are missing in environment configuration');
        }
    }

    public function insertReservation(array $data): bool
    {
        $requiredFields = ['activity_id', 'date_info', 'user', 'name', 'status'];
        $this->validateRequiredFields($requiredFields, $data);

        $data['date_info'] = $this->formatDateTime($data['date_info']);

        $sql = "INSERT INTO `reserves` (`activity_id`, `date_info`, `user`, `name`, `status`) 
                VALUES (:activity_id, :date_info, :user, :name, :status)";

        return $this->executeStatement($sql, $data);
    }

    public function getLastUserReservation(int $activityId, string $user): ?array
    {
        $sql = "SELECT * FROM `reserves` 
                WHERE `activity_id` = :activity_id AND `user` = :user 
                ORDER BY `id` DESC 
                LIMIT 1";

        $result = $this->executeStatement($sql, [
            ':activity_id' => $activityId,
            ':user' => $user
        ]);

        return $result ?: null;
    }

    public function getUserReservations(string $user): array
    {
        $sql = "SELECT * FROM `reserves` WHERE `user` = :user ORDER BY `date_info` DESC";
        $result = $this->executeStatement($sql, [':user' => $user]);

        return $result ?: [];
    }

    public function confirmReservation(int $activityId): bool
    {
        $sql = "UPDATE `reserves` SET `status` = :status WHERE `activity_id` = :id";

        return $this->executeStatement($sql, [
            ':status' => 'confirmed',
            ':id' => $activityId
        ]);
    }

    private function executeStatement(string $sql, array $params = []): mixed
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Return appropriate result based on query type
            if (stripos($sql, 'SELECT') === 0) {
                return $stmt->fetchAll();
            }

            return true;
        } catch (PDOException $e) {
            $this->log('Database error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            return stripos($sql, 'SELECT') === 0 ? [] : false;
        }
    }

    private function validateRequiredFields(array $fields, array $data): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new RuntimeException("Required field '{$field}' is missing or empty");
            }
        }
    }

    private function formatDateTime(string $dateTime): string
    {
        try {
            $date = new DateTime($dateTime);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            throw new RuntimeException('Invalid date format: ' . $dateTime);
        }
    }

    private function log(string $message): void
    {
        $logMessage = sprintf(
            '[%s] %s%s',
            date('Y-m-d H:i:s'),
            $message,
            PHP_EOL
        );

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commitTransaction(): void
    {
        $this->pdo->commit();
    }

    public function rollbackTransaction(): void
    {
        $this->pdo->rollBack();
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}