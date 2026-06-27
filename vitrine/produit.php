<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

// Quick view AJAX
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    $s = $pdo->prepare("SELECT p.*,m.nom AS marque,c.nom AS categorie FROM produits p LEFT JOIN marques m ON p.id_marque=m.id_marque LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie LEFT JOIN categories c ON sc.id_categorie=c.id_categorie WHERE p.id_produit=:id");
    $s->execute([':id'=>$id]); $p=$s->fetch();
    echo json_encode($p ?: ['error'=>'not found']); exit;
}

if (!$id) { header('Location: '.BASE_URL.'/vitrine/index.php'); exit; }
$s = $pdo->prepare("SELECT p.*,m.nom AS marque,c.nom AS categorie,f.nom AS fournisseur FROM produits p LEFT JOIN marques m ON p.id_marque=m.id_marque LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie LEFT JOIN categories c ON sc.id_categorie=c.id_categorie LEFT JOIN fournisseurs f ON p.id_fournisseur=f.id_fournisseur WHERE p.id_produit=:id");
$s->execute([':id'=>$id]); $p=$s->fetch();
if (!$p) { header('Location: '.BASE_URL.'/vitrine/index.php'); exit; }

// Produits similaires
$sim=$pdo->prepare("SELECT p.*,m.nom AS marque FROM produits p LEFT JOIN marques m ON p.id_marque=m.id_marque WHERE p.id_sous_categorie=:sc AND p.id_produit!=:id AND p.statut='Disponible' LIMIT 4");
$sim->execute([':sc'=>$p['id_sous_categorie'],':id'=>$id]); $sim=$sim->fetchAll();

// Log interaction
if (isLoggedIn()) try{$pdo->prepare("INSERT INTO interactions(id_user,id_produit,action,page) VALUES(:u,:p,'view','produit.php') ON DUPLICATE KEY UPDATE id_interaction=LAST_INSERT_ID(id_interaction)")->execute([':u'=>$_SESSION['user_id'],':p'=>$id]);}catch(PDOException){}

// Wishlist
$in_wish = false;
if (isLoggedIn()) { try{$w=$pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE id_user=:u AND id_produit=:p");$w->execute([':u'=>$_SESSION['user_id'],':p'=>$id]);$in_wish=(int)$w->fetchColumn()>0;}catch(PDOException){} }

$pageTitle = $p['nom'];
require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';
?>
<div class="nx-section">
  <nav style="display:flex;align-items:center;gap:.4rem;font-size:.75rem;color:var(--muted);margin-bottom:1.5rem;flex-wrap:wrap;">
    <a href="<?=BASE_URL?>/vitrine/index.php" style="color:var(--muted);">Accueil</a>
    <i class="bi bi-chevron-right" style="font-size:.6rem;"></i>
    <a href="<?=BASE_URL?>/vitrine/index.php" style="color:var(--muted);"><?=e($p['categorie']??'Produits')?></a>
    <i class="bi bi-chevron-right" style="font-size:.6rem;"></i>
    <span style="color:var(--text);"><?=e(mb_substr($p['nom'],0,40))?></span>
  </nav>

  <div class="row g-4 align-items-start">
    <div class="col-12 col-md-5" data-animate>
      <div style="background:linear-gradient(135deg,#131D35,#1A2745);border-radius:var(--radius-lg);aspect-ratio:1;display:flex;align-items:center;justify-content:center;padding:2rem;border:1px solid var(--border);position:relative;">
        <?php if($p['image']):?>
          <img src="<?=e($p['image'])?>" alt="<?=e($p['nom'])?>" style="max-height:280px;max-width:100%;object-fit:contain;transition:transform .3s;" id="mainImg" onerror="this.style.display='none'">
        <?php else:?>
          <i class="bi bi-cpu" style="font-size:5rem;color:rgba(255,255,255,.06);"></i>
        <?php endif;?>
        <span style="position:absolute;top:.9rem;left:.9rem;background:rgba(0,200,255,.1);border:1px solid rgba(0,200,255,.2);color:var(--cyan);font-size:.65rem;font-weight:700;padding:.2rem .6rem;border-radius:5px;text-transform:uppercase;"><?=e($p['categorie']??'Nexio')?></span>
      </div>
    </div>

    <div class="col-12 col-md-7" data-animate style="animation-delay:.08s;">
      <div style="font-size:.7rem;color:var(--muted);margin-bottom:.3rem;"><?=e($p['marque']??'')?></div>
      <h1 style="font-size:clamp(1.2rem,3vw,1.7rem);margin-bottom:.8rem;"><?=e($p['nom'])?></h1>

      <div style="font-size:2rem;font-weight:900;color:var(--text);margin-bottom:.4rem;">
        <?=number_format($p['prix'],0,'.',' ')?> <span style="font-size:.95rem;color:var(--muted);font-weight:500;">HTG</span>
      </div>

      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.2rem;flex-wrap:wrap;">
        <span class="nx-badge <?=$p['statut']==='Disponible'?'nx-badge-success':'nx-badge-danger'?>">
          <i class="bi bi-<?=$p['statut']==='Disponible'?'check-circle':'x-circle'?>-fill"></i> <?=e($p['statut'])?>
        </span>
        <span style="font-size:.78rem;color:var(--muted);"><?=(int)$p['quantite']?> en stock</span>
      </div>

      <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.4rem;">
        <?php if($p['garantie']):?><div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;"><i class="bi bi-shield-check-fill" style="color:var(--success);"></i><strong>Garantie :</strong> <?=e($p['garantie'])?></div><?php endif;?>
        <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;"><i class="bi bi-truck" style="color:var(--cyan);"></i><span>Livraison Port-au-Prince sous 24–48h</span></div>
        <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;"><i class="bi bi-credit-card" style="color:var(--cyan);"></i><span>MonCash · NatCash · Visa · Espèces</span></div>
        <?php if($p['fournisseur']):?><div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;"><i class="bi bi-building" style="color:var(--cyan);"></i><span>Fournisseur : <?=e($p['fournisseur'])?></span></div><?php endif;?>
      </div>

      <?php if($p['description']):?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;margin-bottom:1.2rem;font-size:.85rem;color:var(--text2);line-height:1.7;">
        <?=nl2br(e($p['description']))?>
      </div>
      <?php endif;?>

      <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="<?=BASE_URL?>/vitrine/panier.php?action=add&id=<?=$id?>" class="btn-nx-primary" style="flex:1;min-width:160px;justify-content:center;">
          <i class="bi bi-cart-plus-fill"></i> Ajouter au panier
        </a>
        <?php if(isLoggedIn()):?>
        <a href="<?=BASE_URL?>/vitrine/wishlist.php?action=toggle&id=<?=$id?>" class="btn-nx-ghost" style="<?=$in_wish?'color:var(--danger);border-color:var(--danger);':''?>">
          <i class="bi bi-heart<?=$in_wish?'-fill':''?>"></i>
        </a>
        <?php endif;?>
        <a href="<?=BASE_URL?>/vitrine/checkout.php?direct=<?=$id?>" class="btn-nx-ghost" style="flex:1;min-width:140px;justify-content:center;">
          <i class="bi bi-lightning-charge-fill" style="color:var(--cyan);"></i> Commander
        </a>
      </div>
    </div>
  </div>

  <?php if(!empty($sim)):?>
  <div style="margin-top:3rem;">
    <h2 style="font-size:1.1rem;margin-bottom:1.2rem;"><i class="bi bi-grid me-2" style="color:var(--cyan);"></i>Produits similaires</h2>
    <div class="product-grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));">
      <?php foreach($sim as $s):?>
      <div class="pcard" onclick="location.href='produit.php?id=<?=$s['id_produit']?>'">
        <div class="pcard-img"><?php if($s['image']):?><img src="<?=e($s['image'])?>" alt="<?=e($s['nom'])?>" loading="lazy" onerror="this.style.display='none'"><?php endif;?></div>
        <div class="pcard-body">
          <div class="pcard-brand"><?=e($s['marque']??'')?></div>
          <div class="pcard-name"><?=e($s['nom'])?></div>
          <div class="pcard-price"><?=number_format($s['prix'],0)?><span class="cur">HTG</span></div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php endif;?>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
