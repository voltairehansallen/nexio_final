<?php
/**
 * Nexio S.A. — Marketing IA
 * Tableau de bord, analyses, génération de campagnes, statistiques
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/app.php';
requireAdmin();
$pdo = getDB();

$section = $_GET['s'] ?? 'dashboard';

// ── Stats Marketing ──────────────────────────────────────────
function mktStats(PDO $pdo): array {
    try {
        $nb  = (int)$pdo->query("SELECT COUNT(*) FROM campagnes")->fetchColumn();
        $env = (int)$pdo->query("SELECT COUNT(*) FROM campagnes WHERE statut IN('Envoyée','En cours')")->fetchColumn();
        $msg = (int)$pdo->query("SELECT COALESCE(SUM(nb_envoyes),0) FROM campagnes")->fetchColumn();
        $rev = (float)$pdo->query("SELECT COALESCE(SUM(revenus_generes),0) FROM campagnes")->fetchColumn();
        $cli = (int)$pdo->query("SELECT COUNT(*) FROM profils_ia")->fetchColumn();
        return compact('nb','env','msg','rev','cli');
    } catch(PDOException) {
        return ['nb'=>0,'env'=>0,'msg'=>0,'rev'=>0,'cli'=>0];
    }
}
$ms = mktStats($pdo);

// ── ACTIONS ──────────────────────────────────────────────────
$flash = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrf($_POST['csrf']??'')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'new_campagne') {
        $canal  = $_POST['canal']  ?? 'Email';
        $type   = $_POST['type']   ?? 'globale';
        $seg    = $_POST['segment'] ?? '';
        $uid    = (int)($_POST['id_user'] ?? 0);
        $deb    = $_POST['date_debut'] ?? null;
        $fin    = $_POST['date_fin']   ?? null;
        $nom    = trim($_POST['nom']   ?? 'Campagne '.date('d/m/Y'));

        // Appel IA via Flask
        $payload = json_encode(['canal'=>$canal,'type'=>$type,'segment'=>$seg,'id_user'=>$uid?:null,'nom'=>$nom]);
        $ch = curl_init('http://127.0.0.1:5001/generer-campagne');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_CONNECTTIMEOUT=>3]);
        $resp = curl_exec($ch); $err=curl_error($ch); curl_close($ch);
        $ia = ($resp && !$err) ? (json_decode($resp,true)??[]) : [];

        $pdo->prepare("INSERT INTO campagnes(nom,canal,type,segment,id_user_cible,titre_ia,slogan,contenu,contenu_email,contenu_whatsapp,contenu_facebook,appel_action,statut,date_debut,date_fin) VALUES(:n,:ca,:ty,:se,:u,:ti,:sl,:co,:ce,:cw,:cf,:aa,'Brouillon',:db,:df)")
            ->execute([':n'=>$nom,':ca'=>$canal,':ty'=>$type,':se'=>$seg,':u'=>$uid?:null,':ti'=>$ia['titre']??$nom,':sl'=>$ia['slogan']??'',':co'=>$ia['contenu']??'',':ce'=>$ia['email']??'',':cw'=>$ia['whatsapp']??'',':cf'=>$ia['facebook']??'',':aa'=>$ia['appel_action']??'',':db'=>$deb?:null,':df'=>$fin?:null]);
        $newId = $pdo->lastInsertId();
        $flash = "Campagne créée (#$newId) — ".(!$err?'générée par NEX IA ✓':'mode hors-ligne, contenu à compléter');
    }

    if ($action === 'launch_campagne') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE campagnes SET statut='En cours',date_envoi=NOW() WHERE id_campagne=:i")->execute([':i'=>$id]);
        // Appel IA pour générer messages
        $ch = curl_init('http://127.0.0.1:5001/campagne');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['id_campagne'=>$id]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>3]);
        $resp=curl_exec($ch); curl_close($ch);
        $r = $resp ? (json_decode($resp,true)??[]) : [];
        $flash = "Campagne lancée — ".($r['messages']??'0')." messages générés.";
    }

    if ($action === 'delete_campagne') {
        $pdo->prepare("DELETE FROM campagnes WHERE id_campagne=:i")->execute([':i'=>(int)($_POST['id']??0)]);
        $flash = 'Campagne supprimée.';
    }

    if ($action === 'analyser_user') {
        $uid = (int)($_POST['id_user'] ?? 0);
        $ch = curl_init('http://127.0.0.1:5001/profil-complet');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['id_user'=>$uid]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_CONNECTTIMEOUT=>3]);
        $resp=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
        $profil = ($resp&&!$err) ? (json_decode($resp,true)??[]) : [];
        if ($profil && isset($profil['segment'])) {
            $pdo->prepare("INSERT INTO profils_ia(id_user,centres_interet,score_achat,probabilite_achat,categorie_preferee,budget_moyen,frequence_achat,segment,recommandations,derniere_analyse) VALUES(:u,:ci,:sa,:pa,:cp,:bm,:fa,:se,:re,NOW()) ON DUPLICATE KEY UPDATE centres_interet=:ci2,score_achat=:sa2,probabilite_achat=:pa2,categorie_preferee=:cp2,budget_moyen=:bm2,frequence_achat=:fa2,segment=:se2,recommandations=:re2,derniere_analyse=NOW()")
                ->execute([':u'=>$uid,':ci'=>json_encode($profil['centres_interet']??[]),':sa'=>$profil['score_achat']??0,':pa'=>$profil['probabilite_achat']??0,':cp'=>$profil['categorie_preferee']??'',':bm'=>$profil['budget_moyen']??0,':fa'=>$profil['frequence_achat']??'',':se'=>$profil['segment']??'',':re'=>json_encode($profil['recommandations']??[]),':ci2'=>json_encode($profil['centres_interet']??[]),':sa2'=>$profil['score_achat']??0,':pa2'=>$profil['probabilite_achat']??0,':cp2'=>$profil['categorie_preferee']??'',':bm2'=>$profil['budget_moyen']??0,':fa2'=>$profil['frequence_achat']??'',':se2'=>$profil['segment']??'',':re2'=>json_encode($profil['recommandations']??[])]);
            $flash = "Profil IA mis à jour pour l'utilisateur #$uid.";
        } else {
            $flash = 'Analyse partielle — serveur IA indisponible, données locales utilisées.';
        }
    }
    header("Location: index.php?s=$section&msg=".urlencode($flash)); exit;
}
if (!empty($_GET['msg'])) $flash = urldecode($_GET['msg']);

// ── Données selon section ────────────────────────────────────
$data = [];
switch($section) {
    case 'clients':
        $data['clients'] = $pdo->query("SELECT u.id_user,u.prenom,u.nom,u.email,(SELECT COUNT(*) FROM commandes WHERE id_user=u.id_user) AS nb_cmd,p.segment,p.score_achat,p.probabilite_achat,p.categorie_preferee,p.derniere_analyse FROM users u JOIN roles r ON u.id_role=r.id_role LEFT JOIN profils_ia p ON p.id_user=u.id_user WHERE r.nom='Client' ORDER BY p.score_achat DESC LIMIT 100")->fetchAll();
        break;
    case 'campagnes':
        $data['campagnes'] = $pdo->query("SELECT * FROM campagnes ORDER BY date_creation DESC LIMIT 50")->fetchAll();
       $data['clients'] = $pdo->query("
SELECT
    u.id_user,
    u.prenom,
    u.nom
FROM users u
INNER JOIN roles r
    ON u.id_role = r.id_role
WHERE r.nom = 'Client'
ORDER BY u.nom, u.prenom
LIMIT 200
")->fetchAll();
    case 'stats':
        $data['perf']      = $pdo->query("SELECT nom,canal,type,statut,nb_destins,nb_envoyes,nb_ouverts,nb_clics,revenus_generes,date_creation FROM campagnes ORDER BY date_creation DESC LIMIT 20")->fetchAll();
        $data['segments']  = $pdo->query("SELECT segment,COUNT(*) AS nb FROM profils_ia WHERE segment IS NOT NULL AND segment!='' GROUP BY segment ORDER BY nb DESC")->fetchAll();
        break;
    default: // dashboard
        $data['recent']    = $pdo->query("SELECT * FROM campagnes ORDER BY date_creation DESC LIMIT 5")->fetchAll();
        $data['top_segs']  = [];
        try { $data['top_segs'] = $pdo->query("SELECT segment,COUNT(*) AS nb FROM profils_ia WHERE segment!='' GROUP BY segment ORDER BY nb DESC LIMIT 5")->fetchAll(); } catch(PDOException){}
}

$ptitle = ['dashboard'=>'Dashboard Marketing','clients'=>'Analyse Clients','campagnes'=>'Génération Campagnes','stats'=>'Statistiques & Performances'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($ptitle[$section]??'Marketing IA',ENT_QUOTES)?> — Nexio Marketing IA</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>const BASE_URL='<?=BASE_URL?>';</script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#06080F;color:#EDF2F7;font-family:'Inter',sans-serif;display:flex;min-height:100vh;}
a{color:inherit;text-decoration:none;}button,input,select,textarea{font-family:inherit;}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:#0D1117}::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:3px}

.sb{width:210px;background:#0D1117;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;}
.sb-logo{display:flex;align-items:center;gap:.5rem;font-weight:900;font-size:.95rem;padding:1rem;border-bottom:1px solid rgba(255,255,255,.06);}
.sb-logo i{color:#00C8FF;font-size:1.1rem;}.sb-logo .dot{color:#00C8FF;}
.sb-back{display:flex;align-items:center;gap:.5rem;padding:.6rem 1rem;color:#64748B;font-size:.78rem;font-weight:600;border-bottom:1px solid rgba(255,255,255,.04);}
.sb-back:hover{color:#EDF2F7;}
.sb-sec{font-size:.58rem;font-weight:800;color:#374151;text-transform:uppercase;letter-spacing:.1em;padding:.7rem 1rem .2rem;}
.sb-link{display:flex;align-items:center;gap:.5rem;padding:.5rem 1rem;color:#6B7280;font-size:.8rem;font-weight:600;border-left:2px solid transparent;transition:all .15s;}
.sb-link:hover{color:#EDF2F7;background:rgba(255,255,255,.03);}
.sb-link.active{color:#00C8FF;background:rgba(0,200,255,.06);border-left-color:#00C8FF;}
.sb-link i{font-size:.85rem;width:14px;}
.main{flex:1;margin-left:210px;display:flex;flex-direction:column;}
.topbar{background:#0D1117;border-bottom:1px solid rgba(255,255,255,.06);padding:0 1.4rem;height:52px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
.topbar h1{font-size:.9rem;font-weight:800;}
.content{padding:1.3rem;flex:1;}

.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.8rem;margin-bottom:1.3rem;}
.stat{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:11px;padding:1rem;display:flex;align-items:center;gap:.7rem;}
.stat-ic{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.stat-val{font-size:1.2rem;font-weight:900;line-height:1.1;}
.stat-lbl{font-size:.63rem;color:#64748B;margin-top:.1rem;}

.card{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:11px;overflow:hidden;margin-bottom:1rem;}
.card-hd{padding:.7rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.06);font-size:.82rem;font-weight:800;display:flex;align-items:center;justify-content:space-between;gap:.7rem;flex-wrap:wrap;}
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{background:#0D1117;padding:.45rem .85rem;font-size:.63rem;font-weight:700;color:#6B7280;text-align:left;text-transform:uppercase;white-space:nowrap;}
td{padding:.5rem .85rem;font-size:.79rem;border-bottom:1px solid rgba(255,255,255,.03);}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(255,255,255,.01);}

.nx-label{display:block;font-size:.71rem;font-weight:700;color:#94A3B8;margin-bottom:.25rem;}
.nx-input{width:100%;background:#0D1117;border:1px solid rgba(255,255,255,.08);border-radius:7px;color:#EDF2F7;padding:.52rem .8rem;font-size:.82rem;outline:none;margin-bottom:.7rem;transition:border-color .2s;}
.nx-input:focus{border-color:rgba(0,200,255,.35);}
textarea.nx-input{resize:vertical;}
.form-card{background:#111827;border:1px solid rgba(0,200,255,.12);border-radius:11px;padding:1.1rem;margin-bottom:1rem;}
.form-card h3{font-size:.82rem;font-weight:800;margin-bottom:.85rem;color:#00C8FF;display:flex;align-items:center;gap:.4rem;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.7rem;}

.btn{border:none;border-radius:7px;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;transition:all .15s;font-size:.77rem;font-weight:700;padding:.42rem .85rem;}
.btn-primary{background:#1D4ED8;color:#fff;}.btn-primary:hover{background:#1e40af;}
.btn-success{background:#059669;color:#fff;}.btn-success:hover{background:#047857;}
.btn-cyan{background:linear-gradient(135deg,#0284C7,#00C8FF);color:#000;}.btn-cyan:hover{opacity:.9;}
.btn-purple{background:rgba(139,92,246,.15);border:1px solid rgba(139,92,246,.25);color:#A5B4FC;}
.btn-danger{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.2);color:#EF4444;padding:.32rem .6rem;}
.btn-sm{padding:.28rem .55rem;font-size:.72rem;}

.badge{padding:.16rem .48rem;border-radius:4px;font-size:.6rem;font-weight:800;text-transform:uppercase;display:inline-block;}
.b-ok{background:rgba(16,185,129,.15);color:#6EE7B7;}
.b-run{background:rgba(0,200,255,.12);color:#00C8FF;}
.b-wait{background:rgba(245,158,11,.15);color:#FCD34D;}
.b-end{background:rgba(139,92,246,.12);color:#A5B4FC;}
.b-cancel{background:rgba(239,68,68,.15);color:#FCA5A5;}

.flash-bar{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:8px;padding:.65rem 1rem;font-size:.82rem;font-weight:600;margin-bottom:1rem;color:#6EE7B7;display:flex;align-items:center;gap:.5rem;}
.seg-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .75rem;border-radius:99px;font-size:.72rem;font-weight:700;margin:.2rem;}

/* Progress bar campagne */
.camp-progress{height:4px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden;margin-top:.4rem;}
.camp-progress-bar{height:100%;background:linear-gradient(90deg,#1D4ED8,#00C8FF);border-radius:2px;transition:width .4s;}

.camp-card{background:var(--bg,#06080F);border:1px solid rgba(255,255,255,.06);border-radius:10px;padding:1rem;margin-bottom:.7rem;transition:border-color .15s;}
.camp-card:hover{border-color:rgba(0,200,255,.2);}

@media(max-width:768px){.sb{display:none}.main{margin-left:0}.grid2,.grid3{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>

<div class="sb">
  <div class="sb-logo"><i class="bi bi-cpu-fill"></i>Marketing<span class="dot">.</span>IA</div>
  <a href="../dashboard.php" class="sb-back"><i class="bi bi-arrow-left"></i> Retour Admin</a>
  <div class="sb-sec">Marketing IA</div>
  <a href="?s=dashboard"  class="sb-link <?=$section==='dashboard'?'active':''?>"><i class="bi bi-grid-fill"></i>Tableau de bord</a>
  <a href="?s=clients"    class="sb-link <?=$section==='clients'?'active':''?>"><i class="bi bi-people-fill"></i>Analyse clients</a>
  <a href="?s=campagnes"  class="sb-link <?=$section==='campagnes'?'active':''?>"><i class="bi bi-megaphone-fill"></i>Campagnes IA</a>
  <a href="?s=stats"      class="sb-link <?=$section==='stats'?'active':''?>"><i class="bi bi-bar-chart-fill"></i>Statistiques</a>
  <div class="sb-sec">Liens rapides</div>
  <a href="../agents/index.php" class="sb-link"><i class="bi bi-robot"></i>Agents IA</a>
  <a href="../../vitrine/index.php" class="sb-link" target="_blank"><i class="bi bi-shop"></i>Voir la boutique</a>
</div>

<div class="main">
  <div class="topbar">
    <h1><i class="bi bi-lightning-charge-fill me-1" style="color:#00C8FF;"></i><?=htmlspecialchars($ptitle[$section]??'Marketing IA',ENT_QUOTES)?></h1>
    <div style="font-size:.78rem;color:#64748B;"><?=htmlspecialchars($_SESSION['user_prenom'].' '.$_SESSION['user_nom'],ENT_QUOTES)?></div>
  </div>
  <div class="content">

  <?php if($flash): ?>
  <div class="flash-bar"><i class="bi bi-check-circle-fill"></i><?=htmlspecialchars($flash,ENT_QUOTES)?></div>
  <?php endif; ?>

  <?php if($section==='dashboard'): ?>
  <div class="stats">
    <div class="stat"><div class="stat-ic" style="background:rgba(0,200,255,.1);color:#00C8FF;"><i class="bi bi-megaphone-fill"></i></div><div><div class="stat-val"><?=$ms['nb']?></div><div class="stat-lbl">Campagnes totales</div></div></div>
    <div class="stat"><div class="stat-ic" style="background:rgba(16,185,129,.1);color:#10B981;"><i class="bi bi-send-fill"></i></div><div><div class="stat-val"><?=$ms['env']?></div><div class="stat-lbl">Actives/Envoyées</div></div></div>
    <div class="stat"><div class="stat-ic" style="background:rgba(139,92,246,.1);color:#A5B4FC;"><i class="bi bi-robot"></i></div><div><div class="stat-val"><?=$ms['cli']?></div><div class="stat-lbl">Profils IA créés</div></div></div>
    <div class="stat"><div class="stat-ic" style="background:rgba(245,158,11,.1);color:#F59E0B;"><i class="bi bi-cash-stack"></i></div><div><div class="stat-val"><?=number_format($ms['rev']/1000,1)?>k</div><div class="stat-lbl">Revenus campagnes</div></div></div>
  </div>

  <div class="grid2">
    <div class="card">
      <div class="card-hd"><span><i class="bi bi-megaphone me-1" style="color:#00C8FF;"></i>Dernières campagnes</span><a href="?s=campagnes" style="color:#00C8FF;font-size:.72rem;">Créer →</a></div>
      <?php foreach($data['recent'] as $c):
        $prog = $c['nb_destins']>0 ? round($c['nb_envoyes']/$c['nb_destins']*100) : 0;
        $bmap = ['Brouillon'=>'b-wait','En cours'=>'b-run','Envoyée'=>'b-ok','Terminée'=>'b-end','Annulée'=>'b-cancel','Planifiée'=>'b-wait'];
        $bcls = $bmap[$c['statut']]??'b-wait';
      ?>
      <div class="camp-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.4rem;">
          <div>
            <div style="font-weight:700;font-size:.83rem;"><?=htmlspecialchars($c['nom'],ENT_QUOTES)?></div>
            <div style="font-size:.68rem;color:#64748B;"><?=htmlspecialchars($c['canal'],ENT_QUOTES)?> · <?=htmlspecialchars($c['type'],ENT_QUOTES)?></div>
          </div>
          <span class="badge <?=$bcls?>"><?=htmlspecialchars($c['statut'],ENT_QUOTES)?></span>
        </div>
        <?php if($c['titre_ia']): ?><div style="font-size:.75rem;color:#00C8FF;margin-bottom:.3rem;">«<?=htmlspecialchars(mb_substr($c['titre_ia'],0,60),ENT_QUOTES)?>»</div><?php endif;?>
        <div style="display:flex;gap:.9rem;font-size:.7rem;color:#64748B;">
          <span><i class="bi bi-people me-1"></i><?=(int)$c['nb_destins']?> dest.</span>
          <span><i class="bi bi-cursor-fill me-1"></i><?=(int)$c['nb_clics']?> clics</span>
          <span><i class="bi bi-cash me-1"></i><?=number_format($c['revenus_generes'],0)?> HTG</span>
        </div>
        <?php if($prog>0): ?><div class="camp-progress mt-2"><div class="camp-progress-bar" style="width:<?=$prog?>%"></div></div><?php endif;?>
      </div>
      <?php endforeach; if(empty($data['recent'])): ?><div style="padding:2rem;text-align:center;color:#64748B;font-size:.82rem;">Aucune campagne. <a href="?s=campagnes" style="color:#00C8FF;">Créez-en une</a></div><?php endif;?>
    </div>

    <div class="card">
      <div class="card-hd"><span><i class="bi bi-pie-chart-fill me-1" style="color:#A5B4FC;"></i>Segments clients IA</span><a href="?s=clients" style="color:#00C8FF;font-size:.72rem;">Analyser →</a></div>
      <div style="padding:1rem;">
        <?php if(!empty($data['top_segs'])): foreach($data['top_segs'] as $seg):
          $colors=['gamers'=>'rgba(139,92,246,.2)','entreprises'=>'rgba(0,200,255,.15)','fidèles'=>'rgba(16,185,129,.15)','étudiants'=>'rgba(245,158,11,.15)','nouveaux'=>'rgba(239,68,68,.15)'];
          $c = $colors[$seg['segment']] ?? 'rgba(100,116,139,.15)';
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem;background:<?=$c?>;border-radius:8px;margin-bottom:.4rem;">
          <span style="font-size:.83rem;font-weight:700;text-transform:capitalize;"><?=htmlspecialchars($seg['segment'],ENT_QUOTES)?></span>
          <span style="font-size:.78rem;font-weight:800;color:#00C8FF;"><?=(int)$seg['nb']?> clients</span>
        </div>
        <?php endforeach; else:?>
        <div style="text-align:center;color:#64748B;padding:1.5rem;font-size:.82rem;">
          <i class="bi bi-robot" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
          Analysez vos clients pour voir leurs segments IA.<br>
          <a href="?s=clients" style="color:#00C8FF;margin-top:.4rem;display:inline-block;">→ Lancer l'analyse</a>
        </div>
        <?php endif;?>
      </div>
    </div>
  </div>

  <!-- Actions rapides -->
  <div class="card">
    <div class="card-hd"><i class="bi bi-lightning-charge-fill me-1" style="color:#00C8FF;"></i>Actions rapides</div>
    <div style="padding:1rem;display:flex;gap:.7rem;flex-wrap:wrap;">
      <a href="?s=campagnes" class="btn btn-cyan"><i class="bi bi-plus-circle-fill"></i> Nouvelle campagne IA</a>
      <a href="?s=clients" class="btn btn-purple"><i class="bi bi-robot"></i> Analyser clients</a>
      <button class="btn" style="background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.2);color:#25D366;" onclick="testEnvoi()"><i class="bi bi-send-check-fill"></i> Test d'envoi</button>
      <a href="../agents/index.php" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);color:#EDF2F7;"><i class="bi bi-activity"></i> Statut agents</a>
      <a href="?s=stats" class="btn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);color:#EDF2F7;"><i class="bi bi-bar-chart-fill"></i> Voir statistiques</a>
    </div>
  </div>

  <?php elseif($section==='clients'): ?>

  <!-- Analyse globale -->
  <div class="form-card" style="margin-bottom:1rem;">
    <h3><i class="bi bi-robot"></i> Analyse IA globale des clients</h3>
    <p style="font-size:.82rem;color:#94A3B8;margin-bottom:.9rem;">Lance l'agent comportemental sur tous les clients pour créer leurs profils IA, segments et scores d'achat.</p>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
      <button class="btn btn-cyan" onclick="analyserTous(this)"><i class="bi bi-play-circle-fill"></i> Analyser tous les clients</button>
      <button class="btn btn-purple" onclick="segmenter(this)"><i class="bi bi-diagram-3-fill"></i> Générer segments automatiques</button>
    </div>
    <div id="analyseStatus" style="margin-top:.7rem;font-size:.8rem;color:#64748B;"></div>
  </div>

  <div class="card">
    <div class="card-hd"><span><i class="bi bi-people-fill me-1" style="color:#00C8FF;"></i><?=count($data['clients'])?> clients</span></div>
    <div class="tbl-wrap"><table>
      <thead><tr><th>#</th><th>Client</th><th>Segment IA</th><th>Score achat</th><th>Probabilité</th><th>Catégorie préférée</th><th>Dernière analyse</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($data['clients'] as $i=>$u): ?>
      <tr>
        <td style="color:#64748B;"><?=$i+1?></td>
        <td>
          <div style="font-weight:700;font-size:.82rem;"><?=htmlspecialchars($u['prenom'].' '.$u['nom'],ENT_QUOTES)?></div>
          <div style="font-size:.7rem;color:#64748B;"><?=htmlspecialchars($u['email'],ENT_QUOTES)?></div>
        </td>
        <td>
          <?php if($u['segment']): ?>
          <span class="badge b-run" style="text-transform:capitalize;"><?=htmlspecialchars($u['segment'],ENT_QUOTES)?></span>
          <?php else: ?><span style="color:#374151;font-size:.72rem;">Non analysé</span><?php endif;?>
        </td>
        <td>
          <?php $sc=(int)($u['score_achat']??0); if($sc): ?>
          <div style="display:flex;align-items:center;gap:.4rem;">
            <div style="width:50px;height:5px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;">
              <div style="width:<?=$sc?>%;height:100%;background:<?=$sc>=70?'#10B981':($sc>=40?'#F59E0B':'#EF4444')?>;border-radius:3px;"></div>
            </div>
            <span style="font-size:.75rem;font-weight:700;"><?=$sc?>/100</span>
          </div>
          <?php else: ?><span style="color:#374151;font-size:.72rem;">—</span><?php endif;?>
        </td>
        <td style="font-size:.78rem;font-weight:700;color:<?=($u['probabilite_achat']??0)>=0.6?'#10B981':($u['probabilite_achat']>=0.3?'#F59E0B':'#64748B')?>"><?=$u['probabilite_achat']?number_format($u['probabilite_achat']*100,0).'%':'—'?></td>
        <td style="font-size:.75rem;color:#94A3B8;"><?=htmlspecialchars($u['categorie_preferee']??'—',ENT_QUOTES)?></td>
        <td style="font-size:.7rem;color:#64748B;"><?=$u['derniere_analyse']?date('d/m H:i',strtotime($u['derniere_analyse'])):'Jamais'?></td>
        <td style="white-space:nowrap;">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf" value="<?=csrf()?>">
            <input type="hidden" name="action" value="analyser_user">
            <input type="hidden" name="id_user" value="<?=(int)$u['id_user']?>">
            <button class="btn btn-purple btn-sm" title="Analyser ce client"><i class="bi bi-robot"></i></button>
          </form>
          <a href="?s=campagnes&uid=<?=(int)$u['id_user']?>&prenom=<?=urlencode($u['prenom'])?>" class="btn btn-primary btn-sm" title="Campagne personnalisée"><i class="bi bi-megaphone-fill"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($data['clients'])): ?><tr><td colspan="8" style="text-align:center;color:#64748B;padding:2rem;">Aucun client.</td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>

  <?php elseif($section==='campagnes'):
    $uid_pref = (int)($_GET['uid'] ?? 0);
    $prenom_pref = htmlspecialchars($_GET['prenom'] ?? '', ENT_QUOTES);
  ?>
  <!-- Créer campagne -->
  <div class="form-card">
    <h3><i class="bi bi-plus-circle-fill"></i> Nouvelle campagne IA</h3>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?=csrf()?>">
      <input type="hidden" name="action" value="new_campagne">
      <div class="grid3">
        <div style="grid-column:1/3"><label class="nx-label">Nom de la campagne</label><input type="text" name="nom" class="nx-input" required placeholder="Ex: Soldes Gaming Novembre 2025" value="<?=$prenom_pref?'Campagne personnalisée — '.$prenom_pref:''?>"></div>
        <div><label class="nx-label">Canal</label>
          <select name="canal" class="nx-input">
            <?php foreach(['Email','WhatsApp','Facebook','Notification','Multi-canal'] as $c):?>
            <option value="<?=$c?>"><?=$c?></option><?php endforeach;?>
          </select></div>
      </div>
      <div class="grid3">
        <div><label class="nx-label">Type</label>
          <select name="type" class="nx-input" id="campType" onchange="toggleTarget()">
            <option value="globale">🌍 Globale (tous)</option>
            <option value="segment" <?= $prenom_pref ? '' : '' ?>>📊 Segment</option>
             <option value="personnalisée" <?= $prenom_pref ? 'selected' : '' ?>>👤 Personnalisée</option>
          </select></div>
        <div id="segDiv"><label class="nx-label">Segment cible</label>
          <select name="segment" class="nx-input">
            <option value="">-- Automatique IA --</option>
            <?php foreach(['gamers','entreprises','étudiants','fidèles','nouveaux clients','professionnels'] as $s):?>
            <option value="<?=$s?>"><?=ucfirst($s)?></option><?php endforeach;?>
          </select></div>
        <div id="userDiv"><label class="nx-label">Client spécifique</label>
          <select name="id_user" class="nx-input">
            <option value="">-- Choisir un client --</option>
            <?php foreach($data['clients'] as $u):?>
            <option value="<?=(int)$u['id_user']?>" <?=$uid_pref==$u['id_user']?'selected':''?>><?=htmlspecialchars($u['prenom'].' '.$u['nom'],ENT_QUOTES)?></option>
            <?php endforeach;?>
          </select></div>
      </div>
      <div class="grid2">
        <div><label class="nx-label">Date de début</label><input type="datetime-local" name="date_debut" class="nx-input"></div>
        <div><label class="nx-label">Date de fin</label><input type="datetime-local" name="date_fin" class="nx-input"></div>
      </div>
      <div style="background:rgba(0,200,255,.04);border:1px solid rgba(0,200,255,.12);border-radius:8px;padding:.8rem;margin-bottom:.85rem;font-size:.78rem;color:#94A3B8;">
        <i class="bi bi-robot me-1" style="color:#00C8FF;"></i>
        <strong style="color:#00C8FF;">NEX IA</strong> générera automatiquement : titre accrocheur, slogan, message Email, message WhatsApp, message Facebook et appel à l'action — personnalisés selon le profil du/des client(s) ciblé(s).
      </div>
      <button class="btn btn-cyan" type="submit"><i class="bi bi-lightning-charge-fill"></i> Générer avec NEX IA</button>
    </form>
  </div>

  <!-- Liste campagnes -->
  <div class="card">
    <div class="card-hd"><span><i class="bi bi-list-ul me-1" style="color:#00C8FF;"></i><?=count($data['campagnes'])?> campagne(s)</span></div>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Campagne</th><th>Canal</th><th>Type</th><th>Statut</th><th>Début</th><th>Fin</th><th>Dest.</th><th>Clics</th><th>CA</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($data['campagnes'] as $c):
        $bmap=['Brouillon'=>'b-wait','En cours'=>'b-run','Envoyée'=>'b-ok','Terminée'=>'b-end','Annulée'=>'b-cancel','Planifiée'=>'b-wait'];
        $bcls=$bmap[$c['statut']]??'b-wait';
        // Auto-terminée si date_fin dépassée
        $termine = $c['date_fin'] && strtotime($c['date_fin'])<time() && in_array($c['statut'],['En cours','Planifiée']);
      ?>
      <tr>
        <td>
          <div style="font-weight:700;font-size:.8rem;"><?=htmlspecialchars(mb_substr($c['nom'],0,40),ENT_QUOTES)?></div>
          <?php if($c['titre_ia']):?><div style="font-size:.68rem;color:#00C8FF;">«<?=htmlspecialchars(mb_substr($c['titre_ia'],0,50),ENT_QUOTES)?>»</div><?php endif;?>
          <?php if($c['segment']):?><span style="font-size:.65rem;background:rgba(139,92,246,.12);color:#A5B4FC;padding:.1rem .4rem;border-radius:4px;"><?=htmlspecialchars($c['segment'],ENT_QUOTES)?></span><?php endif;?>
        </td>
        <td style="font-size:.73rem;"><?=htmlspecialchars($c['canal'],ENT_QUOTES)?></td>
        <td style="font-size:.73rem;color:#64748B;"><?=htmlspecialchars($c['type'],ENT_QUOTES)?></td>
        <td><span class="badge <?=$termine?'b-end':$bcls?>"><?=$termine?'Terminée':htmlspecialchars($c['statut'],ENT_QUOTES)?></span></td>
        <td style="font-size:.7rem;color:#64748B;"><?=$c['date_debut']?date('d/m/y',strtotime($c['date_debut'])):'—'?></td>
        <td style="font-size:.7rem;color:<?=$termine?'#EF4444':'#64748B'?>;"><?=$c['date_fin']?date('d/m/y',strtotime($c['date_fin'])):'—'?></td>
        <td style="font-size:.75rem;"><?=(int)$c['nb_destins']?></td>
        <td style="font-size:.75rem;font-weight:700;"><?=(int)$c['nb_clics']?></td>
        <td style="font-size:.73rem;"><?=number_format($c['revenus_generes'],0)?></td>
        <td style="white-space:nowrap;">
          <button class="btn btn-primary btn-sm" onclick="voirCampagne(<?=(int)$c['id_campagne']?>)" title="Voir contenu"><i class="bi bi-eye-fill"></i></button>
          <?php if(!in_array($c['statut'],['Envoyée','Terminée','Annulée'])):?>
          <button class="btn btn-success btn-sm" onclick="envoyerCampagne(<?=(int)$c['id_campagne']?>,this)" title="Envoyer maintenant"><i class="bi bi-send-fill"></i></button>
          <button class="btn btn-sm" style="background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.2);color:#25D366;" onclick="envoyerCanal(<?=(int)$c['id_campagne']?>,'WhatsApp',this)" title="WhatsApp"><i class="bi bi-whatsapp"></i></button>
          <button class="btn btn-sm" style="background:rgba(24,119,242,.12);border:1px solid rgba(24,119,242,.2);color:#60A5FA;" onclick="envoyerCanal(<?=(int)$c['id_campagne']?>,'Facebook',this)" title="Facebook"><i class="bi bi-facebook"></i></button>
          <?php endif;?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_campagne"><input type="hidden" name="id" value="<?=(int)$c['id_campagne']?>"><button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill"></i></button></form>
        </td>
      </tr>
      <?php endforeach; if(empty($data['campagnes'])): ?><tr><td colspan="10" style="text-align:center;color:#64748B;padding:2rem;">Aucune campagne. Créez-en une ci-dessus.</td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>

  <?php elseif($section==='stats'): ?>

  <div class="stats" style="grid-template-columns:repeat(4,1fr);">
    <?php
    $total_env  = array_sum(array_column($data['perf'],'nb_envoyes'));
    $total_ouv  = array_sum(array_column($data['perf'],'nb_ouverts'));
    $total_cli2 = array_sum(array_column($data['perf'],'nb_clics'));
    $total_rev  = array_sum(array_column($data['perf'],'revenus_generes'));
    $taux_ouv   = $total_env>0 ? round($total_ouv/$total_env*100,1) : 0;
    $taux_clic  = $total_env>0 ? round($total_cli2/$total_env*100,1) : 0;
    ?>
    <div class="stat"><div class="stat-ic" style="background:rgba(0,200,255,.1);color:#00C8FF;"><i class="bi bi-send-fill"></i></div><div><div class="stat-val"><?=number_format($total_env)?></div><div class="stat-lbl">Messages envoyés</div></div></div>
    <div class="stat"><div class="stat-ic" style="background:rgba(16,185,129,.1);color:#10B981;"><i class="bi bi-envelope-open-fill"></i></div><div><div class="stat-val"><?=$taux_ouv?>%</div><div class="stat-lbl">Taux d'ouverture</div></div></div>
    <div class="stat"><div class="stat-ic" style="background:rgba(245,158,11,.1);color:#F59E0B;"><i class="bi bi-cursor-fill"></i></div><div><div class="stat-val"><?=$taux_clic?>%</div><div class="stat-lbl">Taux de clic</div></div></div>
    <div class="stat"><div class="stat-ic" style="background:rgba(139,92,246,.1);color:#A5B4FC;"><i class="bi bi-cash-stack"></i></div><div><div class="stat-val"><?=number_format($total_rev/1000,1)?>k</div><div class="stat-lbl">CA généré HTG</div></div></div>
  </div>

  <div class="grid2">
    <div class="card">
      <div class="card-hd"><i class="bi bi-bar-chart-fill me-1" style="color:#00C8FF;"></i>Performances campagnes</div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>Campagne</th><th>Canal</th><th>Envoyés</th><th>Ouverts</th><th>Clics</th><th>CA</th></tr></thead>
        <tbody>
        <?php foreach($data['perf'] as $c):?>
        <tr>
          <td style="font-size:.78rem;font-weight:700;"><?=htmlspecialchars(mb_substr($c['nom'],0,30),ENT_QUOTES)?></td>
          <td style="font-size:.72rem;"><?=htmlspecialchars($c['canal'],ENT_QUOTES)?></td>
          <td><?=(int)$c['nb_envoyes']?></td>
          <td style="color:#10B981;"><?=(int)$c['nb_ouverts']?></td>
          <td style="color:#F59E0B;font-weight:700;"><?=(int)$c['nb_clics']?></td>
          <td style="font-size:.73rem;"><?=number_format($c['revenus_generes'],0)?></td>
        </tr>
        <?php endforeach;?>
        <?php if(empty($data['perf'])):?><tr><td colspan="6" style="text-align:center;color:#64748B;padding:1.5rem;">Aucune donnée.</td></tr><?php endif;?>
        </tbody>
      </table></div>
    </div>
    <div class="card">
      <div class="card-hd"><i class="bi bi-pie-chart-fill me-1" style="color:#A5B4FC;"></i>Répartition par segments</div>
      <div style="padding:1rem;">
        <?php foreach($data['segments'] as $seg):
          $pct = $ms['cli']>0 ? round($seg['nb']/$ms['cli']*100) : 0;
        ?>
        <div style="margin-bottom:.75rem;">
          <div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:.25rem;">
            <span style="font-weight:700;text-transform:capitalize;"><?=htmlspecialchars($seg['segment'],ENT_QUOTES)?></span>
            <span style="color:#64748B;"><?=(int)$seg['nb']?> clients (<?=$pct?>%)</span>
          </div>
          <div style="height:5px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;">
            <div style="width:<?=$pct?>%;height:100%;background:linear-gradient(90deg,#1D4ED8,#00C8FF);border-radius:3px;"></div>
          </div>
        </div>
        <?php endforeach;?>
        <?php if(empty($data['segments'])):?><div style="text-align:center;color:#64748B;padding:1.5rem;font-size:.82rem;">Analysez vos clients pour générer les segments.</div><?php endif;?>
      </div>
    </div>
  </div>
  <?php endif;?>

  </div><!-- /content -->
</div>

<!-- Modal campagne détail -->
<div id="campModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:900;align-items:center;justify-content:center;padding:1rem;">
  <div style="background:#111827;border:1px solid rgba(0,200,255,.2);border-radius:16px;width:100%;max-width:680px;max-height:88vh;overflow-y:auto;padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
      <h3 style="font-size:.95rem;font-weight:800;color:#00C8FF;"><i class="bi bi-megaphone-fill me-2"></i>Contenu campagne</h3>
      <button onclick="document.getElementById('campModal').style.display='none'" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#EDF2F7;width:30px;height:30px;border-radius:50%;cursor:pointer;">✕</button>
    </div>
    <div id="campModalContent" style="font-size:.83rem;"></div>
  </div>
</div>

<script>
// ── Envoi campagne ────────────────────────────────────────────
async function envoyerCampagne(id, btn) {
  if (!confirm('Envoyer cette campagne maintenant à tous les destinataires ?')) return;
  const orig = btn.innerHTML;
  btn.innerHTML='<i class="bi bi-hourglass-split"></i>';
  btn.disabled=true;
  try {
    const r = await fetch(BASE_URL+'/api/envoi.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'envoyer_campagne',id_campagne:id})});
    const d = await r.json();
    if (d.error) { alert('Erreur : '+d.error); }
    else { alert(`✅ Campagne envoyée !\n${d.envoyes||0} messages envoyés sur ${d.destinataires||0} destinataires.`); location.reload(); }
  } catch(e) { alert('Serveur IA non disponible. Lancez python/main.py'); }
  btn.innerHTML=orig; btn.disabled=false;
}

async function envoyerCanal(id, canal, btn) {
  if (!confirm(`Envoyer sur ${canal} ?`)) return;
  const orig = btn.innerHTML;
  btn.innerHTML='<i class="bi bi-hourglass-split"></i>';
  btn.disabled=true;
  try {
    const r = await fetch(BASE_URL+'/api/envoi.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'envoyer_campagne',id_campagne:id,canal})});
    const d = await r.json();
    if (d.error) { alert('Erreur : '+d.error); }
    else { alert(`✅ Envoyé sur ${canal} !\n${d.envoyes||0}/${d.destinataires||0} destinataires.`); }
  } catch(e) { alert('Serveur IA non disponible.'); }
  btn.innerHTML=orig; btn.disabled=false;
}

// ── Test envoi ────────────────────────────────────────────────
async function testEnvoi() {
  const email = prompt('Email de test (ou numéro pour WhatsApp) :');
  if (!email) return;
  const canal = prompt('Canal (Email/WhatsApp/Facebook):', 'Email') || 'Email';
  const msg   = prompt('Message de test :', 'Test Nexio IA — tout fonctionne !') || '';
  const r = await fetch(BASE_URL+'/api/envoi.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'test_envoi',email,message:msg,canal})});
  const d = await r.json();
  alert(`Résultat : ${d.statut||d.msg||JSON.stringify(d)}`);
}

document.addEventListener('DOMContentLoaded', () => {
  // Vérifier campagnes expirées au chargement
  fetch(BASE_URL+'/api/envoi.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'check_expired'})}).catch(()=>{});
});
  const t = document.getElementById('campType').value;
  document.getElementById('segDiv').style.opacity = t==='segment'?'1':'0.4';
  document.getElementById('userDiv').style.opacity = t==='personnalisée'?'1':'0.4';
}
toggleTarget();

async function analyserTous(btn) {
  btn.innerHTML='<i class="bi bi-hourglass-split"></i> Analyse en cours...';
  btn.disabled=true;
  document.getElementById('analyseStatus').innerHTML='<i class="bi bi-robot" style="color:#00C8FF;margin-right:.3rem;"></i>NEX IA analyse les comportements clients...';
  try {
    const r = await fetch('http://127.0.0.1:5001/comportement', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({})});
    const d = await r.json();
    document.getElementById('analyseStatus').innerHTML='<span style="color:#10B981;">✓ '+(d.resultats?d.resultats.length:0)+' clients analysés avec succès.</span>';
    setTimeout(()=>location.reload(),1500);
  } catch {
    document.getElementById('analyseStatus').innerHTML='<span style="color:#F59E0B;">⚠ Serveur IA hors-ligne. Lancez python/main.py</span>';
  }
  btn.innerHTML='<i class="bi bi-play-circle-fill"></i> Analyser tous les clients';
  btn.disabled=false;
}

async function segmenter(btn) {
  btn.innerHTML='<i class="bi bi-hourglass-split"></i> Segmentation...';
  try {
    const r = await fetch('<?=BASE_URL?>/api/marketing.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'segmenter'})});
    const d = await r.json();
    alert(d.msg || 'Segments générés !');
    location.reload();
  } catch { alert('Serveur IA indisponible.'); }
  btn.innerHTML='<i class="bi bi-diagram-3-fill"></i> Générer segments automatiques';
}

async function voirCampagne(id) {
  const modal = document.getElementById('campModal');
  const content = document.getElementById('campModalContent');
  modal.style.display='flex';
  content.innerHTML='<div style="text-align:center;padding:2rem;color:#64748B;">Chargement...</div>';
  try {
    const r = await fetch('<?=BASE_URL?>/api/marketing.php?action=get_campagne&id='+id);
    const d = await r.json();
    if (d.error) { content.innerHTML='<p style="color:#EF4444;">'+d.error+'</p>'; return; }
    content.innerHTML = `
      <div style="margin-bottom:1rem;"><span style="font-size:.68rem;color:#64748B;text-transform:uppercase;font-weight:700;">Titre IA</span><div style="font-size:1.05rem;font-weight:800;color:#00C8FF;margin-top:.2rem;">${d.titre_ia||d.nom||'—'}</div></div>
      ${d.slogan?`<div style="background:rgba(0,200,255,.06);border-left:3px solid #00C8FF;padding:.6rem .9rem;border-radius:0 8px 8px 0;margin-bottom:1rem;font-style:italic;font-size:.85rem;">"${d.slogan}"</div>`:''}
      ${d.contenu_email?`<div style="margin-bottom:.9rem;"><div style="font-size:.7rem;font-weight:700;color:#60A5FA;text-transform:uppercase;margin-bottom:.35rem;"><i class="bi bi-envelope-fill me-1"></i>Email</div><div style="background:#0D1117;border-radius:8px;padding:.8rem;font-size:.8rem;white-space:pre-wrap;line-height:1.6;">${d.contenu_email}</div></div>`:''}
      ${d.contenu_whatsapp?`<div style="margin-bottom:.9rem;"><div style="font-size:.7rem;font-weight:700;color:#25D366;text-transform:uppercase;margin-bottom:.35rem;"><i class="bi bi-whatsapp me-1"></i>WhatsApp</div><div style="background:#0D1117;border-radius:8px;padding:.8rem;font-size:.8rem;white-space:pre-wrap;line-height:1.6;">${d.contenu_whatsapp}</div></div>`:''}
      ${d.contenu_facebook?`<div style="margin-bottom:.9rem;"><div style="font-size:.7rem;font-weight:700;color:#1877F2;text-transform:uppercase;margin-bottom:.35rem;"><i class="bi bi-facebook me-1"></i>Facebook</div><div style="background:#0D1117;border-radius:8px;padding:.8rem;font-size:.8rem;white-space:pre-wrap;line-height:1.6;">${d.contenu_facebook}</div></div>`:''}
      ${d.appel_action?`<div style="margin-top:1rem;"><a href="${d.appel_action}" style="display:inline-block;background:linear-gradient(135deg,#1D4ED8,#00C8FF);color:#000;padding:.6rem 1.4rem;border-radius:8px;font-weight:800;font-size:.85rem;">${d.appel_action}</a></div>`:''}
      ${!d.contenu_email&&!d.contenu&&!d.titre_ia?'<div style="color:#64748B;text-align:center;padding:1.5rem;">Contenu non encore généré. Lancez NEX IA pour cette campagne.</div>':''}
    `;
  } catch(e) { content.innerHTML='<p style="color:#EF4444;">Erreur : '+e.message+'</p>'; }
}

document.getElementById('campModal').addEventListener('click', function(e) { if(e.target===this) this.style.display='none'; });
</script>
</body></html>
