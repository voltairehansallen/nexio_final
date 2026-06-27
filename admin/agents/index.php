<?php
/**
 * Nexio S.A. — Page Agent IA
 * Statut temps réel, connexion GrokCloud, lancement analyses
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
require_once BASE_PATH . '/config/app.php';
requireAdmin();
$pdo = getDB();

// Stats logs
$nb_logs = 0;
try { $nb_logs = (int)$pdo->query("SELECT COUNT(*) FROM log_analyses_ia")->fetchColumn(); } catch(PDOException){}
$recent_logs = [];
try { $recent_logs = $pdo->query("SELECT * FROM log_analyses_ia ORDER BY date_log DESC LIMIT 10")->fetchAll(); } catch(PDOException){}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Agents IA — Nexio Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>const BASE_URL='<?=BASE_URL?>';</script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#06080F;color:#EDF2F7;font-family:'Inter',sans-serif;display:flex;min-height:100vh;}
a{color:inherit;text-decoration:none;}
::-webkit-scrollbar{width:5px}::-webkit-scrollbar-track{background:#0D1117}::-webkit-scrollbar-thumb{background:rgba(255,255,255,.12);border-radius:3px}
.sb{width:210px;background:#0D1117;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;}
.sb-logo{display:flex;align-items:center;gap:.5rem;font-weight:900;font-size:.95rem;padding:1rem;border-bottom:1px solid rgba(255,255,255,.06);}
.sb-logo i{color:#00C8FF;font-size:1.1rem;}.sb-logo .dot{color:#00C8FF;}
.sb-link{display:flex;align-items:center;gap:.5rem;padding:.55rem 1rem;color:#6B7280;font-size:.8rem;font-weight:600;border-left:2px solid transparent;transition:all .15s;}
.sb-link:hover{color:#EDF2F7;background:rgba(255,255,255,.03);}
.sb-link.active{color:#00C8FF;background:rgba(0,200,255,.06);border-left-color:#00C8FF;}
.sb-link i{font-size:.88rem;width:14px;}
.main{flex:1;margin-left:210px;display:flex;flex-direction:column;}
.topbar{background:#0D1117;border-bottom:1px solid rgba(255,255,255,.06);padding:0 1.4rem;height:52px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
.topbar h1{font-size:.9rem;font-weight:800;}
.content{padding:1.3rem;flex:1;}
.card{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:11px;overflow:hidden;margin-bottom:1rem;}
.card-hd{padding:.7rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.06);font-size:.82rem;font-weight:800;display:flex;align-items:center;justify-content:space-between;gap:.7rem;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;}

/* Agent card */
.agent-card{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:11px;padding:1.1rem;display:flex;flex-direction:column;gap:.6rem;transition:border-color .2s;}
.agent-card:hover{border-color:rgba(0,200,255,.15);}
.agent-head{display:flex;align-items:center;justify-content:space-between;}
.agent-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.agent-status{display:flex;align-items:center;gap:.35rem;font-size:.7rem;font-weight:700;}
.dot-status{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.dot-ok  {background:#10B981;box-shadow:0 0 6px rgba(16,185,129,.6);}
.dot-err {background:#EF4444;box-shadow:0 0 6px rgba(239,68,68,.6);}
.dot-load{background:#F59E0B;animation:blink 1s infinite;}
.dot-off {background:#374151;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.agent-name{font-size:.85rem;font-weight:800;margin:.2rem 0;}
.agent-desc{font-size:.72rem;color:#64748B;line-height:1.5;}
.agent-route{font-size:.67rem;color:#374151;font-family:monospace;background:#0D1117;padding:.15rem .4rem;border-radius:4px;margin-top:.2rem;display:inline-block;}
.btn-run{background:rgba(0,200,255,.1);border:1px solid rgba(0,200,255,.2);color:#00C8FF;padding:.35rem .75rem;border-radius:7px;font-size:.75rem;font-weight:700;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:.3rem;font-family:'Inter',sans-serif;}
.btn-run:hover{background:rgba(0,200,255,.2);}
.btn-run:disabled{opacity:.4;cursor:not-allowed;}

/* Serveur status */
.server-card{background:#111827;border:2px solid rgba(0,200,255,.12);border-radius:12px;padding:1.2rem;}
.server-ok{border-color:rgba(16,185,129,.3);background:rgba(16,185,129,.03);}
.server-err{border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.03);}

table{width:100%;border-collapse:collapse;}
th{background:#0D1117;padding:.45rem .85rem;font-size:.63rem;font-weight:700;color:#6B7280;text-align:left;text-transform:uppercase;}
td{padding:.5rem .85rem;font-size:.79rem;border-bottom:1px solid rgba(255,255,255,.03);}
tr:last-child td{border-bottom:none;}
</style>
</head>
<body>

<div class="sb">
  <div class="sb-logo"><i class="bi bi-robot"></i>Agents<span class="dot">.</span>IA</div>
  <a href="../dashboard.php"       class="sb-link"><i class="bi bi-arrow-left"></i> Retour Admin</a>
  <a href="index.php"              class="sb-link active"><i class="bi bi-activity"></i> Statut agents</a>
  <a href="../marketing/index.php" class="sb-link"><i class="bi bi-megaphone-fill"></i> Marketing IA</a>
  <a href="../dashboard.php?page=rapports" class="sb-link"><i class="bi bi-bar-chart-fill"></i> Rapports</a>
</div>

<div class="main">
  <div class="topbar">
    <h1><i class="bi bi-robot me-1" style="color:#00C8FF;"></i>Agents IA — Tableau de bord</h1>
    <div style="display:flex;align-items:center;gap:.6rem;">
      <div id="globalDot" class="dot-status dot-load" style="width:10px;height:10px;"></div>
      <span id="globalStatus" style="font-size:.78rem;color:#64748B;">Vérification...</span>
    </div>
  </div>
  <div class="content">

    <!-- Serveur statut -->
    <div id="serverCard" class="server-card" style="margin-bottom:1.3rem;">
      <div style="display:grid;grid-template-columns:1fr auto;align-items:center;gap:1rem;">
        <div>
          <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem;">
            <div id="serverDot" class="dot-status dot-load" style="width:12px;height:12px;"></div>
            <div style="font-weight:800;font-size:.95rem;" id="serverTitle">Vérification du serveur Flask...</div>
          </div>
          <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:.78rem;">
            <div><span style="color:#64748B;">Modèle IA :</span> <strong id="serverModel">—</strong></div>
            <div><span style="color:#64748B;">Port :</span> <strong>5001</strong></div>
            <div><span style="color:#64748B;">GrokCloud :</span> <strong id="grokStatus">—</strong></div>
            <div><span style="color:#64748B;">Uptime :</span> <strong id="serverUptime">—</strong></div>
          </div>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <button class="btn-run" onclick="pingServer()"><i class="bi bi-arrow-repeat"></i> Actualiser</button>
          <a href="http://127.0.0.1:5001/ping" target="_blank" class="btn-run"><i class="bi bi-box-arrow-up-right"></i> Tester API</a>
        </div>
      </div>
    </div>

    <!-- Agents grid -->
    <div class="grid3" style="margin-bottom:1.3rem;">
      <?php
      $agents = [
        ['1','bi-graph-up-arrow','rgba(0,200,255,.1)','#00C8FF',      'Agent 1 — Comportemental','Analyse connexions, clics, achats, wishlist. Calcule score d\'engagement et fidélité.','POST /comportement','comportement',[]],
        ['2','bi-stars','rgba(139,92,246,.1)','#A5B4FC',               'Agent 2 — Recommandation','Recommandations personnalisées, produits similaires et complémentaires via GrokCloud.','POST /recommander','recommandation',['id_user'=>1]],
        ['3','bi-megaphone-fill','rgba(29,78,216,.15)','#60A5FA',      'Agent 3 — Marketing','Génère campagnes Email/WhatsApp/Facebook adaptées aux profils clients.','POST /campagne','marketing',['id_campagne'=>1]],
        ['4','bi-bar-chart-fill','rgba(16,185,129,.1)','#10B981',      'Agent 4 — Ventes','Analyse CA, bénéfices, panier moyen, taux de conversion. Rapport JSON complet.','GET /rapport-ventes','ventes',[]],
        ['5','bi-graph-down-arrow','rgba(245,158,11,.1)','#F59E0B',    'Agent 5 — Prévision','Prévoit ventes futures, ruptures de stock et tendances via GrokCloud.','GET /previsions','previsions',[]],
        ['6','bi-archive-fill','rgba(99,102,241,.12)','#818CF8',       'Agent 6 — Stock','Détecte ruptures, seuils d\'alerte, produits inactifs et sans image.','GET /stocks','stocks',[]],
        ['7','bi-chat-dots-fill','rgba(34,197,94,.1)','#86EFAC',       'Agent 7 — Chatbot NEX','Répond aux clients, recherche produits, suit commandes via GrokCloud.','POST /chat','chatbot',['message'=>'Test','session_id'=>'admin']],
        ['10','bi-shield-exclamation','rgba(239,68,68,.1)','#FCA5A5',  'Agent 10 — Fraude','Détecte commandes suspectes et comportements anormaux.','POST /fraude','fraude',[]],
        ['11','bi-emoji-smile-fill','rgba(251,191,36,.1)','#FDE68A',   'Agent 11 — Sentiment','Analyse sentiment des avis et commentaires clients.','POST /sentiment','sentiment',[]],
      ];
      foreach($agents as [$num,$ico,$bg,$color,$name,$desc,$route,$slug,$payload]):
      ?>
      <div class="agent-card" id="agent-card-<?=$slug?>">
        <div class="agent-head">
          <div class="agent-ico" style="background:<?=$bg?>;color:<?=$color?>;"><i class="bi <?=$ico?>"></i></div>
          <div class="agent-status" id="status-<?=$slug?>">
            <div class="dot-status dot-off"></div>
            <span>Hors-ligne</span>
          </div>
        </div>
        <div>
          <div class="agent-name"><?=htmlspecialchars($name,ENT_QUOTES)?></div>
          <div class="agent-desc"><?=htmlspecialchars($desc,ENT_QUOTES)?></div>
          <div class="agent-route"><?=htmlspecialchars($route,ENT_QUOTES)?></div>
        </div>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
          <button class="btn-run" id="btn-<?=$slug?>" onclick="runAgent('<?=$slug?>',<?=htmlspecialchars(json_encode($payload),ENT_QUOTES)?>,this)">
            <i class="bi bi-play-fill"></i> Lancer
          </button>
          <div id="result-<?=$slug?>" style="font-size:.7rem;color:#64748B;align-self:center;"></div>
        </div>
      </div>
      <?php endforeach;?>
    </div>

    <!-- API externes -->
    <div class="card">
      <div class="card-hd"><i class="bi bi-cloud-fill me-1" style="color:#00C8FF;"></i>APIs externes & Configuration .env</div>
      <div style="padding:1.1rem;">
        <div class="grid2">
          <?php
          $apis = [
    [
        'GrokCloud',
        'bi-robot',
        '#00C8FF',
        getenv('GROQ_API_KEY') ? 'Clé définie' : 'Non configuré',
        getenv('GROQ_API_KEY')
    ],

    [
        'SMTP Email',
        'bi-envelope-fill',
        '#60A5FA',
        (getenv('SMTP_HOST') || getenv('SMTP_USER')) ? 'Configuré' : 'Non configuré',
        getenv('SMTP_HOST') ?: getenv('SMTP_USER')
    ],

    [
        'WhatsApp Business',
        'bi-whatsapp',
        '#25D366',
        getenv('WHATSAPP_TOKEN') ? 'Token défini' : 'Non configuré',
        getenv('WHATSAPP_TOKEN')
    ],

    [
        'Facebook / Meta',
        'bi-facebook',
        '#1877F2',
        getenv('FACEBOOK_APP_ID') ? 'App ID définie' : 'Non configuré',
        getenv('FACEBOOK_APP_ID')
    ],
];

foreach ($apis as [$name, $ico, $color, $status, $val]):
          ?>
          <div style="background:#0D1117;border:1px solid rgba(255,255,255,.06);border-radius:9px;padding:.85rem;display:flex;align-items:center;gap:.75rem;">
            <div style="width:34px;height:34px;background:rgba(255,255,255,.04);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="bi <?=$ico?>" style="font-size:.95rem;color:<?=$color?>;"></i>
            </div>
            <div>
              <div style="font-size:.82rem;font-weight:700;"><?=htmlspecialchars($name,ENT_QUOTES)?></div>
              <div style="display:flex;align-items:center;gap:.35rem;">
                <div class="dot-status <?=$val?'dot-ok':'dot-err'?>" style="width:7px;height:7px;"></div>
                <span style="font-size:.7rem;color:#64748B;"><?=htmlspecialchars($status,ENT_QUOTES)?></span>
              </div>
            </div>
          </div>
          <?php endforeach;?>
        </div>
        <div style="margin-top:1rem;background:rgba(0,200,255,.04);border:1px solid rgba(0,200,255,.1);border-radius:8px;padding:.8rem;font-size:.78rem;color:#64748B;">
          <i class="bi bi-info-circle me-1" style="color:#00C8FF;"></i>
          Configurez les clés API dans le fichier <code style="background:#0D1117;padding:.1rem .35rem;border-radius:4px;color:#00C8FF;">.env</code> à la racine du projet. Aucune clé n'est codée dans les fichiers PHP.
        </div>
      </div>
    </div>

    <!-- Logs récents -->
    <div class="card">
      <div class="card-hd"><i class="bi bi-journal-text me-1" style="color:#00C8FF;"></i>Journaux IA récents (<?=$nb_logs?> total)</div>
      <div style="overflow-x:auto;"><table>
        <thead><tr><th>Date</th><th>Agent</th><th>Action</th><th>Statut</th><th>Durée</th><th>Tokens</th></tr></thead>
        <tbody>
        <?php foreach($recent_logs as $l):?>
        <tr>
          <td style="font-size:.71rem;color:#64748B;"><?=date('d/m H:i:s',strtotime($l['date_log']))?></td>
          <td><span style="background:rgba(0,200,255,.08);color:#00C8FF;padding:.15rem .45rem;border-radius:4px;font-size:.7rem;font-weight:700;"><?=htmlspecialchars($l['agent'],ENT_QUOTES)?></span></td>
          <td style="font-size:.78rem;"><?=htmlspecialchars($l['action'],ENT_QUOTES)?></td>
          <td>
            <?php $sc=$l['statut']; ?>
            <div class="dot-status <?=$sc==='succès'?'dot-ok':($sc==='en_cours'?'dot-load':'dot-err')?>" style="width:8px;height:8px;display:inline-block;margin-right:.3rem;"></div>
            <span style="font-size:.73rem;"><?=htmlspecialchars($sc,ENT_QUOTES)?></span>
          </td>
          <td style="font-size:.73rem;color:#64748B;"><?=(int)$l['duree_ms']?>ms</td>
          <td style="font-size:.73rem;color:#64748B;"><?=(int)$l['tokens_utilises']?></td>
        </tr>
        <?php endforeach;?>
        <?php if(empty($recent_logs)):?><tr><td colspan="6" style="text-align:center;color:#64748B;padding:2rem;">Aucun log. Les agents IA commenceront à logger lors de leur première exécution.</td></tr><?php endif;?>
        </tbody>
      </table></div>
    </div>

  </div>
</div>

<script>
const ROUTES = {
  comportement: {method:'POST',url:'http://127.0.0.1:5001/comportement',body:{}},
  recommandation:{method:'POST',url:'http://127.0.0.1:5001/recommander',body:{id_user:1}},
  marketing:    {method:'POST',url:'http://127.0.0.1:5001/campagne',body:{id_campagne:1}},
  ventes:       {method:'GET', url:'http://127.0.0.1:5001/rapport-ventes?jours=30'},
  previsions:   {method:'GET', url:'http://127.0.0.1:5001/previsions'},
  stocks:       {method:'GET', url:'http://127.0.0.1:5001/stocks'},
  chatbot:      {method:'POST',url:'http://127.0.0.1:5001/chat',body:{message:'Test admin',session_id:'admin_test'}},
  fraude:       {method:'POST',url:'http://127.0.0.1:5001/fraude',body:{}},
  sentiment:    {method:'POST',url:'http://127.0.0.1:5001/sentiment',body:{}},
};

async function pingServer() {
  const dot   = document.getElementById('serverDot');
  const title = document.getElementById('serverTitle');
  const glob  = document.getElementById('globalDot');
  const gstat = document.getElementById('globalStatus');

  dot.className = 'dot-status dot-load';
  try {
    const r = await fetch('http://127.0.0.1:5001/agents-status', {signal: AbortSignal.timeout(4000)});
    const d = await r.json();
    dot.className = 'dot-status dot-ok';
    title.textContent = '✓ Serveur Flask connecté — Nexio IA opérationnel';
    document.getElementById('serverModel').textContent  = d.model || 'llama-3.3-70b-versatile';
    const grokOk = d.grok_ready;
    document.getElementById('grokStatus').textContent   = grokOk ? 'GrokCloud prêt ✓' : '⚠ Clé API Grok non configurée';
    document.getElementById('grokStatus').style.color   = grokOk ? '#10B981' : '#F59E0B';
    document.getElementById('serverUptime').textContent = `${d.nb_agents} agents disponibles`;
    document.getElementById('serverCard').classList.add('server-ok');
    document.getElementById('serverCard').classList.remove('server-err');
    glob.className = 'dot-status ' + (grokOk ? 'dot-ok' : 'dot-load');
    gstat.textContent = grokOk ? 'Tous les systèmes opérationnels' : 'Serveur OK — Configurez GROQ_API_KEY dans .env';
    gstat.style.color = grokOk ? '#10B981' : '#F59E0B';
    // Mettre à jour statut de tous les agents
    Object.keys(ROUTES).forEach(slug => updateAgentStatus(slug, grokOk ? 'ok' : 'load'));
  } catch {
    dot.className = 'dot-status dot-err';
    title.textContent = '✗ Serveur Flask hors-ligne — Lancez python/main.py';
    document.getElementById('grokStatus').textContent = 'GrokCloud inaccessible';
    document.getElementById('serverCard').classList.add('server-err');
    document.getElementById('serverCard').classList.remove('server-ok');
    glob.className = 'dot-status dot-err';
    gstat.textContent = 'Serveur IA hors-ligne';
    gstat.style.color = '#EF4444';
    Object.keys(ROUTES).forEach(slug => updateAgentStatus(slug, 'off'));
  }
}

function updateAgentStatus(slug, state) {
  const el = document.getElementById('status-'+slug);
  if (!el) return;
  const map = {ok:['dot-ok','Connecté'],err:['dot-err','Erreur'],load:['dot-load','En cours...'],off:['dot-off','Hors-ligne']};
  const [cls,lbl] = map[state] || map.off;
  el.innerHTML = `<div class="dot-status ${cls}"></div><span>${lbl}</span>`;
}

async function runAgent(slug, defaultPayload, btn) {
  const r = ROUTES[slug];
  if (!r) return;
  btn.disabled = true;
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> En cours...';
  updateAgentStatus(slug, 'load');
  const resultEl = document.getElementById('result-'+slug);
  const start = Date.now();

  try {
    const opts = {signal: AbortSignal.timeout(45000)};
    if (r.method === 'POST') {
      opts.method = 'POST';
      opts.headers = {'Content-Type':'application/json'};
      opts.body = JSON.stringify(r.body || defaultPayload || {});
    }
    const resp = await fetch(r.url, opts);
    const d = await resp.json();
    const ms = Date.now()-start;
    updateAgentStatus(slug, 'ok');
    resultEl.innerHTML = `<span style="color:#10B981;">✓ ${ms}ms</span>`;
    resultEl.title = JSON.stringify(d).substring(0,200);
    // Log
    await fetch(BASE_URL+'/api/log_ia.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({agent:slug,action:'manuel',statut:'succès',duree_ms:ms})}).catch(()=>{});
  } catch(e) {
    updateAgentStatus(slug, 'err');
    resultEl.innerHTML = `<span style="color:#EF4444;">Erreur: ${e.message.substring(0,30)}</span>`;
  }
  btn.disabled = false;
  btn.innerHTML = orig;
}

// Init
pingServer();
setInterval(pingServer, 30000);
</script>
</body></html>
