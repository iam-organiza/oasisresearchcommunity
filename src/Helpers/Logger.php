<?php

namespace App\Helpers;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static ?MonologLogger $logger = null;

    public static function getLogger(): MonologLogger
    {
        if (!self::$logger) {
            $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';

            // Create directory if it doesn't exist
            if (!file_exists(dirname($logFile))) {
                mkdir(dirname($logFile), 0777, true);
            }

            $stream = new StreamHandler($logFile, MonologLogger::DEBUG);
            $formatter = new LineFormatter(null, null, true, true);
            $stream->setFormatter($formatter);

            self::$logger = new MonologLogger('app');
            self::$logger->pushHandler($stream);
        }

        return self::$logger;
    }
}
