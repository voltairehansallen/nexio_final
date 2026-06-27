<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
session_destroy();
header('Location: ' . BASE_URL . '/vitrine/index.php');
exit;
