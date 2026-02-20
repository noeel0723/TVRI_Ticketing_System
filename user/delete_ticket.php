<?php
require_once '../config/auth.php';
checkRole('user');
require_once '../config/koneksi.php';
require_once '../config/logger.php';

$user = getUserInfo();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);
    
    // Verify ticket belongs to user and is resolved
    $check_query = "SELECT id, status, judul FROM tickets WHERE id = ? AND user_id = ? AND status = 'Resolved'";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $ticket_id, $user['id']);
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
    // (setelah delete, FK SET NULL akan mengubah ticket_id di log menjadi NULL, tapi log tetap ada)
    logTicketActivity($conn, $ticket_id, $user['id'], 'ticket_deleted', 'Resolved', 'Deleted', 'Tiket #' . $ticket_id . ' "' . $ticket['judul'] . '" dihapus oleh pelapor');

    $delete_query = "DELETE FROM tickets WHERE id = ? AND user_id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt_delete, "ii", $ticket_id, $user['id']);
    
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