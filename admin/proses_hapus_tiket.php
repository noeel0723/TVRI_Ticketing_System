<?php
require_once '../config/auth.php';
checkRole('admin');
require_once '../config/koneksi.php';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$ticket_id = intval($_GET['id']);

// Cek status tiket, hanya boleh hapus yang Resolved
$check_stmt = mysqli_prepare($conn, 'SELECT status FROM tickets WHERE id = ?');
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

if ($ticket['status'] != 'Resolved') {
    header('Location: dashboard.php?error=not_resolved');
    exit();
}

// Mulai transaction
mysqli_begin_transaction($conn);

try {
    // 1. Hapus ticket logs terkait
    $delete_logs_stmt = mysqli_prepare($conn, 'DELETE FROM ticket_logs WHERE ticket_id = ?');
    if (!$delete_logs_stmt) {
        throw new Exception('Gagal menyiapkan delete logs');
    }
    mysqli_stmt_bind_param($delete_logs_stmt, 'i', $ticket_id);
    mysqli_stmt_execute($delete_logs_stmt);
    mysqli_stmt_close($delete_logs_stmt);

    // 2. Hapus tiket
    $delete_ticket_stmt = mysqli_prepare($conn, 'DELETE FROM tickets WHERE id = ?');
    if (!$delete_ticket_stmt) {
        throw new Exception('Gagal menyiapkan delete tiket');
    }
    mysqli_stmt_bind_param($delete_ticket_stmt, 'i', $ticket_id);
    if (!mysqli_stmt_execute($delete_ticket_stmt)) {
        mysqli_stmt_close($delete_ticket_stmt);
        throw new Exception('Gagal menghapus tiket');
    }
    mysqli_stmt_close($delete_ticket_stmt);

    // Commit transaction
    mysqli_commit($conn);

    header('Location: dashboard.php?success=delete');

} catch (Exception $e) {
    mysqli_rollback($conn);
    header('Location: dashboard.php?error=db');
}
exit();
?>
