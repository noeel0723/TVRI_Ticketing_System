<?php
// Helper function untuk mencatat aktivitas tiket
function logTicketActivity($conn, $ticket_id, $user_id, $action, $old_value = '', $new_value = '', $description = '') {
    $ticket_id = intval($ticket_id);
    $user_id = ($user_id === null || $user_id === '') ? null : intval($user_id);
    $action = mysqli_real_escape_string($conn, $action);
    $old_value = mysqli_real_escape_string($conn, $old_value);
    $new_value = mysqli_real_escape_string($conn, $new_value);
    $description = mysqli_real_escape_string($conn, $description);
    
    $user_id_value = ($user_id === null) ? 'NULL' : $user_id;
    $query = "INSERT INTO ticket_logs (ticket_id, user_id, action, old_value, new_value, description)
              VALUES ($ticket_id, $user_id_value, '$action', '$old_value', '$new_value', '$description')";
    
    return mysqli_query($conn, $query);
}

// Function untuk mendapatkan riwayat tiket
function getTicketLogs($conn, $ticket_id, $limit = 50) {
    $ticket_id = intval($ticket_id);
    $limit = intval($limit);
    
    $query = "SELECT tl.*, u.nama, u.role
              FROM ticket_logs tl
              LEFT JOIN users u ON tl.user_id = u.id
              WHERE tl.ticket_id = $ticket_id
              ORDER BY tl.created_at DESC
              LIMIT $limit";
    
    return mysqli_query($conn, $query);
}

// Function untuk mendapatkan statistik notifikasi
function getNotificationCounts($conn, $user_id, $role, $division_id = null) {
    $user_id = intval($user_id);
    $counts = [
        'new_tickets' => 0,
        'assigned_tickets' => 0,
        'in_progress_tickets' => 0,
        'resolved_tickets' => 0
    ];
    
    if ($role == 'admin') {
        // Admin melihat semua tiket baru yang belum di-assign
        $query_new = "SELECT COUNT(*) as total FROM tickets WHERE status = 'Open'";
        $result = mysqli_query($conn, $query_new);
        $counts['new_tickets'] = mysqli_fetch_assoc($result)['total'];
        
        // Tiket yang sedang berjalan
        $query_progress = "SELECT COUNT(*) as total FROM tickets WHERE status = 'In Progress'";
        $result = mysqli_query($conn, $query_progress);
        $counts['in_progress_tickets'] = mysqli_fetch_assoc($result)['total'];
        
    } elseif ($role == 'teknisi' && $division_id) {
        // Teknisi melihat tiket di divisinya yang assigned (belum mulai dikerjakan)
        $query_assigned = "SELECT COUNT(*) as total FROM tickets 
                          WHERE assigned_division_id = $division_id AND status = 'Assigned'";
        $result = mysqli_query($conn, $query_assigned);
        $counts['assigned_tickets'] = mysqli_fetch_assoc($result)['total'];
        
        // Tiket yang sedang dalam progress
        $query_progress = "SELECT COUNT(*) as total FROM tickets 
                          WHERE assigned_division_id = $division_id AND status = 'In Progress'";
        $result = mysqli_query($conn, $query_progress);
        $counts['in_progress_tickets'] = mysqli_fetch_assoc($result)['total'];
        
    } elseif ($role == 'user') {
        // User melihat update pada tiket mereka
        $query_resolved = "SELECT COUNT(*) as total FROM tickets 
                          WHERE user_id = $user_id AND status = 'Resolved'";
        $result = mysqli_query($conn, $query_resolved);
        $counts['resolved_tickets'] = mysqli_fetch_assoc($result)['total'];
    }
    
    return $counts;
}
?>
