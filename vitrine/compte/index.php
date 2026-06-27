<?php
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/app.php';
requireLogin();
$pdo = getDB();
$uid = $_SESSION['user_id'];

$user = $pdo->prepare("SELECT * FROM users WHERE id_user=:id");
$user->execute([':id'=>$uid]); $user = $user->fetch();

$commandes = $pdo->prepare("SELECT * FROM commandes WHERE id_user=:u ORDER BY date_commande DESC LIMIT 10");
$commandes->execute([':u'=>$uid]); $commandes = $commandes->fetchAll();

$success = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profil') {
        $tel  = trim($_POST['telephone'] ?? '');
        $addr = trim($_POST['adresse']   ?? '');
        $pdo->prepare("UPDATE users SET telephone=:t,adresse=:a WHERE id_user=:id")->execute([':t'=>$tel,':a'=>$addr,':id'=>$uid]);
        $_SESSION['user_prenom'] = $user['prenom'];
        $success = 'Profil mis à jour avec succès.';
    }
    if ($action === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cnf = $_POST['confirm_password'] ?? '';
        if (!password_verify($old, $user['mot_de_passe'])) { $error = 'Mot de passe actuel incorrect.'; }
        elseif (strlen($new) < 6)                           { $error = 'Nouveau mot de passe trop court.'; }
        elseif ($new !== $cnf)                              { $error = 'Les mots de passe ne correspondent pas.'; }
        else {
            $pdo->prepare("UPDATE users SET mot_de_passe=:p WHERE id_user=:id")->execute([':p'=>password_hash($new,PASSWORD_BCRYPT),':id'=>$uid]);
            $success = 'Mot de passe modifié avec succès.';
        }
    }
    if ($action) {
        $user = $pdo->prepare("SELECT * FROM users WHERE id_user=:id");
        $user->execute([':id'=>$uid]); $user = $user->fetch();
    }
}

$pageTitle = 'Mon compte';
require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';
?>
<div class="nx-section">
  <div class="row g-4">
    <!-- Sidebar compte -->
    <div class="col-12 col-lg-3" data-animate>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;text-align:center;">
        <div style="width:72px;height:72px;background:linear-gradient(135deg,var(--blue),var(--cyan));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:900;color:#fff;margin:0 auto .9rem;">
          <?=strtoupper(mb_substr($user['prenom'],0,1).''.mb_substr($user['nom'],0,1))?>
        </div>
        <div style="font-weight:800;font-size:1rem;"><?=e($user['prenom'].' '.$user['nom'])?></div>
        <div style="font-size:.78rem;color:var(--muted);"><?=e($user['email'])?></div>
        <span class="nx-badge nx-badge-success mt-2"><?=e($user['statut']??'Actif')?></span>
        <hr style="border-color:var(--border);margin:1rem 0;">
        <nav style="display:flex;flex-direction:column;gap:.3rem;text-align:left;">
          <?php foreach([['#profil','bi-person-fill','Mon profil'],['#commandes','bi-bag-check-fill','Mes commandes'],['#securite','bi-shield-lock-fill','Sécurité'],['../wishlist.php','bi-heart-fill','Liste de souhaits']] as [$href,$ico,$label]): ?>
          <a href="<?=$href?>" style="display:flex;align-items:center;gap:.6rem;padding:.5rem .7rem;border-radius:8px;font-size:.83rem;font-weight:600;color:var(--text2);transition:all .15s;" onmouseover="this.style.background='rgba(0,200,255,.06)';this.style.color='var(--cyan)'" onmouseout="this.style.background='';this.style.color='var(--text2)'">
            <i class="bi <?=$ico?>" style="font-size:.9rem;"></i><?=$label?>
          </a>
          <?php endforeach; ?>
          <a href="<?=BASE_URL?>/auth/logout.php" style="display:flex;align-items:center;gap:.6rem;padding:.5rem .7rem;border-radius:8px;font-size:.83rem;font-weight:600;color:var(--danger);transition:all .15s;" onmouseover="this.style.background='rgba(239,68,68,.06)'" onmouseout="this.style.background=''">
            <i class="bi bi-box-arrow-right"></i>Déconnexion
          </a>
        </nav>
      </div>
    </div>

    <!-- Contenu -->
    <div class="col-12 col-lg-9" data-animate style="animation-delay:.08s;">
      <?php if($success):?><div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius);padding:.8rem 1rem;margin-bottom:1rem;color:var(--success);font-size:.85rem;font-weight:600;"><i class="bi bi-check-circle-fill me-2"></i><?=$success?></div><?php endif;?>
      <?php if($error):?><div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius);padding:.8rem 1rem;margin-bottom:1rem;color:var(--danger);font-size:.85rem;font-weight:600;"><i class="bi bi-exclamation-circle-fill me-2"></i><?=$error?></div><?php endif;?>

      <!-- Profil -->
      <div id="profil" style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;margin-bottom:1.2rem;">
        <h3 style="font-size:1rem;margin-bottom:1.2rem;"><i class="bi bi-person-fill me-2" style="color:var(--cyan);"></i>Mon profil</h3>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <input type="hidden" name="action" value="update_profil">
          <div class="row g-3">
            <div class="col-12 col-sm-6"><label class="nx-label">Prénom</label><input type="text" class="nx-input" value="<?=e($user['prenom'])?>" disabled style="opacity:.6;cursor:not-allowed;"></div>
            <div class="col-12 col-sm-6"><label class="nx-label">Nom</label><input type="text" class="nx-input" value="<?=e($user['nom'])?>" disabled style="opacity:.6;cursor:not-allowed;"></div>
            <div class="col-12"><label class="nx-label">Email</label><input type="email" class="nx-input" value="<?=e($user['email'])?>" disabled style="opacity:.6;cursor:not-allowed;"></div>
            <div class="col-12 col-sm-6"><label class="nx-label">Téléphone</label><input type="text" name="telephone" class="nx-input" value="<?=e($user['telephone']??'')?>"></div>
            <div class="col-12 col-sm-6"><label class="nx-label">Adresse</label><input type="text" name="adresse" class="nx-input" value="<?=e($user['adresse']??'')?>"></div>
            <div class="col-12"><button type="submit" class="btn-nx-primary"><i class="bi bi-check-lg"></i> Mettre à jour</button></div>
          </div>
        </form>
      </div>

      <!-- Commandes -->
      <div id="commandes" style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;margin-bottom:1.2rem;overflow:hidden;">
        <h3 style="font-size:1rem;margin-bottom:1.2rem;"><i class="bi bi-bag-check-fill me-2" style="color:var(--cyan);"></i>Mes commandes</h3>
        <?php if(empty($commandes)):?>
        <p style="color:var(--muted);text-align:center;padding:1.5rem 0;">Aucune commande passée.</p>
        <?php else:?>
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr><?php foreach(['Référence','Montant','Statut','Date'] as $h):?><th style="background:var(--surface);padding:.5rem .9rem;font-size:.68rem;font-weight:700;color:var(--muted);text-align:left;text-transform:uppercase;white-space:nowrap;"><?=$h?></th><?php endforeach;?></tr></thead>
          <tbody>
          <?php foreach($commandes as $o):?>
          <tr style="border-bottom:1px solid rgba(255,255,255,.035);">
            <td style="padding:.6rem .9rem;font-size:.82rem;font-weight:700;">#CMD-<?=str_pad($o['id_commande'],5,'0',STR_PAD_LEFT)?></td>
            <td style="padding:.6rem .9rem;font-size:.82rem;"><?=number_format($o['montant'],0)?> HTG</td>
            <td style="padding:.6rem .9rem;"><span class="nx-badge <?=['En attente'=>'nx-badge-warn','Livrée'=>'nx-badge-success','Annulée'=>'nx-badge-danger'][$o['statut']]??'nx-badge-cyan'?>"><?=e($o['statut'])?></span></td>
            <td style="padding:.6rem .9rem;font-size:.75rem;color:var(--muted);"><?=date('d/m/Y',strtotime($o['date_commande']))?></td>
          </tr>
          <?php endforeach;?>
          </tbody>
        </table>
        </div>
        <?php endif;?>
      </div>

      <!-- Sécurité -->
      <div id="securite" style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1.2rem;"><i class="bi bi-shield-lock-fill me-2" style="color:var(--cyan);"></i>Changer le mot de passe</h3>
        <form method="POST" style="max-width:400px;">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <input type="hidden" name="action" value="change_password">
          <label class="nx-label">Mot de passe actuel *</label>
          <input type="password" name="old_password" class="nx-input" required>
          <label class="nx-label">Nouveau mot de passe *</label>
          <input type="password" name="new_password" class="nx-input" required minlength="6">
          <label class="nx-label">Confirmer *</label>
          <input type="password" name="confirm_password" class="nx-input" required>
          <button type="submit" class="btn-nx-primary"><i class="bi bi-lock-fill"></i> Changer le mot de passe</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
