<?php
session_start();
require_once '../config/koneksi.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Teknisi'])) {
    $_SESSION['error'] = 'Akses ditolak. Hanya Admin dan Teknisi yang dapat menghapus tiket.';
    header('Location: ' . ($_SESSION['role'] === 'Teknisi' ? '../teknisi/dashboard.php' : 'dashboard.php'));
    exit();
}

// Validate ticket ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID tiket tidak valid.';
    header('Location: ' . ($_SESSION['role'] === 'Teknisi' ? '../teknisi/dashboard.php' : 'dashboard.php'));
    exit();
}

$ticket_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get ticket details for validation
$stmt = mysqli_prepare($conn, "SELECT id, judul, status, created_by FROM tickets WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $ticket_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($stmt);
    $_SESSION['error'] = 'Tiket tidak ditemukan.';
    header('Location: ' . ($role === 'Teknisi' ? '../teknisi/dashboard.php' : 'dashboard.php'));
    exit();
}

$ticket = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Verify ticket is resolved before allowing deletion
if ($ticket['status'] !== 'Resolved') {
    $_SESSION['error'] = 'Hanya tiket dengan status Resolved yang dapat dihapus.';
    header('Location: ' . ($role === 'Teknisi' ? '../teknisi/dashboard.php' : 'dashboard.php'));
    exit();
}

// Delete related data first (due to foreign key constraints)
// Delete ticket history
mysqli_query($conn, "DELETE FROM ticket_history WHERE ticket_id = $ticket_id");

// Delete ticket assignments
mysqli_query($conn, "DELETE FROM ticket_assignments WHERE ticket_id = $ticket_id");

// Delete the ticket
$stmt = mysqli_prepare($conn, "DELETE FROM tickets WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $ticket_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    
    // Log the deletion in activity table if it exists
    $activity_check = mysqli_query($conn, "SHOW TABLES LIKE 'activities'");
    if (mysqli_num_rows($activity_check) > 0) {
        $activity_desc = "Menghapus tiket #$ticket_id: " . htmlspecialchars($ticket['judul']);
        $stmt = mysqli_prepare($conn, "INSERT INTO activities (user_id, activity, created_at) VALUES (?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "is", $user_id, $activity_desc);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    $_SESSION['success'] = 'Tiket berhasil dihapus.';
} else {
    $_SESSION['error'] = 'Gagal menghapus tiket: ' . mysqli_error($conn);
}

// Redirect back to dashboard
header('Location: ' . ($role === 'Teknisi' ? '../teknisi/dashboard.php' : 'dashboard.php'));
exit();
