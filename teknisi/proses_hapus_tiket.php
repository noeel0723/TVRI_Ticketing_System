<?php
require_once '../config/auth.php';
checkRole('teknisi');
require_once '../config/koneksi.php';
require_once '../config/logger.php';

$user = getUserInfo();

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$ticket_id = intval($_GET['id']);

// Verifikasi tiket: harus Resolved DAN ditugaskan ke teknisi ini
$check_stmt = mysqli_prepare($conn, 'SELECT id, status, judul, handled_by FROM tickets WHERE id = ?');
if (!$check_stmt) {
    header('Location: dashboard.php?error=db');
    exit();
}
mysqli_stmt_bind_param($check_stmt, 'i', $ticket_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    mysqli_stmt_close($check_stmt);
    header('Location: dashboard.php?error=not_found');
    exit();
}

$ticket = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

// Pastikan tiket ini memang ditugaskan ke teknisi yg sedang login
if ((int)$ticket['handled_by'] !== (int)$user['id']) {
    header('Location: dashboard.php?error=access_denied');
    exit();
}

// Hanya tiket Resolved yang boleh dihapus
if ($ticket['status'] !== 'Resolved') {
    header('Location: dashboard.php?error=not_resolved');
    exit();
}

// Soft delete: tiket disembunyikan dari tampilan teknisi, data tetap ada untuk admin
$hide_stmt = mysqli_prepare($conn, 'UPDATE tickets SET user_hidden = 1 WHERE id = ?');
if (!$hide_stmt) {
    header('Location: dashboard.php?error=db');
    exit();
}
mysqli_stmt_bind_param($hide_stmt, 'i', $ticket_id);
if (mysqli_stmt_execute($hide_stmt)) {
    mysqli_stmt_close($hide_stmt);
    header('Location: dashboard.php?success=deleted');
} else {
    mysqli_stmt_close($hide_stmt);
    header('Location: dashboard.php?error=delete_failed');
}
exit();
?>
