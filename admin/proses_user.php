<?php
require_once '../config/auth.php';
checkRole('admin');
require_once '../config/koneksi.php';

// Only require CSRF for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

$user = getUserInfo();

// Unified action source
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');
    $division_id = (isset($_POST['division_id']) && $_POST['division_id'] !== '') ? intval($_POST['division_id']) : null;

    if ($nama === '' || $username === '' || $password === '' || $role === '') {
        header('Location: kelola_user.php?error=empty'); exit();
    }

    // check username exists
    $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            header('Location: kelola_user.php?error=username_exist'); exit();
        }
        mysqli_stmt_close($stmt);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    if ($division_id === null) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)');
        if ($stmt) mysqli_stmt_bind_param($stmt, 'ssss', $nama, $username, $hashed, $role);
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO users (nama, username, password, role, division_id) VALUES (?, ?, ?, ?, ?)');
        if ($stmt) mysqli_stmt_bind_param($stmt, 'ssssi', $nama, $username, $hashed, $role, $division_id);
    }

    if ($stmt && mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        header('Location: kelola_user.php?success=add'); exit();
    }

    if ($stmt) mysqli_stmt_close($stmt);
    header('Location: kelola_user.php?error=db'); exit();

} elseif ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id = intval($_POST['user_id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');
    $division_id = (isset($_POST['division_id']) && $_POST['division_id'] !== '') ? intval($_POST['division_id']) : null;

    if ($edit_id === 0 || $nama === '' || $username === '' || $role === '') {
        header('Location: kelola_user.php?error=empty'); exit();
    }

    // check username conflict
    $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? AND id != ?');
    mysqli_stmt_bind_param($stmt, 'si', $username, $edit_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        header('Location: kelola_user.php?error=username_exist'); exit();
    }
    mysqli_stmt_close($stmt);

    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        if ($role === 'teknisi' && $division_id !== null) {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET nama=?, username=?, password=?, role=?, division_id=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'ssssii', $nama, $username, $hashed, $role, $division_id, $edit_id);
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET nama=?, username=?, password=?, role=?, division_id=NULL WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'ssssi', $nama, $username, $hashed, $role, $edit_id);
        }
    } else {
        if ($role === 'teknisi' && $division_id !== null) {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET nama=?, username=?, role=?, division_id=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'sssii', $nama, $username, $role, $division_id, $edit_id);
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET nama=?, username=?, role=?, division_id=NULL WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'sssi', $nama, $username, $role, $edit_id);
        }
    }

    if ($stmt && mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        header('Location: kelola_user.php?success=edit'); exit();
    }

    if ($stmt) mysqli_stmt_close($stmt);
    header('Location: kelola_user.php?error=db'); exit();

} elseif ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['id'] ?? 0);
    if ($user_id === 0) { header('Location: kelola_user.php?error=db'); exit(); }
    if ($user_id === $user['id']) { header('Location: kelola_user.php?error=self_delete'); exit(); }

    $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
    if (!$stmt) { header('Location: kelola_user.php?error=db'); exit(); }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        header('Location: kelola_user.php?success=delete'); exit();
    }
    mysqli_stmt_close($stmt);
    header('Location: kelola_user.php?error=db'); exit();

} elseif ($action === 'delete_multiple' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Expect an array of ids in POST 'user_ids' or comma-separated in 'user_ids'
    $raw = $_POST['user_ids'] ?? [];
    $ids = [];
    if (is_array($raw)) {
        foreach ($raw as $v) {
            $v = intval($v);
            if ($v > 0) $ids[] = $v;
        }
    } else {
        // maybe comma separated
        $parts = explode(',', (string)$raw);
        foreach ($parts as $p) {
            $p = intval($p);
            if ($p > 0) $ids[] = $p;
        }
    }

    if (empty($ids)) {
        header('Location: kelola_user.php?error=empty'); exit();
    }

    // Prevent self-delete
    if (in_array(intval($user['id']), $ids, true)) {
        header('Location: kelola_user.php?error=self_delete'); exit();
    }

    // Delete one by one using prepared statement
    $del_stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
    if (!$del_stmt) { header('Location: kelola_user.php?error=db'); exit(); }

    $all_ok = true;
    foreach ($ids as $uid) {
        mysqli_stmt_bind_param($del_stmt, 'i', $uid);
        if (!mysqli_stmt_execute($del_stmt)) {
            $all_ok = false;
            // continue to attempt others
        }
    }
    mysqli_stmt_close($del_stmt);

    if ($all_ok) header('Location: kelola_user.php?success=delete_multi');
    else header('Location: kelola_user.php?error=db');
    exit();

} else {
    header('Location: kelola_user.php'); exit();
}
