<?php
/**
 * Nexio S.A. — Connexion MySQL via PDO
 * Les paramètres DB sont lus depuis .env (via config/env.php ou constantes).
 */

// Charger .env si pas encore chargé
if (!function_exists('loadEnv')) {
    $envPath = dirname(__DIR__) . '/.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k) { putenv("$k=$v"); $_ENV[$k] = $v; }
        }
    }
}

// Priorité : variables d'environnement > constantes par défaut
defined('DB_HOST')    || define('DB_HOST',    getenv('MYSQL_HOST')     ?: 'localhost');
defined('DB_NAME')    || define('DB_NAME',    getenv('MYSQL_DATABASE') ?: 'nexio_db');
defined('DB_USER')    || define('DB_USER',    getenv('MYSQL_USER')     ?: 'root');
defined('DB_PASS')    || define('DB_PASS',    getenv('MYSQL_PASSWORD') ?: '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
