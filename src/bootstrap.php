<?php

use App\Helpers\Config;
use App\Helpers\ResponseHelper;

try {
    $base_url = Config::get('base_url');
    $admin_base_url = Config::get('admin_base_url');
    $api_base_url = Config::get('api_base_url');
    $no_reply_email = Config::get('no_reply_email');
    $email_host = Config::get('email_host');
    $support_email = Config::get('support_email');
    $smtp_port = Config::get('smtp_port');

    $facebook_url = Config::get('facebook_url');
    $instagram_url = Config::get('instagram_url');
    $telegram_url = Config::get('telegram_url');

    define('BASE_URL', $base_url);
    define('ADMIN_BASE_URL', $admin_base_url);
    define('API_BASE_URL', $api_base_url);
    define('NO_REPLY_EMAIL', $no_reply_email);
    define('EMAIL_HOST', $email_host);
    define('SUPPORT_EMAIL', $support_email);
    define('SMTP_PORT', intval($smtp_port));

    define('FACEBOOK_URL', $facebook_url);
    define('INSTAGRAM_URL', $instagram_url);
    define('TELEGRAM_URL', $telegram_url);

    define("BASE_DIR", dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR);
    define("API_BASE_DIR", dirname(__DIR__) . DIRECTORY_SEPARATOR);

    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} catch (PDOException $e) {
    error_log("Bootstrap Error: " . $e->getMessage());
    ResponseHelper::internalError();
}
