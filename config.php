<?php
define('DB_HOST', 'localhost');   
define('DB_NAME', 'vthm');
define('DB_USER', 'root');        
define('DB_PASS', '');           
define('DB_CHARSET', 'utf8mb4');

define('SESSION_LIFETIME', 60 * 60 * 24 * 7);

function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Connexion BDD impossible : ' . $e->getMessage()]));
    }
    return $pdo;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($msg, $code = 400) {
    jsonResponse(['error' => $msg], $code);
}

function getBody() {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

function getOrCreateCartToken() {
    if (!isset($_COOKIE['velour_cart'])) {
        $token = bin2hex(random_bytes(32));
        setcookie('velour_cart', $token, [
            'expires'  => time() + SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return $token;
    }
    return $_COOKIE['velour_cart'];
}

function sessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function currentUser() {
    sessionStart();
    return $_SESSION['user'] ?? null;
}

function requireAdmin() {
    $user = currentUser();
    if (!$user || $user['role'] !== 'admin') {
        jsonError('Accès refusé - administrateur requis.', 403);
    }
}
