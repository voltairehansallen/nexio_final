<?php
/**
 * Nexio S.A. — Page d'accueil vitrine
 * Mobile First | Responsive | Animations | NEX Chatbot
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
$pdo = getDB();

$q    = trim($_GET['q']   ?? '');
$cat  = (int)($_GET['cat'] ?? 0);
$tri  = $_GET['tri'] ?? 'recent';
$pmin = (float)($_GET['pmin'] ?? 0);
$pmax = (float)($_GET['pmax'] ?? 0);

$conditions = ["p.statut = 'Disponible'"];
$params = [];
if ($q)    { $conditions[] = "(p.nom LIKE :q OR p.description LIKE :q2)"; $params[':q'] = "%$q%"; $params[':q2'] = "%$q%"; }
if ($cat)  { $conditions[] = "sc.id_categorie = :cat"; $params[':cat'] = $cat; }
if ($pmin) { $conditions[] = "p.prix >= :pmin"; $params[':pmin'] = $pmin; }
if ($pmax) { $conditions[] = "p.prix <= :pmax"; $params[':pmax'] = $pmax; }
$where = 'WHERE ' . implode(' AND ', $conditions);
$order = match($tri) {
    'prix_asc'  => 'p.prix ASC', 'prix_desc' => 'p.prix DESC',
    'nom' => 'p.nom ASC', default => 'p.id_produit DESC',
};

$stmt = $pdo->prepare("SELECT p.*,m.nom AS marque,c.nom AS categorie FROM produits p LEFT JOIN marques m ON p.id_marque=m.id_marque LEFT JOIN sous_categories sc ON p.id_sous_categorie=sc.id_sous_categorie LEFT JOIN categories c ON sc.id_categorie=c.id_categorie $where ORDER BY $order");
$stmt->execute($params); $produits = $stmt->fetchAll();

$nb_produits = (int)$pdo->query("SELECT COUNT(*) FROM produits WHERE statut='Disponible'")->fetchColumn();
$nb_marques  = (int)$pdo->query("SELECT COUNT(*) FROM marques")->fetchColumn();
$categories  = $pdo->query("SELECT * FROM categories ORDER BY nom")->fetchAll();

$feedbacks_pos = [];
try {
    $feedbacks_pos = $pdo->query("SELECT f.*,u.prenom,u.nom AS nom_client FROM feedbacks f LEFT JOIN users u ON f.id_user=u.id_user WHERE f.statut='Approuvé' AND f.note>=4 ORDER BY f.date_feedback DESC LIMIT 6")->fetchAll();
} catch(PDOException) {}

$wishlist_ids = [];
if (isLoggedIn()) {
    try {
        $ws = $pdo->prepare("SELECT id_produit FROM wishlist WHERE id_user=:u");
        $ws->execute([':u' => $_SESSION['user_id']]);
        $wishlist_ids = array_column($ws->fetchAll(), 'id_produit');
    } catch(PDOException) {}
}

$pageTitle = $q ? "Résultats : $q" : 'Boutique';
$cat_icons = ['Ordinateurs'=>'bi-pc-display','Réseau'=>'bi-wifi','Stockage'=>'bi-device-hdd','Gaming'=>'bi-controller','Périphériques'=>'bi-keyboard','Sécurité IT'=>'bi-shield-lock','Téléphonie'=>'bi-phone','Bureautique'=>'bi-printer'];
?>
<?php require_once BASE_PATH . '/includes/head.php'; ?>
<?php require_once BASE_PATH . '/includes/navbar.php'; ?>

<?php if (!$q && !$cat): ?>
<section class="nx-hero">
  <div class="nx-section" style="padding-top:0;padding-bottom:0;">
    <div class="row align-items-center g-4">
      <div class="col-12 col-lg-7" data-animate>
        <div class="hero-eyebrow"><i class="bi bi-lightning-charge-fill"></i> Haïti #1 Tech Store · Port-au-Prince</div>
        <h1 class="hero-title mb-3">Votre partenaire<br>technologique<br>de confiance</h1>
        <p class="mb-4" style="font-size:.95rem;max-width:480px;">PC, laptops, réseau, gaming, sécurité IT — matériel professionnel certifié, livré à Port-au-Prince sous 24–48h.</p>
        <form class="hero-search-big mb-4" method="GET" action="">
          <input type="text" name="q" placeholder='Rechercher "laptop", "routeur WiFi", "SSD"...' autocomplete="off">
          <select name="cat" class="d-none d-md-block">
            <option value="">Toutes catégories</option>
            <?php foreach ($categories as $c): ?><option value="<?=$c['id_categorie']?>"><?=e($c['nom'])?></option><?php endforeach; ?>
          </select>
          <button type="submit"><i class="bi bi-search"></i> <span>Rechercher</span></button>
        </form>
        <div class="hero-stats">
          <div class="hero-stat"><strong><?=$nb_produits?>+</strong><span>Produits</span></div>
          <div class="hero-stat"><strong><?=$nb_marques?>+</strong><span>Marques</span></div>
          <div class="hero-stat"><strong>24h</strong><span>Livraison</span></div>
        </div>
      </div>
      <?php if (!empty($produits[0]['image'])): ?>
      <div class="col-lg-5 d-none d-lg-flex justify-content-center" data-animate style="animation-delay:.1s;">
        <div style="background:linear-gradient(135deg,#0D1F40,#0A1832);border-radius:24px;padding:2.5rem;border:1px solid rgba(0,200,255,.15);box-shadow:0 32px 64px rgba(0,0,0,.5);max-width:380px;width:100%;position:relative;">
          <img src="<?=e($produits[0]['image'])?>" alt="<?=e($produits[0]['nom'])?>" style="max-height:240px;object-fit:contain;margin:0 auto;" onerror="this.style.display='none'">
          <span style="position:absolute;top:.8rem;right:.8rem;background:var(--danger);color:#fff;font-size:.68rem;font-weight:800;padding:.25rem .65rem;border-radius:5px;">NOUVEAU</span>
          <div style="margin-top:1rem;background:rgba(255,255,255,.05);border-radius:10px;padding:.7rem 1rem;">
            <div style="font-size:.68rem;color:var(--muted);"><?=e($produits[0]['categorie']??'')?></div>
            <div style="font-weight:800;font-size:.88rem;"><?=e(mb_substr($produits[0]['nom'],0,45))?></div>
            <div style="color:var(--cyan);font-weight:900;"><?=number_format($produits[0]['prix'],0,' ',' ')?> HTG</div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="cat-strip">
  <div class="cat-strip-inner">
    <a href="<?=BASE_URL?>/vitrine/index.php" class="cat-chip <?=(!$cat&&!$q)?'active':''?>"><i class="bi bi-grid-fill"></i>Tout</a>
    <?php foreach ($categories as $c):
      $ico = $cat_icons[$c['nom']] ?? 'bi-tag'; ?>
    <a href="?cat=<?=$c['id_categorie']?>" class="cat-chip <?=$cat==$c['id_categorie']?'active':''?>"><i class="bi <?=$ico?>"></i><?=e($c['nom'])?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="trust-bar">
  <div class="trust-bar-inner">
    <div class="trust-item"><i class="bi bi-truck"></i><div><strong>Livraison 24–48h</strong><span>Port-au-Prince</span></div></div>
    <div class="trust-item"><i class="bi bi-shield-check"></i><div><strong>Garantie produit</strong><span>6 mois à 2 ans</span></div></div>
    <div class="trust-item"><i class="bi bi-credit-card"></i><div><strong>MonCash · Visa</strong><span>Paiement sécurisé</span></div></div>
    <div class="trust-item"><i class="bi bi-robot"></i><div><strong>Assistant NEX</strong><span>Support IA 24h/24</span></div></div>
  </div>
</div>

<div class="nx-section">
  <div class="sec-hd">
    <div class="sec-title">
      <h2>
        <?php if ($q): ?><i class="bi bi-search" style="color:var(--cyan);"></i> Résultats pour "<?=e($q)?>"
        <?php elseif ($cat): ?><i class="bi bi-funnel" style="color:var(--cyan);"></i> Produits filtrés
        <?php else: ?><i class="bi bi-star-fill" style="color:var(--cyan);"></i> Nos produits
        <?php endif; ?>
      </h2>
      <span style="color:var(--muted);font-size:.78rem;margin-left:.5rem;"><?=count($produits)?> produit(s)</span>
    </div>
  </div>

  <details class="mb-3" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;">
    <summary style="padding:.7rem 1rem;cursor:pointer;font-size:.83rem;font-weight:700;color:var(--text2);display:flex;align-items:center;gap:.5rem;list-style:none;">
      <i class="bi bi-sliders" style="color:var(--cyan);"></i> Filtres avancés
      <?php if($q||$cat||$pmin||$pmax||$tri!=='recent'):?><span class="nx-badge nx-badge-cyan ms-auto">Actifs</span><?php endif;?>
    </summary>
    <div style="padding:1rem;border-top:1px solid var(--border);">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="nx-label">Prix min</label>
          <input type="number" name="pmin" class="nx-input" style="margin:0;" value="<?=$pmin?:''?>" min="0" placeholder="0">
        </div>
        <div class="col-6 col-md-2">
          <label class="nx-label">Prix max</label>
          <input type="number" name="pmax" class="nx-input" style="margin:0;" value="<?=$pmax?:''?>" min="0" placeholder="200000">
        </div>
        <div class="col-6 col-md-3">
          <label class="nx-label">Catégorie</label>
          <select name="cat" class="nx-input" style="margin:0;">
            <option value="">Toutes</option>
            <?php foreach($categories as $c):?><option value="<?=$c['id_categorie']?>" <?=$cat==$c['id_categorie']?'selected':''?>><?=e($c['nom'])?></option><?php endforeach;?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="nx-label">Trier par</label>
          <select name="tri" class="nx-input" style="margin:0;">
            <option value="recent" <?=$tri==='recent'?'selected':''?>>Plus récents</option>
            <option value="prix_asc" <?=$tri==='prix_asc'?'selected':''?>>Prix ↑</option>
            <option value="prix_desc" <?=$tri==='prix_desc'?'selected':''?>>Prix ↓</option>
            <option value="nom" <?=$tri==='nom'?'selected':''?>>Nom A→Z</option>
          </select>
        </div>
        <?php if($q):?><input type="hidden" name="q" value="<?=e($q)?>"><?php endif;?>
        <div class="col-12 col-md-3 d-flex gap-2">
          <button type="submit" class="btn-nx-primary flex-1"><i class="bi bi-funnel"></i> Appliquer</button>
          <a href="<?=BASE_URL?>/vitrine/index.php" class="btn-nx-ghost"><i class="bi bi-x-lg"></i></a>
        </div>
      </form>
    </div>
  </details>

  <?php if (empty($produits)): ?>
  <div class="text-center py-5" data-animate>
    <i class="bi bi-search" style="font-size:3.5rem;color:var(--muted);display:block;margin-bottom:1rem;"></i>
   <h3 style="color:var(--muted);">
    Aucun produit trouvé<?= $q ? ' pour "' . e($q) . '"' : '' ?>
</h3>
    <a href="<?=BASE_URL?>/vitrine/index.php" class="btn-nx-primary mt-3" style="display:inline-flex;margin:1rem auto 0;"><i class="bi bi-grid-fill"></i> Voir tous les produits</a>
  </div>
  <?php else: ?>
  <div class="product-grid" id="productGrid">
    <?php foreach ($produits as $i => $p):
      $in_wish = in_array($p['id_produit'], $wishlist_ids);
    ?>
    <div class="pcard" data-animate style="animation-delay:<?=min($i*0.04,0.4)?>s;"
         onclick="location.href='<?=BASE_URL?>/vitrine/produit.php?id=<?=$p['id_produit']?>'">

      <?php if ($i<4 && !$q && !$cat):?><span class="pcard-badge badge-new">Nouveau</span><?php endif;?>

      <?php if (isLoggedIn()):?>
      <button class="pcard-wish <?=$in_wish?'active':''?>" data-id="<?=$p['id_produit']?>"
              title="<?=$in_wish?'Retirer':'Ajouter aux souhaits'?>" onclick="event.stopPropagation();Wishlist.toggle(<?=$p['id_produit']?>,this)">
        <i class="bi bi-heart<?=$in_wish?'-fill':''?>"></i>
      </button>
      <?php endif;?>

      <div class="pcard-img">
        <?php if ($p['image']):?>
          <img src="<?=e($p['image'])?>" alt="<?=e($p['nom'])?>" loading="lazy"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
          <div class="no-img" style="display:none;align-items:center;justify-content:center;width:100%;height:100%;font-size:3rem;color:rgba(255,255,255,.05);"><i class="bi bi-cpu"></i></div>
        <?php else:?>
          <div class="no-img" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:3rem;color:rgba(255,255,255,.05);"><i class="bi bi-cpu"></i></div>
        <?php endif;?>
      </div>

      <div class="pcard-body">
        <div class="pcard-brand"><?=e($p['marque']??$p['categorie']??'Nexio')?></div>
        <div class="pcard-name"><?=e($p['nom'])?></div>
        <div class="pcard-price"><?=number_format($p['prix'],0,'.',' ')?><span class="cur">HTG</span></div>
        <?php if($p['garantie']):?><div class="pcard-guarantee"><i class="bi bi-shield-check-fill"></i><?=e($p['garantie'])?></div><?php endif;?>
        <div class="pcard-actions" onclick="event.stopPropagation()">
          <button class="btn-add-cart" data-id="<?=$p['id_produit']?>" data-name="<?=e(addslashes($p['nom']))?>">
            <i class="bi bi-cart-plus"></i> Ajouter
          </button>
          <button class="btn-quick-view" onclick="QuickView.open(<?=$p['id_produit']?>)" title="Aperçu rapide">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (!$q && !$cat): ?>
<div class="nx-section" style="padding-top:0;">
  <div class="promo-banner" data-animate>
    <div>
      <div style="font-size:.68rem;color:var(--cyan);font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.4rem;">Solutions entreprise</div>
      <h2 class="mb-2">Réseau &amp; <span class="promo-hl">Sécurité IT</span></h2>
      <p class="mb-3" style="font-size:.88rem;">Équipez votre entreprise avec du matériel professionnel certifié.</p>
      <a href="?cat=2" class="btn-nx-cyan"><i class="bi bi-lightning-charge-fill"></i> Découvrir</a>
    </div>
  </div>
</div>

<?php if (!empty($feedbacks_pos)): ?>
<div class="nx-section" style="padding-top:0;">
  <div class="sec-hd" data-animate>
    <div class="sec-title"><h2><i class="bi bi-stars" style="color:var(--warn);"></i> Ce que disent nos clients</h2></div>
    <span class="nx-badge nx-badge-cyan"><i class="bi bi-robot"></i> Sélection NEX</span>
  </div>
  <div class="testimonials-grid">
    <?php foreach ($feedbacks_pos as $i => $fb): ?>
    <div class="tcard" data-animate style="animation-delay:<?=$i*0.08?>s;">
      <div class="tcard-stars">
        <?php for($s=1;$s<=5;$s++):?><i class="bi bi-star<?=$s<=$fb['note']?'-fill':''?> <?=$s<=$fb['note']?'star-full':'star-empty'?>"></i><?php endfor;?>
      </div>
      <div class="tcard-text">"<?=e(mb_substr($fb['commentaire'],0,160)).(mb_strlen($fb['commentaire'])>160?'…':'')?>"</div>
      <div class="tcard-author">
        <div class="tcard-avatar"><?=strtoupper(mb_substr($fb['prenom']??'N',0,1))?></div>
        <div>
          <div class="tcard-name"><?=e(trim(($fb['prenom']??'').' '.($fb['nom_client']??'')))?></div>
          <div class="tcard-badge"><i class="bi bi-check-circle-fill me-1"></i>Client vérifié</div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-3">
    <a href="<?=BASE_URL?>/vitrine/feedback.php" class="btn-nx-ghost"><i class="bi bi-chat-square-text"></i> Laisser un avis</a>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php /* ── PUBLICITÉS PERSONNALISÉES NEX IA ── */ if (!$q && !$cat): ?>
<div class="nx-section" style="padding-top:0;" id="pubsSection" data-animate>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
    <h2 style="font-size:1rem;"><i class="bi bi-stars me-2" style="color:var(--cyan);"></i>Sélectionnés pour vous</h2>
    <span id="pubBadge" class="nx-badge nx-badge-cyan" style="display:none;"><i class="bi bi-robot"></i> NEX personnalise</span>
  </div>
  <div class="row g-3" id="pubsGrid">
    <!-- Chargé dynamiquement -->
    <?php for($sk=0;$sk<3;$sk++): ?>
    <div class="col-12 col-sm-4">
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:1rem;min-height:140px;" class="skeleton"></div>
    </div>
    <?php endfor; ?>
  </div>
</div>
<script>
(async function loadPubs() {
  try {
    const r = await fetch('<?=BASE_URL?>/api/publicites.php');
    const d = await r.json();
    if (!d.pubs || !d.pubs.length) { document.getElementById('pubsSection').style.display='none'; return; }
    if (d.personnal) document.getElementById('pubBadge').style.display='inline-flex';
    const grid = document.getElementById('pubsGrid');
    grid.innerHTML = d.pubs.map(pub => `
      <div class="col-12 col-sm-4">
        <a href="<?=BASE_URL?>${pub.lien_relatif||'/vitrine/index.php'}"
           style="display:block;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:all .2s;text-decoration:none;"
           onmouseover="this.style.borderColor='rgba(0,200,255,.25)';this.style.transform='translateY(-3px)'"
           onmouseout="this.style.borderColor='rgba(255,255,255,.06)';this.style.transform=''">
          ${pub.image ? `<div style="height:120px;background:var(--surface);display:flex;align-items:center;justify-content:center;overflow:hidden;"><img src="${pub.image}" style="max-height:110px;max-width:100%;object-fit:contain;padding:.5rem;" onerror="this.parentNode.innerHTML='<i class=\\'bi bi-cpu\\' style=\\'font-size:2.5rem;color:rgba(255,255,255,.06);\\'></i>'"></div>` : ''}
          <div style="padding:.85rem;">
            <div style="font-size:.82rem;font-weight:800;margin-bottom:.25rem;">${pub.titre}</div>
            <div style="font-size:.72rem;color:var(--muted);margin-bottom:.4rem;">${pub.contenu}</div>
            ${pub.prix ? `<div style="font-size:.95rem;font-weight:900;color:var(--cyan);">${pub.prix}</div>` : ''}
          </div>
        </a>
      </div>
    `).join('');
  } catch(e) { document.getElementById('pubsSection').style.display='none'; }
})();
</script>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
