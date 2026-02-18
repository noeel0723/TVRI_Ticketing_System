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
    
    // Delete ticket
    $delete_query = "DELETE FROM tickets WHERE id = ? AND user_id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt_delete, "ii", $ticket_id, $user['id']);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        // Log activity
        logTicketActivity($conn, $ticket_id, $user['id'], 'ticket_deleted', 'Resolved', 'Deleted', 'Tiket "' . $ticket['judul'] . '" dihapus oleh pelapor');
        
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