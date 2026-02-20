<?php
require_once '../config/auth.php';
checkRole('admin');
require_once '../config/koneksi.php';
require_once '../config/logger.php';

$user = getUserInfo();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);
    
    // Verify ticket exists
    $check_query = "SELECT id, judul, status FROM tickets WHERE id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $ticket_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        mysqli_stmt_close($stmt);
        header('Location: dashboard.php?error=not_found');
        exit();
    }
    
    $ticket = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Delete ticket — log SEBELUM delete agar ticket_id masih valid saat insert log
    logTicketActivity($conn, $ticket_id, $user['id'], 'ticket_deleted', $ticket['status'], 'Deleted', 'Tiket #' . $ticket_id . ' "' . $ticket['judul'] . '" dihapus oleh admin');

    $delete_query = "DELETE FROM tickets WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt_delete, "i", $ticket_id);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        mysqli_stmt_close($stmt_delete);
        header('Location: dashboard.php?success=deleted');
        exit();
    } else {
        mysqli_stmt_close($stmt_delete);
        header('Location: dashboard.php?error=delete_failed');
        exit();
    }
} else {
    header('Location: dashboard.php');
    exit();
}
?>