<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
requireLogin();
$pdo = getDB();
$uid = $_SESSION['user_id'];

// Crée table préférences si absente
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS preferences_utilisateur (
        id_pref INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL UNIQUE,
        email_marketing TINYINT(1) NOT NULL DEFAULT 1,
        notif_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
        notif_app TINYINT(1) NOT NULL DEFAULT 1,
        recommandations_nex TINYINT(1) NOT NULL DEFAULT 1,
        alerte_prix TINYINT(1) NOT NULL DEFAULT 1,
        alerte_stock TINYINT(1) NOT NULL DEFAULT 1,
        promo_newsletter TINYINT(1) NOT NULL DEFAULT 1,
        campagne_facebook TINYINT(1) NOT NULL DEFAULT 0,
        categories_interets TEXT DEFAULT NULL,
        budget_min INT DEFAULT NULL,
        budget_max INT DEFAULT NULL,
        marques_favorites TEXT DEFAULT NULL,
        frequence ENUM('immédiat','quotidien','hebdomadaire') NOT NULL DEFAULT 'quotidien',
        date_modif DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(id_user) REFERENCES users(id_user) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch(PDOException){}

$success = '';
// Charger préférences existantes
$pref = $pdo->prepare("SELECT * FROM preferences_utilisateur WHERE id_user=:u");
$pref->execute([':u'=>$uid]); $pref=$pref->fetch();
if (!$pref) {
    $pdo->prepare("INSERT INTO preferences_utilisateur(id_user) VALUES(:u)")->execute([':u'=>$uid]);
    $pref=$pdo->prepare("SELECT * FROM preferences_utilisateur WHERE id_user=:u");
    $pref->execute([':u'=>$uid]); $pref=$pref->fetch();
}

if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrf($_POST['csrf']??'')) {
    $cats  = json_encode($_POST['categories'] ?? []);
    $marqs = json_encode($_POST['marques']    ?? []);
    $pdo->prepare("UPDATE preferences_utilisateur SET
        email_marketing=:em, notif_whatsapp=:wa, notif_app=:na, recommandations_nex=:rn,
        alerte_prix=:ap, alerte_stock=:as_, promo_newsletter=:pn, campagne_facebook=:cf,
        categories_interets=:ci, budget_min=:bmin, budget_max=:bmax, marques_favorites=:mf, frequence=:fr
        WHERE id_user=:u
    ")->execute([
        ':em' =>isset($_POST['email_marketing'])?1:0,
        ':wa' =>isset($_POST['notif_whatsapp'])?1:0,
        ':na' =>isset($_POST['notif_app'])?1:0,
        ':rn' =>isset($_POST['recommandations_nex'])?1:0,
        ':ap' =>isset($_POST['alerte_prix'])?1:0,
        ':as_'=>isset($_POST['alerte_stock'])?1:0,
        ':pn' =>isset($_POST['promo_newsletter'])?1:0,
        ':cf' =>isset($_POST['campagne_facebook'])?1:0,
        ':ci' =>$cats,':mf'=>$marqs,
        ':bmin'=>$_POST['budget_min']?:(null),
        ':bmax'=>$_POST['budget_max']?:(null),
        ':fr' =>$_POST['frequence']??'quotidien',
        ':u'  =>$uid,
    ]);
    // Log
    try {$pdo->prepare("INSERT INTO journal_activites(id_user,action,description,ip_adresse) VALUES(:u,'preferences','Mise à jour des préférences',:ip)")->execute([':u'=>$uid,':ip'=>$_SERVER['REMOTE_ADDR']??'']);}catch(PDOException){}
    $success = 'Préférences sauvegardées avec succès.';
    $pref=$pdo->prepare("SELECT * FROM preferences_utilisateur WHERE id_user=:u");
    $pref->execute([':u'=>$uid]); $pref=$pref->fetch();
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
$marques    = $pdo->query("SELECT * FROM marques ORDER BY nom LIMIT 20")->fetchAll();
$cats_sel   = json_decode($pref['categories_interets']??'[]', true) ?: [];
$marqs_sel  = json_decode($pref['marques_favorites']??'[]',  true) ?: [];

$pageTitle = 'Mes préférences';
require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';

function chk($val): string { return $val ? 'checked' : ''; }
?>
<div class="nx-section" style="max-width:860px;margin:0 auto;">
  <div class="sec-hd mb-4" data-animate>
    <h1 style="font-size:1.5rem;"><i class="bi bi-sliders" style="color:var(--cyan);margin-right:.5rem;"></i>Centre de préférences</h1>
    <a href="compte/index.php" class="btn-nx-ghost" style="font-size:.8rem;"><i class="bi bi-arrow-left"></i> Mon compte</a>
  </div>

  <?php if($success):?>
  <div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius);padding:.8rem 1rem;margin-bottom:1.2rem;color:var(--success);font-size:.85rem;font-weight:600;" data-animate>
    <i class="bi bi-check-circle-fill me-2"></i><?=$success?>
  </div>
  <?php endif;?>

  <form method="POST">
    <input type="hidden" name="csrf" value="<?=csrf()?>">

    <!-- Communications -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;margin-bottom:1rem;" data-animate>
      <h3 style="font-size:.95rem;margin-bottom:1.2rem;"><i class="bi bi-bell-fill me-2" style="color:var(--cyan);"></i>Communications</h3>
      <div style="display:grid;gap:.7rem;">
        <?php
        $opts=[
          ['email_marketing','bi-envelope-fill','#60A5FA','Emails marketing','Recevez nos offres et promotions par email'],
          ['notif_whatsapp','bi-whatsapp','#25D366','Messages WhatsApp','Notifications via WhatsApp'],
          ['notif_app','bi-bell-fill','#00C8FF','Notifications app','Alertes dans l\'application'],
          ['recommandations_nex','bi-robot','#A5B4FC','Recommandations NEX IA','Suggestions personnalisées de l\'assistant NEX'],
          ['alerte_prix','bi-tags-fill','#F59E0B','Alertes baisse de prix','Soyez informé quand un produit de votre wishlist baisse'],
          ['alerte_stock','bi-box-seam-fill','#10B981','Alertes retour en stock','Notification quand un produit redevient disponible'],
          ['promo_newsletter','bi-megaphone-fill','#EF4444','Newsletter promotions','Nouvelles promotions et ventes flash'],
          ['campagne_facebook','bi-facebook','#1877F2','Campagnes Facebook','Publications personnalisées sur Facebook'],
        ];
        foreach($opts as [$name,$ico,$color,$label,$desc]):
        ?>
        <label style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:border-color .15s;" onmouseover="this.style.borderColor='rgba(0,200,255,.2)'" onmouseout="this.style.borderColor='rgba(255,255,255,.06)'">
          <div style="display:flex;align-items:center;gap:.75rem;">
            <div style="width:34px;height:34px;background:rgba(255,255,255,.05);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="bi <?=$ico?>" style="color:<?=$color?>;font-size:.95rem;"></i>
            </div>
            <div>
              <div style="font-size:.85rem;font-weight:700;"><?=$label?></div>
              <div style="font-size:.72rem;color:var(--muted);"><?=$desc?></div>
            </div>
          </div>
          <div style="position:relative;width:42px;height:24px;flex-shrink:0;">
            <input type="checkbox" name="<?=$name?>" <?=chk($pref[$name]??1)?> style="opacity:0;position:absolute;width:100%;height:100%;cursor:pointer;z-index:2;margin:0;" class="toggle-inp" id="tog-<?=$name?>">
            <div class="toggle-track" style="position:absolute;inset:0;background:<?=($pref[$name]??1)?'var(--blue)':'rgba(255,255,255,.1)?>;border-radius:12px;transition:background .2s;"></div>
            <div style="position:absolute;top:3px;<?=($pref[$name]??1)?'left:21px':'left:3px'?>;width:18px;height:18px;background:#fff;border-radius:50%;transition:left .2s;pointer-events:none;"></div>
          </div>
        </label>
        <?php endforeach;?>
      </div>
    </div>

    <!-- Préférences produits -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;margin-bottom:1rem;" data-animate style="animation-delay:.1s;">
      <h3 style="font-size:.95rem;margin-bottom:1.2rem;"><i class="bi bi-heart-fill me-2" style="color:var(--danger);"></i>Préférences produits</h3>

      <label class="nx-label">Catégories d'intérêt</label>
      <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.85rem;">
        <?php foreach($categories as $c):?>
        <label style="cursor:pointer;">
          <input type="checkbox" name="categories[]" value="<?=$c['id_categorie']?>" style="display:none;" <?=in_array($c['id_categorie'],$cats_sel)?'checked':''?> class="cat-cb">
          <span class="nx-badge nx-badge-cyan" style="cursor:pointer;transition:all .15s;<?=in_array($c['id_categorie'],$cats_sel)?'background:rgba(0,200,255,.25);':'opacity:.5;'?>" id="cb-cat-<?=$c['id_categorie']?>"><?=e($c['nom'])?></span>
        </label>
        <?php endforeach;?>
      </div>

      <label class="nx-label">Marques favorites</label>
      <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.85rem;">
        <?php foreach($marques as $m):?>
        <label style="cursor:pointer;">
          <input type="checkbox" name="marques[]" value="<?=$m['id_marque']?>" style="display:none;" <?=in_array($m['id_marque'],$marqs_sel)?'checked':''?> class="marq-cb">
          <span class="nx-badge nx-badge-cyan" style="cursor:pointer;transition:all .15s;<?=in_array($m['id_marque'],$marqs_sel)?'background:rgba(0,200,255,.25);':'opacity:.5;'?>" id="cb-marq-<?=$m['id_marque']?>"><?=e($m['nom'])?></span>
        </label>
        <?php endforeach;?>
      </div>

      <div class="row g-3">
        <div class="col-6"><label class="nx-label">Budget min (HTG)</label><input type="number" name="budget_min" class="nx-input" min="0" placeholder="0" value="<?=$pref['budget_min']??''?>"></div>
        <div class="col-6"><label class="nx-label">Budget max (HTG)</label><input type="number" name="budget_max" class="nx-input" min="0" placeholder="200000" value="<?=$pref['budget_max']??''?>"></div>
        <div class="col-12"><label class="nx-label">Fréquence des messages</label>
          <select name="frequence" class="nx-input">
            <?php foreach(['immédiat'=>'Immédiat (temps réel)','quotidien'=>'Quotidien (résumé)','hebdomadaire'=>'Hebdomadaire'] as $v=>$l):?>
            <option value="<?=$v?>" <?=($pref['frequence']??'quotidien')===$v?'selected':''?>><?=$l?></option>
            <?php endforeach;?>
          </select></div>
      </div>
    </div>

    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
      <button type="submit" class="btn-nx-primary"><i class="bi bi-check-lg"></i> Sauvegarder mes préférences</button>
      <a href="compte/index.php" class="btn-nx-ghost"><i class="bi bi-arrow-left"></i> Annuler</a>
    </div>
  </form>
</div>
<script>
// Toggle UI
document.querySelectorAll('.toggle-inp').forEach(inp => {
  inp.addEventListener('change', function() {
    const track = this.nextElementSibling;
    const knob  = track.nextElementSibling;
    track.style.background = this.checked ? 'var(--blue)' : 'rgba(255,255,255,.1)';
    knob.style.left = this.checked ? '21px' : '3px';
  });
});
// Category/brand chip toggle
document.querySelectorAll('.cat-cb,.marq-cb').forEach(cb => {
  const span = cb.nextElementSibling;
  cb.addEventListener('change', function() {
    span.style.opacity = this.checked ? '1' : '.5';
    span.style.background = this.checked ? 'rgba(0,200,255,.25)' : '';
  });
});
</script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
