<?php

namespace App\Core;

use PDO;
use PDOException;
use App\Helpers\ResponseHelper;

class Database
{
    private static $instance = null;

    public static function connect()
    {
        if (self::$instance === null) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
            $dotenv->load();

            $host = $_ENV['DB_HOST'];
            $db = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USER'];
            $pass = $_ENV['DB_PASS'];

            try {
                self::$instance = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                ResponseHelper::internalError("A database error occurred. Please try again later.", []);
            }
        }

        return self::$instance;
    }
}
