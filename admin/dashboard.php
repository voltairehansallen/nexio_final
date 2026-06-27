<?php
/**
 * Nexio S.A. — Administration Centrale
 * CRUD complet : produits, catégories, sous-catégories, marques,
 * fournisseurs, commandes, clients, stocks, feedbacks,
 * messages, campagnes, journaux
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
requireAdmin();
$pdo  = getDB();
$page = trim($_GET['page'] ?? 'dashboard');

/* ── helpers ────────────────────────────────────────────────── */
function intG(string $k, int $def=0): int   { return isset($_GET[$k])  ? (int)$_GET[$k]  : $def; }
function strG(string $k, string $def=''): string { return trim($_GET[$k]  ?? $def); }
function strP(string $k, string $def=''): string { return trim($_POST[$k] ?? $def); }

function paginate(PDO $pdo, string $sql, array $params, int $pg, int $pp=15): array {
    $c = $pdo->prepare("SELECT COUNT(*) FROM ($sql) t");
    $c->execute($params); $total=(int)$c->fetchColumn();
    $pages = max(1,(int)ceil($total/$pp));
    $pg    = max(1,min($pg,$pages));
    $off   = ($pg-1)*$pp;
    $s     = $pdo->prepare("$sql LIMIT $pp OFFSET $off");
    $s->execute($params);
    return ['rows'=>$s->fetchAll(),'total'=>$total,'pages'=>$pages,'page'=>$pg,'pp'=>$pp];
}

/* ── stats ──────────────────────────────────────────────────── */
$stats = [
    'produits'   => (int)$pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn(),
    'commandes'  => (int)$pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn(),
    'clients'    => (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.id_role=r.id_role WHERE r.nom='Client'")->fetchColumn(),
    'ca'         => (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM commandes WHERE statut!='Annulée'")->fetchColumn(),
    'en_attente' => (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE statut='En attente'")->fetchColumn(),
    'stock_bas'  => (int)$pdo->query("SELECT COUNT(*) FROM produits WHERE quantite<=seuil_alerte")->fetchColumn(),
    'feedbacks'  => (int)$pdo->query("SELECT COUNT(*) FROM feedbacks WHERE statut='En attente'")->fetchColumn(),
    'messages'   => 0,
];
try { $stats['messages']=(int)$pdo->query("SELECT COUNT(*) FROM messages_contact WHERE statut='Non lu'")->fetchColumn(); } catch(PDOException){}

$flash = getFlash();

/* ════════════════════════════════════════════════════════════
   TRAITEMENTS POST
   ════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrf($_POST['csrf']??'')) {
    $action = strP('action');

    /* ─ Produits ─────────────────────────────────────────── */
    if ($action==='save_produit') {
        $id   = (int)strP('id');
        $img  = strP('image');

        // Upload fichier
        if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error']===UPLOAD_ERR_OK) {
            $ext   = strtolower(pathinfo($_FILES['image_file']['name'],PATHINFO_EXTENSION));
            $allow = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext,$allow)) {
                $dir = BASE_PATH.'/assets/uploads/produits/';
                if (!is_dir($dir)) mkdir($dir,0755,true);
                $fn  = 'prod_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
                if (move_uploaded_file($_FILES['image_file']['tmp_name'],$dir.$fn)) {
                    $img = BASE_URL.'/assets/uploads/produits/'.$fn;
                }
            }
        }

        $bind = [
            ':sc'=>strP('id_sous_categorie')?:null, ':ma'=>strP('id_marque')?:null,
            ':fo'=>strP('id_fournisseur')?:null,    ':nom'=>strP('nom'),
            ':desc'=>strP('description'),           ':prix'=>(float)strP('prix'),
            ':cout'=>strP('cout')?(float)strP('cout'):null,
            ':qty'=>(int)strP('quantite'),          ':seuil'=>(int)(strP('seuil_alerte')?:5),
            ':img'=>$img,                           ':gar'=>strP('garantie'),
            ':stat'=>strP('statut')?:'Disponible',
        ];
        if ($id) {
            $pdo->prepare("UPDATE produits SET id_sous_categorie=:sc,id_marque=:ma,id_fournisseur=:fo,nom=:nom,description=:desc,prix=:prix,cout=:cout,quantite=:qty,seuil_alerte=:seuil,image=:img,garantie=:gar,statut=:stat WHERE id_produit=:i")->execute(array_merge($bind,[':i'=>$id]));
            flash('success','Produit mis à jour.');
        } else {
            $pdo->prepare("INSERT INTO produits(id_sous_categorie,id_marque,id_fournisseur,nom,description,prix,cout,quantite,seuil_alerte,image,garantie,statut) VALUES(:sc,:ma,:fo,:nom,:desc,:prix,:cout,:qty,:seuil,:img,:gar,:stat)")->execute($bind);
            flash('success','Produit ajouté !');
        }
        header('Location: dashboard.php?page=produits'); exit;
    }
    if ($action==='delete_produit') { $pdo->prepare("DELETE FROM produits WHERE id_produit=:i")->execute([':i'=>(int)strP('id')]); flash('success','Produit supprimé.'); header('Location: dashboard.php?page=produits'); exit; }

    /* ─ Catégories ───────────────────────────────────────── */
    if ($action==='save_categorie') {
        $id=$id=(int)strP('id'); $nom=strP('nom');
        if($id) $pdo->prepare("UPDATE categories SET nom=:n WHERE id_categorie=:i")->execute([':n'=>$nom,':i'=>$id]);
        else    $pdo->prepare("INSERT INTO categories(nom) VALUES(:n)")->execute([':n'=>$nom]);
        flash('success','Catégorie enregistrée.'); header('Location: dashboard.php?page=categories'); exit;
    }
    if ($action==='delete_categorie') { try{$pdo->prepare("DELETE FROM categories WHERE id_categorie=:i")->execute([':i'=>(int)strP('id')]);flash('success','Supprimée.');}catch(PDOException){flash('danger','Impossible : produits liés.');} header('Location: dashboard.php?page=categories'); exit; }

    /* ─ Sous-catégories ──────────────────────────────────── */
    if ($action==='save_sous_cat') {
        $id=(int)strP('id'); $nom=strP('nom'); $cat=(int)strP('id_categorie');
        if($id) $pdo->prepare("UPDATE sous_categories SET nom=:n,id_categorie=:c WHERE id_sous_categorie=:i")->execute([':n'=>$nom,':c'=>$cat,':i'=>$id]);
        else    $pdo->prepare("INSERT INTO sous_categories(nom,id_categorie) VALUES(:n,:c)")->execute([':n'=>$nom,':c'=>$cat]);
        flash('success','Sous-catégorie enregistrée.'); header('Location: dashboard.php?page=sous_categories'); exit;
    }
    if ($action==='delete_sous_cat') { try{$pdo->prepare("DELETE FROM sous_categories WHERE id_sous_categorie=:i")->execute([':i'=>(int)strP('id')]);flash('success','Supprimée.');}catch(PDOException){flash('danger','Impossible.');} header('Location: dashboard.php?page=sous_categories'); exit; }

    /* ─ Marques ──────────────────────────────────────────── */
    if ($action==='save_marque') {
        $id=(int)strP('id'); $nom=strP('nom'); $pays=strP('pays');
        if($id) $pdo->prepare("UPDATE marques SET nom=:n,pays_origine=:p WHERE id_marque=:i")->execute([':n'=>$nom,':p'=>$pays,':i'=>$id]);
        else    $pdo->prepare("INSERT INTO marques(nom,pays_origine) VALUES(:n,:p)")->execute([':n'=>$nom,':p'=>$pays]);
        flash('success','Marque enregistrée.'); header('Location: dashboard.php?page=marques'); exit;
    }
    if ($action==='delete_marque') { try{$pdo->prepare("DELETE FROM marques WHERE id_marque=:i")->execute([':i'=>(int)strP('id')]);flash('success','Supprimée.');}catch(PDOException){flash('danger','Impossible.');} header('Location: dashboard.php?page=marques'); exit; }

    /* ─ Fournisseurs ─────────────────────────────────────── */
    if ($action==='save_fournisseur') {
        $id=(int)strP('id');
        $bind=[':n'=>strP('nom'),':e'=>strP('email'),':t'=>strP('telephone'),':a'=>strP('adresse'),':p'=>strP('pays')];
        if($id) $pdo->prepare("UPDATE fournisseurs SET nom=:n,email=:e,telephone=:t,adresse=:a,pays=:p WHERE id_fournisseur=:i")->execute(array_merge($bind,[':i'=>$id]));
        else    $pdo->prepare("INSERT INTO fournisseurs(nom,email,telephone,adresse,pays) VALUES(:n,:e,:t,:a,:p)")->execute($bind);
        flash('success','Fournisseur enregistré.'); header('Location: dashboard.php?page=fournisseurs'); exit;
    }
    if ($action==='delete_fournisseur') { try{$pdo->prepare("DELETE FROM fournisseurs WHERE id_fournisseur=:i")->execute([':i'=>(int)strP('id')]);flash('success','Supprimé.');}catch(PDOException){flash('danger','Impossible.');} header('Location: dashboard.php?page=fournisseurs'); exit; }

    /* ─ Commandes ────────────────────────────────────────── */
    if ($action==='update_statut') {
        $pdo->prepare("UPDATE commandes SET statut=:s WHERE id_commande=:i")->execute([':s'=>strP('statut'),':i'=>(int)strP('id')]);
        flash('success','Statut mis à jour.'); header('Location: dashboard.php?page=commandes'); exit;
    }

    /* ─ Clients ──────────────────────────────────────────── */
    if ($action==='toggle_client') {
        $u=(int)strP('id');
        $cur=$pdo->prepare("SELECT statut FROM users WHERE id_user=:i"); $cur->execute([':i'=>$u]); $c=$cur->fetchColumn();
        $pdo->prepare("UPDATE users SET statut=:s WHERE id_user=:i")->execute([':s'=>$c==='Actif'?'Inactif':'Actif',':i'=>$u]);
        flash('success','Statut client mis à jour.'); header('Location: dashboard.php?page=clients'); exit;
    }
    if ($action==='delete_client') {
        try{$pdo->prepare("DELETE FROM users WHERE id_user=:i")->execute([':i'=>(int)strP('id')]);flash('success','Client supprimé.');}
        catch(PDOException){flash('danger','Impossible.');}
        header('Location: dashboard.php?page=clients'); exit;
    }

    /* ─ Feedbacks ────────────────────────────────────────── */
    if ($action==='update_feedback') {
        $pdo->prepare("UPDATE feedbacks SET statut=:s WHERE id_feedback=:i")->execute([':s'=>strP('statut'),':i'=>(int)strP('id')]);
        flash('success','Feedback mis à jour.'); header('Location: dashboard.php?page=feedbacks'); exit;
    }
    if ($action==='delete_feedback') {
        $pdo->prepare("DELETE FROM feedbacks WHERE id_feedback=:i")->execute([':i'=>(int)strP('id')]);
        flash('success','Supprimé.'); header('Location: dashboard.php?page=feedbacks'); exit;
    }

    /* ─ Messages contact ─────────────────────────────────── */
    if ($action==='update_message') {
        try{$pdo->prepare("UPDATE messages_contact SET statut=:s WHERE id_contact=:i")->execute([':s'=>strP('statut'),':i'=>(int)strP('id')]);flash('success','Mis à jour.');}catch(PDOException){}
        header('Location: dashboard.php?page=messages'); exit;
    }

    /* ─ Campagnes ────────────────────────────────────────── */
    if ($action==='save_campagne') {
        $id=(int)strP('id');
        $bind=[':n'=>strP('nom'),':ca'=>strP('canal'),':co'=>strP('contenu'),':d'=>strP('date_envoi')?:null,':s'=>strP('statut')?:'Brouillon'];
        if($id) $pdo->prepare("UPDATE campagnes SET nom=:n,canal=:ca,contenu=:co,date_envoi=:d,statut=:s WHERE id_campagne=:i")->execute(array_merge($bind,[':i'=>$id]));
        else    $pdo->prepare("INSERT INTO campagnes(nom,canal,contenu,date_envoi,statut) VALUES(:n,:ca,:co,:d,:s)")->execute($bind);
        flash('success','Campagne enregistrée.'); header('Location: dashboard.php?page=campagnes'); exit;
    }
    if ($action==='delete_campagne') {
        $pdo->prepare("DELETE FROM campagnes WHERE id_campagne=:i")->execute([':i'=>(int)strP('id')]);
        flash('success','Campagne supprimée.'); header('Location: dashboard.php?page=campagnes'); exit;
    }
}

/* ════════════════════════════════════════════════════════════
   DONNÉES SELON PAGE
   ════════════════════════════════════════════════════════════ */
$d=[]; $pg=intG('pg',1); $pp=15;

switch($page) {
    case 'produits':
        $q=strG('q'); $cat=intG('cat'); $stat=strG('stat');
        $w=[]; $p2=[];
        if($q){$w[]="(p.nom LIKE :q OR p.description LIKE :q2)";$p2[':q']="%$q%";$p2[':q2']="%$q%";}
        if($cat){$w[]="sc.id_categorie=:cat";$p2[':cat']=$cat;}
        if($stat){$w[]="p.statut=:stat";$p2[':stat']=$stat;}
        $wh=$w?'WHERE '.implode(' AND ',$w):'';
        $sql="SELECT p.*,m.nom AS marque,c.nom AS categorie FROM produits p LEFT JOIN marques m ON p.id_marque=m.id_marque LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie LEFT JOIN categories c ON sc.id_categorie=c.id_categorie $wh ORDER BY p.id_produit DESC";
        $d=paginate($pdo,$sql,$p2,$pg,$pp);
        $d['sous_cats']=$pdo->query("SELECT sc.*,c.nom AS cat FROM sous_categories sc LEFT JOIN categories c ON sc.id_categorie=c.id_categorie ORDER BY c.nom,sc.nom")->fetchAll();
        $d['marques']=$pdo->query("SELECT * FROM marques ORDER BY nom")->fetchAll();
        $d['fournisseurs']=$pdo->query("SELECT * FROM fournisseurs ORDER BY nom")->fetchAll();
        $d['categories']=$pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
        $d['q']=$q;$d['cat_f']=$cat;$d['stat_f']=$stat;
        // Produit édité ?
        if(intG('edit')) { $e=$pdo->prepare("SELECT * FROM produits WHERE id_produit=:i"); $e->execute([':i'=>intG('edit')]); $d['edit']=$e->fetch(); }
        break;

    case 'categories':
        $sql="SELECT c.*,(SELECT COUNT(*) FROM sous_categories WHERE id_categorie=c.id_categorie) AS nb_sc,(SELECT COUNT(*) FROM produits p JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie WHERE sc.id_categorie=c.id_categorie) AS nb_prod FROM categories c ORDER BY c.nom";
        $d=paginate($pdo,$sql,[],$pg);
        if(intG('edit')){$e=$pdo->prepare("SELECT * FROM categories WHERE id_categorie=:i");$e->execute([':i'=>intG('edit')]);$d['edit']=$e->fetch();}
        break;

    case 'sous_categories':
        $q=strG('q'); $w=$q?"WHERE sc.nom LIKE :q OR c.nom LIKE :q2":''; $p2=$q?[':q'=>"%$q%",':q2'=>"%$q%"]:[];
        $sql="SELECT sc.*,c.nom AS categorie FROM sous_categories sc LEFT JOIN categories c ON sc.id_categorie=c.id_categorie $w ORDER BY c.nom,sc.nom";
        $d=paginate($pdo,$sql,$p2,$pg);
        $d['categories']=$pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
        $d['q']=$q;
        if(intG('edit')){$e=$pdo->prepare("SELECT * FROM sous_categories WHERE id_sous_categorie=:i");$e->execute([':i'=>intG('edit')]);$d['edit']=$e->fetch();}
        break;

    case 'marques':
        $q=strG('q'); $w=$q?"WHERE nom LIKE :q":''; $p2=$q?[':q'=>"%$q%"]:[];
        $sql="SELECT m.*,(SELECT COUNT(*) FROM produits WHERE id_marque=m.id_marque) AS nb_produits FROM marques m $w ORDER BY nom";
        $d=paginate($pdo,$sql,$p2,$pg);
        $d['q']=$q;
        if(intG('edit')){$e=$pdo->prepare("SELECT * FROM marques WHERE id_marque=:i");$e->execute([':i'=>intG('edit')]);$d['edit']=$e->fetch();}
        break;

    case 'fournisseurs':
        $q=strG('q'); $w=$q?"WHERE nom LIKE :q OR pays LIKE :q2":''; $p2=$q?[':q'=>"%$q%",':q2'=>"%$q%"]:[];
        $sql="SELECT f.*,(SELECT COUNT(*) FROM produits WHERE id_fournisseur=f.id_fournisseur) AS nb_produits FROM fournisseurs f $w ORDER BY nom";
        $d=paginate($pdo,$sql,$p2,$pg);
        $d['q']=$q;
        if(intG('edit')){$e=$pdo->prepare("SELECT * FROM fournisseurs WHERE id_fournisseur=:i");$e->execute([':i'=>intG('edit')]);$d['edit']=$e->fetch();}
        break;

    case 'commandes':
        $sf=strG('statut'); $w=$sf?"WHERE c.statut=:s":''; $p2=$sf?[':s'=>$sf]:[];
        $sql="SELECT c.*,u.nom,u.prenom,u.email FROM commandes c LEFT JOIN users u ON c.id_user=u.id_user $w ORDER BY c.date_commande DESC";
        $d=paginate($pdo,$sql,$p2,$pg);
        $d['statut_f']=$sf;
        if(intG('detail')){
            $cmd=$pdo->prepare("SELECT c.*,u.nom,u.prenom,u.email,u.telephone FROM commandes c LEFT JOIN users u ON c.id_user=u.id_user WHERE c.id_commande=:i");
            $cmd->execute([':i'=>intG('detail')]); $d['detail']=$cmd->fetch();
            $dtl=$pdo->prepare("SELECT dc.*,p.nom,p.image FROM details_commandes dc LEFT JOIN produits p ON dc.id_produit=p.id_produit WHERE dc.id_commande=:i");
            $dtl->execute([':i'=>intG('detail')]); $d['detail_items']=$dtl->fetchAll();
        }
        break;

    case 'clients':
        $q=strG('q'); $sf=strG('statut');
        $w=[]; $p2=[];
        if($q){$w[]="(u.nom LIKE :q OR u.prenom LIKE :q2 OR u.email LIKE :q3)";$p2[':q']="%$q%";$p2[':q2']="%$q%";$p2[':q3']="%$q%";}
        if($sf){$w[]="u.statut=:s";$p2[':s']=$sf;}
        $wh=$w?'WHERE '.implode(' AND ',$w):'';
        $sql="SELECT u.*,r.nom AS role,(SELECT COUNT(*) FROM commandes WHERE id_user=u.id_user) AS nb_cmd,(SELECT COALESCE(SUM(montant),0) FROM commandes WHERE id_user=u.id_user AND statut!='Annulée') AS ca_total FROM users u JOIN roles r ON u.id_role=r.id_role WHERE r.nom='Client' ".($wh?"AND ".str_replace('WHERE ','',$wh):'')." ORDER BY u.date_creation DESC";
        $sql="SELECT u.*,r.nom AS role,(SELECT COUNT(*) FROM commandes WHERE id_user=u.id_user) AS nb_cmd FROM users u JOIN roles r ON u.id_role=r.id_role WHERE r.nom='Client' $wh ORDER BY u.date_creation DESC";
        $d=paginate($pdo,$sql,$p2,$pg);
        $d['q']=$q;$d['statut_f']=$sf;
        break;

    case 'stocks':
        $sf=strG('alerte');
        $w=$sf==='bas'?'WHERE p.quantite<=p.seuil_alerte':($sf==='rupture'?'WHERE p.quantite=0':'');
        $sql="SELECT p.id_produit,p.nom,p.quantite,p.seuil_alerte,p.statut,m.nom AS marque FROM produits p LEFT JOIN marques m ON p.id_marque=m.id_marque $w ORDER BY p.quantite ASC";
        $d=paginate($pdo,$sql,[],$pg);
        $d['alerte_f']=$sf;
        break;

    case 'feedbacks':
        $sf=strG('statut'); $snote=intG('note');
        $w=[]; $p2=[];
        if($sf){$w[]="f.statut=:s";$p2[':s']=$sf;}
        if($snote){$w[]="f.note=:n";$p2[':n']=$snote;}
        $wh=$w?'WHERE '.implode(' AND ',$w):'';
        $sql="SELECT f.*,u.prenom,u.nom AS nom_user FROM feedbacks f LEFT JOIN users u ON f.id_user=u.id_user $wh ORDER BY f.date_feedback DESC";
        $d=paginate($pdo,$sql,$p2,$pg);
        $d['statut_f']=$sf;$d['note_f']=$snote;
        break;

    case 'messages':
        $sf=strG('statut');
        $p2=[]; $wh='';
        try{
            $w=$sf?"WHERE statut=:s":''; $p2=$sf?[':s'=>$sf]:[];
            $sql="SELECT * FROM messages_contact $w ORDER BY date_envoi DESC";
            $d=paginate($pdo,$sql,$p2,$pg);
        }catch(PDOException){$d=['rows'=>[],'total'=>0,'pages'=>1,'page'=>1];}
        $d['statut_f']=$sf;
        break;

    case 'campagnes':
        $sql="SELECT * FROM campagnes ORDER BY id_campagne DESC";
        try{$d=paginate($pdo,$sql,[],$pg);}catch(PDOException){$d=['rows'=>[],'total'=>0,'pages'=>1,'page'=>1];}
        if(intG('edit')){try{$e=$pdo->prepare("SELECT * FROM campagnes WHERE id_campagne=:i");$e->execute([':i'=>intG('edit')]);$d['edit']=$e->fetch();}catch(PDOException){}}
        break;

    case 'rapports':
        $jours=intG('jours',30);
        $d['ventes']=$pdo->query("SELECT DATE(date_commande) AS jour,COUNT(*) AS nb,SUM(montant) AS total FROM commandes WHERE date_commande>=NOW()-INTERVAL $jours DAY AND statut!='Annulée' GROUP BY jour ORDER BY jour DESC")->fetchAll();
        $d['top']=$pdo->query("SELECT p.nom,SUM(dc.quantite) AS qte,SUM(dc.quantite*dc.prix) AS ca FROM details_commandes dc JOIN produits p ON dc.id_produit=p.id_produit JOIN commandes c ON dc.id_commande=c.id_commande WHERE c.statut!='Annulée' GROUP BY dc.id_produit ORDER BY qte DESC LIMIT 10")->fetchAll();
        $d['cat_top']=$pdo->query("SELECT cat.nom,SUM(dc.quantite*dc.prix) AS ca FROM details_commandes dc JOIN produits p ON dc.id_produit=p.id_produit JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie JOIN categories cat ON sc.id_categorie=cat.id_categorie JOIN commandes c ON dc.id_commande=c.id_commande WHERE c.statut!='Annulée' GROUP BY cat.id_categorie ORDER BY ca DESC LIMIT 5")->fetchAll();
        $d['jours']=$jours;
        break;

    case 'journaux':
        try{
            $sql="SELECT j.*,u.nom,u.prenom FROM journal_activites j LEFT JOIN users u ON j.id_user=u.id_user ORDER BY j.date_action DESC";
            $d=paginate($pdo,$sql,[],$pg);
        }catch(PDOException){$d=['rows'=>[],'total'=>0,'pages'=>1,'page'=>1];}
        break;

    case 'chat':
        $sql="SELECT cm.*,u.nom,u.prenom FROM chat_messages cm LEFT JOIN users u ON cm.id_user=u.id_user ORDER BY cm.date_envoi DESC";
        $d=paginate($pdo,$sql,[],$pg);
        break;

    default: // dashboard
        $d['recent']=$pdo->query("SELECT c.*,u.nom,u.prenom FROM commandes c LEFT JOIN users u ON c.id_user=u.id_user ORDER BY c.date_commande DESC LIMIT 8")->fetchAll();
        $d['alerts']=$pdo->query("SELECT nom,quantite,seuil_alerte FROM produits WHERE quantite<=seuil_alerte ORDER BY quantite ASC LIMIT 6")->fetchAll();
        $d['ventes7']=$pdo->query("SELECT DATE(date_commande) AS j,COALESCE(SUM(montant),0) AS t FROM commandes WHERE date_commande>=NOW()-INTERVAL 7 DAY AND statut!='Annulée' GROUP BY j ORDER BY j")->fetchAll();
        $d['nb_feedbacks_att']=$stats['feedbacks'];
        break;
}

$pages_labels=['dashboard'=>'Tableau de bord','produits'=>'Produits','categories'=>'Catégories','sous_categories'=>'Sous-catégories','marques'=>'Marques','fournisseurs'=>'Fournisseurs','commandes'=>'Commandes','clients'=>'Clients','stocks'=>'Stocks','feedbacks'=>'Feedbacks','messages'=>'Messages','campagnes'=>'Campagnes','rapports'=>'Rapports','journaux'=>'Journaux','chat'=>'Chat'];
$ptitle=$pages_labels[$page]??'Admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=e($ptitle)?> — Nexio Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>const BASE_URL='<?=BASE_URL?>';</script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#06080F;color:#EDF2F7;font-family:'Inter',sans-serif;display:flex;min-height:100vh;overflow-x:hidden;}
a{color:inherit;text-decoration:none;}button,input,select,textarea{font-family:inherit;}
::-webkit-scrollbar{width:5px;height:5px;}::-webkit-scrollbar-track{background:#0D1117;}::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:3px;}

/* SIDEBAR */
.sb{width:220px;background:#0D1117;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;transition:transform .25s;}
.sb-logo{display:flex;align-items:center;gap:.5rem;font-weight:900;font-size:1.05rem;padding:1rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.06);flex-shrink:0;}
.sb-logo i{color:#00C8FF;font-size:1.2rem;}.sb-logo .dot{color:#00C8FF;}
.sb-sec{font-size:.6rem;font-weight:800;color:#374151;text-transform:uppercase;letter-spacing:.1em;padding:.75rem 1.1rem .2rem;flex-shrink:0;}
.sb-link{display:flex;align-items:center;gap:.55rem;padding:.5rem 1.1rem;color:#6B7280;font-size:.8rem;font-weight:600;border-left:2px solid transparent;transition:all .15s;position:relative;}
.sb-link:hover{color:#EDF2F7;background:rgba(255,255,255,.03);}
.sb-link.active{color:#00C8FF;background:rgba(0,200,255,.06);border-left-color:#00C8FF;}
.sb-link i{font-size:.88rem;width:14px;flex-shrink:0;}
.sb-badge{background:#EF4444;color:#fff;font-size:.58rem;font-weight:800;padding:.1rem .35rem;border-radius:4px;margin-left:auto;}
.sb-bottom{margin-top:auto;padding:.85rem 1.1rem;border-top:1px solid rgba(255,255,255,.06);flex-shrink:0;}
.btn-logout{width:100%;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#EF4444;padding:.4rem;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer;}

/* MAIN */
.main{flex:1;display:flex;flex-direction:column;margin-left:220px;min-width:0;}
.topbar{background:#0D1117;border-bottom:1px solid rgba(255,255,255,.06);padding:0 1.4rem;height:52px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;position:sticky;top:0;z-index:50;gap:1rem;}
.topbar h1{font-size:.9rem;font-weight:800;white-space:nowrap;}
.tb-right{display:flex;align-items:center;gap:.6rem;font-size:.78rem;color:#64748B;}
.content{padding:1.3rem;flex:1;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.85rem;margin-bottom:1.3rem;}
.stat{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:11px;padding:1rem;display:flex;align-items:center;gap:.75rem;}
.stat-ic{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
.stat-val{font-size:1.25rem;font-weight:900;line-height:1.1;}
.stat-lbl{font-size:.65rem;color:#64748B;margin-top:.12rem;}

/* CARD */
.card{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:11px;overflow:hidden;margin-bottom:1rem;}
.card-hd{padding:.7rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.06);font-size:.82rem;font-weight:800;display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;}

/* TABLE */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{background:#0D1117;padding:.48rem .85rem;font-size:.63rem;font-weight:700;color:#6B7280;text-align:left;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;}
td{padding:.52rem .85rem;font-size:.79rem;border-bottom:1px solid rgba(255,255,255,.03);}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(255,255,255,.01);}

/* FORM ELEMENTS */
.nx-label{display:block;font-size:.71rem;font-weight:700;color:#94A3B8;margin-bottom:.25rem;}
.nx-input{width:100%;background:#0D1117;border:1px solid rgba(255,255,255,.08);border-radius:7px;color:#EDF2F7;padding:.52rem .8rem;font-size:.82rem;outline:none;margin-bottom:.75rem;transition:border-color .2s;}
.nx-input:focus{border-color:rgba(0,200,255,.35);}textarea.nx-input{resize:vertical;}
.form-card{background:#111827;border:1px solid rgba(0,200,255,.1);border-radius:11px;padding:1.1rem;margin-bottom:1rem;}
.form-card h3{font-size:.82rem;font-weight:800;margin-bottom:.85rem;color:#00C8FF;display:flex;align-items:center;gap:.4rem;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.7rem;}

/* BUTTONS */
.btn{border:none;border-radius:7px;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;transition:all .15s;font-size:.77rem;font-weight:700;padding:.42rem .85rem;}
.btn-primary{background:#1D4ED8;color:#fff;}.btn-primary:hover{background:#1e40af;}
.btn-success{background:#059669;color:#fff;}.btn-success:hover{background:#047857;}
.btn-danger{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.2);color:#EF4444;padding:.32rem .6rem;}
.btn-warn{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.2);color:#FCD34D;padding:.32rem .6rem;}
.btn-sm{padding:.28rem .55rem;font-size:.72rem;}

/* BADGES */
.badge{padding:.16rem .48rem;border-radius:4px;font-size:.6rem;font-weight:800;text-transform:uppercase;display:inline-block;}
.b-wait{background:rgba(245,158,11,.15);color:#FCD34D;}
.b-ok{background:rgba(16,185,129,.15);color:#6EE7B7;}
.b-ship{background:rgba(96,165,250,.15);color:#93C5FD;}
.b-cancel{background:rgba(239,68,68,.15);color:#FCA5A5;}
.b-avail{background:rgba(16,185,129,.1);color:#10B981;}
.b-rupture{background:rgba(239,68,68,.1);color:#EF4444;}
.b-warn{background:rgba(245,158,11,.1);color:#F59E0B;}

/* FLASH */
.flash{padding:.6rem .95rem;border-radius:8px;font-size:.81rem;font-weight:600;margin-bottom:.9rem;display:flex;align-items:center;gap:.5rem;}
.flash-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:#6EE7B7;}
.flash-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#FCA5A5;}

/* PAGINATION */
.pagi{display:flex;gap:.3rem;flex-wrap:wrap;align-items:center;padding:.6rem 1.1rem;border-top:1px solid rgba(255,255,255,.04);}
.pagi a,.pagi span{padding:.3rem .6rem;border-radius:6px;font-size:.75rem;font-weight:600;}
.pagi a{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:#CBD5E1;transition:all .15s;}
.pagi a:hover{background:rgba(0,200,255,.08);border-color:rgba(0,200,255,.2);color:#00C8FF;}
.pagi .cur{background:rgba(0,200,255,.15);border:1px solid rgba(0,200,255,.3);color:#00C8FF;}

/* DASH GRID */
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}

/* SEARCH BAR */
.search-row{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}
.search-row input,.search-row select{background:#0D1117;border:1px solid rgba(255,255,255,.08);border-radius:7px;color:#EDF2F7;padding:.42rem .75rem;font-size:.8rem;outline:none;}
.search-row input:focus,.search-row select:focus{border-color:rgba(0,200,255,.3);}

/* Image preview */
.img-preview{width:56px;height:44px;object-fit:contain;background:#0D1117;border-radius:6px;}

/* Responsive */
@media(max-width:900px){.sb{transform:translateX(-100%)}.main{margin-left:0}.dash-grid{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:576px){.grid2,.grid3{grid-template-columns:1fr}.stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sb" id="sidebar">
  <div class="sb-logo"><i class="bi bi-cpu-fill"></i>Nexio<span class="dot">.</span>Admin</div>

  <div class="sb-sec">Principal</div>
  <a href="?page=dashboard"  class="sb-link <?=$page==='dashboard'?'active':''?>"><i class="bi bi-grid-fill"></i>Tableau de bord</a>
  <a href="../vitrine/index.php" class="sb-link" target="_blank"><i class="bi bi-shop"></i>Voir la boutique</a>

  <div class="sb-sec">Catalogue</div>
  <a href="?page=produits"       class="sb-link <?=$page==='produits'?'active':''?>"><i class="bi bi-box-seam-fill"></i>Produits</a>
  <a href="?page=categories"     class="sb-link <?=$page==='categories'?'active':''?>"><i class="bi bi-tag-fill"></i>Catégories</a>
  <a href="?page=sous_categories" class="sb-link <?=$page==='sous_categories'?'active':''?>"><i class="bi bi-tags-fill"></i>Sous-catégories</a>
  <a href="?page=marques"        class="sb-link <?=$page==='marques'?'active':''?>"><i class="bi bi-award-fill"></i>Marques</a>
  <a href="?page=fournisseurs"   class="sb-link <?=$page==='fournisseurs'?'active':''?>"><i class="bi bi-building"></i>Fournisseurs</a>

  <div class="sb-sec">Commerce</div>
  <a href="?page=commandes"  class="sb-link <?=$page==='commandes'?'active':''?>">
    <i class="bi bi-bag-check-fill"></i>Commandes
    <?php if($stats['en_attente']>0):?><span class="sb-badge"><?=$stats['en_attente']?></span><?php endif;?>
  </a>
  <a href="?page=clients"    class="sb-link <?=$page==='clients'?'active':''?>"><i class="bi bi-people-fill"></i>Clients</a>
  <a href="?page=stocks"     class="sb-link <?=$page==='stocks'?'active':''?>">
    <i class="bi bi-archive-fill"></i>Stocks
    <?php if($stats['stock_bas']>0):?><span class="sb-badge"><?=$stats['stock_bas']?></span><?php endif;?>
  </a>
  <a href="?page=campagnes"  class="sb-link <?=$page==='campagnes'?'active':''?>"><i class="bi bi-megaphone-fill"></i>Campagnes</a>

  <div class="sb-sec">Communauté</div>
  <a href="?page=feedbacks"  class="sb-link <?=$page==='feedbacks'?'active':''?>">
    <i class="bi bi-star-fill"></i>Feedbacks
    <?php if($stats['feedbacks']>0):?><span class="sb-badge"><?=$stats['feedbacks']?></span><?php endif;?>
  </a>
  <a href="?page=messages"   class="sb-link <?=$page==='messages'?'active':''?>">
    <i class="bi bi-envelope-fill"></i>Messages
    <?php if($stats['messages']>0):?><span class="sb-badge"><?=$stats['messages']?></span><?php endif;?>
  </a>
  <a href="?page=chat"       class="sb-link <?=$page==='chat'?'active':''?>"><i class="bi bi-chat-dots-fill"></i>Chat NEX</a>

  <div class="sb-sec">Intelligence Artificielle</div>
  <a href="marketing/index.php" class="sb-link"><i class="bi bi-lightning-charge-fill" style="color:#00C8FF;"></i>Marketing IA</a>
  <a href="agents/index.php"    class="sb-link"><i class="bi bi-robot" style="color:#A5B4FC;"></i>Agents IA</a>

  <div class="sb-sec">IA & Marketing</div>
  <a href="../admin/marketing/index.php" class="sb-link"><i class="bi bi-lightning-charge-fill"></i>Marketing IA</a>
  <a href="../admin/agents/index.php"    class="sb-link"><i class="bi bi-robot"></i>Agents IA</a>

  <div class="sb-sec">Analyse</div>
  <a href="?page=rapports"   class="sb-link <?=$page==='rapports'?'active':''?>"><i class="bi bi-bar-chart-fill"></i>Rapports</a>
  <a href="?page=journaux"   class="sb-link <?=$page==='journaux'?'active':''?>"><i class="bi bi-journal-text"></i>Journaux</a>

  <div class="sb-bottom">
    <a href="../auth/logout.php"><button class="btn-logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</button></a>
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:.7rem;">
      <button onclick="document.getElementById('sidebar').style.transform=document.getElementById('sidebar').style.transform===''||document.getElementById('sidebar').style.transform==='none'?'translateX(0)':'none'" style="background:transparent;border:1px solid rgba(255,255,255,.08);border-radius:7px;color:#EDF2F7;padding:.3rem .5rem;cursor:pointer;font-size:.95rem;display:none;" id="menuBtn"><i class="bi bi-list"></i></button>
      <h1><?=e($ptitle)?></h1>
    </div>
    <div class="tb-right">
      <i class="bi bi-person-circle" style="color:#00C8FF;font-size:1.05rem;"></i>
      <span><?=e($_SESSION['user_prenom'].' '.$_SESSION['user_nom'])?></span>
    </div>
  </div>

  <div class="content">
    <?php if($flash):?>
    <div class="flash flash-<?=e($flash['type'])?>"><i class="bi bi-<?=$flash['type']==='success'?'check-circle':'exclamation-circle'?>-fill"></i><?=e($flash['msg'])?></div>
    <?php endif;?>

<?php
/* ════════════════════════════════════════════════════════════
   RENDU DES PAGES
   ════════════════════════════════════════════════════════════ */

// ── Macro pagination ─────────────────────────────────────────
function renderPagi(array $d, string $page, array $extra=[]): void {
    if($d['pages']<=1) return;
    echo '<div class="pagi">';
    $qs=array_merge(['page'=>$page],$extra);
    for($i=1;$i<=$d['pages'];$i++){
        $url='?'.http_build_query(array_merge($qs,['pg'=>$i]));
        if($i===$d['page']) echo "<span class='cur'>$i</span>";
        else echo "<a href='$url'>$i</a>";
    }
    echo '</div>';
}

function badgeStatut(string $s): string {
    $map=['En attente'=>'b-wait','Confirmée'=>'b-ship','Expédiée'=>'b-ship','Livrée'=>'b-ok','Annulée'=>'b-cancel','Disponible'=>'b-avail','Rupture'=>'b-rupture','Actif'=>'b-ok','Inactif'=>'b-cancel','Brouillon'=>'b-wait','Envoyée'=>'b-ok','En cours'=>'b-ship','Approuvé'=>'b-ok','En attente'=>'b-wait','Rejeté'=>'b-cancel','Non lu'=>'b-warn','Lu'=>'b-ok','Répondu'=>'b-ship'];
    $cls=$map[$s]??'b-wait';
    return "<span class='badge $cls'>".htmlspecialchars($s,ENT_QUOTES,'UTF-8')."</span>";
}

switch($page):

/* ═══ DASHBOARD ═══════════════════════════════════════════════ */
case 'dashboard': ?>
<div class="stats">
  <div class="stat"><div class="stat-ic" style="background:rgba(0,200,255,.1);color:#00C8FF;"><i class="bi bi-box-seam-fill"></i></div><div><div class="stat-val"><?=$stats['produits']?></div><div class="stat-lbl">Produits</div></div></div>
  <div class="stat"><div class="stat-ic" style="background:rgba(29,78,216,.15);color:#60A5FA;"><i class="bi bi-bag-check-fill"></i></div><div><div class="stat-val"><?=$stats['commandes']?></div><div class="stat-lbl">Commandes</div></div></div>
  <div class="stat"><div class="stat-ic" style="background:rgba(245,158,11,.1);color:#F59E0B;"><i class="bi bi-hourglass"></i></div><div><div class="stat-val" style="color:#F59E0B;"><?=$stats['en_attente']?></div><div class="stat-lbl">En attente</div></div></div>
  <div class="stat"><div class="stat-ic" style="background:rgba(16,185,129,.1);color:#10B981;"><i class="bi bi-people-fill"></i></div><div><div class="stat-val"><?=$stats['clients']?></div><div class="stat-lbl">Clients</div></div></div>
  <div class="stat"><div class="stat-ic" style="background:rgba(99,102,241,.15);color:#A5B4FC;"><i class="bi bi-cash-stack"></i></div><div><div class="stat-val"><?=number_format($stats['ca']/1000,0)?>k</div><div class="stat-lbl">CA HTG</div></div></div>
  <div class="stat"><div class="stat-ic" style="background:rgba(239,68,68,.1);color:#EF4444;"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="stat-val" style="color:#EF4444;"><?=$stats['stock_bas']?></div><div class="stat-lbl">Stock bas</div></div></div>
</div>
<div class="dash-grid">
  <div class="card">
    <div class="card-hd"><span><i class="bi bi-bag-check me-1" style="color:#00C8FF;"></i>Dernières commandes</span><a href="?page=commandes" style="color:#00C8FF;font-size:.72rem;">Tout voir →</a></div>
    <table><thead><tr><th>Réf</th><th>Client</th><th>Montant</th><th>Statut</th></tr></thead><tbody>
    <?php foreach($d['recent'] as $o):?>
    <tr>
      <td><strong style="font-size:.73rem;">#CMD-<?=str_pad($o['id_commande'],5,'0',STR_PAD_LEFT)?></strong></td>
      <td><?=e($o['prenom'].' '.$o['nom'])?></td>
      <td><?=number_format($o['montant'],0)?> HTG</td>
      <td><?=badgeStatut($o['statut'])?></td>
    </tr>
    <?php endforeach;?>
    </tbody></table>
  </div>
  <div class="card">
    <div class="card-hd"><span><i class="bi bi-exclamation-triangle me-1" style="color:#F59E0B;"></i>Alertes stock bas</span><a href="?page=stocks&alerte=bas" style="color:#00C8FF;font-size:.72rem;">Voir tout →</a></div>
    <table><thead><tr><th>Produit</th><th>Qté</th><th>Seuil</th></tr></thead><tbody>
    <?php if(empty($d['alerts'])):?><tr><td colspan="3" style="text-align:center;color:#64748B;padding:1.5rem;">✓ Tous les stocks sont OK</td></tr>
    <?php else: foreach($d['alerts'] as $s):?>
    <tr><td><?=e($s['nom'])?></td><td style="color:#EF4444;font-weight:800;"><?=(int)$s['quantite']?></td><td style="color:#64748B;"><?=(int)$s['seuil_alerte']?></td></tr>
    <?php endforeach; endif;?>
    </tbody></table>
  </div>
</div>
<?php break;

/* ═══ PRODUITS ════════════════════════════════════════════════ */
case 'produits': ?>
<div class="form-card">
  <h3><i class="bi bi-<?=isset($d['edit'])?'pencil':'plus-circle'?>"></i> <?=isset($d['edit'])?'Modifier le produit':'Nouveau produit'?></h3>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="save_produit">
    <input type="hidden" name="id" value="<?=$d['edit']['id_produit']??''?>">
    <div class="grid3">
      <div style="grid-column:1/3"><label class="nx-label">Nom du produit *</label><input type="text" name="nom" class="nx-input" required value="<?=e($d['edit']['nom']??'')?>"></div>
      <div><label class="nx-label">Prix HTG *</label><input type="number" name="prix" class="nx-input" required min="0" step="0.01" value="<?=e($d['edit']['prix']??'')?>"></div>
    </div>
    <div class="grid3">
      <div><label class="nx-label">Coût d'achat HTG</label><input type="number" name="cout" class="nx-input" min="0" step="0.01" value="<?=e($d['edit']['cout']??'')?>"></div>
      <div><label class="nx-label">Quantité</label><input type="number" name="quantite" class="nx-input" min="0" value="<?=e($d['edit']['quantite']??0)?>"></div>
      <div><label class="nx-label">Seuil alerte</label><input type="number" name="seuil_alerte" class="nx-input" min="0" value="<?=e($d['edit']['seuil_alerte']??5)?>"></div>
    </div>
    <div class="grid3">
      <div><label class="nx-label">Sous-catégorie</label>
        <select name="id_sous_categorie" class="nx-input"><option value="">-- Aucune --</option>
        <?php foreach($d['sous_cats'] as $sc):?><option value="<?=$sc['id_sous_categorie']?>" <?=($d['edit']['id_sous_categorie']??'')==$sc['id_sous_categorie']?'selected':''?>><?=e($sc['cat'].' › '.$sc['nom'])?></option><?php endforeach;?>
        </select></div>
      <div><label class="nx-label">Marque</label>
        <select name="id_marque" class="nx-input"><option value="">-- Aucune --</option>
        <?php foreach($d['marques'] as $m):?><option value="<?=$m['id_marque']?>" <?=($d['edit']['id_marque']??'')==$m['id_marque']?'selected':''?>><?=e($m['nom'])?></option><?php endforeach;?>
        </select></div>
      <div><label class="nx-label">Fournisseur</label>
        <select name="id_fournisseur" class="nx-input"><option value="">-- Aucun --</option>
        <?php foreach($d['fournisseurs'] as $f):?><option value="<?=$f['id_fournisseur']?>" <?=($d['edit']['id_fournisseur']??'')==$f['id_fournisseur']?'selected':''?>><?=e($f['nom'])?></option><?php endforeach;?>
        </select></div>
    </div>
    <div class="grid2">
      <div><label class="nx-label">Garantie</label><input type="text" name="garantie" class="nx-input" placeholder="Ex: 1 an" value="<?=e($d['edit']['garantie']??'')?>"></div>
      <div><label class="nx-label">Statut</label>
        <select name="statut" class="nx-input">
          <?php foreach(['Disponible','Rupture','Bientôt disponible'] as $s):?><option value="<?=$s?>" <?=($d['edit']['statut']??'Disponible')===$s?'selected':''?>><?=$s?></option><?php endforeach;?>
        </select></div>
    </div>
    <label class="nx-label">Image — URL</label>
    <input type="text" name="image" class="nx-input" placeholder="https://images.unsplash.com/..." value="<?=e($d['edit']['image']??'')?>">
    <label class="nx-label">Image — Upload fichier (JPG/PNG/WEBP)</label>
    <input type="file" name="image_file" class="nx-input" accept="image/jpeg,image/png,image/webp,image/gif">
    <?php if(!empty($d['edit']['image'])):?>
    <div style="margin-bottom:.75rem;display:flex;align-items:center;gap:.6rem;">
      <img src="<?=e($d['edit']['image'])?>" class="img-preview" onerror="this.style.display='none'">
      <span style="font-size:.72rem;color:#64748B;">Image actuelle</span>
    </div>
    <?php endif;?>
    <label class="nx-label">Description</label>
    <textarea name="description" class="nx-input" rows="2"><?=e($d['edit']['description']??'')?></textarea>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
      <button class="btn btn-success" type="submit"><i class="bi bi-check-lg"></i> <?=isset($d['edit'])?'Mettre à jour':'Enregistrer'?></button>
      <?php if(isset($d['edit'])):?><a href="?page=produits" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;">✕ Annuler</a><?php endif;?>
    </div>
  </form>
</div>
<div class="card">
  <div class="card-hd">
    <span><i class="bi bi-box-seam me-1" style="color:#00C8FF;"></i><?=$d['total']?> produit(s)</span>
    <form method="GET" class="search-row">
      <input type="hidden" name="page" value="produits">
      <input type="text"   name="q"    placeholder="Rechercher..." value="<?=e($d['q'])?>">
      <select name="cat"><option value="">Toutes catégories</option><?php foreach($d['categories'] as $c):?><option value="<?=$c['id_categorie']?>" <?=$d['cat_f']==$c['id_categorie']?'selected':''?>><?=e($c['nom'])?></option><?php endforeach;?></select>
      <select name="stat"><option value="">Tous statuts</option><?php foreach(['Disponible','Rupture','Bientôt disponible'] as $s):?><option value="<?=$s?>" <?=$d['stat_f']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select>
      <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
      <?php if($d['q']||$d['cat_f']||$d['stat_f']):?><a href="?page=produits" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;">✕</a><?php endif;?>
    </form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>Img</th><th>Produit</th><th>Catégorie</th><th>Marque</th><th>Prix HTG</th><th>Stock</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $p):?>
  <tr>
    <td><?php if($p['image']):?><img src="<?=e($p['image'])?>" class="img-preview" onerror="this.style.display='none'"><?php endif;?></td>
    <td><strong style="font-size:.8rem;"><?=e(mb_substr($p['nom'],0,45))?></strong></td>
    <td style="color:#64748B;font-size:.73rem;"><?=e($p['categorie']??'—')?></td>
    <td style="color:#64748B;font-size:.73rem;"><?=e($p['marque']??'—')?></td>
    <td style="font-weight:700;"><?=number_format($p['prix'],0)?></td>
    <td style="font-weight:800;color:<?=$p['quantite']<=($p['seuil_alerte']??5)?'#EF4444':'#10B981'?>;"><?=(int)$p['quantite']?></td>
    <td><?=badgeStatut($p['statut'])?></td>
    <td style="white-space:nowrap;">
      <a href="?page=produits&edit=<?=$p['id_produit']?>" class="btn btn-warn btn-sm"><i class="bi bi-pencil-fill"></i></a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="action" value="delete_produit">
        <input type="hidden" name="id" value="<?=$p['id_produit']?>">
        <button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($d['rows'])):?><tr><td colspan="8" style="text-align:center;color:#64748B;padding:2rem;">Aucun produit trouvé.</td></tr><?php endif;?>
  </tbody></table></div>
  <?php renderPagi($d,'produits',['q'=>$d['q'],'cat'=>$d['cat_f'],'stat'=>$d['stat_f']]);?>
</div>
<?php break;

/* ═══ CATÉGORIES ══════════════════════════════════════════════ */
case 'categories': ?>
<div class="form-card">
  <h3><i class="bi bi-<?=isset($d['edit'])?'pencil':'plus-circle'?>"></i> <?=isset($d['edit'])?'Modifier':'Nouvelle'?> catégorie</h3>
  <form method="POST" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="save_categorie">
    <input type="hidden" name="id" value="<?=$d['edit']['id_categorie']??''?>">
    <div style="flex:1;min-width:180px;"><label class="nx-label">Nom *</label><input type="text" name="nom" class="nx-input" required style="margin:0;" value="<?=e($d['edit']['nom']??'')?>"></div>
    <button class="btn btn-success" type="submit"><i class="bi bi-check-lg"></i> <?=isset($d['edit'])?'Mettre à jour':'Ajouter'?></button>
    <?php if(isset($d['edit'])):?><a href="?page=categories" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;">✕</a><?php endif;?>
  </form>
</div>
<div class="card">
  <div class="card-hd"><span><i class="bi bi-tag-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> catégorie(s)</span></div>
  <div class="tbl-wrap"><table><thead><tr><th>#</th><th>Nom</th><th>Sous-catégories</th><th>Produits</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $i=>$c):?>
  <tr>
    <td style="color:#64748B;"><?=$d['pp']*($d['page']-1)+$i+1?></td>
    <td><strong><?=e($c['nom'])?></strong></td>
    <td><span class="badge b-ship"><?=$c['nb_sc']?></span></td>
    <td><span class="badge b-ok"><?=$c['nb_prod']?></span></td>
    <td style="white-space:nowrap;">
      <a href="?page=categories&edit=<?=$c['id_categorie']?>" class="btn btn-warn btn-sm"><i class="bi bi-pencil-fill"></i></a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer la catégorie &quot;<?=e(addslashes($c['nom']))?>&quot; ?')">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="action" value="delete_categorie">
        <input type="hidden" name="id" value="<?=$c['id_categorie']?>">
        <button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody></table></div>
  <?php renderPagi($d,'categories');?>
</div>
<?php break;

/* ═══ SOUS-CATÉGORIES ═════════════════════════════════════════ */
case 'sous_categories': ?>
<div class="form-card">
  <h3><i class="bi bi-<?=isset($d['edit'])?'pencil':'plus-circle'?>"></i> <?=isset($d['edit'])?'Modifier':'Nouvelle'?> sous-catégorie</h3>
  <form method="POST" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="save_sous_cat">
    <input type="hidden" name="id" value="<?=$d['edit']['id_sous_categorie']??''?>">
    <div style="flex:1;min-width:160px;"><label class="nx-label">Nom *</label><input type="text" name="nom" class="nx-input" required style="margin:0;" value="<?=e($d['edit']['nom']??'')?>"></div>
    <div><label class="nx-label">Catégorie *</label>
      <select name="id_categorie" class="nx-input" required style="margin:0;">
        <option value="">-- Choisir --</option>
        <?php foreach($d['categories'] as $c):?><option value="<?=$c['id_categorie']?>" <?=($d['edit']['id_categorie']??'')==$c['id_categorie']?'selected':''?>><?=e($c['nom'])?></option><?php endforeach;?>
      </select></div>
    <button class="btn btn-success" type="submit"><i class="bi bi-check-lg"></i> Enregistrer</button>
    <?php if(isset($d['edit'])):?><a href="?page=sous_categories" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;">✕</a><?php endif;?>
  </form>
</div>
<div class="card">
  <div class="card-hd">
    <span><i class="bi bi-tags-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> sous-catégorie(s)</span>
    <form method="GET" class="search-row"><input type="hidden" name="page" value="sous_categories"><input type="text" name="q" placeholder="Rechercher..." value="<?=e($d['q'])?>"><button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button></form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>#</th><th>Nom</th><th>Catégorie parente</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $i=>$sc):?>
  <tr>
    <td style="color:#64748B;"><?=$d['pp']*($d['page']-1)+$i+1?></td>
    <td><strong><?=e($sc['nom'])?></strong></td>
    <td style="color:#64748B;"><?=e($sc['categorie']??'—')?></td>
    <td style="white-space:nowrap;">
      <a href="?page=sous_categories&edit=<?=$sc['id_sous_categorie']?>" class="btn btn-warn btn-sm"><i class="bi bi-pencil-fill"></i></a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_sous_cat"><input type="hidden" name="id" value="<?=$sc['id_sous_categorie']?>"><button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button></form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody></table></div>
  <?php renderPagi($d,'sous_categories',['q'=>$d['q']]);?>
</div>
<?php break;

/* ═══ MARQUES ═════════════════════════════════════════════════ */
case 'marques': ?>
<div class="form-card">
  <h3><i class="bi bi-<?=isset($d['edit'])?'pencil':'plus-circle'?>"></i> <?=isset($d['edit'])?'Modifier':'Nouvelle'?> marque</h3>
  <form method="POST" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="save_marque">
    <input type="hidden" name="id" value="<?=$d['edit']['id_marque']??''?>">
    <div style="flex:1;min-width:160px;"><label class="nx-label">Nom *</label><input type="text" name="nom" class="nx-input" required style="margin:0;" value="<?=e($d['edit']['nom']??'')?>"></div>
    <div><label class="nx-label">Pays d'origine</label><input type="text" name="pays" class="nx-input" style="margin:0;" placeholder="USA, Chine..." value="<?=e($d['edit']['pays_origine']??'')?>"></div>
    <button class="btn btn-success" type="submit"><i class="bi bi-check-lg"></i> Enregistrer</button>
    <?php if(isset($d['edit'])):?><a href="?page=marques" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;">✕</a><?php endif;?>
  </form>
</div>
<div class="card">
  <div class="card-hd"><span><i class="bi bi-award-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> marque(s)</span>
    <form method="GET" class="search-row"><input type="hidden" name="page" value="marques"><input type="text" name="q" placeholder="Rechercher..." value="<?=e($d['q'])?>"><button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button></form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>#</th><th>Nom</th><th>Pays</th><th>Produits</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $i=>$m):?>
  <tr>
    <td style="color:#64748B;"><?=$d['pp']*($d['page']-1)+$i+1?></td>
    <td><strong><?=e($m['nom'])?></strong></td>
    <td style="color:#64748B;"><?=e($m['pays_origine']??'—')?></td>
    <td><span class="badge b-ship"><?=(int)$m['nb_produits']?></span></td>
    <td style="white-space:nowrap;">
      <a href="?page=marques&edit=<?=$m['id_marque']?>" class="btn btn-warn btn-sm"><i class="bi bi-pencil-fill"></i></a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_marque"><input type="hidden" name="id" value="<?=$m['id_marque']?>"><button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button></form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody></table></div>
  <?php renderPagi($d,'marques',['q'=>$d['q']]);?>
</div>
<?php break;

/* ═══ FOURNISSEURS ════════════════════════════════════════════ */
case 'fournisseurs': ?>
<div class="form-card">
  <h3><i class="bi bi-<?=isset($d['edit'])?'pencil':'plus-circle'?>"></i> <?=isset($d['edit'])?'Modifier':'Nouveau'?> fournisseur</h3>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="save_fournisseur">
    <input type="hidden" name="id" value="<?=$d['edit']['id_fournisseur']??''?>">
    <div class="grid3">
      <div><label class="nx-label">Nom *</label><input type="text" name="nom" class="nx-input" required value="<?=e($d['edit']['nom']??'')?>"></div>
      <div><label class="nx-label">Email</label><input type="email" name="email" class="nx-input" value="<?=e($d['edit']['email']??'')?>"></div>
      <div><label class="nx-label">Téléphone</label><input type="text" name="telephone" class="nx-input" value="<?=e($d['edit']['telephone']??'')?>"></div>
      <div style="grid-column:1/3"><label class="nx-label">Adresse</label><input type="text" name="adresse" class="nx-input" value="<?=e($d['edit']['adresse']??'')?>"></div>
      <div><label class="nx-label">Pays</label><input type="text" name="pays" class="nx-input" value="<?=e($d['edit']['pays']??'')?>"></div>
    </div>
    <div style="display:flex;gap:.6rem;"><button class="btn btn-success" type="submit"><i class="bi bi-check-lg"></i> Enregistrer</button><?php if(isset($d['edit'])):?><a href="?page=fournisseurs" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;">✕</a><?php endif;?></div>
  </form>
</div>
<div class="card">
  <div class="card-hd"><span><i class="bi bi-building me-1" style="color:#00C8FF;"></i><?=$d['total']?> fournisseur(s)</span>
    <form method="GET" class="search-row"><input type="hidden" name="page" value="fournisseurs"><input type="text" name="q" placeholder="Rechercher..." value="<?=e($d['q'])?>"><button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button></form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Pays</th><th>Produits</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $f):?>
  <tr>
    <td><strong><?=e($f['nom'])?></strong></td>
    <td style="font-size:.73rem;"><?=e($f['email']??'—')?></td>
    <td style="font-size:.73rem;"><?=e($f['telephone']??'—')?></td>
    <td style="color:#64748B;"><?=e($f['pays']??'—')?></td>
    <td><span class="badge b-ship"><?=(int)$f['nb_produits']?></span></td>
    <td style="white-space:nowrap;">
      <a href="?page=fournisseurs&edit=<?=$f['id_fournisseur']?>" class="btn btn-warn btn-sm"><i class="bi bi-pencil-fill"></i></a>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_fournisseur"><input type="hidden" name="id" value="<?=$f['id_fournisseur']?>"><button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button></form>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody></table></div>
  <?php renderPagi($d,'fournisseurs',['q'=>$d['q']]);?>
</div>
<?php break;

/* ═══ COMMANDES ═══════════════════════════════════════════════ */
case 'commandes':
if(isset($d['detail'])): $o=$d['detail']; ?>
<div class="card" style="max-width:780px;">
  <div class="card-hd">
    <span><i class="bi bi-bag-check me-1" style="color:#00C8FF;"></i>Commande #CMD-<?=str_pad($o['id_commande'],5,'0',STR_PAD_LEFT)?></span>
    <a href="?page=commandes" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);font-size:.75rem;color:#EDF2F7;"><i class="bi bi-arrow-left"></i> Retour</a>
  </div>
  <div style="padding:1.1rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem;font-size:.82rem;">
    <div><div style="color:#64748B;font-size:.68rem;text-transform:uppercase;font-weight:700;margin-bottom:.2rem;">Client</div><div style="font-weight:700;"><?=e($o['prenom'].' '.$o['nom'])?></div><div style="color:#64748B;"><?=e($o['email']??'')?>  <?=e($o['telephone']??'')?></div></div>
    <div><div style="color:#64748B;font-size:.68rem;text-transform:uppercase;font-weight:700;margin-bottom:.2rem;">Livraison</div><div><?=e($o['adresse_livraison']??'Non précisée')?></div></div>
    <div><div style="color:#64748B;font-size:.68rem;text-transform:uppercase;font-weight:700;margin-bottom:.2rem;">Montant</div><div style="font-size:1.2rem;font-weight:900;color:#00C8FF;"><?=number_format($o['montant'],0)?> HTG</div></div>
    <div>
      <div style="color:#64748B;font-size:.68rem;text-transform:uppercase;font-weight:700;margin-bottom:.4rem;">Changer statut</div>
      <form method="POST" style="display:flex;gap:.4rem;">
        <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="update_statut"><input type="hidden" name="id" value="<?=$o['id_commande']?>">
        <select name="statut" class="nx-input" style="margin:0;font-size:.78rem;">
          <?php foreach(['En attente','Confirmée','Expédiée','Livrée','Annulée'] as $s):?><option value="<?=$s?>" <?=$o['statut']===$s?'selected':''?>><?=$s?></option><?php endforeach;?>
        </select>
        <button class="btn btn-warn"><i class="bi bi-check-lg"></i></button>
      </form>
    </div>
  </div>
  <div style="padding:0 1.1rem 1.1rem;">
    <div style="font-size:.7rem;font-weight:700;color:#64748B;text-transform:uppercase;margin-bottom:.6rem;">Articles commandés</div>
    <table><thead><tr><th>Img</th><th>Produit</th><th>Qté</th><th>Prix u.</th><th>Total</th></tr></thead><tbody>
    <?php foreach($d['detail_items'] as $it):?>
    <tr>
      <td><?php if($it['image']):?><img src="<?=e($it['image'])?>" class="img-preview"><?php endif;?></td>
      <td><?=e($it['nom']??'—')?></td>
      <td><?=(int)$it['quantite']?></td>
      <td><?=number_format($it['prix'],0)?> HTG</td>
      <td><strong><?=number_format($it['prix']*$it['quantite'],0)?> HTG</strong></td>
    </tr>
    <?php endforeach;?>
    </tbody></table>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="card-hd">
    <span><i class="bi bi-bag-check-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> commande(s)</span>
    <form method="GET" class="search-row">
      <input type="hidden" name="page" value="commandes">
      <select name="statut" onchange="this.form.submit()"><option value="">Tous statuts</option><?php foreach(['En attente','Confirmée','Expédiée','Livrée','Annulée'] as $s):?><option value="<?=$s?>" <?=$d['statut_f']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select>
    </form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>Réf</th><th>Client</th><th>Email</th><th>Montant</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $o):?>
  <tr>
    <td><strong style="font-size:.73rem;">#CMD-<?=str_pad($o['id_commande'],5,'0',STR_PAD_LEFT)?></strong></td>
    <td><?=e($o['prenom'].' '.$o['nom'])?></td>
    <td style="font-size:.72rem;color:#64748B;"><?=e($o['email']??'')?></td>
    <td style="font-weight:700;"><?=number_format($o['montant'],0)?> HTG</td>
    <td><?=badgeStatut($o['statut'])?></td>
    <td style="color:#64748B;font-size:.72rem;"><?=date('d/m/Y H:i',strtotime($o['date_commande']))?></td>
    <td style="white-space:nowrap;">
      <a href="?page=commandes&detail=<?=$o['id_commande']?>" class="btn btn-warn btn-sm"><i class="bi bi-eye-fill"></i></a>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="update_statut"><input type="hidden" name="id" value="<?=$o['id_commande']?>">
        <select name="statut" style="font-size:.7rem;padding:.22rem .4rem;background:#0D1117;border:1px solid rgba(255,255,255,.1);border-radius:5px;color:#EDF2F7;" onchange="this.form.submit()">
          <?php foreach(['En attente','Confirmée','Expédiée','Livrée','Annulée'] as $s):?><option value="<?=$s?>" <?=$o['statut']===$s?'selected':''?>><?=$s?></option><?php endforeach;?>
        </select>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($d['rows'])):?><tr><td colspan="7" style="text-align:center;color:#64748B;padding:2rem;">Aucune commande.</td></tr><?php endif;?>
  </tbody></table></div>
  <?php renderPagi($d,'commandes',['statut'=>$d['statut_f']]);?>
</div>
<?php endif; break;

/* ═══ CLIENTS ═════════════════════════════════════════════════ */
case 'clients': ?>
<div class="card">
  <div class="card-hd">
    <span><i class="bi bi-people-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> client(s)</span>
    <form method="GET" class="search-row">
      <input type="hidden" name="page" value="clients">
      <input type="text" name="q" placeholder="Nom, email..." value="<?=e($d['q'])?>">
      <select name="statut"><option value="">Tous</option><?php foreach(['Actif','Inactif'] as $s):?><option value="<?=$s?>" <?=$d['statut_f']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select>
      <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
    </form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>#</th><th>Client</th><th>Email</th><th>Tél</th><th>Commandes</th><th>Statut</th><th>Inscrit</th><th>Actions</th></tr></thead><tbody>
  <?php if(empty($d['rows'])):?><tr><td colspan="8" style="text-align:center;color:#64748B;padding:2rem;">Aucun client.</td></tr>
  <?php else: foreach($d['rows'] as $i=>$u):?>
  <tr>
    <td style="color:#64748B;"><?=$d['pp']*($d['page']-1)+$i+1?></td>
    <td><strong><?=e(trim($u['prenom'].' '.$u['nom']))?></strong><?php if($u['pseudo']??''):?><div style="font-size:.68rem;color:#64748B;">@<?=e($u['pseudo'])?></div><?php endif;?></td>
    <td style="font-size:.73rem;"><?=e($u['email'])?></td>
    <td style="font-size:.73rem;color:#64748B;"><?=e($u['telephone']??'—')?></td>
    <td><span class="badge b-ship"><?=(int)$u['nb_cmd']?></span></td>
    <td><?=badgeStatut($u['statut']??'Actif')?></td>
    <td style="color:#64748B;font-size:.72rem;"><?=date('d/m/Y',strtotime($u['date_creation']))?></td>
    <td style="white-space:nowrap;">
      <form method="POST" style="display:inline;">
        <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="toggle_client"><input type="hidden" name="id" value="<?=$u['id_user']?>">
        <button class="btn btn-warn btn-sm" title="Activer/Désactiver"><i class="bi bi-toggle-on"></i></button>
      </form>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce client ?')">
        <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_client"><input type="hidden" name="id" value="<?=$u['id_user']?>">
        <button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach; endif;?>
  </tbody></table></div>
  <?php renderPagi($d,'clients',['q'=>$d['q'],'statut'=>$d['statut_f']]);?>
</div>
<?php break;

/* ═══ STOCKS ══════════════════════════════════════════════════ */
case 'stocks': ?>
<div class="card">
  <div class="card-hd">
    <span><i class="bi bi-archive-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> produit(s)</span>
    <form method="GET" class="search-row">
      <input type="hidden" name="page" value="stocks">
      <select name="alerte" onchange="this.form.submit()">
        <option value="">Tous</option>
        <option value="bas" <?=$d['alerte_f']==='bas'?'selected':''?>>Stock bas</option>
        <option value="rupture" <?=$d['alerte_f']==='rupture'?'selected':''?>>Rupture</option>
      </select>
    </form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>Produit</th><th>Marque</th><th>Quantité</th><th>Seuil</th><th>Alerte</th><th>Statut</th><th>Action</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $s): $bas=$s['quantite']<=$s['seuil_alerte'];?>
  <tr style="<?=$bas?'background:rgba(239,68,68,.015);':''?>">
    <td><strong style="font-size:.8rem;"><?=e($s['nom'])?></strong></td>
    <td style="color:#64748B;font-size:.73rem;"><?=e($s['marque']??'—')?></td>
    <td style="font-weight:800;color:<?=$s['quantite']==0?'#EF4444':($bas?'#F59E0B':'#10B981')?>;"><?=(int)$s['quantite']?></td>
    <td style="color:#64748B;"><?=(int)$s['seuil_alerte']?></td>
    <td><?= $s['quantite']==0 ? "<span class='badge b-rupture'>Rupture</span>" : ($bas?"<span class='badge b-warn'>⚠ Bas</span>":"<span class='badge b-ok'>✓ OK</span>") ?></td>
    <td><?=badgeStatut($s['statut'])?></td>
    <td><a href="?page=produits&edit=<?=$s['id_produit']?>" class="btn btn-warn btn-sm"><i class="bi bi-pencil-fill"></i></a></td>
  </tr>
  <?php endforeach;?>
  </tbody></table></div>
  <?php renderPagi($d,'stocks',['alerte'=>$d['alerte_f']]);?>
</div>
<?php break;

/* ═══ FEEDBACKS ═══════════════════════════════════════════════ */
case 'feedbacks': ?>
<div class="card">
  <div class="card-hd">
    <span><i class="bi bi-star-fill me-1" style="color:#F59E0B;"></i><?=$d['total']?> feedback(s)</span>
    <form method="GET" class="search-row">
      <input type="hidden" name="page" value="feedbacks">
      <select name="statut" onchange="this.form.submit()"><option value="">Tous statuts</option><?php foreach(['En attente','Approuvé','Rejeté'] as $s):?><option value="<?=$s?>" <?=$d['statut_f']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select>
      <select name="note" onchange="this.form.submit()"><option value="">Toutes notes</option><?php for($n=5;$n>=1;$n--):?><option value="<?=$n?>" <?=$d['note_f']==$n?'selected':''?>><?=str_repeat('★',$n)?></option><?php endfor;?></select>
    </form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>Client</th><th>Note</th><th>Type</th><th>Commentaire</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $fb):?>
  <tr>
    <td style="font-size:.78rem;"><?=e(trim(($fb['prenom']??'').' '.($fb['nom_user']??$fb['nom']??'')))?></td>
    <td style="color:#F59E0B;"><?=str_repeat('★',(int)$fb['note'])?></td>
    <td style="font-size:.72rem;color:#64748B;"><?=e($fb['type_feedback']??'')?></td>
    <td style="font-size:.78rem;max-width:260px;"><?=e(mb_substr($fb['commentaire'],0,80)).(mb_strlen($fb['commentaire'])>80?'…':'')?></td>
    <td><?=badgeStatut($fb['statut'])?></td>
    <td style="color:#64748B;font-size:.72rem;"><?=date('d/m/Y',strtotime($fb['date_feedback']))?></td>
    <td style="white-space:nowrap;">
      <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="update_feedback"><input type="hidden" name="id" value="<?=$fb['id_feedback']?>">
        <select name="statut" onchange="this.form.submit()" style="font-size:.7rem;padding:.2rem .4rem;background:#0D1117;border:1px solid rgba(255,255,255,.1);border-radius:5px;color:#EDF2F7;">
          <?php foreach(['En attente','Approuvé','Rejeté'] as $s):?><option value="<?=$s?>" <?=$fb['statut']===$s?'selected':''?>><?=$s?></option><?php endforeach;?>
        </select>
      </form>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_feedback"><input type="hidden" name="id" value="<?=$fb['id_feedback']?>"><button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button></form>
    </td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($d['rows'])):?><tr><td colspan="7" style="text-align:center;color:#64748B;padding:2rem;">Aucun feedback.</td></tr><?php endif;?>
  </tbody></table></div>
  <?php renderPagi($d,'feedbacks',['statut'=>$d['statut_f'],'note'=>$d['note_f']]);?>
</div>
<?php break;

/* ═══ MESSAGES ════════════════════════════════════════════════ */
case 'messages': ?>
<div class="card">
  <div class="card-hd">
    <span><i class="bi bi-envelope-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> message(s)</span>
    <form method="GET" class="search-row"><input type="hidden" name="page" value="messages"><select name="statut" onchange="this.form.submit()"><option value="">Tous</option><?php foreach(['Non lu','Lu','Répondu'] as $s):?><option value="<?=$s?>" <?=($d['statut_f']===$s)?'selected':''?>><?=$s?></option><?php endforeach;?></select></form>
  </div>
  <div class="tbl-wrap"><table><thead><tr><th>De</th><th>Email</th><th>Sujet</th><th>Message</th><th>Statut</th><th>Date</th><th>Action</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $m):?>
  <tr style="<?=$m['statut']==='Non lu'?'background:rgba(0,200,255,.02);':''?>">
    <td style="font-weight:<?=$m['statut']==='Non lu'?800:400?>;font-size:.8rem;"><?=e($m['nom']??'—')?></td>
    <td style="font-size:.73rem;color:#64748B;"><?=e($m['email']??'')?></td>
    <td style="font-size:.78rem;"><?=e(mb_substr($m['sujet']??'',0,30))?></td>
    <td style="font-size:.75rem;max-width:220px;"><?=e(mb_substr($m['message'],0,60)).(mb_strlen($m['message'])>60?'…':'')?></td>
    <td><?=badgeStatut($m['statut'])?></td>
    <td style="color:#64748B;font-size:.72rem;"><?=date('d/m H:i',strtotime($m['date_envoi']))?></td>
    <td style="white-space:nowrap;">
      <form method="POST" style="display:inline;"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="update_message"><input type="hidden" name="id" value="<?=$m['id_contact']?>">
        <select name="statut" onchange="this.form.submit()" style="font-size:.7rem;padding:.2rem .4rem;background:#0D1117;border:1px solid rgba(255,255,255,.1);border-radius:5px;color:#EDF2F7;">
          <?php foreach(['Non lu','Lu','Répondu'] as $s):?><option value="<?=$s?>" <?=$m['statut']===$s?'selected':''?>><?=$s?></option><?php endforeach;?>
        </select>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($d['rows'])):?><tr><td colspan="7" style="text-align:center;color:#64748B;padding:2rem;">Aucun message.</td></tr><?php endif;?>
  </tbody></table></div>
  <?php renderPagi($d,'messages',['statut'=>$d['statut_f']]);?>
</div>
<?php break;

/* ═══ CAMPAGNES ═══════════════════════════════════════════════ */
case 'campagnes': ?>
<div class="form-card">
  <h3><i class="bi bi-<?=isset($d['edit'])?'pencil':'megaphone'?>-fill"></i> <?=isset($d['edit'])?'Modifier':'Nouvelle'?> campagne</h3>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?=csrf()?>">
    <input type="hidden" name="action" value="save_campagne">
    <input type="hidden" name="id" value="<?=$d['edit']['id_campagne']??''?>">
    <div class="grid3">
      <div style="grid-column:1/3"><label class="nx-label">Nom de la campagne *</label><input type="text" name="nom" class="nx-input" required value="<?=e($d['edit']['nom']??'')?>"></div>
      <div><label class="nx-label">Canal</label>
        <select name="canal" class="nx-input"><?php foreach(['Email','WhatsApp','Facebook','Notification'] as $c):?><option value="<?=$c?>" <?=($d['edit']['canal']??'')===$c?'selected':''?>><?=$c?></option><?php endforeach;?></select></div>
    </div>
    <label class="nx-label">Contenu / Message</label>
    <textarea name="contenu" class="nx-input" rows="4" placeholder="Rédigez votre message marketing..."><?=e($d['edit']['contenu']??'')?></textarea>
    <div class="grid2">
      <div><label class="nx-label">Date d'envoi programmée</label><input type="datetime-local" name="date_envoi" class="nx-input" value="<?=$d['edit']['date_envoi']??''?>"></div>
      <div><label class="nx-label">Statut</label>
        <select name="statut" class="nx-input"><?php foreach(['Brouillon','En cours','Envoyée','Annulée'] as $s):?><option value="<?=$s?>" <?=($d['edit']['statut']??'Brouillon')===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div>
    </div>
    <div style="display:flex;gap:.6rem;"><button class="btn btn-success" type="submit"><i class="bi bi-check-lg"></i> Enregistrer</button><?php if(isset($d['edit'])):?><a href="?page=campagnes" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;">✕</a><?php endif;?></div>
  </form>
</div>
<div class="card">
  <div class="card-hd"><span><i class="bi bi-megaphone-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> campagne(s)</span></div>
  <div class="tbl-wrap"><table><thead><tr><th>Nom</th><th>Canal</th><th>Statut</th><th>Date envoi</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $c):?>
  <tr>
    <td><strong><?=e($c['nom'])?></strong><div style="font-size:.7rem;color:#64748B;margin-top:.15rem;"><?=e(mb_substr($c['contenu'],0,50)).'…'?></div></td>
    <td><span class="badge b-ship"><?=e($c['canal'])?></span></td>
    <td><?=badgeStatut($c['statut'])?></td>
    <td style="color:#64748B;font-size:.73rem;"><?=$c['date_envoi']?date('d/m/Y H:i',strtotime($c['date_envoi'])):'—'?></td>
    <td style="white-space:nowrap;">
      <a href="?page=campagnes&edit=<?=$c['id_campagne']?>" class="btn btn-warn btn-sm"><i class="bi bi-pencil-fill"></i></a>
      <button class="btn btn-primary btn-sm" onclick="genererCampagne(<?=$c['id_campagne']?>)" title="Générer avec NEX IA"><i class="bi bi-robot"></i></button>
      <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_campagne"><input type="hidden" name="id" value="<?=$c['id_campagne']?>"><button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button></form>
    </td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($d['rows'])):?><tr><td colspan="5" style="text-align:center;color:#64748B;padding:2rem;">Aucune campagne. Créez-en une ci-dessus.</td></tr><?php endif;?>
  </tbody></table></div>
  <?php renderPagi($d,'campagnes');?>
</div>
<?php break;

/* ═══ RAPPORTS ════════════════════════════════════════════════ */
case 'rapports':
$ca30=array_sum(array_column($d['ventes'],'total'));
$nb30=array_sum(array_column($d['ventes'],'nb'));
$panier=$nb30>0?$ca30/$nb30:0;
?>
<div style="display:flex;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center;">
  <span style="font-size:.82rem;color:#64748B;">Période :</span>
  <?php foreach([7,14,30,90] as $j):?>
  <a href="?page=rapports&jours=<?=$j?>" class="btn <?=$d['jours']==$j?'btn-primary':''?>" style="<?=$d['jours']!=$j?'background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;':''?>"><?=$j?> jours</a>
  <?php endforeach;?>
</div>
<div class="stats" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat"><div class="stat-ic" style="background:rgba(0,200,255,.1);color:#00C8FF;"><i class="bi bi-calendar3"></i></div><div><div class="stat-val"><?=count($d['ventes'])?></div><div class="stat-lbl">Jours actifs</div></div></div>
  <div class="stat"><div class="stat-ic" style="background:rgba(16,185,129,.1);color:#10B981;"><i class="bi bi-cash-stack"></i></div><div><div class="stat-val"><?=number_format($ca30/1000,1)?>k</div><div class="stat-lbl">CA HTG (<?=$d['jours']?>j)</div></div></div>
  <div class="stat"><div class="stat-ic" style="background:rgba(99,102,241,.15);color:#A5B4FC;"><i class="bi bi-cart3"></i></div><div><div class="stat-val"><?=number_format($panier,0)?></div><div class="stat-lbl">Panier moyen</div></div></div>
</div>
<div class="dash-grid">
  <div class="card">
    <div class="card-hd"><i class="bi bi-calendar3 me-1" style="color:#00C8FF;"></i>Ventes par jour</div>
    <div class="tbl-wrap"><table><thead><tr><th>Date</th><th>Commandes</th><th>CA HTG</th></tr></thead><tbody>
    <?php foreach($d['ventes'] as $v):?>
    <tr><td><?=date('d/m/Y',strtotime($v['jour']))?></td><td><?=(int)$v['nb']?></td><td><strong><?=number_format($v['total'],0)?></strong></td></tr>
    <?php endforeach;?>
    <?php if(empty($d['ventes'])):?><tr><td colspan="3" style="text-align:center;color:#64748B;padding:1.5rem;">Aucune vente.</td></tr><?php endif;?>
    </tbody></table></div>
  </div>
  <div>
    <div class="card">
      <div class="card-hd"><i class="bi bi-trophy-fill me-1" style="color:#F59E0B;"></i>Top produits</div>
      <div class="tbl-wrap"><table><thead><tr><th>Produit</th><th>Qté</th><th>CA</th></tr></thead><tbody>
      <?php foreach($d['top'] as $t):?><tr><td style="font-size:.75rem;"><?=e(mb_substr($t['nom'],0,35))?></td><td><?=(int)$t['qte']?></td><td><?=number_format($t['ca'],0)?></td></tr><?php endforeach;?>
      <?php if(empty($d['top'])):?><tr><td colspan="3" style="text-align:center;color:#64748B;padding:1.5rem;">—</td></tr><?php endif;?>
      </tbody></table></div>
    </div>
    <div class="card">
      <div class="card-hd"><i class="bi bi-pie-chart-fill me-1" style="color:#A5B4FC;"></i>Top catégories</div>
      <div class="tbl-wrap"><table><thead><tr><th>Catégorie</th><th>CA HTG</th></tr></thead><tbody>
      <?php foreach($d['cat_top'] as $t):?><tr><td><?=e($t['nom'])?></td><td><strong><?=number_format($t['ca'],0)?></strong></td></tr><?php endforeach;?>
      <?php if(empty($d['cat_top'])):?><tr><td colspan="2" style="text-align:center;color:#64748B;padding:1.5rem;">—</td></tr><?php endif;?>
      </tbody></table></div>
    </div>
  </div>
</div>
<?php break;

/* ═══ JOURNAUX ════════════════════════════════════════════════ */
case 'journaux': ?>
<div class="card">
  <div class="card-hd"><span><i class="bi bi-journal-text me-1" style="color:#00C8FF;"></i>Journaux d'activité — <?=$d['total']?> entrées</span></div>
  <div class="tbl-wrap"><table><thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Description</th><th>IP</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $j):?>
  <tr>
    <td style="font-size:.72rem;color:#64748B;white-space:nowrap;"><?=date('d/m H:i',strtotime($j['date_action']))?></td>
    <td style="font-size:.78rem;"><?=e(trim(($j['prenom']??'').' '.($j['nom']??'')))?:e($j['id_user']?:"Système")?></td>
    <td><span class="badge b-ship" style="text-transform:none;font-size:.65rem;"><?=e($j['action']??'')?></span></td>
    <td style="font-size:.75rem;max-width:300px;"><?=e(mb_substr($j['description']??'',0,80))?></td>
    <td style="font-size:.7rem;color:#64748B;"><?=e($j['ip_adresse']??'—')?></td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($d['rows'])):?><tr><td colspan="5" style="text-align:center;color:#64748B;padding:2rem;">Aucun journal enregistré. <br><small>Les logs apparaissent automatiquement lors des connexions/actions.</small></td></tr><?php endif;?>
  </tbody></table></div>
  <?php renderPagi($d,'journaux');?>
</div>
<?php break;

/* ═══ CHAT ════════════════════════════════════════════════════ */
case 'chat': ?>
<div class="card">
  <div class="card-hd"><span><i class="bi bi-chat-dots-fill me-1" style="color:#00C8FF;"></i><?=$d['total']?> message(s) NEX</span></div>
  <div class="tbl-wrap"><table><thead><tr><th>Date</th><th>Session</th><th>Client</th><th>Rôle</th><th>Message</th></tr></thead><tbody>
  <?php foreach($d['rows'] as $m):?>
  <tr>
    <td style="font-size:.71rem;color:#64748B;white-space:nowrap;"><?=date('d/m H:i',strtotime($m['date_envoi']))?></td>
    <td style="font-size:.68rem;color:#64748B;"><?=e(mb_substr($m['session_id']??'',0,10))?>…</td>
    <td style="font-size:.78rem;"><?=e(trim(($m['prenom']??'').' '.($m['nom']??'')))?:e('Anonyme')?></td>
    <td><span class="badge <?=$m['role']==='user'?'b-ship':'b-ok'?>"><?=e($m['role'])?></span></td>
    <td style="font-size:.77rem;max-width:360px;"><?=e(mb_substr($m['contenu'],0,120)).(mb_strlen($m['contenu'])>120?'…':'')?></td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($d['rows'])):?><tr><td colspan="5" style="text-align:center;color:#64748B;padding:2rem;">Aucun message de chat.</td></tr><?php endif;?>
  </tbody></table></div>
  <?php renderPagi($d,'chat');?>
</div>
<?php break;
endswitch; ?>

  </div><!-- /content -->
</div><!-- /main -->

<script>
// Mobile sidebar toggle
const menuBtn=document.getElementById('menuBtn');
if(menuBtn) { menuBtn.style.display='flex'; menuBtn.addEventListener('click',()=>{ const s=document.getElementById('sidebar'); s.style.transform=s.style.transform.includes('0px')||s.style.transform===''?'translateX(-100%)':'translateX(0px)'; }); }

// Campagne IA
async function genererCampagne(id) {
  if (!confirm('Générer les messages de cette campagne avec NEX IA ?')) return;
  const btn = event.target.closest('button');
  btn.innerHTML='<i class="bi bi-hourglass-split"></i>';
  try {
    const r = await fetch(BASE_URL+'/api/ia.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'campagne',id_campagne:id})});
    const d = await r.json();
    btn.innerHTML='<i class="bi bi-check-lg"></i>';
    alert(d.msg || 'Campagne générée !');
  } catch(e) { btn.innerHTML='<i class="bi bi-robot"></i>'; alert('Erreur de connexion au serveur IA.'); }
}

// Auto-flash disappear
document.querySelectorAll('.flash').forEach(el => setTimeout(() => { el.style.transition='opacity .4s'; el.style.opacity='0'; setTimeout(()=>el.remove(),400); }, 4000));

// Responsive: show menu button on small screens
if (window.innerWidth < 900) { document.getElementById('menuBtn').style.display='flex'; document.getElementById('sidebar').style.transform='translateX(-100%)'; }
window.addEventListener('resize', () => { const s=document.getElementById('sidebar'); if(window.innerWidth>=900){s.style.transform='';} });
</script>
</body>
</html>
