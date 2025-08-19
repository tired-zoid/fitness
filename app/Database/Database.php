<?php

namespace App\Database;

use PDO;

class Database
{
    private $connection;

    public function __construct(string $db)
    {
        try {

            $this->connection = new PDO('sqlite:' . $db);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Метод для выполнения SQL-запросов (SELECT, INSERT, UPDATE, DELETE)
     *
     * @param string $sql - SQL-запрос
     * @param array $params - Параметры запроса (по умолчанию пустой массив)
     * @return mixed - Результат запроса (или true для запросов типа INSERT/UPDATE/DELETE)
     */
    public function query(string $sql, array $params = [])
    {
        $stmt = $this->connection->prepare($sql);

        // Привязываем параметры, если они переданы
        foreach ($params as $key => &$value) {
            $stmt->bindParam(':' . $key, $value);
        }

        // Выполняем запрос
        $stmt->execute();

        // Если запрос SELECT, возвращаем результат
        if (stripos($sql, 'SELECT') === 0) {
            return $stmt->fetchAll(PDO::FETCH_OBJ); // Возвращаем все строки результата
        }

        // Если запрос был INSERT, UPDATE или DELETE, возвращаем true
        return $stmt->rowCount() > 0; // Возвращаем true, если была изменена хотя бы одна строка
    }

    /**
     * Метод для выполнения вставки записи в таблицу
     */
    public function insert(string $table, array $data)
    {
        // Формируем SQL-запрос
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";

        return $this->query($sql, $data);
    }

    /**
     * Метод для выполнения обновления записи в таблице
     */
    public function update(string $table, array $data, array $conditions)
    {
        // Формируем SQL-запрос
        $set = "";
        foreach ($data as $key => $value) {
            $set .= "{$key} = :{$key}, ";
        }
        $set = rtrim($set, ", "); // Убираем лишнюю запятую в конце

        $where = "";
        foreach ($conditions as $key => $value) {
            $where .= "{$key} = :{$key}_condition AND ";
        }
        $where = rtrim($where, " AND"); // Убираем лишнюю "AND" в конце

        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        return $this->query($sql, array_merge($data, $this->addConditionPrefix($conditions)));
    }

    /**
     * Метод для выполнения удаления записи из таблицы
     */
    public function delete(string $table, array $conditions)
    {
        $where = "";
        foreach ($conditions as $key => $value) {
            $where .= "{$key} = :{$key}_condition AND ";
        }
        $where = rtrim($where, " AND");

        $sql = "DELETE FROM {$table} WHERE {$where}";

        return $this->query($sql, $this->addConditionPrefix($conditions));
    }

    /**
     * Приватный метод для добавления префикса "_condition" к ключам условий
     */
    private function addConditionPrefix(array $conditions)
    {
        $prefixedConditions = [];
        foreach ($conditions as $key => $value) {
            $prefixedConditions[$key . '_condition'] = $value;
        }
        return $prefixedConditions;
    }
}
