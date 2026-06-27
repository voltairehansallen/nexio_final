<?php
/**
 * Nexio S.A. — API bridge Admin → Python IA
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
requireAdmin();
header('Content-Type: application/json; charset=utf-8');

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

switch($action) {
    case 'campagne':
        $id = (int)($body['id_campagne'] ?? 0);
        $ch = curl_init('http://127.0.0.1:5001/campagne');
        curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode(['id_campagne'=>$id]), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30, CURLOPT_CONNECTTIMEOUT=>3]);
        $r=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
        if ($err) echo json_encode(['error'=>true,'msg'=>'Serveur IA non disponible. Lancez python/main.py']);
        else echo $r;
        break;

    case 'rapport':
        $jours = (int)($body['jours'] ?? 30);
        $ch = curl_init("http://127.0.0.1:5001/rapport-ventes?jours=$jours");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30, CURLOPT_CONNECTTIMEOUT=>3]);
        $r=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
        if ($err) echo json_encode(['error'=>true,'msg'=>'Serveur IA non disponible.']);
        else echo $r;
        break;

    case 'stocks':
        $ch = curl_init("http://127.0.0.1:5001/stocks");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30, CURLOPT_CONNECTTIMEOUT=>3]);
        $r=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
        if ($err) echo json_encode(['error'=>true,'msg'=>'Serveur IA non disponible.']);
        else echo $r;
        break;

    default:
        echo json_encode(['error'=>true,'msg'=>'Action inconnue']);
}
