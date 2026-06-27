<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/vitrine/index.php'); exit; }

$error = ''; $old_email = '';
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $error = 'Token invalide.'; }
    else {
        $email  = trim($_POST['email'] ?? '');
        $pwd    = $_POST['password'] ?? '';
        $old_email = $email;
        if (!$email || !$pwd) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT u.*,r.nom AS role FROM users u LEFT JOIN roles r ON u.id_role=r.id_role WHERE u.email=:e LIMIT 1");
            $stmt->execute([':e' => $email]);
            $user = $stmt->fetch();
            if ($user && password_verify($pwd, $user['mot_de_passe'])) {
                if ($user['statut'] !== 'Actif') {
                    $error = 'Compte désactivé. Contactez l\'administrateur.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']     = $user['id_user'];
                    $_SESSION['user_nom']    = $user['nom'];
                    $_SESSION['user_prenom'] = $user['prenom'];
                    $_SESSION['user_email']  = $user['email'];
                    $_SESSION['user_role']   = $user['role'];
                    // Redirect admin to dashboard, clients to store
                    if ($user['role'] === 'Administrateur') {
                        header('Location: ' . BASE_URL . '/admin/dashboard.php'); exit;
                    } else {
                        header('Location: ' . BASE_URL . '/vitrine/index.php'); exit;
                    }
                }
            } else {
                $error = 'Email ou mot de passe incorrect.';
            }
        }
    }
}
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Connexion — Nexio S.A.</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#06080F;color:#EDF2F7;font-family:'Inter',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem;}
.card{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:20px;padding:2.5rem;width:100%;max-width:420px;box-shadow:0 24px 60px rgba(0,0,0,.5);}
.logo{display:flex;align-items:center;gap:.5rem;font-size:1.4rem;font-weight:900;justify-content:center;margin-bottom:2rem;}
.logo i{color:#00C8FF;font-size:1.6rem;} .logo span{color:#00C8FF;}
h2{font-size:1.4rem;font-weight:800;text-align:center;margin-bottom:.3rem;}
.sub{color:#64748B;font-size:.88rem;text-align:center;margin-bottom:1.8rem;}
label{display:block;font-size:.8rem;font-weight:700;color:#94A3B8;margin-bottom:.35rem;}
input{width:100%;background:#0D1117;border:1px solid rgba(255,255,255,.08);border-radius:9px;color:#EDF2F7;padding:.65rem .9rem;font-size:.9rem;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;}
input:focus{border-color:rgba(0,200,255,.4);}
.field{margin-bottom:1.1rem;}
.alert-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:9px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#FCA5A5;display:flex;align-items:center;gap:.5rem;}
.alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);border-radius:9px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#6EE7B7;display:flex;align-items:center;gap:.5rem;}
.btn{width:100%;background:#1D4ED8;color:#fff;border:none;padding:.75rem;border-radius:10px;font-size:.95rem;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif;margin-top:.3rem;transition:background .15s;}
.btn:hover{background:#1e40af;}
.link{text-align:center;margin-top:1.2rem;font-size:.85rem;color:#64748B;}
.link a{color:#00C8FF;font-weight:600;}
.back{display:flex;align-items:center;gap:.4rem;color:#64748B;font-size:.82rem;text-decoration:none;margin-bottom:1rem;justify-content:center;transition:color .15s;}
.back:hover{color:#EDF2F7;}
</style>
</head>
<body>
<a href="../vitrine/index.php" class="back"><i class="bi bi-arrow-left"></i> Retour à la boutique</a>
<div class="card">
  <div class="logo"><i class="bi bi-cpu-fill"></i>Nexio<span>.</span>ht</div>
  <h2>Connexion</h2>
  <p class="sub">Accédez à votre compte Nexio S.A.</p>

  <?php if ($flash): ?>
  <div class="alert-<?= e($flash['type']) ?>"><i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>-fill"></i><?= e($flash['msg']) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert-danger"><i class="bi bi-exclamation-circle-fill"></i><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($old_email) ?>" required autofocus></div>
    <div class="field"><label>Mot de passe</label><input type="password" name="password" required></div>
    <button class="btn" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Se connecter</button>
  </form>
  <div class="link">Pas encore de compte ? <a href="register.php">Créer un compte</a></div>
</div>
</body></html>
