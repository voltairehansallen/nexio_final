<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
$pdo = getDB();
$pageTitle = 'À propos de Nexio S.A.';
require_once BASE_PATH . '/includes/head.php';
require_once BASE_PATH . '/includes/navbar.php';
?>
<div class="nx-section">
  <div class="text-center mb-5" data-animate>
    <div class="hero-eyebrow" style="display:inline-flex;"><i class="bi bi-info-circle-fill"></i> Notre histoire</div>
    <h1 class="hero-title mt-2 mb-3">Nexio<span style="color:var(--cyan);">.</span>ht</h1>
    <p style="max-width:600px;margin:0 auto;font-size:1rem;">Plateforme e-commerce intelligente spécialisée dans la vente de matériel informatique en Haïti, propulsée par l'IA NEX.</p>
  </div>

  <div class="row g-4 mb-5">
    <?php foreach([
        ['bi-bullseye','Notre mission','Rendre la technologie accessible à tous les Haïtiens en offrant du matériel informatique professionnel de qualité à des prix compétitifs, avec un service client d\'excellence.','var(--cyan)'],
        ['bi-eye-fill','Notre vision','Devenir la plateforme technologique de référence en Haïti et dans la Caraïbe, en combinant e-commerce, intelligence artificielle et expertise locale.','var(--blue)'],
        ['bi-heart-fill','Nos valeurs','Excellence, intégrité, innovation et service. Nous croyons que chaque client mérite la meilleure expérience possible.','var(--danger)'],
    ] as [$icon,$title,$text,$color]): ?>
    <div class="col-12 col-md-4" data-animate>
      <div style="background:var(--card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:2rem;height:100%;text-align:center;">
        <div style="width:56px;height:56px;background:rgba(0,200,255,.08);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;">
          <i class="bi <?=$icon?>" style="font-size:1.5rem;color:<?=$color?>;"></i>
        </div>
        <h3 style="font-size:1.1rem;margin-bottom:.75rem;"><?=$title?></h3>
        <p style="font-size:.88rem;line-height:1.7;"><?=$text?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-4 mb-5">
    <div class="col-12 col-lg-6" data-animate>
      <h2 class="mb-4"><i class="bi bi-tools me-2" style="color:var(--cyan);"></i>Technologies utilisées</h2>
      <?php foreach([['PHP 8','Backend robuste et sécurisé'],['Python 3.12','Agents IA et machine learning'],['MySQL','Base de données relationnelle'],['GrokCloud IA','Intelligence artificielle avancée'],['Bootstrap 5','Design responsive Mobile First'],['XAMPP','Environnement de développement']] as [$tech,$desc]): ?>
      <div style="display:flex;align-items:center;gap:.8rem;padding:.75rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:.5rem;">
        <div style="width:8px;height:8px;background:var(--cyan);border-radius:50%;flex-shrink:0;"></div>
        <strong style="font-size:.88rem;min-width:120px;"><?=$tech?></strong>
        <span style="font-size:.82rem;color:var(--muted);"><?=$desc?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="col-12 col-lg-6" data-animate style="animation-delay:.1s;">
      <h2 class="mb-4"><i class="bi bi-award me-2" style="color:var(--cyan);"></i>Nos avantages</h2>
      <?php foreach(['Livraison rapide à Port-au-Prince (24–48h)','Garantie produit de 6 mois à 2 ans','Paiement MonCash, NatCash, Visa, Espèces','Assistant IA NEX disponible 24h/24','Prix compétitifs et transparents','Support technique expert','Catalogue de '.$nb_produits_about.' produits disponibles','Interface mobile optimisée'] as $avantage): ?>
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.6rem;">
        <i class="bi bi-check-circle-fill" style="color:var(--success);flex-shrink:0;"></i>
        <span style="font-size:.88rem;"><?=$avantage?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="background:linear-gradient(135deg,#0D1F40,#0A1832);border:1px solid rgba(0,200,255,.15);border-radius:var(--radius-lg);padding:2.5rem;text-align:center;" data-animate>
    <div style="font-size:2rem;margin-bottom:.5rem;">🤖</div>
    <h3 style="color:var(--cyan);margin-bottom:.75rem;">Propulsé par NEX IA</h3>
    <p style="max-width:500px;margin:0 auto 1.5rem;font-size:.9rem;">NEX est notre assistant IA intelligent qui analyse, recommande et personnalise votre expérience d'achat en temps réel, en utilisant GrokCloud avec le modèle llama-3.3-70b-versatile.</p>
    <a href="<?=BASE_URL?>/vitrine/contact.php" class="btn-nx-cyan"><i class="bi bi-chat-dots-fill"></i> Nous contacter</a>
  </div>
</div>
<?php
$nb_produits_about = (int)($pdo->query("SELECT COUNT(*) FROM produits WHERE statut='Disponible'")->fetchColumn());
require_once BASE_PATH . '/includes/footer.php'; ?>
