<?php

namespace App\Connectors;
require __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
 use DateTime;
 use PDO;
 use PDOException;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
 class DbConnector {
     public string $host;
     public string $dbname;
     public string $user;
     public string $pass;
     public PDO $pdo;
     public function __construct ()
     {
         $this->host = $_ENV["DB_HOST"];
         $this->dbname = $_ENV["DB_NAME"];
         $this->user = $_ENV["DB_LOGIN"];
         $this->pass = $_ENV["DB_PASS"];
         $this->connect();
     }


     public function connect()
     {
         try {
             $this->pdo = new PDO("mysql:host=$this->host;dbname=$this->dbname;charset=utf8", $this->user, $this->pass, [
                 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             ]);
             file_put_contents( __DIR__ . '/../../logs/logfile.log', "Успешно подключились", FILE_APPEND);
             echo "Подключение успешно!";
         } catch (PDOException $e) {
             file_put_contents( __DIR__ . '/../../logs/logfile.log', 'ошибка подключения', FILE_APPEND);
             die("Ошибка подключения: " . $e->getMessage());
         }
     }

     public function insert($params)
     {
         $date = new DateTime($params['date_info']);
         $params['date_info'] = $date->format('Y-m-d H:i:s');
         try {
             $sql = "INSERT INTO `reserves` (`activity_id`, `date_info`, `user`, `name`, `status`) VALUES (:activity_id, :date_info, :user, :name, :status)";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute($params);
         } catch (PDOException $e) {
             file_put_contents( __DIR__ . '/../../logs/logfile.log', 'Ошибка: ' . $e->getMessage() . "\n", FILE_APPEND);
         }

     }

     public function select($params)
     {
         try {
             $sql = "SELECT * FROM `reserves` WHERE `activity_id` = :activity_id AND `user` = :user";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute($params);
             return $stmt->fetch();
         } catch (PDOException $e) {
             file_put_contents( __DIR__ . '/../../logs/logfile.log', 'Ошибка: ' . $e->getMessage() . "\n", FILE_APPEND);
         }

     }

     public function selectAll($user)
     {
         try {
             $sql = "SELECT * FROM `reserves` WHERE `user` = :user";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([':user' => $user]);
             return $stmt->fetchAll();
         } catch (PDOException $e) {
             file_put_contents( __DIR__ . '/../../logs/logfile.log', 'Ошибка: ' . $e->getMessage() . "\n", FILE_APPEND);
         }

     }
     public function update($id)
     {
         try {
             $sql = "UPDATE `reserves` SET `status` = :status WHERE `activity_id` = :id";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([
                 ':status' => "confirmed",
                 ':id' => $id
             ]);
         } catch (PDOException $e) {
             file_put_contents( __DIR__ . '/../../logs/logfile.log', 'Ошибка: ' . $e->getMessage() . "\n", FILE_APPEND);
         }

     }


 }