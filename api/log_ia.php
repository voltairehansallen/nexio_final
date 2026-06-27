<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
header('Content-Type: application/json');
$b = json_decode(file_get_contents('php://input'),true) ?? [];
try {
    getDB()->prepare("INSERT INTO log_analyses_ia(agent,action,statut,duree_ms,tokens_utilises) VALUES(:a,:ac,:s,:d,:t)")
        ->execute([':a'=>$b['agent']??'',':ac'=>$b['action']??'manuel',':s'=>$b['statut']??'succès',':d'=>(int)($b['duree_ms']??0),':t'=>(int)($b['tokens']??0)]);
    echo json_encode(['ok'=>true]);
} catch(PDOException $e){ echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
