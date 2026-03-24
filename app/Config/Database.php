<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    // This variable holds our single connection
    private static $connection = null;

    public static function connect()
    {
        // If we don't have a connection yet, create one
        if (self::$connection === null) {

            // Pull credentials from the .env file securely
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = (int) ($_ENV['DB_PORT'] ?? 3306);
            $dbName = $_ENV['DB_DATABASE'] ?? 'lgu_san_enrique_scholarship';
            $username = $_ENV['DB_USERNAME'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';
            $sslCa = trim((string) ($_ENV['DB_SSL_CA'] ?? ''));
            $sslVerifyServerCert = filter_var(
                $_ENV['DB_SSL_VERIFY_SERVER_CERT'] ?? 'true',
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE
            );
            $sslVerifyServerCert = $sslVerifyServerCert ?? true;

            try {
                // The DSN (Data Source Name) tells PDO exactly where to connect
                $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

                // PDO Options for maximum security and ease of use
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Crash and show exact error if SQL fails
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Always return data as a clean array
                    PDO::ATTR_EMULATE_PREPARES => false,                  // Use real prepared statements (blocks SQL injection)
                ];

                if ($sslCa !== '') {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
                    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $sslVerifyServerCert;
                    }
                }

                // Create the actual connection
                self::$connection = new PDO($dsn, $username, $password, $options);

            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                die('Database connection failed. Please contact the system administrator.');
            }
        }

        // Return the active connection
        return self::$connection;
    }
}
