<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
requireLogin();
$pdo = getDB();
if (empty($_SESSION['panier'])) { header('Location: panier.php'); exit; }
$items=[]; $total=0;
foreach(array_keys($_SESSION['panier']) as $id){
    $s=$pdo->prepare("SELECT * FROM produits WHERE id_produit=:id"); $s->execute([':id'=>$id]); $prod=$s->fetch();
    if($prod){$qty=$_SESSION['panier'][$id];$st=$prod['prix']*$qty;$total+=$st;$items[]=['produit'=>$prod,'qty'=>$qty,'st'=>$st];}
}
$success=''; $cmdId=0;
if($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrf($_POST['csrf']??'')){
    $methode=trim($_POST['methode']??'MonCash');
    $adresse=trim($_POST['adresse']??'');
    $pdo->prepare("INSERT INTO commandes(id_user,montant,statut,adresse_livraison) VALUES(:u,:m,'En attente',:a)")->execute([':u'=>$_SESSION['user_id'],':m'=>$total,':a'=>$adresse]);
    $cmdId=$pdo->lastInsertId();
    foreach($items as $it){$pdo->prepare("INSERT INTO details_commandes(id_commande,id_produit,quantite,prix) VALUES(:c,:p,:q,:pr)")->execute([':c'=>$cmdId,':p'=>$it['produit']['id_produit'],':q'=>$it['qty'],':pr'=>$it['produit']['prix']]);}
    $pdo->prepare("INSERT INTO paiements(id_commande,montant,methode,statut) VALUES(:c,:m,:me,'En attente')")->execute([':c'=>$cmdId,':m'=>$total,':me'=>$methode]);
    $_SESSION['panier']=[];
    $success=true;
}
$pageTitle='Finaliser la commande';
require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';
?>
<div class="nx-section" style="max-width:860px;margin:0 auto;">
  <h1 style="font-size:1.5rem;margin-bottom:2rem;" data-animate>
    <i class="bi bi-bag-check-fill" style="color:var(--cyan);margin-right:.5rem;"></i>Finaliser la commande
  </h1>
  <?php if($success): ?>
  <div style="text-align:center;padding:3rem 1rem;background:var(--card);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius-lg);" data-animate>
    <div style="font-size:3.5rem;margin-bottom:1rem;">🎉</div>
    <h2 style="color:var(--success);margin-bottom:.6rem;">Commande confirmée !</h2>
    <p style="color:var(--text2);margin-bottom:.4rem;">Référence : <strong style="color:var(--text);">#CMD-<?=str_pad($cmdId,5,'0',STR_PAD_LEFT)?></strong></p>
    <p style="color:var(--text2);margin-bottom:1.5rem;">Montant total : <strong style="color:var(--cyan);"><?=number_format($total,0)?> HTG</strong></p>
    <p style="font-size:.85rem;color:var(--muted);margin-bottom:2rem;">Vous serez contacté par notre équipe pour confirmer la livraison. Tél : <strong>4810-8541</strong></p>
    <div style="display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;">
      <a href="<?=BASE_URL?>/vitrine/index.php" class="btn-nx-primary"><i class="bi bi-house-fill"></i> Retour à la boutique</a>
      <a href="<?=BASE_URL?>/vitrine/compte/index.php#commandes" class="btn-nx-ghost"><i class="bi bi-bag-check"></i> Mes commandes</a>
    </div>
  </div>
  <?php else: ?>
  <div class="row g-4">
    <div class="col-12 col-md-7" data-animate>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.4rem;margin-bottom:1rem;">
          <h3 style="font-size:.9rem;font-weight:800;margin-bottom:1rem;"><i class="bi bi-geo-alt-fill me-2" style="color:var(--cyan);"></i>Adresse de livraison</h3>
          <label class="nx-label">Adresse complète *</label>
          <textarea name="adresse" class="nx-input" rows="3" required placeholder="Ex: Delmas 31, #14, Port-au-Prince, Haiti"></textarea>
        </div>
        <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.4rem;margin-bottom:1.2rem;">
          <h3 style="font-size:.9rem;font-weight:800;margin-bottom:1rem;"><i class="bi bi-credit-card-fill me-2" style="color:var(--cyan);"></i>Mode de paiement</h3>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem;" id="methodes">
            <?php foreach([['MonCash','bi-phone-fill','#10B981'],['NatCash','bi-phone','#3B82F6'],['Visa','bi-credit-card-fill','#8B5CF6'],['Espèces','bi-cash-stack','#F59E0B']] as [$m,$ico,$color]):?>
            <label style="background:var(--surface);border:2px solid var(--border);border-radius:var(--radius);padding:.9rem;cursor:pointer;transition:all .15s;text-align:center;" class="methode-lbl" id="lbl-<?=$m?>">
              <input type="radio" name="methode" value="<?=$m?>" style="display:none;" <?=$m==='MonCash'?'checked':''?>>
              <i class="bi <?=$ico?>" style="font-size:1.3rem;color:<?=$color?>;display:block;margin-bottom:.3rem;"></i>
              <div style="font-size:.8rem;font-weight:700;"><?=$m?></div>
            </label>
            <?php endforeach;?>
          </div>
        </div>
        <button type="submit" class="btn-nx-primary" style="width:100%;justify-content:center;padding:.85rem;font-size:.95rem;">
          <i class="bi bi-bag-check-fill"></i> Confirmer — <?=number_format($total,0)?> HTG
        </button>
      </form>
    </div>
    <div class="col-12 col-md-5" data-animate style="animation-delay:.1s;">
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.4rem;position:sticky;top:80px;">
        <h3 style="font-size:.9rem;font-weight:800;margin-bottom:1rem;"><i class="bi bi-receipt me-2" style="color:var(--cyan);"></i>Votre commande</h3>
        <?php foreach($items as $it):?>
        <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.5rem;gap:.5rem;">
          <span style="color:var(--text2);"><?=e(mb_substr($it['produit']['nom'],0,28))?> ×<?=$it['qty']?></span>
          <span style="font-weight:700;flex-shrink:0;"><?=number_format($it['st'],0)?></span>
        </div>
        <?php endforeach;?>
        <div style="display:flex;justify-content:space-between;font-size:1.05rem;font-weight:900;color:var(--cyan);border-top:1px solid var(--border);padding-top:.9rem;margin-top:.5rem;">
          <span>Total</span><span><?=number_format($total,0)?> HTG</span>
        </div>
        <div style="margin-top:1rem;padding:.9rem;background:var(--surface);border-radius:var(--radius);font-size:.75rem;color:var(--muted);line-height:1.8;">
          <div><i class="bi bi-shield-check-fill me-1" style="color:var(--success);"></i>Paiement 100% sécurisé</div>
          <div><i class="bi bi-truck me-1" style="color:var(--cyan);"></i>Livraison 24–48h</div>
          <div><i class="bi bi-telephone-fill me-1" style="color:var(--warn);"></i>Confirmation par SMS</div>
          <div><i class="bi bi-arrow-repeat me-1" style="color:var(--purple);"></i>Retour 7 jours si défaut</div>
        </div>
      </div>
    </div>
  </div>
  <?php endif;?>
</div>
<script>
document.querySelectorAll('.methode-lbl').forEach(lbl => {
  const inp = lbl.querySelector('input');
  const update = () => {
    document.querySelectorAll('.methode-lbl').forEach(l => {
      l.style.borderColor = 'var(--border)';
      l.style.background  = 'var(--surface)';
    });
    if (inp.checked) {
      lbl.style.borderColor = 'var(--cyan)';
      lbl.style.background  = 'rgba(0,200,255,.06)';
    }
  };
  lbl.addEventListener('click', () => { inp.checked=true; update(); document.querySelectorAll('.methode-lbl').forEach(l2 => { if(l2.querySelector('input').checked) { l2.style.borderColor='var(--cyan)'; l2.style.background='rgba(0,200,255,.06)'; } else { l2.style.borderColor='var(--border)'; l2.style.background='var(--surface)'; }}); });
  if (inp.checked) update();
});
</script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
