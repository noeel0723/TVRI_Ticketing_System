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

// Catat log SEBELUM menghapus tiket agar history tetap tersimpan
// ticket_id pada log akan di-SET NULL otomatis oleh FK setelah tiket dihapus
logTicketActivity($conn, $ticket_id, $user['id'], 'ticket_deleted', $ticket['status'], 'Deleted', 'Tiket #' . $ticket_id . ' "' . $ticket['judul'] . '" dihapus oleh teknisi');

// Hapus tiket (ticket_logs TIDAK dihapus — FK ON DELETE SET NULL menjaga history)
$delete_stmt = mysqli_prepare($conn, 'DELETE FROM tickets WHERE id = ?');
if (!$delete_stmt) {
    header('Location: dashboard.php?error=db');
    exit();
}
mysqli_stmt_bind_param($delete_stmt, 'i', $ticket_id);
if (mysqli_stmt_execute($delete_stmt)) {
    mysqli_stmt_close($delete_stmt);
    header('Location: dashboard.php?success=deleted');
} else {
    mysqli_stmt_close($delete_stmt);
    header('Location: dashboard.php?error=delete_failed');
}
exit();
?>
