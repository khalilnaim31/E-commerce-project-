<?php
require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

sessionStart();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if     ($action === 'login')          authLogin();
elseif ($action === 'logout')         authLogout();
elseif ($action === 'register')       authRegister();
elseif ($action === 'me')             authMe();
elseif ($action === 'products')       productsGet();
elseif ($action === 'product')        productGet();
elseif ($action === 'product_create') productCreate();
elseif ($action === 'product_update') productUpdate();
elseif ($action === 'product_delete') productDelete();
elseif ($action === 'cart')           cartGet();
elseif ($action === 'cart_add')       cartAdd();
elseif ($action === 'cart_update')    cartUpdate();
elseif ($action === 'cart_remove')    cartRemove();
elseif ($action === 'cart_clear')     cartClear();
elseif ($action === 'order')          orderCreate();
elseif ($action === 'orders')         ordersGet();
elseif ($action === 'admin_users')    adminUsers();
else   jsonError('Action inconnue : ' . $action, 404);

function authLogin() {
    $b = getBody();
    $email = trim($b['email'] ?? '');
    $pwd   = $b['password'] ?? '';

    if (!$email || !$pwd) jsonError('Email et mot de passe requis.');

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM utilisateurs WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pwd, $user['password'])) {
        jsonError('Email ou mot de passe incorrect.', 401);
    }

    unset($user['password']);
    $_SESSION['user'] = $user;

    $token = getOrCreateCartToken();
    $db->prepare('UPDATE panier_items SET user_id = ? WHERE session_token = ? AND user_id IS NULL')
       ->execute([$user['id'], $token]);

    jsonResponse(['user' => $user]);
}

function authLogout() {
    sessionStart();
    session_destroy();
    jsonResponse(['ok' => true]);
}

function authRegister() {
    $b    = getBody();
    $nom   = trim($b['nom']   ?? '');
    $email = trim($b['email'] ?? '');
    $pwd   = $b['password']   ?? '';

    if (!$nom || !$email || !$pwd) jsonError('Tous les champs sont requis.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Email invalide.');
    if (strlen($pwd) < 6) jsonError('Mot de passe trop court (6 car. min).');

    $db = getDB();
    $check = $db->prepare('SELECT id FROM utilisateurs WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) jsonError('Cet email est déjà utilisé.');

    $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare('INSERT INTO utilisateurs (nom, email, password, role) VALUES (?, ?, ?, "client")')
       ->execute([$nom, $email, $hash]);
    $id = (int)$db->lastInsertId();

    $user = ['id' => $id, 'nom' => $nom, 'email' => $email, 'role' => 'client'];
    $_SESSION['user'] = $user;

    $token = getOrCreateCartToken();
    $db->prepare('UPDATE panier_items SET user_id = ? WHERE session_token = ? AND user_id IS NULL')
       ->execute([$id, $token]);

    jsonResponse(['user' => $user], 201);
}

function authMe() {
    $user = currentUser();
    if (!$user) jsonError('Non connecté.', 401);
    jsonResponse(['user' => $user]);
}

function productsGet() {
    $db    = getDB();
    $genre = $_GET['genre'] ?? '';
    $cat   = $_GET['categorie'] ?? '';
    $u     = currentUser();
    $actif = isset($_GET['all']) && $u && $u['role'] === 'admin' ? '' : ' AND actif = 1';

    $sql    = 'SELECT * FROM produits WHERE 1=1' . $actif;
    $params = [];

    if ($genre) { $sql .= ' AND genre = ?'; $params[] = $genre; }
    if ($cat)   { $sql .= ' AND categorie = ?'; $params[] = $cat; }
    $sql .= ' ORDER BY created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['id']    = (int)$r['id'];
        $r['prix']  = (float)$r['prix'];
        $r['actif'] = (bool)$r['actif'];
    }
    jsonResponse($rows);
}

function productGet() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('id manquant.');
    $stmt = getDB()->prepare('SELECT * FROM produits WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) jsonError('Produit introuvable.', 404);
    $p['id']   = (int)$p['id'];
    $p['prix'] = (float)$p['prix'];
    jsonResponse($p);
}

function productCreate() {
    requireAdmin();
    $b = getBody();
    $required = ['nom','genre','categorie','prix','img'];
    foreach ($required as $f) {
        if (empty($b[$f])) jsonError("Champ requis : $f");
    }
    if (!in_array($b['genre'], ['homme','femme'])) jsonError('genre invalide.');
    $prix = (float)$b['prix'];
    if ($prix <= 0) jsonError('Prix invalide.');

    $db = getDB();
    $db->prepare('INSERT INTO produits (genre,categorie,nom,prix,tag,img,description)
                  VALUES (?,?,?,?,?,?,?)')
       ->execute([
           $b['genre'], $b['categorie'], $b['nom'], $prix,
           $b['tag'] ?? '', $b['img'], $b['description'] ?? ''
       ]);
    $id = (int)$db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM produits WHERE id = ?');
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    $p['id'] = $id; $p['prix'] = $prix;
    jsonResponse($p, 201);
}

function productUpdate() {
    requireAdmin();
    $b  = getBody();
    $id = (int)($b['id'] ?? 0);
    if (!$id) jsonError('id manquant.');

    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM produits WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) jsonError('Produit introuvable.', 404);

    $fields = ['genre','categorie','nom','prix','tag','img','description','actif'];
    $sets   = []; $params = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $b)) {
            $sets[]   = "$f = ?";
            $params[] = $f === 'prix' ? (float)$b[$f] : $b[$f];
        }
    }
    if (!$sets) jsonError('Aucun champ à modifier.');
    $params[] = $id;
    $db->prepare('UPDATE produits SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    jsonResponse(['ok' => true]);
}

function productDelete() {
    requireAdmin();
    $b  = getBody();
    $id = (int)($b['id'] ?? 0);
    if (!$id) jsonError('id manquant.');
    getDB()->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

function cartToken() {
    $user = currentUser();
    if ($user) {
        return getOrCreateCartToken();
    }
    return getOrCreateCartToken();
}

function cartWhere() {
    $user  = currentUser();
    $token = getOrCreateCartToken();
    if ($user) {
        return ['WHERE (user_id = ? OR session_token = ?)', [$user['id'], $token]];
    }
    return ['WHERE session_token = ? AND user_id IS NULL', [$token]];
}

function cartGet() {
    [$where, $params] = cartWhere();
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT pi.id, pi.produit_id, pi.taille, pi.quantite,
               p.nom, p.prix, p.img, p.genre
        FROM panier_items pi
        JOIN produits p ON p.id = pi.produit_id AND p.actif = 1
        $where
        ORDER BY pi.created_at
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id']         = (int)$r['id'];
        $r['produit_id'] = (int)$r['produit_id'];
        $r['quantite']   = (int)$r['quantite'];
        $r['prix']       = (float)$r['prix'];
    }
    jsonResponse($rows);
}

function cartAdd() {
    $b         = getBody();
    $produitId = (int)($b['produit_id'] ?? 0);
    $taille    = $b['taille'] ?? 'M';
    $qty       = max(1, (int)($b['quantite'] ?? 1));

    if (!$produitId) jsonError('produit_id manquant.');

    $db = getDB();
    $chk = $db->prepare('SELECT id FROM produits WHERE id = ? AND actif = 1');
    $chk->execute([$produitId]);
    if (!$chk->fetch()) jsonError('Produit introuvable.', 404);

    $user  = currentUser();
    $token = getOrCreateCartToken();
    $userId = $user ? $user['id'] : null;

    if ($userId) {
        $exist = $db->prepare('SELECT id, quantite FROM panier_items WHERE (user_id = ? OR session_token = ?) AND produit_id = ? AND taille = ? LIMIT 1');
        $exist->execute([$userId, $token, $produitId, $taille]);
    } else {
        $exist = $db->prepare('SELECT id, quantite FROM panier_items WHERE session_token = ? AND user_id IS NULL AND produit_id = ? AND taille = ? LIMIT 1');
        $exist->execute([$token, $produitId, $taille]);
    }
    $row = $exist->fetch();

    if ($row) {
        $db->prepare('UPDATE panier_items SET quantite = quantite + ? WHERE id = ?')
           ->execute([$qty, $row['id']]);
    } else {
        $db->prepare('INSERT INTO panier_items (session_token, user_id, produit_id, taille, quantite) VALUES (?,?,?,?,?)')
           ->execute([$token, $userId, $produitId, $taille, $qty]);
    }
    jsonResponse(['ok' => true]);
}

function cartUpdate() {
    $b      = getBody();
    $itemId = (int)($b['id'] ?? 0);
    $qty    = (int)($b['quantite'] ?? 1);
    if (!$itemId) jsonError('id manquant.');

    [$where, $params] = cartWhere();
    $db = getDB();

    if ($qty < 1) {
        $db->prepare("DELETE FROM panier_items WHERE id = ? AND " . str_replace('WHERE ', '', $where))
           ->execute(array_merge([$itemId], $params));
    } else {
        $db->prepare("UPDATE panier_items SET quantite = ? WHERE id = ? AND " . str_replace('WHERE ', '', $where))
           ->execute(array_merge([$qty, $itemId], $params));
    }
    jsonResponse(['ok' => true]);
}

function cartRemove() {
    $b      = getBody();
    $itemId = (int)($b['id'] ?? 0);
    if (!$itemId) jsonError('id manquant.');
    [$where, $params] = cartWhere();
    getDB()->prepare("DELETE FROM panier_items WHERE id = ? AND " . str_replace('WHERE ', '', $where))
           ->execute(array_merge([$itemId], $params));
    jsonResponse(['ok' => true]);
}

function cartClear() {
    [$where, $params] = cartWhere();
    getDB()->prepare("DELETE FROM panier_items $where")->execute($params);
    jsonResponse(['ok' => true]);
}

function orderCreate() {
    $user = currentUser();
    if (!$user) jsonError('Connexion requise pour commander.', 401);

    [$where, $params] = cartWhere();
    $db = getDB();
    $stmt = $db->prepare("
        SELECT pi.*, p.nom, p.prix FROM panier_items pi
        JOIN produits p ON p.id = pi.produit_id AND p.actif = 1
        $where
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    if (!$items) jsonError('Panier vide.');

    $sous_total = array_sum(array_map(fn($i) => $i['prix'] * $i['quantite'], $items));
    $livraison  = $sous_total >= 150 ? 0 : 9.90;
    $total      = $sous_total + $livraison;
    $token      = getOrCreateCartToken();

    $db->prepare('INSERT INTO commandes (user_id, session_token, total, livraison) VALUES (?,?,?,?)')
       ->execute([$user['id'], $token, $total, $livraison]);
    $orderId = (int)$db->lastInsertId();

    $insLine = $db->prepare('INSERT INTO commande_lignes (commande_id,produit_id,nom_produit,prix_unitaire,taille,quantite) VALUES (?,?,?,?,?,?)');
    foreach ($items as $i) {
        $insLine->execute([$orderId, $i['produit_id'], $i['nom'], $i['prix'], $i['taille'], $i['quantite']]);
    }

    $db->prepare("DELETE FROM panier_items $where")->execute($params);

    jsonResponse(['ok' => true, 'commande_id' => $orderId, 'total' => $total], 201);
}

function ordersGet() {
    $user = currentUser();
    if (!$user) jsonError('Connexion requise.', 401);

    $db = getDB();
    if ($user['role'] === 'admin') {
        $stmt = $db->prepare('SELECT c.*, u.nom as client_nom, u.email as client_email FROM commandes c LEFT JOIN utilisateurs u ON u.id = c.user_id ORDER BY c.created_at DESC LIMIT 100');
        $stmt->execute();
    } else {
        $stmt = $db->prepare('SELECT * FROM commandes WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user['id']]);
    }
    jsonResponse($stmt->fetchAll());
}

function adminUsers() {
    requireAdmin();
    $stmt = getDB()->query('SELECT id, nom, email, role, created_at FROM utilisateurs ORDER BY created_at DESC');
    jsonResponse($stmt->fetchAll());
}
