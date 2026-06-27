<?php
/**
 * Nexio S.A. — En-tête HTML partagé (Mobile First)
 * Usage : require_once BASE_PATH.'/includes/head.php';
 * Variables attendues : $pageTitle, $pageDesc (optionnel)
 */
$pageTitle = $pageTitle ?? 'Nexio S.A.';
$pageDesc  = $pageDesc  ?? 'Matériel informatique professionnel — Port-au-Prince, Haïti';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>">
<meta name="theme-color" content="#06080F">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — Nexio S.A.</title>

<!-- Preload fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- Nexio CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/nexio.css">

<!-- BASE_URL for JS -->
<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<!-- Progress bar -->
<div id="progress-bar"></div>
</head>
<body>
