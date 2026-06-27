/**
 * Nexio S.A. — JavaScript Global
 * ES6+ | Mobile First | Animations | AJAX | UX
 */

'use strict';

// ── Progress bar ───────────────────────────────────────────────
const Progress = {
  bar: null,
  init() {
    this.bar = document.getElementById('progress-bar');
    window.addEventListener('beforeunload', () => this.set(80));
  },
  set(pct) { if (this.bar) this.bar.style.width = pct + '%'; },
  done()   { this.set(100); setTimeout(() => { if (this.bar) this.bar.style.width = '0'; }, 300); },
};

// ── Navbar scroll shadow ───────────────────────────────────────
const Navbar = {
  init() {
    const nav = document.querySelector('.nx-nav');
    if (!nav) return;
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 30);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Mobile menu
    const toggler = document.querySelector('.nx-toggler');
    const menu    = document.querySelector('.nx-mobile-menu');
    if (toggler && menu) {
      toggler.addEventListener('click', () => {
        menu.classList.toggle('open');
        toggler.innerHTML = menu.classList.contains('open')
          ? '<i class="bi bi-x-lg"></i>'
          : '<i class="bi bi-list"></i>';
      });
    }
  },
};

// ── Back to top ────────────────────────────────────────────────
const BackTop = {
  init() {
    const btn = document.getElementById('backTop');
    if (!btn) return;
    window.addEventListener('scroll', () => btn.classList.toggle('show', window.scrollY > 400), { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  },
};

// ── Intersection Observer — fade-in on scroll ──────────────────
const ScrollReveal = {
  init() {
    const els = document.querySelectorAll('[data-animate]');
    if (!els.length) return;
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((e, i) => {
        if (e.isIntersecting) {
          setTimeout(() => e.target.classList.add('animated'), i * 60);
          observer.unobserve(e.target);
        }
      });
    }, { threshold: 0.12 });
    els.forEach(el => observer.observe(el));
  },
};

// ── Lazy loading images ────────────────────────────────────────
const LazyLoad = {
  init() {
    const imgs = document.querySelectorAll('img[data-src]');
    if (!imgs.length) return;
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          const img = e.target;
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
          observer.unobserve(img);
        }
      });
    });
    imgs.forEach(img => observer.observe(img));
  },
};

// ── Flash notification ─────────────────────────────────────────
const Flash = {
  show(msg, type = 'success', duration = 3500) {
    const el = document.createElement('div');
    el.className = `nx-flash nx-flash-${type}`;
    el.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}-fill"></i>${msg}`;
    document.body.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateX(120%)';
      el.style.transition = 'all .3s ease';
      setTimeout(() => el.remove(), 350);
    }, duration);
  },
};
window.Flash = Flash;

// ── Cart badge update ──────────────────────────────────────────
const Cart = {
  async addProduct(id, name) {
    const dot = document.querySelector('.cart-dot');
    try {
      const r = await fetch(`${BASE_URL}/vitrine/panier.php?action=add&id=${id}&ajax=1`);
      const d = await r.json();
      if (d.count !== undefined && dot) {
        dot.textContent = d.count;
        dot.style.display = d.count > 0 ? 'flex' : 'none';
      }
      Flash.show(`${name || 'Produit'} ajouté au panier !`);
    } catch {
      Flash.show('Ajout au panier...', 'info');
      window.location.href = `${BASE_URL}/vitrine/panier.php?action=add&id=${id}`;
    }
  },
};
window.Cart = Cart;

// ── Wishlist toggle ────────────────────────────────────────────
const Wishlist = {
  async toggle(id, btn) {
    try {
      const r = await fetch(`${BASE_URL}/vitrine/wishlist.php?action=toggle&id=${id}&ajax=1`);
      const d = await r.json();
      btn.classList.toggle('active', d.added);
      Flash.show(d.added ? 'Ajouté à votre liste de souhaits !' : 'Retiré de la liste de souhaits.', d.added ? 'success' : 'info');
    } catch {
      Flash.show('Connectez-vous pour gérer votre liste.', 'info');
    }
  },
};
window.Wishlist = Wishlist;

// ── NEX Chatbot ────────────────────────────────────────────────
const Nex = {
  bubble:   null,
  toggle:   null,
  messages: null,
  input:    null,
  icon:     null,

  init() {
    this.bubble   = document.getElementById('nexBubble');
    this.toggle   = document.getElementById('nexToggle');
    this.messages = document.getElementById('nexMessages');
    this.input    = document.getElementById('nexInput');
    this.icon     = document.getElementById('nexIcon');
    if (!this.bubble) return;

    this.toggle.addEventListener('click', () => this.open());
    document.getElementById('nexClose')?.addEventListener('click', () => this.close());
    document.getElementById('nexForm')?.addEventListener('submit', e => { e.preventDefault(); this.send(); });
    this.input?.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.send(); } });
  },

  open() {
    const isOpen = this.bubble.classList.contains('open');
    if (isOpen) { this.close(); return; }
    this.bubble.style.display = 'flex';
    requestAnimationFrame(() => this.bubble.classList.add('open'));
    this.icon.className = 'bi bi-x-lg';
    this.input?.focus();
  },

  close() {
    this.bubble.classList.remove('open');
    this.icon.className = 'bi bi-chat-dots-fill';
    setTimeout(() => { if (!this.bubble.classList.contains('open')) this.bubble.style.display = 'none'; }, 320);
  },

  addMsg(text, role) {
    const div = document.createElement('div');
    div.className = `msg msg-${role === 'user' ? 'user' : 'nex'}`;
    div.textContent = text;
    this.messages.appendChild(div);
    this.messages.scrollTop = this.messages.scrollHeight;
    return div;
  },

  typing() {
    const div = document.createElement('div');
    div.className = 'msg msg-nex msg-typing';
    div.innerHTML = '<div class="dot-t"></div><div class="dot-t"></div><div class="dot-t"></div>';
    this.messages.appendChild(div);
    this.messages.scrollTop = this.messages.scrollHeight;
    return div;
  },

  async send() {
    const msg = this.input?.value.trim();
    if (!msg) return;
    this.input.value = '';
    this.addMsg(msg, 'user');
    const t = this.typing();
    try {
      const r = await fetch(`${BASE_URL}/api/chat.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg }),
      });
      const d = await r.json();
      t.remove();
      this.addMsg(d.reply || 'Désolé, une erreur est survenue.', 'nex');
    } catch {
      t.remove();
      this.addMsg('Connexion impossible. Réessayez.', 'nex');
    }
  },
};

// ── Quick view modal ───────────────────────────────────────────
const QuickView = {
  overlay: null,
  init() {
    this.overlay = document.getElementById('quickViewOverlay');
    if (!this.overlay) return;
    this.overlay.addEventListener('click', e => {
      if (e.target === this.overlay) this.close();
    });
    document.getElementById('quickViewClose')?.addEventListener('click', () => this.close());
  },

  async open(id) {
    if (!this.overlay) return;
    this.overlay.classList.add('open');
    const body = this.overlay.querySelector('.nx-modal');
    body.innerHTML = '<div class="text-center py-5"><div class="skeleton skel-line w80 mb-2"></div><div class="skeleton skel-line w60"></div></div>';
    try {
      const r = await fetch(`${BASE_URL}/vitrine/produit.php?id=${id}&ajax=1`);
      const d = await r.json();
      body.innerHTML = `
        <div class="nx-modal-hd"><h3 style="font-size:1rem;">${d.nom}</h3><button class="nx-modal-close" id="quickViewClose"><i class="bi bi-x-lg"></i></button></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start;">
          <div style="background:var(--card2);border-radius:12px;aspect-ratio:1;display:flex;align-items:center;justify-content:center;padding:1rem;">
            ${d.image ? `<img src="${d.image}" style="max-height:180px;object-fit:contain;">` : '<i class="bi bi-cpu" style="font-size:4rem;color:rgba(255,255,255,.06);"></i>'}
          </div>
          <div>
            <div style="font-size:.7rem;color:var(--cyan);font-weight:700;text-transform:uppercase;margin-bottom:.3rem;">${d.categorie || ''}</div>
            <div style="font-weight:700;font-size:.9rem;margin-bottom:.6rem;">${d.nom}</div>
            <div style="font-size:1.3rem;font-weight:900;margin-bottom:.6rem;">${Number(d.prix).toLocaleString()} <span style="font-size:.75rem;color:var(--muted);">HTG</span></div>
            <div style="font-size:.8rem;color:var(--text2);margin-bottom:1rem;">${d.description || ''}</div>
            <button class="btn-nx-primary" style="width:100%;" onclick="Cart.addProduct(${d.id_produit},'${d.nom}')"><i class="bi bi-cart-plus"></i> Ajouter au panier</button>
          </div>
        </div>
      `;
      document.getElementById('quickViewClose')?.addEventListener('click', () => this.close());
    } catch {
      body.innerHTML = '<p class="text-center" style="color:var(--muted);padding:2rem;">Erreur de chargement.</p>';
    }
  },

  close() {
    this.overlay?.classList.remove('open');
  },
};
window.QuickView = QuickView;

// ── Skeleton → real content ────────────────────────────────────
const Skeleton = {
  replace(container) {
    container.querySelectorAll('.skeleton-card').forEach((el, i) => {
      setTimeout(() => el.classList.add('fade-in'), i * 50);
    });
  },
};

// ── Ripple effect on buttons ───────────────────────────────────
const Ripple = {
  init() {
    document.addEventListener('click', e => {
      const btn = e.target.closest('.btn-nx-primary, .btn-nx-cyan, .btn-add-cart');
      if (!btn) return;
      const circle = document.createElement('span');
      const d = Math.max(btn.clientWidth, btn.clientHeight);
      const rect = btn.getBoundingClientRect();
      Object.assign(circle.style, {
        width: height = d + 'px',
        left: (e.clientX - rect.left - d/2) + 'px',
        top:  (e.clientY - rect.top  - d/2) + 'px',
        position: 'absolute', borderRadius: '50%',
        background: 'rgba(255,255,255,.25)',
        transform: 'scale(0)', animation: 'ripple .5s linear',
        pointerEvents: 'none',
      });
      if (!btn.style.position || btn.style.position === 'static') btn.style.position = 'relative';
      btn.style.overflow = 'hidden';
      btn.appendChild(circle);
      setTimeout(() => circle.remove(), 600);
    });
    // CSS for ripple
    const style = document.createElement('style');
    style.textContent = '@keyframes ripple{to{transform:scale(3);opacity:0}}';
    document.head.appendChild(style);
  },
};

// ── Auto-hide flash from PHP ───────────────────────────────────
const AutoFlash = {
  init() {
    document.querySelectorAll('.nx-flash').forEach(el => {
      setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateX(120%)';
        el.style.transition = 'all .3s ease';
        setTimeout(() => el.remove(), 350);
      }, 3500);
    });
  },
};

// ── Smooth image loading with fallback ─────────────────────────
const ImageLoader = {
  init() {
    document.querySelectorAll('.pcard-img img').forEach(img => {
      img.addEventListener('error', function () {
        this.style.display = 'none';
        const fallback = document.createElement('i');
        fallback.className = 'bi bi-cpu no-img';
        fallback.style.cssText = 'font-size:3rem;color:rgba(255,255,255,.05);';
        this.parentNode.appendChild(fallback);
      });
    });
  },
};

// ── Product card add-to-cart AJAX ──────────────────────────────
const ProductCards = {
  init() {
    document.querySelectorAll('.btn-add-cart[data-id]').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const id   = btn.dataset.id;
        const name = btn.dataset.name;
        Cart.addProduct(id, name);
        // Button feedback
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Ajouté !';
        btn.style.background = 'var(--success)';
        setTimeout(() => {
          btn.innerHTML = orig;
          btn.style.background = '';
        }, 1500);
      });
    });

    // Wishlist buttons
    document.querySelectorAll('.pcard-wish[data-id]').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        Wishlist.toggle(btn.dataset.id, btn);
      });
    });
  },
};

// ── Cart quantity AJAX ─────────────────────────────────────────
const CartPage = {
  init() {
    document.querySelectorAll('.qty-input').forEach(input => {
      input.addEventListener('change', async function () {
        const id  = this.dataset.id;
        const qty = parseInt(this.value) || 0;
        try {
          const r = await fetch(`${BASE_URL}/vitrine/panier.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update&id=${id}&qty=${qty}&ajax=1`,
          });
          const d = await r.json();
          if (d.total !== undefined) {
            document.querySelectorAll('.cart-total-val').forEach(el => {
              el.textContent = Number(d.total).toLocaleString();
            });
          }
          if (qty === 0) this.closest('tr, .cart-item')?.remove();
          Flash.show('Panier mis à jour.');
        } catch { window.location.reload(); }
      });
    });
  },
};

// ── Search suggestions (simple) ────────────────────────────────
const SearchSuggest = {
  timer: null,
  init() {
    const inputs = document.querySelectorAll('.nx-search input, .hero-search-big input');
    inputs.forEach(input => {
      input.addEventListener('input', () => {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
          if (input.value.length >= 2) this.suggest(input.value, input);
        }, 300);
      });
    });
  },

  async suggest(q, input) {
    // simple: navigate to results
    // Can be extended with fetch + dropdown
  },
};

// ── Global BASE_URL (set by PHP in HTML) ───────────────────────
if (typeof BASE_URL === 'undefined') window.BASE_URL = '';

// ── Init all ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  Progress.init();
  Navbar.init();
  BackTop.init();
  ScrollReveal.init();
  LazyLoad.init();
  Ripple.init();
  AutoFlash.init();
  ImageLoader.init();
  ProductCards.init();
  CartPage.init();
  Nex.init();
  QuickView.init();
  Progress.done();
});

// ── Export for inline use ──────────────────────────────────────
window.NexioChatbot = Nex;
