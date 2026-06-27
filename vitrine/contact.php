<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
$pdo = getDB();
$pageTitle = 'Contact';
$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? '')) {
    $nom     = trim($_POST['nom']     ?? '');
    $email   = trim($_POST['email']   ?? '');
    $sujet   = trim($_POST['sujet']   ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($nom && $email && $message) {
        try {
            $pdo->prepare("INSERT INTO messages_contact(nom,email,sujet,message,date_envoi) VALUES(:n,:e,:s,:m,NOW())")
                ->execute([':n'=>$nom,':e'=>$email,':s'=>$sujet,':m'=>$message]);
            $success = 'Votre message a été envoyé ! Nous vous répondrons sous 24h.';
        } catch(PDOException) { $error = 'Erreur lors de l\'envoi. Veuillez réessayer.'; }
    } else { $error = 'Veuillez remplir tous les champs obligatoires.'; }
}

require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';
?>
<div class="nx-section">
  <div class="row g-4">
    <div class="col-12 col-lg-5" data-animate>
      <h1 style="font-size:1.8rem;margin-bottom:1rem;">Contactez<br><span style="color:var(--cyan);">Nexio S.A.</span></h1>
      <p class="mb-4">Notre équipe est disponible du lundi au samedi pour répondre à toutes vos questions.</p>
      <div class="row g-3">
        <?php foreach([
            ['bi-telephone-fill','Téléphone','4810-8541','tel:48108541'],
            ['bi-envelope-fill','Email','info@nexio.ht','mailto:info@nexio.ht'],
            ['bi-geo-alt-fill','Adresse','Delmas, Port-au-Prince, Haïti',null],
            ['bi-clock-fill','Horaires','Lundi–Samedi : 8h00–18h00',null],
        ] as [$icon,$label,$val,$href]): ?>
        <div class="col-12 col-sm-6">
          <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem;display:flex;gap:.8rem;align-items:flex-start;">
            <div style="width:38px;height:38px;background:rgba(0,200,255,.1);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="bi <?=$icon?>" style="color:var(--cyan);font-size:1rem;"></i>
            </div>
            <div>
              <div style="font-size:.7rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;"><?=$label?></div>
              <?php if($href):?><a href="<?=$href?>" style="font-weight:700;font-size:.88rem;color:var(--text);"><?=$val?></a><?php else:?>
              <div style="font-weight:700;font-size:.88rem;"><?=$val?></div><?php endif;?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-12 col-lg-7" data-animate style="animation-delay:.1s;">
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.8rem;">
        <h3 style="font-size:1.1rem;margin-bottom:1.2rem;"><i class="bi bi-send me-2" style="color:var(--cyan);"></i>Envoyer un message</h3>
        <?php if($success):?><div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius);padding:.8rem 1rem;margin-bottom:1rem;color:var(--success);font-size:.85rem;font-weight:600;"><i class="bi bi-check-circle-fill me-2"></i><?=$success?></div><?php endif;?>
        <?php if($error):?><div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius);padding:.8rem 1rem;margin-bottom:1rem;color:var(--danger);font-size:.85rem;font-weight:600;"><i class="bi bi-exclamation-circle-fill me-2"></i><?=$error?></div><?php endif;?>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <div class="row g-3">
            <div class="col-12 col-sm-6"><label class="nx-label">Nom complet *</label><input type="text" name="nom" class="nx-input" required value="<?=e($_POST['nom']??'')?>"></div>
            <div class="col-12 col-sm-6"><label class="nx-label">Email *</label><input type="email" name="email" class="nx-input" required value="<?=e($_POST['email']??'')?>"></div>
            <div class="col-12"><label class="nx-label">Sujet</label><input type="text" name="sujet" class="nx-input" value="<?=e($_POST['sujet']??'')?>"></div>
            <div class="col-12"><label class="nx-label">Message *</label><textarea name="message" class="nx-input" rows="5" required style="resize:vertical;"><?=e($_POST['message']??'')?></textarea></div>
            <div class="col-12"><button type="submit" class="btn-nx-primary w-100"><i class="bi bi-send-fill"></i> Envoyer le message</button></div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
