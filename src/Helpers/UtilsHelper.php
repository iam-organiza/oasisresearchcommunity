<?php

namespace App\Helpers;

use App\Core\Database;
use DateTime;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use PDO;

class UtilsHelper
{

    public static function settings(string $type)
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM `settings` WHERE `type` = :type");
        $stmt->bindParam(":type", $type, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result->value;
    }

    public static function generateNumericOTP($n)
    {
        $generator = "1357902468";

        $result = "";

        for ($i = 1; $i <= $n; $i++) {
            $result .= substr($generator, rand() % strlen($generator), 1);
        }

        return $result;
    }

    public static function generateUniqueRef(string $tableName, string $uniqueColumn)
    {
        $db = Database::connect();
        $endString = Uuid::uuid4();

        $stmt = $db->prepare("SELECT * FROM `$tableName` WHERE `$uniqueColumn` ='$endString'");
        $stmt->execute();
        $count = $stmt->rowCount();
        if ($count > 0) {
            self::generateUniqueRef($tableName, $uniqueColumn);
        } else {
            return $endString;
        }
    }

    public static function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        if (!preg_match('/[\W]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return $errors;
    }

    public static function getDateTime()
    {
        $timestamp = time();

        //Supported Timezones: http://php.net/manual/en/timezones.php
        $userTimezone = 'Africa/Lagos';

        $dt = new DateTime();
        // Set the timestamp
        $dt->setTimestamp($timestamp);
        // Set the timezone
        $dt->setTimezone(new DateTimeZone($userTimezone));
        // Format the date
        $date = $dt->format('Y-m-d H:i:s');

        return $date;
    }

    public static function parseMultipartPut()
    {
        $input = fopen("php://input", "r");
        $data = stream_get_contents($input);

        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
        $boundary = $matches[1] ?? null;

        if (!$boundary) {
            return ['fields' => [], 'files' => []];
        }

        $blocks = preg_split("/-+$boundary/", $data);
        array_pop($blocks);

        $fields = [];
        $files = [];

        foreach ($blocks as $block) {
            if (empty(trim($block))) continue;

            // Parse name
            preg_match('/name="([^"]*)"/', $block, $nameMatch);
            $name = $nameMatch[1] ?? null;
            if (!$name) continue;

            // Is file?
            if (preg_match('/filename="([^"]*)"/', $block, $fileMatch)) {
                $filename = $fileMatch[1];
                preg_match('/Content-Type: ([^\n]*)/', $block, $typeMatch);
                $type = trim($typeMatch[1]);

                // Extract file content
                $content = substr($block, strpos($block, "\r\n\r\n") + 4);
                $content = rtrim($content, "\r\n");

                $tmpPath = sys_get_temp_dir() . '/' . uniqid('php_put_');
                file_put_contents($tmpPath, $content);

                $files[$name] = [
                    'name' => $filename,
                    'type' => $type,
                    'tmp_name' => $tmpPath,
                    'error' => 0,
                    'size' => strlen($content)
                ];
            } else {
                // Normal form field
                $value = trim(substr($block, strpos($block, "\r\n\r\n") + 4));
                $value = rtrim($value, "\r\n");
                $fields[$name] = $value;
            }
        }

        return ['fields' => $fields, 'files' => $files];
    }
}
