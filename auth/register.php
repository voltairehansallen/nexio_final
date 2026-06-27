<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/vitrine/index.php'); exit; }

$errors = []; $old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $errors[] = 'Token invalide.'; }
    else {
        $f = fn($k) => trim($_POST[$k] ?? '');
        $nom    = $f('nom');
        $prenom = $f('prenom');
        $email  = $f('email');
        $pwd    = $f('password');
        $pwd2   = $f('password2');
        $tel    = $f('telephone');
        $old    = compact('nom','prenom','email','tel');

        if (!$nom)                        $errors[] = 'Le nom est obligatoire.';
        if (!$prenom)                     $errors[] = 'Le prénom est obligatoire.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        if (strlen($pwd) < 6)             $errors[] = 'Mot de passe : 6 caractères minimum.';
        if ($pwd !== $pwd2)               $errors[] = 'Les mots de passe ne correspondent pas.';

        if (empty($errors)) {
            $pdo = getDB();
            $exists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e");
            $exists->execute([':e' => $email]);
            if ((int)$exists->fetchColumn() > 0) {
                $errors[] = 'Cet email est déjà utilisé.';
            } else {
                $roleId = (int)$pdo->query("SELECT id_role FROM roles WHERE nom='Client' LIMIT 1")->fetchColumn();
                $pdo->prepare("INSERT INTO users(id_role,nom,prenom,email,telephone,mot_de_passe,statut) VALUES(:r,:n,:p,:e,:t,:pwd,'Actif')")
                    ->execute([':r'=>$roleId,':n'=>$nom,':p'=>$prenom,':e'=>$email,':t'=>$tel,':pwd'=>password_hash($pwd,PASSWORD_BCRYPT)]);
                flash('success', "Compte créé ! Vous pouvez maintenant vous connecter.");
                header('Location: ' . BASE_URL . '/auth/login.php'); exit;
            }
        }
    }
}
?>
<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Créer un compte — Nexio S.A.</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#06080F;color:#EDF2F7;font-family:'Inter',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem;}
.card{background:#111827;border:1px solid rgba(255,255,255,.06);border-radius:20px;padding:2.5rem;width:100%;max-width:480px;box-shadow:0 24px 60px rgba(0,0,0,.5);}
.logo{display:flex;align-items:center;gap:.5rem;font-size:1.4rem;font-weight:900;justify-content:center;margin-bottom:2rem;}
.logo i{color:#00C8FF;font-size:1.6rem;}
.logo span{color:#00C8FF;}
h2{font-size:1.4rem;font-weight:800;margin-bottom:.3rem;text-align:center;}
.sub{color:#64748B;font-size:.88rem;text-align:center;margin-bottom:1.8rem;}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
label{display:block;font-size:.8rem;font-weight:700;color:#94A3B8;margin-bottom:.35rem;}
input{width:100%;background:#0D1117;border:1px solid rgba(255,255,255,.08);border-radius:9px;color:#EDF2F7;padding:.65rem .9rem;font-size:.9rem;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;}
input:focus{border-color:rgba(0,200,255,.4);}
.field{margin-bottom:1rem;}
.errors{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:9px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#FCA5A5;}
.errors ul{list-style:disc;padding-left:1.2rem;}
.btn{width:100%;background:#1D4ED8;color:#fff;border:none;padding:.75rem;border-radius:10px;font-size:.95rem;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif;margin-top:.5rem;transition:background .15s;}
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
  <h2>Créer un compte</h2>
  <p class="sub">Rejoignez Nexio S.A. pour commander du matériel informatique</p>

  <?php if ($errors): ?>
  <div class="errors"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="row2">
      <div class="field"><label>Prénom *</label><input type="text" name="prenom" value="<?= e($old['prenom']??'') ?>" required></div>
      <div class="field"><label>Nom *</label><input type="text" name="nom" value="<?= e($old['nom']??'') ?>" required></div>
    </div>
    <div class="field"><label>Email *</label><input type="email" name="email" value="<?= e($old['email']??'') ?>" required></div>
    <div class="field"><label>Téléphone</label><input type="text" name="telephone" value="<?= e($old['tel']??'') ?>" placeholder="+509 ..."></div>
    <div class="row2">
      <div class="field"><label>Mot de passe *</label><input type="password" name="password" required></div>
      <div class="field"><label>Confirmer *</label><input type="password" name="password2" required></div>
    </div>
    <button class="btn" type="submit"><i class="bi bi-person-plus me-1"></i>Créer mon compte</button>
  </form>
  <div class="link">Déjà un compte ? <a href="login.php">Se connecter</a></div>
</div>
</body></html>
