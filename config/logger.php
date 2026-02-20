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

    // Auto-fetch ticket title & preserve original ID for display after deletion
    $ticket_judul_esc = '';
    $ticket_id_orig_value = 'NULL';
    if ($ticket_id > 0) {
        $ticket_id_orig_value = $ticket_id;
        $tj_r = mysqli_query($conn, "SELECT judul FROM tickets WHERE id = $ticket_id LIMIT 1");
        if ($tj_r && $tj_row = mysqli_fetch_assoc($tj_r)) {
            $ticket_judul_esc = mysqli_real_escape_string($conn, $tj_row['judul']);
        }
    }

    $query = "INSERT INTO ticket_logs (ticket_id, ticket_judul, ticket_id_orig, user_id, action, old_value, new_value, description)
              VALUES ($ticket_id, '$ticket_judul_esc', $ticket_id_orig_value, $user_id_value, '$action', '$old_value', '$new_value', '$description')";

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
        $query_new = "SELECT COUNT(*) as total FROM tickets WHERE status = 'Open'";
        $result = mysqli_query($conn, $query_new);
        $counts['new_tickets'] = mysqli_fetch_assoc($result)['total'];

        $query_progress = "SELECT COUNT(*) as total FROM tickets WHERE status = 'In Progress'";
        $result = mysqli_query($conn, $query_progress);
        $counts['in_progress_tickets'] = mysqli_fetch_assoc($result)['total'];

    } elseif ($role == 'teknisi' && $user_id) {
        $query_assigned = "SELECT COUNT(*) as total FROM tickets
                          WHERE handled_by = $user_id AND status = 'Assigned'";
        $result = mysqli_query($conn, $query_assigned);
        $counts['assigned_tickets'] = mysqli_fetch_assoc($result)['total'];

        $query_progress = "SELECT COUNT(*) as total FROM tickets
                          WHERE handled_by = $user_id AND status = 'In Progress'";
        $result = mysqli_query($conn, $query_progress);
        $counts['in_progress_tickets'] = mysqli_fetch_assoc($result)['total'];

    } elseif ($role == 'user') {
        $query_resolved = "SELECT COUNT(*) as total FROM tickets
                          WHERE user_id = $user_id AND status = 'Resolved'";
        $result = mysqli_query($conn, $query_resolved);
        $counts['resolved_tickets'] = mysqli_fetch_assoc($result)['total'];
    }

    return $counts;
}
?>