<?php
function startSecureSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSecureSession();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate($token) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        startSecureSession();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_require() {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($token)) {
        http_response_code(403);
        exit('CSRF validation failed');
    }
}
?>
