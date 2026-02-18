<?php
require_once __DIR__ . '/bootstrap.php';
startSecureSession();
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}
function checkRole($allowed_roles) {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
    if (!is_array($allowed_roles)) {
        $allowed_roles = array($allowed_roles);
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        redirectToDashboard();
    }
}
function redirectToDashboard() {
    $role = $_SESSION['role'];
    if ($role == 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($role == 'user') {
        header('Location: ../user/dashboard.php');
    } elseif ($role == 'teknisi') {
        header('Location: ../teknisi/dashboard.php');
    } else {
        session_destroy();
        header('Location: ../index.php');
    }
    exit();
}
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}
function getUserInfo() {
    return array(
        'id' => $_SESSION['user_id'] ?? null,
        'nama' => $_SESSION['nama'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'division_id' => $_SESSION['division_id'] ?? null,
        'nama_divisi' => $_SESSION['nama_divisi'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? ''
    );
}
function getRoleLabel($role = null) {
    if ($role === null) {
        $role = $_SESSION['role'] ?? '';
    }
    $labels = array(
        'admin' => 'Administrator',
        'user' => 'User (Pelapor)',
        'teknisi' => 'Teknisi'
    );
    return $labels[$role] ?? 'Unknown';
}
function secureSession() {
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
    if (isset($_SESSION['user_ip'])) {
        if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
            session_destroy();
            header('Location: ../index.php?error=invalid_session');
            exit();
        }
    } else {
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    }
}
if (isLoggedIn()) {
    secureSession();
}
?>

