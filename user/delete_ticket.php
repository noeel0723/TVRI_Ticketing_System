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
    
    // Soft delete: tiket disembunyikan dari tampilan user, data tetap ada untuk admin
    $hide_query = "UPDATE tickets SET user_hidden = 1 WHERE id = ? AND user_id = ?";
    $stmt_hide = mysqli_prepare($conn, $hide_query);
    mysqli_stmt_bind_param($stmt_hide, "ii", $ticket_id, $user['id']);

    if (mysqli_stmt_execute($stmt_hide)) {
        mysqli_stmt_close($stmt_hide);
        header('Location: dashboard.php?success=deleted');
        exit();
    } else {
        mysqli_stmt_close($stmt_hide);
        header('Location: dashboard.php?error=delete_failed');
        exit();
    }
} else {
    header('Location: dashboard.php');
    exit();
}
?>