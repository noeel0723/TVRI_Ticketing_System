<?php
/**
 * API Endpoint: Check for new/updated tickets (AJAX Polling)
 * Returns JSON with ticket counts and latest ticket info per role.
 * Used by dashboard JS to show real-time toast notifications.
 */
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/koneksi.php';

$role = $_SESSION['role'];
$user_id = intval($_SESSION['user_id']);
$division_id = isset($_SESSION['division_id']) ? intval($_SESSION['division_id']) : 0;

// Get the "last_check" timestamp from client (ISO 8601 or MySQL datetime)
$last_check = isset($_GET['since']) ? trim($_GET['since']) : date('Y-m-d H:i:s', strtotime('-5 minutes'));

// Sanitize
$last_check = mysqli_real_escape_string($conn, $last_check);

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'counts'    => [],
    'new_items' => []
];

if ($role === 'admin') {
    // Counts
    $q = mysqli_query($conn, "SELECT 
        COUNT(*) as total,
        SUM(status='Open') as open_count,
        SUM(status IN ('Assigned','In Progress')) as progress_count,
        SUM(status='Resolved') as resolved_count
        FROM tickets");
    $c = mysqli_fetch_assoc($q);
    $response['counts'] = [
        'total'    => (int)$c['total'],
        'open'     => (int)$c['open_count'],
        'progress' => (int)$c['progress_count'],
        'resolved' => (int)$c['resolved_count']
    ];

    // New tickets since last check
    $q2 = mysqli_query($conn, "SELECT t.id, t.judul, t.status, t.priority, t.created_at, t.updated_at, u.nama as pelapor
        FROM tickets t LEFT JOIN users u ON t.user_id = u.id
        WHERE t.updated_at > '$last_check'
        ORDER BY t.updated_at DESC LIMIT 5");
    while ($row = mysqli_fetch_assoc($q2)) {
        $response['new_items'][] = [
            'id'       => (int)$row['id'],
            'judul'    => $row['judul'],
            'status'   => $row['status'],
            'priority' => $row['priority'],
            'pelapor'  => $row['pelapor'],
            'updated'  => $row['updated_at']
        ];
    }

} elseif ($role === 'teknisi') {
    $q = mysqli_query($conn, "SELECT 
        COUNT(*) as total,
        SUM(status='Assigned') as assigned_count,
        SUM(status='In Progress') as progress_count,
        SUM(status='Resolved') as resolved_count
        FROM tickets WHERE handled_by = $user_id");
    $c = mysqli_fetch_assoc($q);
    $response['counts'] = [
        'total'    => (int)$c['total'],
        'assigned' => (int)$c['assigned_count'],
        'progress' => (int)$c['progress_count'],
        'resolved' => (int)$c['resolved_count']
    ];

    $q2 = mysqli_query($conn, "SELECT t.id, t.judul, t.status, t.priority, t.updated_at, u.nama as pelapor
        FROM tickets t LEFT JOIN users u ON t.user_id = u.id
        WHERE t.handled_by = $user_id AND t.updated_at > '$last_check'
        ORDER BY t.updated_at DESC LIMIT 5");
    while ($row = mysqli_fetch_assoc($q2)) {
        $response['new_items'][] = [
            'id'       => (int)$row['id'],
            'judul'    => $row['judul'],
            'status'   => $row['status'],
            'priority' => $row['priority'],
            'pelapor'  => $row['pelapor'],
            'updated'  => $row['updated_at']
        ];
    }

} elseif ($role === 'user') {
    $q = mysqli_query($conn, "SELECT 
        COUNT(*) as total,
        SUM(status='Open') as open_count,
        SUM(status IN ('Assigned','In Progress')) as progress_count,
        SUM(status='Resolved') as resolved_count
        FROM tickets WHERE user_id = $user_id");
    $c = mysqli_fetch_assoc($q);
    $response['counts'] = [
        'total'    => (int)$c['total'],
        'open'     => (int)$c['open_count'],
        'progress' => (int)$c['progress_count'],
        'resolved' => (int)$c['resolved_count']
    ];

    $q2 = mysqli_query($conn, "SELECT t.id, t.judul, t.status, t.priority, t.updated_at
        FROM tickets t
        WHERE t.user_id = $user_id AND t.updated_at > '$last_check'
        ORDER BY t.updated_at DESC LIMIT 5");
    while ($row = mysqli_fetch_assoc($q2)) {
        $response['new_items'][] = [
            'id'      => (int)$row['id'],
            'judul'   => $row['judul'],
            'status'  => $row['status'],
            'priority'=> $row['priority'],
            'updated' => $row['updated_at']
        ];
    }
}

echo json_encode($response);
