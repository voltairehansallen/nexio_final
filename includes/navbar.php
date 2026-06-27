<?php
/**
 * Nexio S.A. — Navbar partagée (Mobile First)
 */
$nb_panier = array_sum($_SESSION['panier'] ?? []);
$q_nav     = htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8');
$categories_nav = isset($pdo) ? $pdo->query("SELECT id_categorie, nom FROM categories ORDER BY nom LIMIT 10")->fetchAll() : [];
?>
<!-- TOPBAR -->
<div class="nx-topbar">
  <span><i class="bi bi-geo-alt-fill"></i> Delmas, Port-au-Prince</span>
  <span><i class="bi bi-clock-fill"></i> Lun–Sam 8h–18h</span>
  <span><i class="bi bi-telephone-fill"></i> 4810-8541</span>
  <span><i class="bi bi-shield-check-fill"></i> Garantie qualité</span>
</div>

<!-- NAVBAR -->
<nav class="nx-nav">
  <div class="nx-nav-inner">

    <!-- Logo -->
    <a href="<?= BASE_URL ?>/vitrine/index.php" class="nx-logo">
      <i class="bi bi-cpu-fill"></i>Nexio<span class="dot">.</span>ht
    </a>

    <!-- Mobile toggle -->
    <button class="nx-toggler" id="navToggler" aria-label="Menu">
      <i class="bi bi-list"></i>
    </button>

    <!-- Search (hidden on small mobile, shown md+) -->
    <div class="nx-search d-none d-sm-block">
      <form class="nx-search-inner" method="GET" action="<?= BASE_URL ?>/vitrine/index.php">
        <input type="text" name="q" placeholder='Rechercher "laptop", "SSD", "routeur"...' value="<?= $q_nav ?>" autocomplete="off">
        <button type="submit"><i class="bi bi-search"></i> <span>Chercher</span></button>
      </form>
    </div>

    <!-- Nav Actions -->
    <div class="nx-actions">
      <?php if (isLoggedIn()): ?>
        <a href="<?= BASE_URL ?>/vitrine/compte/index.php" class="btn-nav d-none d-md-flex">
          <i class="bi bi-person-fill"></i>
          <span><?= e($_SESSION['user_prenom'] ?? 'Mon compte') ?></span>
        </a>
        <?php if (isAdmin()): ?>
          <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn-nav d-none d-lg-flex">
            <i class="bi bi-speedometer2"></i><span>Admin</span>
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/vitrine/wishlist.php" class="btn-nav d-none d-md-flex">
          <i class="bi bi-heart-fill" style="color:var(--danger);"></i>
        </a>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="btn-nav d-none d-lg-flex">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/auth/login.php" class="btn-nav">
          <i class="bi bi-person"></i><span>Connexion</span>
        </a>
        <a href="<?= BASE_URL ?>/auth/register.php" class="btn-nav btn-nav-primary d-none d-sm-flex">
          <i class="bi bi-person-plus"></i><span>S'inscrire</span>
        </a>
      <?php endif; ?>

      <!-- Cart -->
      <a href="<?= BASE_URL ?>/vitrine/panier.php" class="btn-nav cart-wrap">
        <i class="bi bi-cart2" style="font-size:1.05rem;"></i>
        <span class="d-none d-sm-inline">Panier</span>
        <?php if ($nb_panier > 0): ?>
          <span class="cart-dot"><?= $nb_panier ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>

  <!-- Mobile menu -->
  <div class="nx-mobile-menu" id="navMobileMenu">
    <!-- Mobile search -->
    <form class="nx-search-inner" method="GET" action="<?= BASE_URL ?>/vitrine/index.php" style="margin-bottom:.5rem;">
      <input type="text" name="q" placeholder="Rechercher..." value="<?= $q_nav ?>">
      <button type="submit"><i class="bi bi-search"></i></button>
    </form>
    <?php if (isLoggedIn()): ?>
      <a href="<?= BASE_URL ?>/vitrine/compte/index.php" class="btn-nav" style="justify-content:flex-start;"><i class="bi bi-person-fill"></i> Mon compte</a>
      <a href="<?= BASE_URL ?>/vitrine/wishlist.php"     class="btn-nav" style="justify-content:flex-start;"><i class="bi bi-heart"></i> Mes souhaits</a>
      <a href="<?= BASE_URL ?>/vitrine/preferences.php"   class="btn-nav" style="justify-content:flex-start;"><i class="bi bi-sliders"></i> Préférences</a>
      <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/admin/dashboard.php"   class="btn-nav" style="justify-content:flex-start;"><i class="bi bi-speedometer2"></i> Administration</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/auth/logout.php"          class="btn-nav" style="justify-content:flex-start;color:var(--danger);"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/auth/login.php"    class="btn-nav" style="justify-content:flex-start;"><i class="bi bi-person"></i> Connexion</a>
      <a href="<?= BASE_URL ?>/auth/register.php" class="btn-nav btn-nav-primary" style="justify-content:flex-start;"><i class="bi bi-person-plus"></i> S'inscrire</a>
    <?php endif; ?>
    <!-- Categories on mobile -->
    <div style="border-top:1px solid var(--border);padding-top:.5rem;margin-top:.25rem;">
      <?php foreach ($categories_nav as $c): ?>
        <a href="<?= BASE_URL ?>/vitrine/index.php?cat=<?= $c['id_categorie'] ?>" class="btn-nav" style="justify-content:flex-start;font-size:.78rem;"><?= e($c['nom']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>
