<?php
/**
 * DIAGNOSTIC FILE - DELETE AFTER DEBUGGING
 * Access: https://dev.oasisresearchcommunity.org/diag.php
 * Shows exactly what is failing on the server.
 */

header('Content-Type: application/json');
$results = [];

// 1. Check PHP version
$results['php_version'] = PHP_VERSION;

// 2. Check vendor/autoload.php exists
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
$results['vendor_exists'] = file_exists($vendorPath);

// 3. Check .env exists
$envPath = dirname(__DIR__) . '/.env';
$results['env_exists'] = file_exists($envPath);

// 4. Load .env and test DB
if ($results['vendor_exists']) {
    require_once $vendorPath;
    if ($results['env_exists']) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->load();
            $results['env_loaded'] = true;
            $results['db_host'] = $_ENV['DB_HOST'] ?? 'NOT SET';
            $results['db_name'] = $_ENV['DB_NAME'] ?? 'NOT SET';
            $results['db_user'] = $_ENV['DB_USER'] ?? 'NOT SET';

            // Test DB connection
            $pdo = new PDO(
                "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
                $_ENV['DB_USER'],
                $_ENV['DB_PASS']
            );
            $results['db_connected'] = true;

            // Check tables exist
            $stmt = $pdo->query("SHOW TABLES");
            $results['tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Check settings table has rows
            if (in_array('settings', $results['tables'])) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
                $results['settings_row_count'] = (int) $stmt->fetchColumn();
            } else {
                $results['settings_table_missing'] = true;
            }

        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
        }
    } else {
        $results['env_error'] = '.env file missing from project root (' . dirname(__DIR__) . ')';
    }
} else {
    $results['vendor_error'] = 'vendor/autoload.php not found — run: composer install --no-dev';
}

echo json_encode($results, JSON_PRETTY_PRINT);
