<?php
/**
 * Nexio S.A. — Configuration globale
 * Modifier BASE_URL selon votre installation XAMPP
 */
require_once __DIR__ . '/env.php';
define('APP_NAME',    'Nexio S.A.');
define('APP_VERSION', '2.0');
define(
    'PYTHON_API_URL',
    getenv('PYTHON_API_URL') ?: 'https://nexio-ai.onrender.com'
);
require_once __DIR__ . '/database.php';



// ── Session ──────────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 7200,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool { startSession(); return !empty($_SESSION['user_id']); }
function isAdmin(): bool    { return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'Administrateur'; }

function requireLogin(): void {
    startSession();
    if (!isLoggedIn()) { header('Location: ' . BASE_URL . '/auth/login.php'); exit; }
}
function requireAdmin(): void {
    startSession();
    if (!isAdmin()) { header('Location: ' . BASE_URL . '/auth/login.php?err=access'); exit; }
}

// ── Helpers ──────────────────────────────────────────────────
if (!function_exists('e')) {
    function e(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}

function csrf(): string {
    startSession();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verifyCsrf(string $token): bool {
    startSession();
    return !empty($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

function flash(string $type, string $msg): void {
    startSession();
    $_SESSION['flash'] = compact('type', 'msg');
}

function getFlash(): ?array {
    startSession();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

startSession();
