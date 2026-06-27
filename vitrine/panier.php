<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
$pdo    = getDB();
$action = trim($_GET['action'] ?? '');
$pid    = (int)($_GET['id'] ?? 0);
$ajax   = !empty($_GET['ajax']);
if (!isset($_SESSION['panier'])) $_SESSION['panier'] = [];

if ($action === 'add' && $pid) {
    $_SESSION['panier'][$pid] = ($_SESSION['panier'][$pid] ?? 0) + 1;
    if ($ajax) { header('Content-Type: application/json'); echo json_encode(['count'=>array_sum($_SESSION['panier']),'status'=>'ok']); exit; }
    flash('success', 'Produit ajouté au panier !');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL.'/vitrine/index.php')); exit;
}
if ($action === 'remove' && $pid) { unset($_SESSION['panier'][$pid]); header('Location: panier.php'); exit; }
if ($action === 'clear')          { $_SESSION['panier'] = []; header('Location: panier.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $id2  = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $qty  = (int)($_POST['qty'] ?? 0);
    if ($id2) { if ($qty <= 0) unset($_SESSION['panier'][$id2]); else $_SESSION['panier'][$id2] = $qty; }
    $ajax2 = !empty($_POST['ajax']) || !empty($_GET['ajax']);
    if ($ajax2) {
        $items2 = []; $total2 = 0;
        foreach (array_keys($_SESSION['panier']) as $i) {
            $s = $pdo->prepare("SELECT prix FROM produits WHERE id_produit=:id"); $s->execute([':id'=>$i]); $pr = $s->fetch();
            if ($pr) { $q2 = $_SESSION['panier'][$i]; $total2 += $pr['prix'] * $q2; $items2[] = ['id'=>$i,'qty'=>$q2]; }
        }
        header('Content-Type: application/json');
        echo json_encode(['total'=>$total2,'count'=>array_sum($_SESSION['panier']),'items'=>$items2]); exit;
    }
    header('Location: panier.php'); exit;
}

$items = []; $total = 0;
foreach (array_keys($_SESSION['panier']) as $id) {
    $s = $pdo->prepare("SELECT p.*,m.nom AS marque FROM produits p LEFT JOIN marques m ON p.id_marque=m.id_marque WHERE p.id_produit=:id");
    $s->execute([':id'=>$id]); $prod = $s->fetch();
    if ($prod) { $qty=$_SESSION['panier'][$id]; $st=$prod['prix']*$qty; $total+=$st; $items[]=['produit'=>$prod,'qty'=>$qty,'st'=>$st]; }
}
$nb_panier = array_sum($_SESSION['panier']);
$pageTitle = 'Mon panier';
require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';
?>
<div class="nx-section">
  <h1 style="font-size:1.5rem;margin-bottom:2rem;" data-animate>
    <i class="bi bi-cart2" style="color:var(--cyan);margin-right:.5rem;"></i>Mon panier
    <?php if(!empty($items)):?><span style="font-size:.85rem;color:var(--muted);font-weight:400;margin-left:.5rem;"><?=count($items)?> article(s)</span><?php endif;?>
  </h1>

  <?php if(empty($items)): ?>
  <div style="text-align:center;padding:5rem 0;" data-animate>
    <i class="bi bi-cart-x" style="font-size:3.5rem;color:var(--muted);display:block;margin-bottom:1rem;"></i>
    <h3 style="color:var(--muted);">Votre panier est vide</h3>
    <a href="<?=BASE_URL?>/vitrine/index.php" class="btn-nx-primary" style="display:inline-flex;margin-top:1.2rem;"><i class="bi bi-grid-fill"></i> Voir le catalogue</a>
  </div>
  <?php else: ?>
  <div class="row g-4">
    <div class="col-12 col-lg-8" data-animate>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
        <?php foreach ($items as $it): $p=$it['produit']; ?>
        <div style="display:grid;grid-template-columns:72px 1fr auto;gap:1rem;align-items:center;padding:1rem 1.2rem;border-bottom:1px solid rgba(255,255,255,.04);">
          <div style="width:64px;height:64px;background:var(--surface);border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden;">
            <?php if($p['image']):?><img src="<?=e($p['image'])?>" alt="<?=e($p['nom'])?>" style="max-width:100%;max-height:100%;object-fit:contain;" onerror="this.style.display='none'"><?php else:?><i class="bi bi-cpu" style="color:rgba(255,255,255,.1);"></i><?php endif;?>
          </div>
          <div>
            <div style="font-weight:700;font-size:.88rem;"><?=e($p['nom'])?></div>
            <div style="font-size:.72rem;color:var(--muted);"><?=e($p['marque']??'')?></div>
            <div style="font-weight:800;color:var(--cyan);font-size:.95rem;margin:.2rem 0;"><?=number_format($p['prix'],0)?> HTG</div>
            <div style="display:flex;align-items:center;gap:.4rem;margin-top:.3rem;">
              <form method="POST" action="panier.php?action=update&id=<?=$p['id_produit']?>" style="display:flex;align-items:center;gap:.3rem;">
                <button type="button" style="width:24px;height:24px;background:rgba(255,255,255,.08);border:1px solid var(--border);border-radius:5px;color:var(--text);cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;"
                  onclick="this.form.qty.value=Math.max(0,parseInt(this.form.qty.value)-1);this.form.submit()">−</button>
                <input type="number" name="qty" class="qty-input" value="<?=$it['qty']?>" min="0" max="99" data-id="<?=$p['id_produit']?>" style="width:40px;text-align:center;background:var(--surface);border:1px solid var(--border);border-radius:5px;color:var(--text);padding:.2rem;font-size:.82rem;font-family:inherit;">
                <button type="button" style="width:24px;height:24px;background:rgba(255,255,255,.08);border:1px solid var(--border);border-radius:5px;color:var(--text);cursor:pointer;font-size:.85rem;display:flex;align-items:center;justify-content:center;"
                  onclick="this.form.qty.value=parseInt(this.form.qty.value)+1;this.form.submit()">+</button>
                <button type="submit" style="display:none;"></button>
              </form>
              <a href="panier.php?action=remove&id=<?=$p['id_produit']?>" style="color:var(--danger);font-size:.8rem;margin-left:.3rem;"><i class="bi bi-trash"></i></a>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-weight:900;font-size:1rem;"><?=number_format($it['st'],0)?></div>
            <div style="font-size:.68rem;color:var(--muted);">HTG</div>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="padding:.75rem 1.2rem;display:flex;justify-content:space-between;align-items:center;">
          <a href="<?=BASE_URL?>/vitrine/index.php" style="color:var(--muted);font-size:.82rem;display:flex;align-items:center;gap:.3rem;"><i class="bi bi-arrow-left"></i> Continuer mes achats</a>
          <a href="panier.php?action=clear" style="color:var(--danger);font-size:.78rem;">Vider le panier</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4" data-animate style="animation-delay:.1s;">
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.4rem;position:sticky;top:80px;">
        <h3 style="font-size:.95rem;font-weight:800;margin-bottom:1.2rem;">Récapitulatif</h3>
        <?php foreach($items as $it):?>
        <div style="display:flex;justify-content:space-between;font-size:.8rem;color:var(--muted);margin-bottom:.4rem;">
          <span><?=e(mb_substr($it['produit']['nom'],0,28)).(mb_strlen($it['produit']['nom'])>28?'…':'')?>  ×<?=$it['qty']?></span>
          <span><?=number_format($it['st'],0)?></span>
        </div>
        <?php endforeach;?>
        <div style="display:flex;justify-content:space-between;font-size:1.1rem;font-weight:900;color:var(--cyan);border-top:1px solid var(--border);padding-top:.9rem;margin-top:.6rem;">
          <span>Total</span><span><?=number_format($total,0)?> HTG</span>
        </div>
        <?php if(isLoggedIn()):?>
        <a href="checkout.php" class="btn-nx-primary" style="width:100%;justify-content:center;margin-top:1rem;font-size:.95rem;padding:.75rem;"><i class="bi bi-bag-check-fill"></i> Commander maintenant</a>
        <?php else:?>
        <a href="<?=BASE_URL?>/auth/login.php" class="btn-nx-primary" style="width:100%;justify-content:center;margin-top:1rem;"><i class="bi bi-box-arrow-in-right"></i> Connexion pour commander</a>
        <?php endif;?>
        <div style="margin-top:1rem;font-size:.72rem;color:var(--muted);display:flex;flex-direction:column;gap:.3rem;">
          <div><i class="bi bi-shield-check-fill" style="color:var(--success);margin-right:.3rem;"></i>Paiement sécurisé</div>
          <div><i class="bi bi-truck" style="color:var(--cyan);margin-right:.3rem;"></i>Livraison 24–48h</div>
          <div><i class="bi bi-arrow-repeat" style="color:var(--warn);margin-right:.3rem;"></i>Retour 7 jours</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
