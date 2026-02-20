<?php
require_once '../config/auth.php';
checkRole('admin');
require_once '../config/koneksi.php';

$user = getUserInfo();

// get user photo
$_foto_q = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
mysqli_stmt_bind_param($_foto_q, "i", $user['id']);
mysqli_stmt_execute($_foto_q);
$_foto_r = mysqli_stmt_get_result($_foto_q);
$_foto_d = mysqli_fetch_assoc($_foto_r);
mysqli_stmt_close($_foto_q);
$_user_photo = !empty($_foto_d['foto']) ? '../uploads/profile_photos/' . $_foto_d['foto'] : '';

// global ticket stats for admin
$query_total = "SELECT COUNT(*) as total FROM tickets";
$query_open = "SELECT COUNT(*) as total FROM tickets WHERE status = 'Open'";
$query_progress = "SELECT COUNT(*) as total FROM tickets WHERE status IN ('Assigned','In Progress')";
$query_resolved = "SELECT COUNT(*) as total FROM tickets WHERE status = 'Resolved'";

$total_tickets = (int) ((mysqli_fetch_assoc(mysqli_query($conn, $query_total))['total']) ?? 0);
$open_tickets = (int) ((mysqli_fetch_assoc(mysqli_query($conn, $query_open))['total']) ?? 0);
$progress_tickets = (int) ((mysqli_fetch_assoc(mysqli_query($conn, $query_progress))['total']) ?? 0);
$resolved_tickets = (int) ((mysqli_fetch_assoc(mysqli_query($conn, $query_resolved))['total']) ?? 0);

// Resolution rate
$resolution_rate = $total_tickets > 0 ? round(($resolved_tickets / $total_tickets) * 100) : 0;

// For badge notification
$open_count = $open_tickets;

// Top Teknisi - most resolved tickets
$query_top_teknisi = "SELECT u.id, u.nama, u.foto, COUNT(t.id) as resolved_count,
    (SELECT COUNT(*) FROM tickets t2 WHERE t2.handled_by = u.id AND t2.status IN ('Assigned','In Progress')) as active_count
    FROM users u
    INNER JOIN tickets t ON t.handled_by = u.id AND t.status = 'Resolved'
    WHERE u.role = 'teknisi'
    GROUP BY u.id, u.nama, u.foto
    ORDER BY resolved_count DESC
    LIMIT 5";
$result_top_teknisi = mysqli_query($conn, $query_top_teknisi);
$top_teknisi = [];
if ($result_top_teknisi) {
    while ($tk = mysqli_fetch_assoc($result_top_teknisi)) {
        $top_teknisi[] = $tk;
    }
}

// Get divisions for filters and bulk assign
$divisions_result = mysqli_query($conn, "SELECT id, nama_divisi FROM divisions ORDER BY nama_divisi");
$divisions_list = [];
while ($dv = mysqli_fetch_assoc($divisions_result)) {
    $divisions_list[] = $dv;
}

// Filter parameters
$filter_division = isset($_GET['division']) ? intval($_GET['division']) : 0;
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build filtered query
$where_clauses = [];
$count_where = [];
if ($filter_division > 0) {
    $where_clauses[] = "t.assigned_division_id = $filter_division";
    $count_where[] = "assigned_division_id = $filter_division";
}
if (!empty($filter_priority) && in_array($filter_priority, ['Low','Medium','High','Urgent'])) {
    $where_clauses[] = "t.priority = '" . mysqli_real_escape_string($conn, $filter_priority) . "'";
    $count_where[] = "priority = '" . mysqli_real_escape_string($conn, $filter_priority) . "'";
}
if (!empty($filter_status) && in_array($filter_status, ['Open','Assigned','In Progress','Resolved'])) {
    $where_clauses[] = "t.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
    $count_where[] = "status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
if (!empty($filter_date_from)) {
    $where_clauses[] = "t.created_at >= '" . mysqli_real_escape_string($conn, $filter_date_from) . " 00:00:00'";
    $count_where[] = "created_at >= '" . mysqli_real_escape_string($conn, $filter_date_from) . " 00:00:00'";
}
if (!empty($filter_date_to)) {
    $where_clauses[] = "t.created_at <= '" . mysqli_real_escape_string($conn, $filter_date_to) . " 23:59:59'";
    $count_where[] = "created_at <= '" . mysqli_real_escape_string($conn, $filter_date_to) . " 23:59:59'";
}

$where_sql = count($where_clauses) > 0 ? ' WHERE ' . implode(' AND ', $where_clauses) : '';
$count_where_sql = count($count_where) > 0 ? ' WHERE ' . implode(' AND ', $count_where) : '';
$has_filters = count($where_clauses) > 0;

// Count filtered tickets
$filtered_total = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tickets" . $count_where_sql))['total'] ?? 0);

$limit = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$total_pages = max(1, (int) ceil($filtered_total / max(1, $limit)));

// list tickets for admin
$query_tickets = "SELECT t.*, u.nama as pelapor, u.foto as pelapor_foto, d.nama_divisi, TIMESTAMPDIFF(SECOND, t.updated_at, NOW()) as seconds_ago
                  FROM tickets t
                  LEFT JOIN users u ON t.user_id = u.id
                  LEFT JOIN divisions d ON t.assigned_division_id = d.id
                  $where_sql
                  ORDER BY t.created_at DESC
                  LIMIT $limit OFFSET $offset";
$result_tickets = mysqli_query($conn, $query_tickets);

// Today's date for greeting
$greeting = 'Hello';

$days_id = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$months_id = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
$today_en = date('l, d F Y');
$today = str_replace(array_keys($days_id), array_values($days_id), $today_en);
$today = str_replace(array_keys($months_id), array_values($months_id), $today);
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Ticketing TVRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#f0f2f5}
        .sidebar{background:#10367D;min-height:100vh;color:#fff;position:fixed;top:0;left:0;width:230px;z-index:100;display:flex;flex-direction:column}
        .sidebar-brand{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:12px}
        .sidebar-brand .brand-icon{width:36px;height:36px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem}
        .sidebar-brand h5{font-size:1rem;font-weight:700;margin:0;letter-spacing:-.3px}
        .sidebar-brand small{display:block;font-size:.7rem;opacity:.5;margin-top:2px}
        .sidebar-nav{padding:16px 12px;flex:1}
        .sidebar .nav-link{color:rgba(255,255,255,.65);padding:10px 16px;font-size:.88rem;font-weight:500;border-radius:10px;margin-bottom:4px;display:flex;align-items:center;gap:12px;transition:all .2s;text-decoration:none}
        .sidebar .nav-link:hover{background:rgba(255,255,255,.1);color:#fff}
        .sidebar .nav-link.active{background:rgba(255,255,255,.18);color:#fff}
        .sidebar .nav-link i{font-size:1.05rem;width:20px;text-align:center}
        .sidebar-footer{padding:20px;border-top:1px solid rgba(255,255,255,.08)}
        .sidebar-footer .user-info{display:flex;align-items:center;gap:10px;margin-bottom:12px}
        .sidebar-footer .user-avatar{width:34px;height:34px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.85rem;overflow:hidden}
        .sidebar-footer .user-avatar img{width:100%;height:100%;object-fit:cover}
        .sidebar-footer .user-name{font-size:.85rem;font-weight:600}
        .sidebar-footer .user-role{font-size:.7rem;opacity:.5}
        .btn-logout{width:100%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;padding:8px;border-radius:8px;font-size:.82rem;font-weight:500;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .2s}
        .btn-logout:hover{background:rgba(255,255,255,.2);color:#fff}
        .main-content{margin-left:230px;padding:28px 32px;min-height:100vh}
        .top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .top-bar-left h1{font-size:1.3rem;font-weight:700;color:#1a1a2e;margin:0}
        .top-bar-left .date-text{color:#10367D;font-size:.82rem;font-weight:600;margin-top:2px}
        .top-bar-right{display:flex;align-items:center;gap:14px}
        .notif-btn{position:relative;background:#fff;border:1px solid #e8eaef;width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#6b7280;font-size:1.1rem;cursor:pointer;transition:all .2s;text-decoration:none}
        .notif-btn:hover{background:#f0f4ff;color:#10367D;border-color:#10367D}
        .notif-btn .badge-dot{position:absolute;top:8px;right:8px;width:8px;height:8px;background:#dc2626;border-radius:50%;border:2px solid #fff}
        .admin-profile{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #e8eaef;border-radius:12px;padding:6px 14px 6px 6px;cursor:pointer;transition:all .2s;text-decoration:none;color:inherit}
        .admin-profile:hover{border-color:#10367D;box-shadow:0 2px 8px rgba(16,54,125,.08)}
        .admin-profile .ap-avatar{width:34px;height:34px;background:#10367D;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:#fff;overflow:hidden}
        .admin-profile .ap-avatar img{width:100%;height:100%;object-fit:cover}
        .admin-profile .ap-name{font-size:.84rem;font-weight:600;color:#1a1a2e}
        .welcome-banner{background:linear-gradient(135deg,#10367D 0%,#1a4fa0 50%,#2563eb 100%);border-radius:20px;padding:36px 40px;color:#fff;display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;position:relative;overflow:hidden;min-height:160px}
        .welcome-banner::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;background:rgba(255,255,255,.05);border-radius:50%}
        .welcome-banner::after{content:'';position:absolute;bottom:-60px;right:80px;width:160px;height:160px;background:rgba(255,255,255,.03);border-radius:50%}
        .welcome-text h2{font-size:1.8rem;font-weight:800;margin:0 0 6px;letter-spacing:-.5px}
        .welcome-text p{font-size:.92rem;opacity:.85;margin:0;font-weight:400}
        .welcome-logo{flex-shrink:0;position:relative;z-index:1}
        .welcome-logo img{height:100px;filter:drop-shadow(0 4px 16px rgba(0,0,0,.2));object-fit:contain}
        .overview-label{font-size:.78rem;font-weight:600;color:#8b8fa3;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
        .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:28px}
        .stat-card{border-radius:16px;padding:22px 20px;display:flex;align-items:center;gap:16px;border:none;transition:all .3s;position:relative;overflow:hidden;cursor:default}
        .stat-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(0,0,0,.1)}
        .stat-card .sc-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0}
        .stat-card .sc-info{flex:1;min-width:0}
        .stat-card .sc-number{font-size:1.8rem;font-weight:800;line-height:1;margin-bottom:2px}
        .stat-card .sc-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.4px;opacity:.7}
        .sc-total{background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#10367D}
        .sc-total .sc-icon{background:rgba(16,54,125,.15);color:#10367D}
        .sc-open{background:linear-gradient(135deg,#fef2f2,#fecaca);color:#dc2626}
        .sc-open .sc-icon{background:rgba(220,38,38,.12);color:#dc2626}
        .sc-progress-card{background:linear-gradient(135deg,#fffbeb,#fde68a);color:#92400e}
        .sc-progress-card .sc-icon{background:rgba(217,119,6,.12);color:#d97706}
        .sc-resolved-card{background:linear-gradient(135deg,#f0fdf4,#bbf7d0);color:#166534}
        .sc-resolved-card .sc-icon{background:rgba(22,163,74,.12);color:#16a34a}        .filters-card{background:#fff;border-radius:16px;border:1px solid #e8eaef;padding:18px 22px;margin-bottom:24px}
        .filters-card .filters-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
        .filters-card .filters-title{font-size:.9rem;font-weight:700;color:#1a1a2e;display:flex;align-items:center;gap:8px}
        .filters-card .filters-title i{color:#10367D}
        .filters-card .btn-clear-filters{background:none;border:none;color:#dc2626;font-size:.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px;padding:4px 8px;border-radius:6px;transition:all .2s;text-decoration:none}
        .filters-card .btn-clear-filters:hover{background:#fef2f2}
        .filters-row{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap}
        .filter-group{display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px}
        .filter-group label{font-size:.72rem;font-weight:600;color:#8b8fa3;text-transform:uppercase;letter-spacing:.3px}
        .filter-group select,.filter-group input[type="date"]{width:100%;border:1px solid #e2e5eb;border-radius:10px;padding:9px 12px;font-size:.84rem;font-family:'Inter',sans-serif;background:#fff;transition:all .2s;color:#374151}
        .filter-group select:focus,.filter-group input[type="date"]:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08);outline:none}
        .btn-filter{background:#10367D;color:#fff;border:none;padding:9px 20px;border-radius:10px;font-size:.84rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;font-family:'Inter',sans-serif;white-space:nowrap}
        .btn-filter:hover{background:#0c2d68;transform:translateY(-1px)}
        .active-filters{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}
        .filter-tag{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#eff6ff;color:#10367D;border-radius:20px;font-size:.75rem;font-weight:600}
        .content-grid{display:grid;grid-template-columns:1fr 1fr 340px;gap:16px;margin-bottom:24px}
        .chart-card{background:#fff;border-radius:16px;border:1px solid #e8eaef;padding:22px;overflow:hidden}
        .chart-card h3{font-size:.92rem;font-weight:700;color:#1a1a2e;margin:0 0 16px;display:flex;align-items:center;gap:8px}
        .chart-card h3 i{color:#10367D;font-size:1rem}
        .chart-container{position:relative;height:220px}
        .top-teknisi-card{background:#fff;border-radius:16px;border:1px solid #e8eaef;padding:22px;overflow:hidden}
        .top-teknisi-card h3{font-size:.92rem;font-weight:700;color:#1a1a2e;margin:0 0 16px;display:flex;align-items:center;gap:8px}
        .top-teknisi-card h3 i{color:#d97706;font-size:1rem}
        .teknisi-list{display:flex;flex-direction:column;gap:10px}
        .teknisi-item{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;transition:all .2s}
        .teknisi-item:hover{background:#f8f9fc}
        .teknisi-rank{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;flex-shrink:0}
        .rank-1{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff}
        .rank-2{background:linear-gradient(135deg,#d1d5db,#9ca3af);color:#fff}
        .rank-3{background:linear-gradient(135deg,#f97316,#ea580c);color:#fff}
        .rank-other{background:#f3f4f6;color:#6b7280}
        .teknisi-avatar{width:36px;height:36px;border-radius:10px;overflow:hidden;flex-shrink:0;background:linear-gradient(135deg,#10367D,#1a4fa0);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem}
        .teknisi-avatar img{width:100%;height:100%;object-fit:cover}
        .teknisi-info{flex:1;min-width:0}
        .teknisi-name{font-size:.84rem;font-weight:600;color:#1a1a2e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .teknisi-stats{font-size:.72rem;color:#8b8fa3;display:flex;align-items:center;gap:8px}
        .teknisi-resolved-count{font-weight:700;color:#16a34a;font-size:.82rem;white-space:nowrap}
        .empty-teknisi{text-align:center;padding:30px 10px;color:#9ca3af;font-size:.84rem}
        .empty-teknisi i{font-size:2rem;display:block;margin-bottom:8px;color:#d1d5db}
        .table-card{background:#fff;border-radius:16px;border:1px solid #e8eaef;overflow:hidden}
        .table-card-header{padding:18px 22px;border-bottom:1px solid #eef0f5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
        .table-card-header h2{font-size:1rem;font-weight:700;color:#1a1a2e;margin:0;display:flex;align-items:center;gap:8px}
        .table-card-header h2 i{color:#10367D}
        .table-header-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .search-box{position:relative}
        .search-box input{padding:9px 14px 9px 36px;border:1px solid #e5e7eb;border-radius:10px;font-size:.84rem;width:260px;font-family:'Inter',sans-serif;transition:all .2s}
        .search-box input:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08);outline:none}
        .search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.85rem}
        .btn-toggle-checkbox{background:#10367D;color:#fff;border:none;padding:9px 16px;border-radius:10px;font-size:.82rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;font-family:'Inter',sans-serif}
        .btn-toggle-checkbox:hover{background:#0c2d68}
        .table-wrap{overflow-x:auto}
        .ticket-table{width:100%;border-collapse:collapse;min-width:980px}
        .ticket-table thead th{padding:12px 16px;font-size:.72rem;font-weight:600;color:#8b8fa3;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #eef0f5;background:#fafbfd;white-space:nowrap;text-align:left}
        .ticket-table tbody td{padding:14px 16px;border-bottom:1px solid #f3f4f8;vertical-align:middle;font-size:.84rem;color:#374151}
        .ticket-row{cursor:pointer;transition:all .2s ease;border-left:3px solid transparent;outline:none}
        .ticket-row:hover{background:#f0f4ff;border-left-color:#10367D}
        .ticket-row:focus-visible{background:#eef1fb;border-left-color:#10367D;box-shadow:inset 0 0 0 2px rgba(16,54,125,.15)}
        .ticket-row:active{background:#e4eaf8;transform:scale(.998)}
        .ticket-row:last-child td{border-bottom:none}
        .t-title{font-weight:600;color:#1a1a2e;font-size:.85rem;display:block;margin-bottom:1px}
        .t-id{font-size:.73rem;color:#8b8fa3;font-weight:500}
        .status-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:.73rem;font-weight:600;white-space:nowrap}
        .sp-open{background:#fef2f2;color:#dc2626}.sp-assigned{background:#eff6ff;color:#2563eb}.sp-progress{background:#fffbeb;color:#d97706}.sp-resolved{background:#f0fdf4;color:#16a34a}
        .status-dot{width:6px;height:6px;border-radius:50%;display:inline-block}
        .sd-open{background:#dc2626}.sd-assigned{background:#2563eb}.sd-progress{background:#d97706}.sd-resolved{background:#16a34a}
        .priority-pill{font-size:.78rem;font-weight:600;display:inline-flex;align-items:center;gap:5px}
        .pp-low{color:#9ca3af}.pp-medium{color:#3b82f6}.pp-high{color:#f59e0b}.pp-urgent{color:#ef4444}
        .pp-dot{width:6px;height:6px;border-radius:2px;display:inline-block}
        .pp-low .pp-dot{background:#9ca3af}.pp-medium .pp-dot{background:#3b82f6}.pp-high .pp-dot{background:#f59e0b}.pp-urgent .pp-dot{background:#ef4444}
        .date-cell{font-size:.78rem;color:#8b8fa3;white-space:nowrap}
        .btn-action{padding:5px 14px;border-radius:8px;font-size:.76rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all .2s;white-space:nowrap}
        .btn-detail{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}.btn-detail:hover{background:#e5e7eb;color:#1f2937}
        .btn-print{background:#eff6ff;color:#2563eb;border:1px solid #dbeafe}.btn-print:hover{background:#dbeafe;color:#1d4ed8}
        .cb-cell{width:40px;text-align:center}
        .cb-cell.hidden{display:none!important}
        .cb-cell input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#10367D}
        .empty-state{text-align:center;padding:56px 20px}
        .empty-state i{font-size:2.8rem;color:#d1d5db;display:block;margin-bottom:10px}
        .empty-state p{color:#9ca3af;font-size:.88rem;margin:0}
        .pagination-bar{display:flex;justify-content:center;align-items:center;gap:6px;padding:16px}
        .pagination-bar a,.pagination-bar span{padding:7px 13px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#374151;font-size:.84rem;transition:all .2s}
        .pagination-bar a:hover{border-color:#10367D;color:#10367D;background:#f0f4ff}
        .pagination-bar .active-page{background:#10367D;color:#fff;border-color:#10367D;font-weight:600}
        .bulk-bar{display:none;align-items:center;gap:12px;padding:12px 22px;background:linear-gradient(135deg,#10367D,#1a4fa0);border-radius:12px;margin-bottom:16px;color:#fff;flex-wrap:wrap}
        .bulk-bar.show{display:flex}
        .bulk-bar .bulk-count{font-size:.88rem;font-weight:600;min-width:100px}
        .bulk-bar select,.bulk-bar button{font-family:'Inter',sans-serif}
        .bulk-bar select{padding:7px 12px;border:1px solid rgba(255,255,255,.3);border-radius:8px;font-size:.82rem;background:rgba(255,255,255,.15);color:#fff;cursor:pointer}
        .bulk-bar select option{color:#1a1a2e;background:#fff}
        .bulk-bar .btn-bulk{padding:7px 16px;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:5px}
        .btn-bulk-delete{background:#dc2626;color:#fff}.btn-bulk-delete:hover{background:#b91c1c}
        .btn-bulk-cancel{background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3)!important}.btn-bulk-cancel:hover{background:rgba(255,255,255,.3)}
        .toast-container{position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:360px}
        .toast-notif{background:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 8px 30px rgba(0,0,0,.12);border-left:4px solid #10367D;display:flex;align-items:flex-start;gap:12px;animation:slideIn .3s ease;font-size:.84rem;color:#374151}
        .toast-notif .toast-icon{font-size:1.2rem;margin-top:1px;flex-shrink:0}
        .toast-notif .toast-body{flex:1}
        .toast-notif .toast-title{font-weight:600;font-size:.85rem;margin-bottom:2px}
        .toast-notif .toast-msg{font-size:.78rem;color:#8b8fa3}
        .toast-notif .toast-close{background:none;border:none;color:#8b8fa3;cursor:pointer;font-size:1rem;padding:0;margin-left:8px}
        @keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
        .hamburger-menu{display:none;position:fixed;top:20px;left:20px;z-index:1100;background:#fff;border:none;width:44px;height:44px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);cursor:pointer;padding:10px;flex-direction:column;justify-content:space-around;transition:all .3s}
        .hamburger-menu span{display:block;width:100%;height:3px;background:#10367D;border-radius:2px;transition:all .3s}
        .hamburger-menu.active span:nth-child(1){transform:rotate(45deg) translate(8px,8px)}
        .hamburger-menu.active span:nth-child(2){opacity:0}
        .hamburger-menu.active span:nth-child(3){transform:rotate(-45deg) translate(7px,-7px)}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:998;opacity:0;transition:opacity .3s}
        .sidebar-overlay.active{display:block;opacity:1}        @media(max-width:1200px){.content-grid{grid-template-columns:1fr 1fr}.top-teknisi-card{grid-column:1/-1}.teknisi-list{flex-direction:row;flex-wrap:wrap;gap:8px}.teknisi-item{flex:1;min-width:180px}}
        @media(max-width:992px){.stats-row{grid-template-columns:repeat(2,1fr)}.content-grid{grid-template-columns:1fr}.filters-row{flex-direction:column}.filter-group{min-width:100%}}
        @media(max-width:768px){
            .hamburger-menu{display:flex}
            .sidebar{position:fixed!important;top:0;left:-100%!important;width:260px!important;height:var(--sidebar-h,100vh);z-index:999;transition:left .3s;overflow-y:auto;-webkit-overflow-scrolling:touch}
            .sidebar.active{left:0!important}.sidebar-nav{flex:none!important}
            .main-content{margin-left:0;padding:56px 16px 20px}
            .top-bar{flex-direction:column;gap:6px;align-items:stretch;margin-bottom:16px}
            .top-bar-left{margin-top:-8px}
            .top-bar-left h1{margin:0;font-size:1.2rem}
            .top-bar-right{justify-content:flex-end;gap:10px}
            .welcome-banner{flex-direction:column;text-align:center;padding:20px 24px;min-height:auto}
            .welcome-text h2{font-size:1.4rem}
            .welcome-text p{font-size:.84rem}
            .welcome-logo{margin-top:16px}
            .welcome-logo img{height:70px}
            .stats-row{grid-template-columns:1fr 1fr;gap:10px}
            .stat-card{padding:16px 14px}
            .stat-card .sc-number{font-size:1.4rem}
            .stat-card .sc-icon{width:40px;height:40px;font-size:1.05rem}
            .filters-card{padding:14px 16px}
            .content-grid{gap:12px}
            .chart-card{padding:16px}
            .chart-container{height:180px}
            .top-teknisi-card{padding:16px}
            .teknisi-list{flex-direction:column}
            .teknisi-item{min-width:auto}
            .table-card{border-radius:12px}
            .table-card-header{flex-direction:column;align-items:stretch;padding:14px 16px;gap:10px}
            .table-header-actions{flex-direction:column}
            .search-box{display:none}
            .table-wrap{overflow-x:visible;overflow-y:visible}
            .ticket-table{min-width:0!important}
            .ticket-table thead{display:none}
            .ticket-table tbody{display:flex;flex-direction:column;gap:10px;padding:12px}
            .ticket-row{display:flex!important;flex-wrap:wrap;align-items:center;gap:6px 10px;padding:14px!important;border-radius:12px!important;border:1px solid #e8eaef!important;background:#fff;border-left:3px solid #10367D!important}
            .ticket-row:hover{background:#f8faff!important;box-shadow:0 2px 8px rgba(0,0,0,.04)}
            .ticket-row td{padding:0!important;border:none!important}
            .ticket-row td.cb-cell{position:absolute;top:12px;right:12px;width:auto}
            .ticket-row td:nth-child(1){flex:0 0 100%}
            .ticket-row td:nth-child(2){flex:0 0 100%;margin-bottom:4px}
            .ticket-row td:nth-child(3){order:3}
            .ticket-row td:nth-child(4){order:4}
            .ticket-row td:nth-child(5){order:5}
            .ticket-row td:nth-child(6){order:6;margin-left:auto}
            .ticket-row td:nth-child(7){display:none!important}
            .ticket-row td:nth-child(8){order:7;flex:0 0 100%;display:flex!important;gap:6px;margin-top:8px;padding-top:10px!important;border-top:1px solid #f0f2f5!important}
            .ticket-row td:nth-child(8) .btn-action{flex:1;justify-content:center;padding:8px 12px;font-size:.78rem}
            .bulk-bar{border-radius:10px;padding:10px 14px;gap:8px}
            .pagination-bar{padding:12px 8px;gap:4px;flex-wrap:wrap}
            .pagination-bar a,.pagination-bar span{padding:6px 10px;font-size:.8rem}
            .toast-container{left:12px;right:12px;max-width:none}
        }
        @media(max-width:576px){
            .stats-row{grid-template-columns:1fr}
            .main-content{padding:70px 12px 16px}
            .stat-card .sc-number{font-size:1.3rem}
            .welcome-banner{padding:16px 18px}
            .welcome-text h2{font-size:1.2rem}
            .welcome-logo{margin-top:12px}
            .welcome-logo img{height:56px}
            .ticket-row{padding:12px!important;position:relative}
            .t-title{font-size:.82rem}
            .status-pill{font-size:.7rem;padding:3px 8px}
            .chart-container{height:160px}
        }
    </style>
</head>
<body>
    <button class="hamburger-menu" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link active position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($open_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$open_count?></span><?php endif; ?></a>
            <a class="nav-link" href="kelola_user.php"><i class="bi bi-people"></i> Kelola User</a>
            <a class="nav-link" href="activity_logs.php"><i class="bi bi-clock-history"></i> Activity Logs</a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'],0,1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Administrator</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>

    <div class="toast-container" id="toastContainer"></div>

    <div class="main-content">
        <div class="top-bar">
            <div class="top-bar-left">
                <h1>Dashboard</h1>
                <div class="date-text"><?= $today ?></div>
            </div>
            <div class="top-bar-right">
                <a href="dashboard.php" class="notif-btn" title="Refresh"><i class="bi bi-arrow-clockwise"></i></a>
                <a href="profile.php" class="admin-profile" title="Profil">
                    <div class="ap-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'],0,1)) ?><?php endif; ?></div>
                    <span class="ap-name"><?= htmlspecialchars($user['nama']) ?></span>
                </a>
            </div>
        </div>
        <div class="welcome-banner">
            <div class="welcome-text">
                <h2><?= $greeting ?>, <?= htmlspecialchars(explode(' ', $user['nama'])[0]) ?>!</h2>
                <p>Pantau dan kelola semua tiket support di sini. Tetap produktif!</p>
            </div>
            <div class="welcome-logo"><img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo"></div>
        </div>

        <div class="overview-label">Overview</div>
        <div class="stats-row">
            <div class="stat-card sc-total">
                <div class="sc-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
                <div class="sc-info"><div class="sc-number" id="statTotal"><?= $total_tickets ?></div><div class="sc-label">Total Tiket</div></div>
            </div>
            <div class="stat-card sc-open">
                <div class="sc-icon"><i class="bi bi-exclamation-circle-fill"></i></div>
                <div class="sc-info"><div class="sc-number" id="statOpen"><?= $open_tickets ?></div><div class="sc-label">Open</div></div>
            </div>
            <div class="stat-card sc-progress-card">
                <div class="sc-icon"><i class="bi bi-arrow-repeat"></i></div>
                <div class="sc-info"><div class="sc-number" id="statProgress"><?= $progress_tickets ?></div><div class="sc-label">Diproses</div></div>
            </div>
            <div class="stat-card sc-resolved-card">
                <div class="sc-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="sc-info"><div class="sc-number" id="statResolved"><?= $resolved_tickets ?></div><div class="sc-label">Resolved (<?= $resolution_rate ?>%)</div></div>
            </div>
        </div>



        <div class="content-grid">
            <div class="chart-card">
                <h3><i class="bi bi-pie-chart-fill"></i> Distribusi Status</h3>
                <div class="chart-container"><canvas id="statusChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3><i class="bi bi-bar-chart-fill"></i> Tiket per Bulan</h3>
                <div class="chart-container"><canvas id="monthlyChart"></canvas></div>
            </div>
            <div class="top-teknisi-card">
                <h3><i class="bi bi-trophy-fill"></i> Top Teknisi</h3>
                <?php if(count($top_teknisi) > 0): ?>
                <div class="teknisi-list">
                    <?php foreach($top_teknisi as $idx => $tk):
                        $rank = $idx + 1;
                        $rankClass = $rank <= 3 ? "rank-$rank" : "rank-other";
                        $tkInitial = strtoupper(substr($tk['nama'], 0, 1));
                    ?>
                    <div class="teknisi-item">
                        <span class="teknisi-rank <?= $rankClass ?>"><?= $rank ?></span>
                        <div class="teknisi-avatar">
                            <?php if(!empty($tk['foto'])): ?><img src="../uploads/profile_photos/<?= htmlspecialchars($tk['foto']) ?>" alt=""><?php else: ?><?= $tkInitial ?><?php endif; ?>
                        </div>
                        <div class="teknisi-info">
                            <div class="teknisi-name"><?= htmlspecialchars($tk['nama']) ?></div>
                            <div class="teknisi-stats">
                                <span><i class="bi bi-check-circle-fill" style="color:#16a34a"></i> <?= $tk['resolved_count'] ?> resolved</span>
                                <?php if($tk['active_count'] > 0): ?><span><i class="bi bi-arrow-repeat" style="color:#d97706"></i> <?= $tk['active_count'] ?> aktif</span><?php endif; ?>
                            </div>
                        </div>
                        <span class="teknisi-resolved-count"><?= $tk['resolved_count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-teknisi"><i class="bi bi-trophy"></i><p>Belum ada data teknisi.</p></div>
                <?php endif; ?>
            </div>
        </div>


        <div class="filters-card">
            <div class="filters-header">
                <div class="filters-title"><i class="bi bi-funnel-fill"></i> Quick Filters</div>
                <?php if($has_filters): ?><a href="dashboard.php" class="btn-clear-filters"><i class="bi bi-x-circle"></i> Reset</a><?php endif; ?>
            </div>
            <form method="GET" action="dashboard.php" id="quickFilterForm">
                <input type="hidden" name="page" value="1">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Divisi</label>
                        <select name="division" onchange="document.getElementById('quickFilterForm').submit()">
                            <option value="">Semua Divisi</option>
                            <?php foreach($divisions_list as $dv): ?>
                            <option value="<?= $dv['id'] ?>" <?= $filter_division==$dv['id']?'selected':'' ?>><?= htmlspecialchars($dv['nama_divisi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Prioritas</label>
                        <select name="priority" onchange="document.getElementById('quickFilterForm').submit()">
                            <option value="">Semua</option>
                            <option value="Low" <?= $filter_priority=='Low'?'selected':'' ?>>Low</option>
                            <option value="Medium" <?= $filter_priority=='Medium'?'selected':'' ?>>Medium</option>
                            <option value="High" <?= $filter_priority=='High'?'selected':'' ?>>High</option>
                            <option value="Urgent" <?= $filter_priority=='Urgent'?'selected':'' ?>>Urgent</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" onchange="document.getElementById('quickFilterForm').submit()">
                            <option value="">Semua</option>
                            <option value="Open" <?= $filter_status=='Open'?'selected':'' ?>>Open</option>
                            <option value="Assigned" <?= $filter_status=='Assigned'?'selected':'' ?>>Assigned</option>
                            <option value="In Progress" <?= $filter_status=='In Progress'?'selected':'' ?>>In Progress</option>
                            <option value="Resolved" <?= $filter_status=='Resolved'?'selected':'' ?>>Resolved</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Dari Tanggal</label>
                        <input type="date" name="date_from" onchange="document.getElementById('quickFilterForm').submit()" value="<?= htmlspecialchars($filter_date_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="date_to" onchange="document.getElementById('quickFilterForm').submit()" value="<?= htmlspecialchars($filter_date_to) ?>">
                    </div>
                    <button type="submit" class="btn-filter" style="display:none"><i class="bi bi-search"></i> Filter</button>
                </div>
            </form>
            <?php if($has_filters): ?>
            <div class="active-filters">
                <?php if($filter_division): foreach($divisions_list as $dv): if($dv['id']==$filter_division): ?><span class="filter-tag"><i class="bi bi-building"></i> <?= htmlspecialchars($dv['nama_divisi']) ?></span><?php endif; endforeach; endif; ?>
                <?php if($filter_priority): ?><span class="filter-tag"><i class="bi bi-flag"></i> <?= $filter_priority ?></span><?php endif; ?>
                <?php if($filter_status): ?><span class="filter-tag"><i class="bi bi-circle"></i> <?= $filter_status ?></span><?php endif; ?>
                <?php if($filter_date_from): ?><span class="filter-tag"><i class="bi bi-calendar"></i> Dari: <?= $filter_date_from ?></span><?php endif; ?>
                <?php if($filter_date_to): ?><span class="filter-tag"><i class="bi bi-calendar"></i> Sampai: <?= $filter_date_to ?></span><?php endif; ?>
                <span class="filter-tag" style="background:#f0fdf4;color:#16a34a"><i class="bi bi-list-check"></i> <?= $filtered_total ?> tiket</span>
            </div>
            <?php endif; ?>
        </div>
        <form id="bulkForm" method="POST" action="proses_bulk.php">
        <div class="bulk-bar" id="bulkBar">
            <span class="bulk-count"><span id="bulkCount">0</span> tiket dipilih</span>
            <input type="hidden" name="bulk_action" id="bulkAction" value="delete">
            <button type="button" class="btn-bulk btn-bulk-delete" onclick="submitBulkDelete()"><i class="bi bi-trash"></i> Hapus Tiket</button>
            <button type="button" class="btn-bulk btn-bulk-cancel" onclick="clearBulk()"><i class="bi bi-x-lg"></i> Batal</button>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <h2><i class="bi bi-list-ul"></i> <?= $has_filters ? 'Hasil Filter' : 'Semua Tiket' ?></h2>
                <div class="table-header-actions">
                    <button type="button" class="btn-toggle-checkbox" id="btnToggleCheckbox" onclick="toggleCheckboxes()"><i class="bi bi-check-square"></i> Pilih Cepat</button>
                    <div class="search-box"><i class="bi bi-search"></i><input type="text" id="searchTicket" placeholder="Cari tiket..." onkeyup="filterTickets()"></div>
                    <span class="ticket-count"><?= $has_filters ? $filtered_total : $total_tickets ?> tiket &middot; Hal <?= $page ?>/<?= max(1,$total_pages) ?></span>
                </div>
            </div>
            <?php if(mysqli_num_rows($result_tickets) > 0): ?>
            <div class="table-wrap">
            <table class="ticket-table">
                <thead><tr><th class="cb-cell"><input type="checkbox" id="selectAll" title="Pilih semua"></th><th>Judul</th><th>Status</th><th>Prioritas</th><th>Divisi</th><th>Updated</th><th>Created</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php while($t = mysqli_fetch_assoc($result_tickets)):
                    $sp = 'sp-open'; $sd = 'sd-open';
                    if($t['status']=='Assigned'){$sp='sp-assigned';$sd='sd-assigned';}
                    elseif($t['status']=='In Progress'){$sp='sp-progress';$sd='sd-progress';}
                    elseif($t['status']=='Resolved'){$sp='sp-resolved';$sd='sd-resolved';}
                    $pp = 'pp-low';
                    if(($t['priority']??'')=='Medium') $pp='pp-medium';
                    elseif(($t['priority']??'')=='High') $pp='pp-high';
                    elseif(($t['priority']??'')=='Urgent') $pp='pp-urgent';
                    $sec = $t['seconds_ago'];
                    if($sec < 60) $updated = 'Baru saja';
                    elseif($sec < 3600) $updated = floor($sec/60) . ' mnt lalu';
                    elseif($sec < 86400) $updated = floor($sec/3600) . ' jam lalu';
                    else $updated = floor($sec/86400) . ' hari lalu';
                    $created = date('d M Y', strtotime($t['created_at']));
                    $url = ($t['status']=='Open' || $t['status']=='Assigned') ? 'assign.php?id='.$t['id'] : '../detail.php?id='.$t['id'];
                ?>
                <tr class="ticket-row" tabindex="0" onclick="handleRowClick(event,this,'<?= $url ?>')" onkeydown="handleRowKeydown(event,this,'<?= $url ?>')" role="link" aria-label="Tiket #<?= $t['id'] ?>">
                    <td class="cb-cell" onclick="event.stopPropagation()"><input type="checkbox" name="ticket_ids[]" value="<?= $t['id'] ?>" class="ticket-cb" onchange="updateBulkBar()"></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <?php $has_foto = !empty($t['pelapor_foto']); if($has_foto): ?>
                            <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;flex-shrink:0;border:2px solid #e5e7eb"><img src="../uploads/profile_photos/<?= htmlspecialchars($t['pelapor_foto']) ?>" alt="" style="width:100%;height:100%;object-fit:cover"></div>
                            <?php else: $ini = !empty($t['pelapor']) ? strtoupper(substr($t['pelapor'],0,1)) : '?'; ?>
                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#10367D,#1a4fa0);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.85rem;flex-shrink:0"><?= $ini ?></div>
                            <?php endif; ?>
                            <div style="flex:1;min-width:0">
                                <div class="t-title"><?= htmlspecialchars($t['judul']) ?></div>
                                <div style="display:flex;align-items:center;gap:8px;margin-top:2px">
                                    <span class="t-id">#<?= $t['id'] ?></span>
                                    <span style="font-size:.75rem;color:#8b8fa3;display:inline-flex;align-items:center;gap:4px"><i class="bi bi-person" style="font-size:.7rem"></i><?= htmlspecialchars($t['pelapor'] ?? '-') ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><span class="status-pill <?= $sp ?>"><span class="status-dot <?= $sd ?>"></span> <?= $t['status'] ?></span></td>
                    <td><span class="priority-pill <?= $pp ?>"><span class="pp-dot"></span> <?= $t['priority'] ?? '-' ?></span></td>
                    <td><span class="date-cell"><?= htmlspecialchars($t['nama_divisi'] ?? '-') ?></span></td>
                    <td><span class="date-cell"><?= $updated ?></span></td>
                    <td><span class="date-cell"><?= $created ?></span></td>
                    <td>
                        <?php if($t['status']=='Open' || $t['status']=='Assigned'): ?>
                        <a href="assign.php?id=<?= $t['id'] ?>" class="btn-action btn-detail" onclick="event.stopPropagation()"><i class="bi bi-person-plus"></i> Assign</a>
                        <?php else: ?>
                        <a href="../detail.php?id=<?= $t['id'] ?>" class="btn-action btn-detail" onclick="event.stopPropagation()"><i class="bi bi-eye"></i> Detail</a>
                        <a href="../cetak.php?id=<?= $t['id'] ?>" class="btn-action btn-print" onclick="event.stopPropagation()" target="_blank"><i class="bi bi-printer"></i> Cetak</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>            <?php
            $fp = [];
            if($filter_division) $fp[] = "division=$filter_division";
            if($filter_priority) $fp[] = "priority=".urlencode($filter_priority);
            if($filter_status) $fp[] = "status=".urlencode($filter_status);
            if($filter_date_from) $fp[] = "date_from=".urlencode($filter_date_from);
            if($filter_date_to) $fp[] = "date_to=".urlencode($filter_date_to);
            $fq = count($fp) > 0 ? '&'.implode('&',$fp) : '';
            ?>
            <?php if($total_pages > 1): ?>
            <div class="pagination-bar">
                <?php if($page > 1): ?><a href="?page=<?= $page-1 ?><?= $fq ?>">&laquo;</a><?php endif; ?>
                <?php for($i=1;$i<=$total_pages;$i++): ?>
                    <?php if($i==$page): ?><span class="active-page"><?= $i ?></span>
                    <?php else: ?><a href="?page=<?= $i ?><?= $fq ?>"><?= $i ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if($page < $total_pages): ?><a href="?page=<?= $page+1 ?><?= $fq ?>">&raquo;</a><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p><?= $has_filters ? 'Tidak ada tiket yang cocok dengan filter.' : 'Belum ada tiket.' ?></p>
                <?php if($has_filters): ?><a href="dashboard.php" style="color:#10367D;font-size:.84rem;font-weight:600;margin-top:8px;display:inline-block">Reset Filter</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function filterTickets(){const i=document.getElementById('searchTicket');const f=i.value.toUpperCase();const t=document.querySelector('.ticket-table');if(!t)return;const r=t.getElementsByClassName('ticket-row');const isMobile=window.innerWidth<=768;for(let x=0;x<r.length;x++){const match=(r[x].textContent||r[x].innerText).toUpperCase().indexOf(f)>-1;if(match){r[x].style.display=isMobile?'flex':'';}else{r[x].style.display='none';}}}

    let checkboxesVisible=false;
    function toggleCheckboxes(){checkboxesVisible=!checkboxesVisible;const c=document.querySelectorAll('.cb-cell');const b=document.getElementById('btnToggleCheckbox');c.forEach(cell=>{checkboxesVisible?cell.classList.remove('hidden'):cell.classList.add('hidden')});if(checkboxesVisible){b.innerHTML='<i class="bi bi-x-square"></i> Sembunyikan';b.style.background='#dc2626';}else{b.innerHTML='<i class="bi bi-check-square"></i> Pilih Cepat';b.style.background='#10367D';clearBulk();}}
    document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.cb-cell').forEach(c=>c.classList.add('hidden'));});

    const selectAll=document.getElementById('selectAll');
    if(selectAll){selectAll.addEventListener('change',function(){document.querySelectorAll('.ticket-cb').forEach(cb=>{cb.checked=this.checked});updateBulkBar();});}
    function updateBulkBar(){const c=document.querySelectorAll('.ticket-cb:checked');const b=document.getElementById('bulkBar');const n=document.getElementById('bulkCount');if(c.length>0){b.classList.add('show');n.textContent=c.length;}else{b.classList.remove('show');}}
    function submitBulkDelete(){const c=document.querySelectorAll('.ticket-cb:checked');if(c.length===0)return;Swal.fire({title:'Hapus Tiket?',html:'Anda yakin ingin menghapus <strong>'+c.length+'</strong> tiket?<br><small>Tindakan ini tidak dapat dibatalkan.</small>',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#6b7280',confirmButtonText:'Ya, Hapus',cancelButtonText:'Batal'}).then(r=>{if(r.isConfirmed){document.getElementById('bulkForm').submit();}});}
    function clearBulk(){const checkboxes=document.querySelectorAll('.ticket-cb');checkboxes.forEach(cb=>cb.checked=false);const selectAll=document.getElementById('selectAll');if(selectAll)selectAll.checked=false;updateBulkBar();}
    
    function isBulkSelectMode(){return checkboxesVisible;}
    function handleRowClick(event,row,url){if(event.target.closest('a,button,input,label,select,textarea'))return;if(isBulkSelectMode()){const cb=row.querySelector('.ticket-cb');if(cb){cb.checked=!cb.checked;updateBulkBar();}return;}window.location=url;}
    function handleRowKeydown(event,row,url){if(event.key!=='Enter'&&event.key!==' ')return;event.preventDefault();if(isBulkSelectMode()){const cb=row.querySelector('.ticket-cb');if(cb){cb.checked=!cb.checked;updateBulkBar();}return;}window.location=url;}


    fetch('../api/chart_data.php').then(r=>r.json()).then(data=>{
        if(data.status_distribution){new Chart(document.getElementById('statusChart'),{type:'doughnut',data:{labels:data.status_distribution.labels,datasets:[{data:data.status_distribution.data,backgroundColor:data.status_distribution.colors,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{position:'bottom',labels:{padding:14,usePointStyle:true,pointStyle:'circle',font:{size:11,family:'Inter'}}}}}});}
        if(data.monthly_tickets){new Chart(document.getElementById('monthlyChart'),{type:'bar',data:{labels:data.monthly_tickets.labels,datasets:[{label:'Tiket',data:data.monthly_tickets.data,backgroundColor:'rgba(16,54,125,0.75)',borderRadius:8,borderSkipped:false,barThickness:28}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{stepSize:1,font:{size:11,family:'Inter'}},grid:{color:'#f0f2f5'}},x:{ticks:{font:{size:11,family:'Inter'}},grid:{display:false}}},plugins:{legend:{display:false}}}});}
    }).catch(e=>console.warn('Chart error:',e));
    let lastPollTime=new Date().toISOString().slice(0,19).replace('T',' ');
    let prevCounts={total:<?=$total_tickets?>,open:<?=$open_tickets?>,progress:<?=$progress_tickets?>,resolved:<?=$resolved_tickets?>};
    function pollTickets(){fetch('../api/check_tickets.php?since='+encodeURIComponent(lastPollTime)).then(r=>r.json()).then(data=>{lastPollTime=data.timestamp;const c=data.counts;if(c.total!==undefined){animateNumber('statTotal',prevCounts.total,c.total);animateNumber('statOpen',prevCounts.open,c.open);animateNumber('statProgress',prevCounts.progress,c.progress);animateNumber('statResolved',prevCounts.resolved,c.resolved);}if(data.new_items&&data.new_items.length>0&&(c.total!==prevCounts.total||c.open!==prevCounts.open)){data.new_items.forEach(item=>{showToast(item.status==='Open'?'bi-plus-circle text-primary':'bi-arrow-repeat text-warning','Tiket #'+item.id,item.judul+'  '+item.status);});}prevCounts={total:c.total||0,open:c.open||0,progress:c.progress||0,resolved:c.resolved||0};}).catch(()=>{});}
    setInterval(pollTickets,15000);
    function animateNumber(id,from,to){const el=document.getElementById(id);if(!el||from===to)return;el.textContent=to;el.style.transition='transform .3s';el.style.transform='scale(1.15)';setTimeout(()=>{el.style.transform='scale(1)';},300);}
    function showToast(ic,title,msg){const c=document.getElementById('toastContainer');const t=document.createElement('div');t.className='toast-notif';t.innerHTML='<i class="bi '+ic+' toast-icon"></i><div class="toast-body"><div class="toast-title">'+title+'</div><div class="toast-msg">'+msg+'</div></div><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';c.appendChild(t);setTimeout(()=>{if(t.parentElement)t.remove();},6000);}

    const urlParams=new URLSearchParams(window.location.search);
    if(urlParams.get('success')==='bulk_delete'){Swal.fire({icon:'success',title:'Berhasil',text:'Berhasil menghapus '+(urlParams.get('count')||'')+' tiket.',timer:3000,showConfirmButton:false});}
    else if(urlParams.get('success')==='assign'){Swal.fire({icon:'success',title:'Berhasil',text:'Tiket berhasil di-assign.',timer:2500,showConfirmButton:false});}

    // AJAX Quick Filter
    (function(){
        let filterTimeout;
        const filters=['filterDivision','filterPriority','filterStatus','filterDateFrom','filterDateTo'];
        filters.forEach(id=>{
            const el=document.getElementById(id);
            if(el){
                el.addEventListener('change',function(){
                    clearTimeout(filterTimeout);
                    filterTimeout=setTimeout(applyFiltersAjax,300);
                });
            }
        });
        
        function applyFiltersAjax(){
            const params=new URLSearchParams();
            params.set('page','1');
            const div=document.getElementById('filterDivision').value;
            const pri=document.getElementById('filterPriority').value;
            const sta=document.getElementById('filterStatus').value;
            const df=document.getElementById('filterDateFrom').value;
            const dt=document.getElementById('filterDateTo').value;
            if(div)params.set('division',div);
            if(pri)params.set('priority',pri);
            if(sta)params.set('status',sta);
            if(df)params.set('date_from',df);
            if(dt)params.set('date_to',dt);
            
            const url='dashboard.php?'+params.toString();
            
            fetch(url)
                .then(r=>r.text())
                .then(html=>{
                    const parser=new DOMParser();
                    const doc=parser.parseFromString(html,'text/html');
                    
                    // Update stats cards
                    const stats=['statTotal','statOpen','statProgress','statResolved'];
                    stats.forEach(id=>{
                        const newEl=doc.getElementById(id);
                        const oldEl=document.getElementById(id);
                        if(newEl && oldEl){
                            const newVal=newEl.textContent;
                            const oldVal=oldEl.textContent;
                            if(newVal!==oldVal){
                                oldEl.textContent=newVal;
                                oldEl.style.transition='transform .3s';
                                oldEl.style.transform='scale(1.15)';
                                setTimeout(()=>{oldEl.style.transform='scale(1)';},300);
                            }
                        }
                    });
                    
                    // Update active filters display
                    const newFilters=doc.querySelector('.active-filters');
                    const oldFilters=document.querySelector('.active-filters');
                    if(newFilters && oldFilters){
                        oldFilters.innerHTML=newFilters.innerHTML;
                    }else if(newFilters && !oldFilters){
                        const filtersCard=document.querySelector('.filters-card form');
                        if(filtersCard){
                            const div=document.createElement('div');
                            div.className='active-filters';
                            div.innerHTML=newFilters.innerHTML;
                            filtersCard.parentNode.insertBefore(div,filtersCard.nextSibling);
                        }
                    }else if(!newFilters && oldFilters){
                        oldFilters.remove();
                    }
                    
                    // Update Reset button
                    const newReset=doc.querySelector('.btn-clear-filters');
                    const oldReset=document.querySelector('.btn-clear-filters');
                    if(newReset && !oldReset){
                        const header=document.querySelector('.filters-header');
                        if(header){
                            header.innerHTML=doc.querySelector('.filters-header').innerHTML;
                        }
                    }else if(!newReset && oldReset){
                        oldReset.remove();
                    }
                    
                    // Update table header title
                    const newTitle=doc.querySelector('.table-card-header h2');
                    const oldTitle=document.querySelector('.table-card-header h2');
                    if(newTitle && oldTitle){
                        oldTitle.innerHTML=newTitle.innerHTML;
                    }
                    
                    // Update ticket count
                    const newCount=doc.querySelector('.ticket-count');
                    const oldCount=document.querySelector('.ticket-count');
                    if(newCount && oldCount){
                        oldCount.innerHTML=newCount.innerHTML;
                    }
                    
                    // Update table content
                    const newTable=doc.querySelector('.table-card .table-wrap');
                    const oldTable=document.querySelector('.table-card .table-wrap');
                    const newEmpty=doc.querySelector('.table-card .empty-state');
                    const oldEmpty=document.querySelector('.table-card .empty-state');
                    const newPagination=doc.querySelector('.pagination-bar');
                    const oldPagination=document.querySelector('.pagination-bar');
                    
                    if(newTable){
                        if(oldTable){
                            oldTable.innerHTML=newTable.innerHTML;
                        }else{
                            // Replace empty state with table
                            if(oldEmpty)oldEmpty.remove();
                            const tableCard=document.querySelector('.table-card');
                            const header=tableCard.querySelector('.table-card-header');
                            const wrap=document.createElement('div');
                            wrap.className='table-wrap';
                            wrap.innerHTML=newTable.innerHTML;
                            header.parentNode.insertBefore(wrap,header.nextSibling);
                        }
                    }else if(newEmpty){
                        if(oldTable)oldTable.remove();
                        if(oldEmpty){
                            oldEmpty.innerHTML=newEmpty.innerHTML;
                        }else{
                            const tableCard=document.querySelector('.table-card');
                            const header=tableCard.querySelector('.table-card-header');
                            const div=document.createElement('div');
                            div.className='empty-state';
                            div.innerHTML=newEmpty.innerHTML;
                            header.parentNode.insertBefore(div,header.nextSibling);
                        }
                    }
                    
                    // Update pagination
                    if(newPagination && oldPagination){
                        oldPagination.innerHTML=newPagination.innerHTML;
                    }else if(newPagination && !oldPagination){
                        const tableCard=document.querySelector('.table-card');
                        const div=document.createElement('div');
                        div.className='pagination-bar';
                        div.innerHTML=newPagination.innerHTML;
                        tableCard.appendChild(div);
                    }else if(!newPagination && oldPagination){
                        oldPagination.remove();
                    }
                    
                    // Update URL without reload
                    history.pushState(null,'',url);
                    
                    // Clear bulk selection
                    clearBulk();
                    document.querySelectorAll('.cb-cell').forEach(c=>c.classList.add('hidden'));
                    checkboxesVisible=false;
                })
                .catch(err=>console.error('Filter error:',err));
        }
    })();

    (function(){const h=document.getElementById('hamburgerBtn');const s=document.querySelector('.sidebar');const o=document.getElementById('sidebarOverlay');if(!h||!s||!o)return;h.addEventListener('click',function(){this.classList.toggle('active');s.classList.toggle('active');o.classList.toggle('active');document.body.style.overflow=s.classList.contains('active')?'hidden':'';});o.addEventListener('click',function(){h.classList.remove('active');s.classList.remove('active');this.classList.remove('active');document.body.style.overflow='';});document.querySelectorAll('.sidebar .nav-link, .sidebar a').forEach(function(link){link.addEventListener('click',function(){if(window.innerWidth<=768){h.classList.remove('active');s.classList.remove('active');o.classList.remove('active');document.body.style.overflow='';}});});})();
    </script>
<script>!function(){function s(){document.documentElement.style.setProperty("--sidebar-h",window.innerHeight+"px")}s();window.addEventListener("resize",s);window.addEventListener("orientationchange",function(){setTimeout(s,150)})}();</script>
</body>
</html>
