<?php
/**
 * Nexio S.A. — Connexion MySQL PDO
 */

if (!function_exists('loadEnv')) {
    $envPath = dirname(__DIR__) . '/.env';

    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {

            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            list($key,$value)=explode('=',$line,2);

            $key=trim($key);
            $value=trim($value);

            putenv("$key=$value");
            $_ENV[$key]=$value;
        }
    }
}

define('DB_HOST', getenv('MYSQL_HOST') ?: 'localhost');
define('DB_PORT', getenv('MYSQL_PORT') ?: '3306');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'nexio_db');
define('DB_USER', getenv('MYSQL_USER') ?: 'root');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: '');
define('DB_CHARSET','utf8mb4');

function getDB():PDO{

    static $pdo=null;

    if($pdo===null){

        $dsn=sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $pdo=new PDO($dsn,DB_USER,DB_PASS,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false
        ]);
    }

    return $pdo;
}