<?php
/**
 * API Endpoint: Chart data for dashboard graphs
 * Returns JSON with chart datasets for Chart.js rendering.
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

$response = [];

// ── 1. Status Distribution (Doughnut chart) ──
$where = '';
if ($role === 'user') {
    $where = "WHERE user_id = $user_id";
} elseif ($role === 'teknisi') {
    $where = "WHERE handled_by = $user_id";
}

$q = mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM tickets $where GROUP BY status ORDER BY FIELD(status,'Open','Assigned','In Progress','Resolved')");
$status_labels = [];
$status_data   = [];
$status_colors = [];
$color_map = [
    'Open'        => '#dc2626',
    'Assigned'    => '#2563eb',
    'In Progress' => '#d97706',
    'Resolved'    => '#16a34a'
];
while ($row = mysqli_fetch_assoc($q)) {
    $status_labels[] = $row['status'];
    $status_data[]   = (int)$row['cnt'];
    $status_colors[] = $color_map[$row['status']] ?? '#6b7280';
}
$response['status_distribution'] = [
    'labels'  => $status_labels,
    'data'    => $status_data,
    'colors'  => $status_colors
];

// ── 2. Tickets per Month (Bar chart — last 6 months) ──
$months_labels = [];
$months_data   = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $months_labels[] = date('M Y', strtotime("-$i months"));
    $month_where = $where ? "$where AND" : 'WHERE';
    $qm = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tickets $month_where DATE_FORMAT(created_at,'%Y-%m') = '$m'");
    $months_data[] = (int)mysqli_fetch_assoc($qm)['cnt'];
}
$response['monthly_tickets'] = [
    'labels' => $months_labels,
    'data'   => $months_data
];

// ── 3. Priority Breakdown (for admin/teknisi) ──
if ($role !== 'user') {
    $pq = mysqli_query($conn, "SELECT priority, COUNT(*) as cnt FROM tickets $where GROUP BY priority ORDER BY FIELD(priority,'Low','Medium','High','Urgent')");
    $pri_labels = [];
    $pri_data   = [];
    $pri_colors = [];
    $pri_color_map = [
        'Low'    => '#9ca3af',
        'Medium' => '#3b82f6',
        'High'   => '#f59e0b',
        'Urgent' => '#ef4444'
    ];
    while ($row = mysqli_fetch_assoc($pq)) {
        if ($row['priority']) {
            $pri_labels[] = $row['priority'];
            $pri_data[]   = (int)$row['cnt'];
            $pri_colors[] = $pri_color_map[$row['priority']] ?? '#6b7280';
        }
    }
    $response['priority_breakdown'] = [
        'labels' => $pri_labels,
        'data'   => $pri_data,
        'colors' => $pri_colors
    ];
}

// ── 4. Division Workload (admin only) ──
if ($role === 'admin') {
    $dq = mysqli_query($conn, "SELECT d.nama_divisi, COUNT(t.id) as cnt 
        FROM divisions d 
        LEFT JOIN tickets t ON t.assigned_division_id = d.id 
        GROUP BY d.id, d.nama_divisi 
        ORDER BY d.nama_divisi");
    $div_labels = [];
    $div_data   = [];
    while ($row = mysqli_fetch_assoc($dq)) {
        $div_labels[] = $row['nama_divisi'];
        $div_data[]   = (int)$row['cnt'];
    }
    $response['division_workload'] = [
        'labels' => $div_labels,
        'data'   => $div_data
    ];
}

echo json_encode($response);
