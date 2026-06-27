<?php
/**
 * Nexio S.A. — Footer partagé + Chatbot NEX + Scripts
 */
$cats_footer = isset($pdo) ? $pdo->query("SELECT id_categorie, nom FROM categories ORDER BY nom LIMIT 6")->fetchAll() : [];
$flash_footer = getFlash();
?>

<!-- FOOTER -->
<footer class="nx-footer">
  <div class="nx-footer-grid">

    <!-- Brand -->
    <div class="nx-footer-brand">
      <div style="display:flex;align-items:center;gap:.5rem;font-size:1.1rem;font-weight:900;">
        <i class="bi bi-cpu-fill" style="color:var(--cyan);"></i>
        Nexio<span style="color:var(--cyan);">.</span>ht
      </div>
      <p>Votre partenaire technologique #1 à Port-au-Prince.<br>Matériel informatique, réseau, gaming et sécurité IT.</p>
      <div class="socials">
        <a href="#" class="social-btn" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
        <a href="#" class="social-btn" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
        <a href="#" class="social-btn" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
        <a href="#" class="social-btn" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
      </div>
    </div>

    <!-- Navigation -->
    <div>
      <h4>Navigation</h4>
      <ul>
        <li><a href="<?= BASE_URL ?>/vitrine/index.php">Accueil</a></li>
        <li><a href="<?= BASE_URL ?>/vitrine/contact.php">Contact</a></li>
        <li><a href="<?= BASE_URL ?>/vitrine/about.php">À propos</a></li>
        <li><a href="<?= BASE_URL ?>/vitrine/feedback.php">Feedback</a></li>
        <?php if(isLoggedIn()): ?>
        <li><a href="<?= BASE_URL ?>/vitrine/compte/index.php">Mon compte</a></li>
        <li><a href="<?= BASE_URL ?>/vitrine/wishlist.php">Liste de souhaits</a></li>
        <?php endif; ?>
      </ul>
    </div>

    <!-- Categories -->
    <div>
      <h4>Catégories</h4>
      <ul>
        <?php foreach ($cats_footer as $c): ?>
        <li><a href="<?= BASE_URL ?>/vitrine/index.php?cat=<?= $c['id_categorie'] ?>"><?= e($c['nom']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Contact -->
    <div>
      <h4>Contact</h4>
      <ul>
        <li><a href="<?= BASE_URL ?>/vitrine/contact.php"><i class="bi bi-geo-alt me-1"></i>Delmas, Port-au-Prince</a></li>
        <li><a href="tel:48108541"><i class="bi bi-telephone me-1"></i>4810-8541</a></li>
        <li><a href="mailto:info@nexio.ht"><i class="bi bi-envelope me-1"></i>info@nexio.ht</a></li>
        <li><span style="color:var(--muted);font-size:.83rem;"><i class="bi bi-clock me-1"></i>Lun–Sam 8h–18h</span></li>
      </ul>
    </div>

  </div>

  <div class="nx-footer-bottom">
    <span>&copy; <?= date('Y') ?> Nexio S.A. — Tous droits réservés</span>
    <span>Propulsé par <strong style="color:var(--cyan);">NEX IA</strong> · UP/FSI</span>
  </div>
</footer>

<!-- BACK TO TOP -->
<button id="backTop" aria-label="Retour en haut"><i class="bi bi-arrow-up"></i></button>

<!-- PUBLICITÉS INTELLIGENTES NEX IA -->
<div id="nexPubBar" style="display:none;position:fixed;bottom:5.5rem;left:50%;transform:translateX(-50%);z-index:450;max-width:480px;width:calc(100vw - 2rem);">
  <div id="nexPubContent"></div>
</div>

<!-- NEX CHATBOT -->
<button class="nex-toggle" id="nexToggle" aria-label="Ouvrir NEX">
  <i class="bi bi-chat-dots-fill" id="nexIcon"></i>
  <div class="nex-pulse"></div>
</button>

<div class="nex-bubble" id="nexBubble">
  <div class="nex-head">
    <div class="nex-head-info">
      <div class="nex-avatar">🤖</div>
      <div>
        <div class="nex-name">NEX</div>
        <div class="nex-status"><span class="nex-online"></span>Assistant IA Nexio</div>
      </div>
    </div>
    <button class="nex-close" id="nexClose"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="nex-messages" id="nexMessages">
    <div class="msg msg-nex">👋 Bonjour ! Je suis <strong>NEX</strong>, votre assistant IA Nexio S.A. Je peux vous aider avec nos produits, vos commandes ou des conseils tech. Comment puis-je vous aider ?</div>
  </div>
  <form class="nex-form" id="nexForm">
    <input class="nex-input" id="nexInput" type="text" placeholder="Votre message..." autocomplete="off" maxlength="500">
    <button class="nex-send" type="submit" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
  </form>
</div>

<!-- Quick View Modal -->
<div class="nx-modal-overlay" id="quickViewOverlay">
  <div class="nx-modal" id="quickViewModal"></div>
</div>

<?php if ($flash_footer): ?>
<div class="nx-flash nx-flash-<?= e($flash_footer['type']) ?>" style="position:fixed;top:80px;right:1rem;z-index:600;">
  <i class="bi bi-<?= $flash_footer['type']==='success'?'check-circle':'exclamation-circle' ?>-fill"></i>
  <?= e($flash_footer['msg']) ?>
</div>
<?php endif; ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/nexio.js"></script>
<?php if (!empty($extraJs)) echo $extraJs; ?>
<!-- Publicités intelligentes IA -->
<script>
(async function() {
  try {
    const r = await fetch('<?=BASE_URL?>/api/publicites.php');
    const d = await r.json();
    if (!d.publicites || !d.publicites.length) return;
    const pub = d.publicites[Math.floor(Math.random()*d.publicites.length)];
    if (!pub || !pub.titre) return;
    const bar = document.getElementById('nexPubBar');
    const content = document.getElementById('nexPubContent');
    if (!bar || !content) return;
    content.innerHTML = `
      <div style="background:linear-gradient(135deg,#0D1F40,#111827);border:1px solid rgba(0,200,255,.2);border-radius:12px;padding:.8rem 1.1rem;display:flex;align-items:center;gap:.8rem;box-shadow:0 8px 32px rgba(0,0,0,.5);">
        ${pub.image?`<img src="${pub.image}" style="width:42px;height:42px;object-fit:contain;border-radius:7px;background:rgba(255,255,255,.05);" onerror="this.remove()">`:'<div style="width:42px;height:42px;background:rgba(0,200,255,.08);border-radius:7px;display:flex;align-items:center;justify-content:center;"><i class="bi bi-robot" style="color:#00C8FF;"></i></div>'}
        <div style="flex:1;min-width:0;">
          <div style="font-size:.68rem;color:#00C8FF;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.15rem;"><i class="bi bi-robot me-1"></i>NEX recommande</div>
          <div style="font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${pub.titre}</div>
          <div style="font-size:.72rem;color:#94A3B8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${pub.contenu||''}</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:.3rem;align-items:flex-end;flex-shrink:0;">
          ${pub.prix||pub.lien_relatif?`<a href="<?=BASE_URL?>${pub.lien_relatif||''}" style="background:var(--cyan,#00C8FF);color:#000;font-size:.72rem;font-weight:800;padding:.3rem .7rem;border-radius:6px;white-space:nowrap;">${pub.prix||'Voir →'}</a>`:''}
          <button onclick="this.closest('[id=nexPubBar]').style.display='none'" style="background:transparent;border:none;color:rgba(255,255,255,.3);font-size:.75rem;cursor:pointer;padding:.1rem .3rem;">✕</button>
        </div>
      </div>
    `;
    bar.style.display='block';
    // Auto-cache après 8 secondes
    setTimeout(()=>{bar.style.opacity='0';bar.style.transition='opacity .4s';setTimeout(()=>bar.style.display='none',400);},8000);
  } catch {}
})();
</script>
</body>
</html>
