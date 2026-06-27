<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
requireLogin();
$pdo = getDB();

// Crée table wishlist si absente
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
        id_wish INT AUTO_INCREMENT PRIMARY KEY,
        id_user INT NOT NULL,
        id_produit INT NOT NULL,
        date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq(id_user,id_produit),
        FOREIGN KEY(id_user) REFERENCES users(id_user) ON DELETE CASCADE,
        FOREIGN KEY(id_produit) REFERENCES produits(id_produit) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch(PDOException) {}

$action = $_GET['action'] ?? '';
$pid    = (int)($_GET['id'] ?? 0);
$ajax   = !empty($_GET['ajax']);
$uid    = $_SESSION['user_id'];

if ($action === 'toggle' && $pid) {
    $exists = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user=:u AND id_produit=:p");
    $exists->execute([':u'=>$uid,':p'=>$pid]);
    $added = false;
    if ((int)$exists->fetchColumn() > 0) {
        $pdo->prepare("DELETE FROM wishlist WHERE id_user=:u AND id_produit=:p")->execute([':u'=>$uid,':p'=>$pid]);
    } else {
        $pdo->prepare("INSERT IGNORE INTO wishlist(id_user,id_produit) VALUES(:u,:p)")->execute([':u'=>$uid,':p'=>$pid]);
        $added = true;
    }
    if ($ajax) { header('Content-Type: application/json'); echo json_encode(['added'=>$added]); exit; }
    flash($added?'success':'info', $added?'Produit ajouté à vos souhaits !':'Produit retiré.');
    header('Location: wishlist.php'); exit;
}

if ($action === 'move_to_cart' && $pid) {
    $_SESSION['panier'][$pid] = ($_SESSION['panier'][$pid] ?? 0) + 1;
    flash('success', 'Produit déplacé vers le panier !');
    header('Location: panier.php'); exit;
}

if ($action === 'remove' && $pid) {
    $pdo->prepare("DELETE FROM wishlist WHERE id_user=:u AND id_produit=:p")->execute([':u'=>$uid,':p'=>$pid]);
    flash('success', 'Produit retiré de votre liste.');
    header('Location: wishlist.php'); exit;
}

$items = $pdo->prepare("SELECT p.*,m.nom AS marque,c.nom AS categorie FROM wishlist w JOIN produits p ON w.id_produit=p.id_produit LEFT JOIN marques m ON p.id_marque=m.id_marque LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie LEFT JOIN categories c ON sc.id_categorie=c.id_categorie WHERE w.id_user=:u ORDER BY w.date_ajout DESC");
$items->execute([':u'=>$uid]); $items = $items->fetchAll();
$pageTitle = 'Liste de souhaits';
require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';
?>
<div class="nx-section">
  <div class="sec-hd mb-4" data-animate>
    <h1 style="font-size:1.6rem;"><i class="bi bi-heart-fill" style="color:var(--danger);"></i> Ma liste de souhaits</h1>
    <span style="color:var(--muted);font-size:.85rem;"><?=count($items)?> produit(s)</span>
  </div>
  <?php if (empty($items)): ?>
  <div style="text-align:center;padding:5rem 0;" data-animate>
    <i class="bi bi-heart" style="font-size:3.5rem;color:var(--muted);display:block;margin-bottom:1rem;"></i>
    <h3 style="color:var(--muted);">Votre liste de souhaits est vide</h3>
    <a href="<?=BASE_URL?>/vitrine/index.php" class="btn-nx-primary" style="display:inline-flex;margin-top:1.2rem;"><i class="bi bi-grid-fill"></i> Parcourir le catalogue</a>
  </div>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($items as $p): ?>
    <div class="pcard" data-animate>
      <div class="pcard-img" onclick="location.href='<?=BASE_URL?>/vitrine/produit.php?id=<?=$p['id_produit']?>';cursor:pointer;">
        <?php if($p['image']):?><img src="<?=e($p['image'])?>" alt="<?=e($p['nom'])?>" loading="lazy" onerror="this.style.display='none'"><?php else:?><div class="no-img" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:3rem;color:rgba(255,255,255,.05);"><i class="bi bi-cpu"></i></div><?php endif;?>
      </div>
      <div class="pcard-body">
        <div class="pcard-brand"><?=e($p['marque']??$p['categorie']??'')?></div>
        <div class="pcard-name"><?=e($p['nom'])?></div>
        <div class="pcard-price"><?=number_format($p['prix'],0,'.',' ')?><span class="cur">HTG</span></div>
        <div style="margin-bottom:.6rem;">
          <span class="nx-badge <?=$p['statut']==='Disponible'?'nx-badge-success':'nx-badge-danger'?>"><?=e($p['statut'])?></span>
        </div>
        <div style="display:flex;flex-direction:column;gap:.4rem;">
          <a href="wishlist.php?action=move_to_cart&id=<?=$p['id_produit']?>" class="btn-nx-primary" style="width:100%;justify-content:center;">
            <i class="bi bi-cart-plus"></i> Déplacer au panier
          </a>
          <a href="wishlist.php?action=remove&id=<?=$p['id_produit']?>" class="btn-nx-ghost" style="width:100%;justify-content:center;font-size:.78rem;">
            <i class="bi bi-trash"></i> Retirer
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
