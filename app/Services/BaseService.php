<?php

namespace App\Services;

use App\Database\Database;

abstract class BaseService
{
    protected static $db;
    protected $table;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public static function setDatabase(Database $database): void
    {
        self::$db = $database;
    }

    /**
     * Проверяет существование записи в таблице по заданным условиям.
     */
    public function exists(array $conditions): bool
    {
        $where = self::$db->buildWhere($conditions);
        $result = self::$db->query("SELECT 1 FROM {$this->table} WHERE {$where['query']}", $where['params']);
        return !empty($result);
    }

    /**
     * Добавляет новую запись в таблицу.
     */
    public function add(array $data): void
    {
        self::$db->insert($this->table, $data);
    }

    /**
     * Удаляет запись из таблицы по заданным условиям.
     */
    public function delete(array $conditions): void
    {
        self::$db->delete($this->table, $conditions);
    }

    /**
     * Создаёт SQL-условие WHERE и массив параметров для запроса.
     * Возвращает массив с ключами "query" и "params".
     */
    private function buildWhere(array $conditions): array
    {
        $queryParts = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            $queryParts[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        return [
            'query' => implode(' AND ', $queryParts),
            'params' => $params,
        ];
    }
}
