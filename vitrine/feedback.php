<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
$pdo = getDB();
$pageTitle = 'Feedback';
$success = ''; $error = '';

// Crée table si besoin
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedbacks (
        id_feedback INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT DEFAULT NULL,
        nom VARCHAR(100),
        email VARCHAR(150),
        note TINYINT NOT NULL DEFAULT 5,
        type_feedback ENUM('Expérience','Problème','Suggestion','Commentaire') DEFAULT 'Expérience',
        commentaire TEXT,
        statut ENUM('En attente','Approuvé','Rejeté') DEFAULT 'En attente',
        date_feedback DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(id_user) REFERENCES users(id_user) ON DELETE SET NULL
    ) ENGINE=InnoDB");
} catch(PDOException) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? '')) {
    $note    = (int)($_POST['note'] ?? 5);
    $type    = $_POST['type_feedback'] ?? 'Expérience';
    $comment = trim($_POST['commentaire'] ?? '');
    $nom     = isLoggedIn() ? ($_SESSION['user_prenom'].' '.$_SESSION['user_nom']) : trim($_POST['nom']??'');
    $email   = isLoggedIn() ? $_SESSION['user_email'] : trim($_POST['email']??'');
    if ($comment && $nom) {
        try {
            $pdo->prepare("INSERT INTO feedbacks(id_user,nom,email,note,type_feedback,commentaire) VALUES(:u,:n,:e,:no,:t,:c)")
                ->execute([':u'=>$_SESSION['user_id']??null,':n'=>$nom,':e'=>$email,':no'=>$note,':t'=>$type,':c'=>$comment]);
            $success = 'Merci pour votre retour ! Il sera examiné par notre équipe.';
        } catch(PDOException $ex) { $error = 'Erreur : '.$ex->getMessage(); }
    } else { $error = 'Veuillez remplir les champs obligatoires.'; }
}

// Feedbacks approuvés
$feedbacks = $pdo->query("SELECT f.*,u.prenom FROM feedbacks f LEFT JOIN users u ON f.id_user=u.id_user WHERE f.statut='Approuvé' ORDER BY f.date_feedback DESC LIMIT 12")->fetchAll();

require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';
?>
<div class="nx-section">
  <div class="text-center mb-5" data-animate>
    <h1 style="font-size:1.8rem;">Votre <span style="color:var(--cyan);">avis compte</span></h1>
    <p>Aidez-nous à améliorer Nexio S.A. en partageant votre expérience.</p>
  </div>
  <div class="row g-4">
    <div class="col-12 col-lg-5" data-animate>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.8rem;">
        <h3 style="font-size:1rem;margin-bottom:1.2rem;"><i class="bi bi-chat-square-heart me-2" style="color:var(--cyan);"></i>Laisser un feedback</h3>
        <?php if($success):?><div style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius);padding:.8rem;margin-bottom:1rem;color:var(--success);font-size:.85rem;font-weight:600;"><i class="bi bi-check-circle-fill me-2"></i><?=$success?></div><?php endif;?>
        <?php if($error):?><div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius);padding:.8rem;margin-bottom:1rem;color:var(--danger);font-size:.85rem;font-weight:600;"><?=$error?></div><?php endif;?>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?=csrf()?>">
          <?php if (!isLoggedIn()):?>
          <div class="row g-2">
            <div class="col-12 col-sm-6"><label class="nx-label">Votre nom *</label><input type="text" name="nom" class="nx-input" required></div>
            <div class="col-12 col-sm-6"><label class="nx-label">Email</label><input type="email" name="email" class="nx-input"></div>
          </div>
          <?php endif;?>
          <label class="nx-label">Note *</label>
          <div style="display:flex;gap:.5rem;margin-bottom:.85rem;" id="starRow">
            <?php for($s=1;$s<=5;$s++):?>
            <label style="cursor:pointer;font-size:1.8rem;color:rgba(245,158,11,.3);transition:color .15s;" id="star-<?=$s?>">
              <input type="radio" name="note" value="<?=$s?>" style="display:none;" <?=$s==5?'checked':''?>>
              ★
            </label>
            <?php endfor;?>
          </div>
          <label class="nx-label">Type</label>
          <select name="type_feedback" class="nx-input">
            <?php foreach(['Expérience','Problème','Suggestion','Commentaire'] as $t):?><option value="<?=$t?>"><?=$t?></option><?php endforeach;?>
          </select>
          <label class="nx-label">Commentaire *</label>
          <textarea name="commentaire" class="nx-input" rows="4" required placeholder="Partagez votre expérience..."></textarea>
          <button type="submit" class="btn-nx-primary w-100"><i class="bi bi-send-fill"></i> Envoyer mon feedback</button>
        </form>
      </div>
    </div>
    <div class="col-12 col-lg-7" data-animate style="animation-delay:.1s;">
      <h3 style="font-size:1rem;margin-bottom:1.2rem;"><i class="bi bi-stars me-2" style="color:var(--warn);"></i>Avis de nos clients (<?=count($feedbacks)?>)</h3>
      <div class="row g-3">
        <?php foreach ($feedbacks as $i => $fb):?>
        <div class="col-12 col-sm-6">
          <div class="tcard" data-animate style="animation-delay:<?=$i*0.06?>s;">
            <div class="tcard-stars">
              <?php for($s=1;$s<=5;$s++):?><i class="bi bi-star<?=$s<=$fb['note']?'-fill':''?> <?=$s<=$fb['note']?'star-full':'star-empty'?>"></i><?php endfor;?>
            </div>
            <div style="font-size:.68rem;color:var(--cyan);font-weight:700;text-transform:uppercase;margin-bottom:.4rem;"><?=e($fb['type_feedback']??'Expérience')?></div>
            <div class="tcard-text">"<?=e(mb_substr($fb['commentaire'],0,120)).(mb_strlen($fb['commentaire'])>120?'…':'')?>"</div>
            <div class="tcard-author">
              <div class="tcard-avatar"><?=strtoupper(mb_substr($fb['prenom']??$fb['nom']??'N',0,1))?></div>
              <div>
                <div class="tcard-name"><?=e($fb['prenom']??$fb['nom']??'Client')?></div>
                <div style="font-size:.68rem;color:var(--muted);"><?=date('d/m/Y',strtotime($fb['date_feedback']))?></div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach;?>
        <?php if(empty($feedbacks)):?><div class="col-12"><p style="color:var(--muted);text-align:center;padding:2rem;">Soyez le premier à laisser un avis !</p></div><?php endif;?>
      </div>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('#starRow label').forEach((lbl,i) => {
  lbl.addEventListener('mouseenter', () => {
    document.querySelectorAll('#starRow label').forEach((l,j) => l.style.color = j<=i ? 'var(--warn)' : 'rgba(245,158,11,.3)');
  });
  lbl.addEventListener('click', () => {
    document.querySelectorAll('#starRow label').forEach((l,j) => l.style.color = j<=i ? 'var(--warn)' : 'rgba(245,158,11,.3)');
  });
  lbl.addEventListener('mouseleave', () => {
    const checked = document.querySelector('#starRow input:checked');
    if (checked) {
      const v = parseInt(checked.value);
      document.querySelectorAll('#starRow label').forEach((l,j) => l.style.color = j<v ? 'var(--warn)' : 'rgba(245,158,11,.3)');
    }
  });
});
// Init 5 stars
document.querySelectorAll('#starRow label').forEach(l => l.style.color = 'var(--warn)');
</script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
