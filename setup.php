<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config.php';

$errors = [];
$infos  = [];

try {
    $db = getDB();
    $infos[] = "✓ Connexion MySQL réussie (host: " . DB_HOST . ", db: " . DB_NAME . ")";
} catch (Throwable $e) {
    $errors[] = "✗ Connexion MySQL impossible : " . $e->getMessage();
    goto render;
}

foreach (['utilisateurs','produits','panier_items','commandes','commande_lignes'] as $t) {
    try {
        $n = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $infos[] = "✓ Table '$t' : $n ligne(s)";
    } catch (Throwable $e) {
        $errors[] = "✗ Table '$t' introuvable — avez-vous importé vthm.sql ?";
    }
}

try {
    $hash = password_hash('admin2025', PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $db->prepare("UPDATE utilisateurs SET password = ? WHERE email = 'admin@vthl.fr'");
    $stmt->execute([$hash]);
    $affected = $stmt->rowCount();

    if ($affected === 0) {
        $db->prepare("INSERT INTO utilisateurs (nom, email, password, role) VALUES ('Administrateur','admin@vthm.fr',?,'admin')")
           ->execute([$hash]);
        $infos[] = "✓ Compte admin créé";
    } else {
        $infos[] = "✓ Hash admin mis à jour";
    }

    $row = $db->query("SELECT password FROM utilisateurs WHERE email='admin@vthm.fr'")->fetch();
    if ($row && password_verify('admin2025', $row['password'])) {
        $infos[] = "✓ Vérification hash : OK — connexion admin fonctionnelle";
    } else {
        $errors[] = "✗ Vérification hash échouée";
    }
} catch (Throwable $e) {
    $errors[] = "✗ Erreur mise à jour admin : " . $e->getMessage();
}

render:
header('Content-Type: text/html; charset=utf-8');
$ok = empty($errors);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <title>VTHM Street — Setup</title>
  <style>
    body{background:#0a0a0a;color:#f0ebe5;font-family:'Courier New',monospace;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem}
    .box{background:#141414;border:1px solid <?= $ok?'rgba(110,201,122,.4)':'rgba(224,96,96,.4)' ?>;padding:2rem 2.5rem;max-width:580px;width:100%}
    h1{font-size:.9rem;letter-spacing:.3em;text-transform:uppercase;color:<?= $ok?'#6ec97a':'#e06060' ?>;margin-bottom:1.5rem}
    .line{font-size:.75rem;margin-bottom:.5rem;padding:.3rem 0;border-bottom:1px solid #1a1a1a}
    .ok {color:#6ec97a}.err{color:#e06060}.info{color:#c9a84c}
    a{display:block;margin-top:1.5rem;text-align:center;padding:.8rem;font-size:.6rem;letter-spacing:.28em;text-transform:uppercase;background:#c9a84c;color:#000;text-decoration:none;font-weight:700}
    .warn{font-size:.6rem;color:rgba(255,100,100,.7);margin-top:.8rem;letter-spacing:.1em}
    .creds{background:#1a1a1a;padding:1rem;margin:.8rem 0;font-size:.78rem}
    .creds span{color:#c9a84c}
  </style>
</head>
<body>
<div class="box">
  <h1><?= $ok ? '✓ Setup réussi' : '✗ Erreurs détectées' ?></h1>

  <?php foreach ($infos as $i): ?>
    <div class="line ok"><?= htmlspecialchars($i) ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $e): ?>
    <div class="line err"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <?php if ($ok): ?>
    <div class="creds" style="margin-top:1rem">
    </div>
    <a href="index.html">Aller sur le site →</a>
    <p class="warn">⚠ Supprimez setup.php une fois terminé !</p>
  <?php else: ?>
    <p style="font-size:.7rem;color:#888;margin-top:1rem">Corrigez les erreurs ci-dessus puis rechargez cette page.</p>
  <?php endif; ?>
</div>
</body>
</html>
