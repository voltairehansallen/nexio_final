<?php
/**
 * Nexio S.A. — API Marketing IA
 * Endpoints : get_campagne, segmenter, generer, publicitees_user
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? (json_decode(file_get_contents('php://input'),true)['action'] ?? '');
$body   = json_decode(file_get_contents('php://input'),true) ?? [];
$pdo    = getDB();

switch($action) {

    case 'get_campagne':
        $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
        if (!$id) { echo json_encode(['error'=>'id requis']); break; }
        $s = $pdo->prepare("SELECT * FROM campagnes WHERE id_campagne=:i");
        $s->execute([':i'=>$id]); $c=$s->fetch();
        echo json_encode($c ?: ['error'=>'Campagne introuvable']);
        break;

    case 'segmenter':
        // Demande au Flask de segmenter + sauvegarde locale
        $ch = curl_init('http://127.0.0.1:5001/comportement');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'{}',CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_CONNECTTIMEOUT=>3]);
        $resp=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
        if ($err) { echo json_encode(['msg'=>'Serveur IA non disponible. Segments non générés.']); break; }
        $d = json_decode($resp,true) ?? [];
        $nb = count($d['resultats'] ?? []);
        echo json_encode(['msg'=>"$nb profils clients mis à jour avec segments IA."]);
        break;

    case 'publicites_user':
        $uid = (int)($body['id_user'] ?? 0);
        // Récupère profil IA de l'utilisateur
        $profil = null;
        if ($uid) {
            try {
                $s=$pdo->prepare("SELECT * FROM profils_ia WHERE id_user=:u");
                $s->execute([':u'=>$uid]); $profil=$s->fetch();
            } catch(PDOException){}
        }
        // Récupère publicités actives correspondant au segment
        try {
            if ($profil && $profil['segment']) {
                $pubs=$pdo->prepare("SELECT * FROM publicites WHERE statut='Active' AND (segment_cible=:seg OR segment_cible IS NULL) LIMIT 3");
                $pubs->execute([':seg'=>$profil['segment']]);
            } else {
                $pubs=$pdo->query("SELECT * FROM publicites WHERE statut='Active' LIMIT 3");
            }
            $pubs=$pubs->fetchAll();
        } catch(PDOException) { $pubs=[]; }

        // Si pas de pubs spécifiques, utilise IA pour générer du contenu personnalisé
        if (empty($pubs) && $profil) {
            $pubs = [[
                'id_pub'=>0,
                'titre'=>'Offre spéciale pour vous',
                'contenu'=>"Basé sur vos préférences (".($profil['categorie_preferee']??'tech').").",
                'lien'=>BASE_URL.'/vitrine/index.php',
            ]];
        }
        echo json_encode(['publicites'=>$pubs,'segment'=>$profil['segment']??null]);
        break;

    case 'log':
        // Enregistre log via API (depuis JS agents page)
        $agent   = $body['agent']   ?? 'unknown';
        $action2 = $body['action']  ?? 'run';
        $statut  = $body['statut']  ?? 'succès';
        $ms      = (int)($body['duree_ms'] ?? 0);
        try {
            $pdo->prepare("INSERT INTO log_analyses_ia(agent,action,statut,duree_ms) VALUES(:a,:ac,:s,:d)")
                ->execute([':a'=>$agent,':ac'=>$action2,':s'=>$statut,':d'=>$ms]);
        } catch(PDOException){}
        echo json_encode(['ok'=>true]);
        break;

    default:
        echo json_encode(['error'=>'Action inconnue : '.$action]);
}
