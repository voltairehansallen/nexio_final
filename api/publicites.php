<?php
/**
 * Nexio S.A. — API Publicités Personnalisées
 * Retourne des publicités selon profil IA utilisateur.
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = getDB();
$uid = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

// 1. Essai Flask si connecté
$pubs_ia = [];
if ($uid) {
    $ch = curl_init('http://127.0.0.1:5001/publicites');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['id_user'=>$uid]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_CONNECTTIMEOUT=>2]);
    $resp=curl_exec($ch); curl_close($ch);
    if ($resp) { $d=json_decode($resp,true); $pubs_ia=$d['publicites']??[]; }
}

// 2. Fallback DB produits populaires
if (empty($pubs_ia)) {
    $s=$pdo->prepare("SELECT p.id_produit,p.nom,p.prix,p.image,c.nom AS categorie,m.nom AS marque FROM produits p LEFT JOIN marques m ON p.id_marque=m.id_marque LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie LEFT JOIN categories c ON sc.id_categorie=c.id_categorie WHERE p.statut='Disponible' ORDER BY p.id_produit DESC LIMIT 3");
    $s->execute(); $prods=$s->fetchAll();
    foreach($prods as $p){
        $pubs_ia[]=['titre'=>'🔥 '.htmlspecialchars($p['nom'],ENT_QUOTES),'contenu'=>(htmlspecialchars($p['marque']??$p['categorie']??'',ENT_QUOTES)).' — Disponible maintenant','prix'=>number_format($p['prix'],0,'.',' ').' HTG','image'=>$p['image']??'','lien_relatif'=>'/vitrine/produit.php?id='.$p['id_produit'],'id_produit'=>$p['id_produit']];
    }
}

// Log impressions
try{$pdo->exec("UPDATE publicites SET impressions=impressions+1 WHERE statut='Active' LIMIT 3");}catch(PDOException){}

echo json_encode(['pubs'=>array_slice($pubs_ia,0,3),'personnal'=>$uid>0,'uid'=>$uid]);
