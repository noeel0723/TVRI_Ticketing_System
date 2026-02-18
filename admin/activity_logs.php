<?php
require_once '../config/auth.php';
checkRole('admin');
require_once '../config/koneksi.php';

$user = getUserInfo();

// Fetch user photo
$_foto_q = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
mysqli_stmt_bind_param($_foto_q, "i", $user['id']);
mysqli_stmt_execute($_foto_q);
$_foto_r = mysqli_stmt_get_result($_foto_q);
$_foto_d = mysqli_fetch_assoc($_foto_r);
mysqli_stmt_close($_foto_q);
$_user_photo = !empty($_foto_d['foto']) ? '../uploads/profile_photos/' . $_foto_d['foto'] : '';

// Count open tickets untuk notification
$open_count_query = "SELECT COUNT(*) as total FROM tickets WHERE status = 'Open'";
$open_count_result = mysqli_query($conn, $open_count_query);
$open_count = mysqli_fetch_assoc($open_count_result)['total'];

// Filter params
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$date_from = isset($_GET['from']) ? $_GET['from'] : '';
$date_to = isset($_GET['to']) ? $_GET['to'] : '';

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build WHERE
$where_clauses = [];
if (!empty($filter_action)) {
    $fa = mysqli_real_escape_string($conn, $filter_action);
    $where_clauses[] = "tl.action = '$fa'";
}
if ($filter_user > 0) {
    $where_clauses[] = "tl.user_id = $filter_user";
}
if (!empty($search)) {
    $where_clauses[] = "(tl.description LIKE '%$search%' OR t.judul LIKE '%$search%' OR u.nama LIKE '%$search%')";
}
if (!empty($date_from)) {
    $df = mysqli_real_escape_string($conn, $date_from);
    $where_clauses[] = "DATE(tl.created_at) >= '$df'";
}
if (!empty($date_to)) {
    $dt = mysqli_real_escape_string($conn, $date_to);
    $where_clauses[] = "DATE(tl.created_at) <= '$dt'";
}
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Counts per action (for tabs)
$cnt_all_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM ticket_logs tl LEFT JOIN tickets t ON tl.ticket_id=t.id LEFT JOIN users u ON tl.user_id=u.id $where_sql");
// We need counts without action filter but keeping other filters
$base_where = [];
if ($filter_user > 0) $base_where[] = "tl.user_id = $filter_user";
if (!empty($search)) $base_where[] = "(tl.description LIKE '%$search%' OR t.judul LIKE '%$search%' OR u.nama LIKE '%$search%')";
if (!empty($date_from)) { $df = mysqli_real_escape_string($conn, $date_from); $base_where[] = "DATE(tl.created_at) >= '$df'"; }
if (!empty($date_to))   { $dt = mysqli_real_escape_string($conn, $date_to);   $base_where[] = "DATE(tl.created_at) <= '$dt'"; }
$base_w = !empty($base_where) ? 'WHERE ' . implode(' AND ', $base_where) : '';

$cnt_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM ticket_logs tl LEFT JOIN tickets t ON tl.ticket_id=t.id LEFT JOIN users u ON tl.user_id=u.id $base_w"))['c'];
$cnt_create = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM ticket_logs tl LEFT JOIN tickets t ON tl.ticket_id=t.id LEFT JOIN users u ON tl.user_id=u.id " . (!empty($base_where) ? 'WHERE '.implode(' AND ',$base_where).' AND ' : 'WHERE ') . "tl.action='create'"))['c'];
$cnt_assign = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM ticket_logs tl LEFT JOIN tickets t ON tl.ticket_id=t.id LEFT JOIN users u ON tl.user_id=u.id " . (!empty($base_where) ? 'WHERE '.implode(' AND ',$base_where).' AND ' : 'WHERE ') . "tl.action='assign'"))['c'];
$cnt_status = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM ticket_logs tl LEFT JOIN tickets t ON tl.ticket_id=t.id LEFT JOIN users u ON tl.user_id=u.id " . (!empty($base_where) ? 'WHERE '.implode(' AND ',$base_where).' AND ' : 'WHERE ') . "tl.action='status_update'"))['c'];

// Main query
$count_query = "SELECT COUNT(*) as total FROM ticket_logs tl LEFT JOIN tickets t ON tl.ticket_id=t.id LEFT JOIN users u ON tl.user_id=u.id $where_sql";
$total_records = mysqli_fetch_assoc(mysqli_query($conn, $count_query))['total'];
$total_pages = max(1, ceil($total_records / $limit));

$query = "SELECT tl.*, u.nama as user_nama, u.role as user_role, t.judul as ticket_judul, t.id as ticket_id
          FROM ticket_logs tl
          LEFT JOIN users u ON tl.user_id = u.id
          LEFT JOIN tickets t ON tl.ticket_id = t.id
          $where_sql
          ORDER BY tl.created_at DESC
          LIMIT $limit OFFSET $offset";
$logs_result = mysqli_query($conn, $query);

// Users for dropdown
$users_result = mysqli_query($conn, "SELECT DISTINCT u.id, u.nama, u.role FROM users u INNER JOIN ticket_logs tl ON u.id=tl.user_id ORDER BY u.nama");

// Build query string helper
function qs($overrides = []) {
    $params = $_GET;
    foreach($overrides as $k => $v) {
        if($v === null || $v === '') unset($params[$k]);
        else $params[$k] = $v;
    }
    unset($params['page']); // reset page on filter change unless explicitly set
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - TVRI Ticketing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        html{width:100%}
        body{font-family:'Inter',sans-serif;background:#f8f9fc}
        /* Sidebar */
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
        /* Main */
        .main-content{margin-left:230px;padding:32px 40px;min-height:100vh}
        /* Page title row */
        .page-title-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
        .page-title{font-size:1.5rem;font-weight:700;color:#1a1a2e;margin:0}
        .search-box{position:relative;width:280px}
        .search-box input{width:100%;border:1px solid #e2e5eb;border-radius:10px;padding:10px 14px 10px 38px;font-size:.85rem;font-family:'Inter',sans-serif;background:#fff;transition:all .2s}
        .search-box input:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08);outline:none}
        .search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.9rem}
        /* Filter bar */
        .filter-bar{display:flex;align-items:center;gap:10px;margin-bottom:24px;flex-wrap:wrap}
        .date-pill{display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #e2e5eb;border-radius:10px;padding:7px 14px;font-size:.82rem;color:#374151;cursor:pointer;transition:all .15s;font-family:'Inter',sans-serif}
        .date-pill:hover{border-color:#10367D}
        .date-pill i{color:#9ca3af;font-size:.85rem}
        .date-pill input[type=date]{border:none;background:transparent;font-size:.82rem;font-family:'Inter',sans-serif;color:#374151;outline:none;width:120px;cursor:pointer}
        .tab-pills{display:flex;align-items:center;gap:0;background:#fff;border:1px solid #e2e5eb;border-radius:10px;overflow:hidden}
        .tab-pill{padding:8px 18px;font-size:.82rem;font-weight:600;color:#6b7280;cursor:pointer;transition:all .15s;border-right:1px solid #e2e5eb;text-decoration:none;white-space:nowrap;display:flex;align-items:center;gap:6px}
        .tab-pill:last-child{border-right:none}
        .tab-pill:hover{background:#f3f4f6;color:#374151}
        .tab-pill.active{background:#10367D;color:#fff}
        .tab-pill .cnt{background:rgba(0,0,0,.1);padding:1px 8px;border-radius:10px;font-size:.72rem;font-weight:700}
        .tab-pill.active .cnt{background:rgba(255,255,255,.25)}
        .user-filter{display:flex;align-items:center;gap:6px}
        .user-filter select{border:1px solid #e2e5eb;border-radius:10px;padding:8px 12px;font-size:.82rem;font-family:'Inter',sans-serif;background:#fff;cursor:pointer;color:#374151}
        .user-filter select:focus{outline:none;border-color:#10367D}
        .clear-btn{background:#fff;border:1px solid #e2e5eb;border-radius:10px;padding:8px 14px;font-size:.82rem;font-weight:600;color:#6b7280;cursor:pointer;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:5px}
        .clear-btn:hover{background:#fef2f2;border-color:#fca5a5;color:#dc2626}
        /* Table card */
        .table-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #eef0f5;overflow:hidden}
        .tbl{width:100%;border-collapse:collapse}
        .tbl thead th{font-size:.72rem;font-weight:500;color:#8b8fa3;text-transform:uppercase;letter-spacing:.5px;padding:12px 20px;border-bottom:1px solid #eef0f5;background:#fafbfd;text-align:left;white-space:nowrap;cursor:default}
        .tbl thead th .sort-icon{color:#c0c4cc;margin-left:4px;font-size:.7rem}
        .tbl tbody td{padding:14px 20px;border-bottom:1px solid #f5f6f8;font-size:.85rem;color:#374151;vertical-align:middle}
        .tbl tbody tr{transition:background .15s}
        .tbl tbody tr:hover{background:#f8f9ff}
        .tbl tbody tr:last-child td{border-bottom:none}
        /* Type cell */
        .type-cell{display:flex;align-items:center;gap:10px}
        .type-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
        .dot-create{background:#22c55e}
        .dot-assign{background:#3b82f6}
        .dot-status{background:#f59e0b}
        .type-label{font-weight:600;color:#1a1a2e;font-size:.84rem}
        /* Ticket cell */
        .ticket-cell{display:flex;flex-direction:column;gap:2px}
        .ticket-id{color:#10367D;font-weight:700;font-size:.82rem;text-decoration:none}
        .ticket-id:hover{text-decoration:underline}
        .ticket-title{font-size:.76rem;color:#9ca3af;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        /* Status badge */
        .status-badge{display:inline-flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600}
        .status-badge .status-icon{font-size:.7rem}
        .st-success{color:#059669}
        .st-info{color:#2563eb}
        .st-warning{color:#d97706}
        .st-failed{color:#dc2626}
        /* Changes */
        .change-arrow{display:inline-flex;align-items:center;gap:6px;font-size:.82rem}
        .change-old{color:#ef4444;font-weight:500;text-decoration:line-through;opacity:.7}
        .change-new{color:#22c55e;font-weight:600}
        .change-sep{color:#d1d5db}
        /* People cell */
        .people-cell{display:flex;align-items:center;gap:10px}
        .people-avatar{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.72rem;color:#fff;flex-shrink:0}
        .p-admin{background:#ef4444}
        .p-user{background:#10367D}
        .p-teknisi{background:#f59e0b;color:#1a1a2e}
        .people-name{font-weight:600;color:#1a1a2e;font-size:.84rem}
        /* Description */
        .desc-text{font-size:.82rem;color:#6b7280;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        /* Pagination */
        .pagination-bar{display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-top:1px solid #eef0f5;flex-wrap:wrap;gap:10px}
        .pag-info{font-size:.8rem;color:#8b8fa3}
        .pag-btns{display:flex;gap:4px}
        .pag-btn{padding:6px 12px;border:1px solid #e2e5eb;border-radius:8px;font-size:.82rem;font-weight:500;color:#374151;background:#fff;text-decoration:none;transition:all .15s;cursor:pointer}
        .pag-btn:hover{background:#f3f4f6;border-color:#d1d5db;color:#374151}
        .pag-btn.active{background:#10367D;border-color:#10367D;color:#fff}
        .pag-btn.disabled{opacity:.4;pointer-events:none}
        /* Timestamp */
        .ts-cell{font-size:.8rem;color:#6b7280;line-height:1.4}
        .ts-date{font-weight:600;color:#374151}
        /* Empty */
        .empty-state{text-align:center;padding:56px 20px;color:#9ca3af}
        .empty-state i{font-size:2.8rem;display:block;margin-bottom:12px;color:#d1d5db}
        .empty-state p{font-size:.88rem;margin:0}
        @media(max-width:1100px){.search-box{width:100%}}
        @media(max-width:768px){
            html,body{overflow-x:hidden;width:100%}
            .sidebar{width:100%;position:relative;min-height:auto}
            .main-content{margin-left:0;padding:20px 16px;width:100%;max-width:100%;overflow-x:hidden}
            .filter-bar{flex-direction:column;align-items:stretch;gap:8px}
            .search-box{font-size:.85rem}
            .tab-pills{overflow-x:scroll;-webkit-overflow-scrolling:touch;width:100%;scrollbar-width:none}
            .tab-pills::-webkit-scrollbar{display:none}
            .tab-pill{flex:0 0 auto;padding:8px 12px;font-size:.78rem}
            .table-card{overflow-x:visible}
            .table-container{overflow-x:scroll;-webkit-overflow-scrolling:touch;margin:0;padding:0;width:100%;max-width:100%;position:relative}
            .log-table{min-width:900px}
            .log-table th,.log-table td{font-size:.8rem;padding:10px 8px}
            .page-header h1{font-size:1.4rem}
        }
        @media(max-width:576px){
            html,body{overflow-x:hidden;width:100%}
            .main-content{padding:16px 12px;width:100%;max-width:100%;overflow-x:hidden}
            .table-card{overflow-x:visible}
            .table-container{margin:0;padding:0;width:100%;max-width:100%;overflow-x:scroll;-webkit-overflow-scrolling:touch;position:relative}
            .log-table{min-width:800px;font-size:.75rem}
            .log-table th{font-size:.7rem;padding:8px 6px}
            .log-table td{padding:8px 6px}
            .page-header h1{font-size:1.2rem}
            .search-box{font-size:.8rem;padding:8px 12px}
            .filter-bar select{font-size:.8rem;padding:8px}
            .log-avatar{width:28px;height:28px;font-size:.75rem}
            .pagination-bar{flex-direction:column;align-items:center;justify-content:center;text-align:center}
            .pag-btns{justify-content:center;flex-wrap:wrap}
        }

        @media(min-width:992px){
            .table-card{overflow-x:auto}
        }
/* Mobile Hamburger Menu */
.hamburger-menu{display:none;position:fixed;top:20px;left:20px;z-index:1100;background:white;border:none;width:45px;height:45px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);cursor:pointer;padding:10px;flex-direction:column;justify-content:space-around;transition:all 0.3s ease}
.hamburger-menu span{display:block;width:100%;height:3px;background:#6366f1;border-radius:2px;transition:all 0.3s ease}
.hamburger-menu.active span:nth-child(1){transform:rotate(45deg) translate(8px, 8px)}
.hamburger-menu.active span:nth-child(2){opacity:0}
.hamburger-menu.active span:nth-child(3){transform:rotate(-45deg) translate(7px, -7px)}
.sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:998;opacity:0;transition:opacity 0.3s ease}
.sidebar-overlay.active{display:block;opacity:1}
@media(max-width:768px){.hamburger-menu{display:flex}.sidebar{position:fixed!important;top:0;left:-100%!important;width:260px!important;height:100vh;z-index:999;transition:left 0.3s ease;overflow-y:auto}.sidebar.active{left:0!important}}
    </style>
</head>
<body>
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger-menu" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
<!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($open_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$open_count?></span><?php endif; ?></a>
            <a class="nav-link" href="kelola_user.php"><i class="bi bi-people"></i> Kelola User</a>
            <a class="nav-link active" href="activity_logs.php"><i class="bi bi-clock-history"></i> Activity Logs</a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a></div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Administrator</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>

    <div class="main-content">
        <!-- Title row -->
        <div class="page-title-row">
            <h1 class="page-title"><i class="bi bi-clock-history" style="margin-right:8px"></i>Activities</h1>
            <form method="GET" id="searchForm" class="search-box">
                <!-- preserve existing filters -->
                <?php if(!empty($filter_action)): ?><input type="hidden" name="action" value="<?= htmlspecialchars($filter_action) ?>"><?php endif; ?>
                <?php if($filter_user > 0): ?><input type="hidden" name="user_id" value="<?= $filter_user ?>"><?php endif; ?>
                <?php if(!empty($date_from)): ?><input type="hidden" name="from" value="<?= htmlspecialchars($date_from) ?>"><?php endif; ?>
                <?php if(!empty($date_to)): ?><input type="hidden" name="to" value="<?= htmlspecialchars($date_to) ?>"><?php endif; ?>
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Cari aktivitas..." value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <!-- Date range -->
            <div class="date-pill">
                <i class="bi bi-calendar3"></i>
                <input type="date" id="dateFrom" value="<?= htmlspecialchars($date_from) ?>" title="Dari tanggal">
                <span style="color:#c0c4cc">-</span>
                <input type="date" id="dateTo" value="<?= htmlspecialchars($date_to) ?>" title="Sampai tanggal">
            </div>

            <!-- Tab pills -->
            <div class="tab-pills">
                <a class="tab-pill <?= empty($filter_action) ? 'active' : '' ?>" href="?<?= qs(['action'=>null]) ?>">
                    All <span class="cnt"><?= $cnt_all ?></span>
                </a>
                <a class="tab-pill <?= $filter_action=='create' ? 'active' : '' ?>" href="?<?= qs(['action'=>'create']) ?>">
                    <i class="bi bi-plus-circle" style="font-size:.8rem"></i> Created <span class="cnt"><?= $cnt_create ?></span>
                </a>
                <a class="tab-pill <?= $filter_action=='assign' ? 'active' : '' ?>" href="?<?= qs(['action'=>'assign']) ?>">
                    <i class="bi bi-person-check" style="font-size:.8rem"></i> Assigned <span class="cnt"><?= $cnt_assign ?></span>
                </a>
                <a class="tab-pill <?= $filter_action=='status_update' ? 'active' : '' ?>" href="?<?= qs(['action'=>'status_update']) ?>">
                    <i class="bi bi-arrow-repeat" style="font-size:.8rem"></i> Updated <span class="cnt"><?= $cnt_status ?></span>
                </a>
            </div>

            <!-- User filter -->
            <div class="user-filter">
                <select id="userFilter" onchange="applyUserFilter(this.value)">
                    <option value="0">Semua User</option>
                    <?php while($uf = mysqli_fetch_assoc($users_result)): ?>
                    <option value="<?= $uf['id'] ?>" <?= $filter_user == $uf['id'] ? 'selected' : '' ?>><?= htmlspecialchars($uf['nama']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <?php if(!empty($filter_action) || $filter_user > 0 || !empty($search) || !empty($date_from) || !empty($date_to)): ?>
            <a class="clear-btn" href="activity_logs.php"><i class="bi bi-x-lg"></i> Clear</a>
            <?php endif; ?>
        </div>

        <!-- Table -->
        <div class="table-card">
            <?php if(mysqli_num_rows($logs_result) > 0): ?>
            <div class="table-container">
            <table class="tbl log-table" id="logsTable">
                <thead>
                    <tr>
                        <th>Type <i class="bi bi-arrow-down-up sort-icon"></i></th>
                        <th>Ticket</th>
                        <th>Changes</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>People</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($log = mysqli_fetch_assoc($logs_result)):
                        // Type dot
                        $dot = 'dot-create';
                        $type_label = 'Created';
                        if($log['action'] == 'assign') { $dot = 'dot-assign'; $type_label = 'Assigned'; }
                        elseif($log['action'] == 'status_update') { $dot = 'dot-status'; $type_label = 'Status Update'; }

                        // Status style
                        $st_class = 'st-success'; $st_icon = 'bi-check-circle-fill'; $st_text = 'Success';
                        if($log['action'] == 'create') { $st_class = 'st-success'; $st_icon = 'bi-check-circle-fill'; $st_text = 'Created'; }
                        elseif($log['action'] == 'assign') { $st_class = 'st-info'; $st_icon = 'bi-dot'; $st_text = 'Assigned'; }
                        elseif($log['action'] == 'status_update') {
                            $nv = $log['new_value'] ?? '';
                            if($nv == 'Resolved') { $st_class = 'st-success'; $st_icon = 'bi-check-circle-fill'; $st_text = 'Resolved'; }
                            elseif($nv == 'In Progress') { $st_class = 'st-warning'; $st_icon = 'bi-dot'; $st_text = 'In Progress'; }
                            else { $st_class = 'st-info'; $st_icon = 'bi-arrow-repeat'; $st_text = $nv ?: 'Updated'; }
                        }

                        // Avatar
                        $p_class = 'p-user';
                        if(($log['user_role'] ?? '') == 'admin') $p_class = 'p-admin';
                        elseif(($log['user_role'] ?? '') == 'teknisi') $p_class = 'p-teknisi';
                    ?>
                    <tr>
                        <td>
                            <div class="type-cell">
                                <span class="type-dot <?= $dot ?>"></span>
                                <span class="type-label"><?= $type_label ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if($log['ticket_id']): ?>
                            <div class="ticket-cell">
                                <a class="ticket-id" href="../detail.php?id=<?= $log['ticket_id'] ?>">#<?= $log['ticket_id'] ?></a>
                                <span class="ticket-title"><?= htmlspecialchars($log['ticket_judul'] ?? '-') ?></span>
                            </div>
                            <?php else: ?>
                            <span style="color:#c0c4cc">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($log['old_value']) || !empty($log['new_value'])): ?>
                            <div class="change-arrow">
                                <?php if(!empty($log['old_value'])): ?>
                                <span class="change-old"><?= htmlspecialchars($log['old_value']) ?></span>
                                <span class="change-sep"><i class="bi bi-arrow-right"></i></span>
                                <?php endif; ?>
                                <span class="change-new"><?= htmlspecialchars($log['new_value']) ?></span>
                            </div>
                            <?php else: ?>
                            <span style="color:#d1d5db">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?= $st_class ?>">
                                <i class="<?= $st_icon ?> status-icon"></i> <?= $st_text ?>
                            </span>
                        </td>
                        <td><span class="desc-text" title="<?= htmlspecialchars($log['description'] ?? '') ?>"><?= htmlspecialchars($log['description'] ?? '-') ?></span></td>
                        <td>
                            <div class="people-cell">
                                <div class="people-avatar <?= $p_class ?>"><?= strtoupper(substr($log['user_nama'] ?? '?', 0, 1)) ?></div>
                                <span class="people-name"><?= htmlspecialchars($log['user_nama'] ?? 'Unknown') ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="ts-cell">
                                <div class="ts-date"><?= date('d/m/Y', strtotime($log['created_at'])) ?></div>
                                <div><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>Tidak ada aktivitas ditemukan</p>
            </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination-bar">
                <div class="pag-info">
                    Halaman <?= $page ?> dari <?= $total_pages ?> &bull; <?= $total_records ?> total
                </div>
                <div class="pag-btns">
                    <a class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="?page=<?= $page-1 ?>&<?= qs(['page'=>null]) ?>"><i class="bi bi-chevron-left"></i></a>
                    <?php
                    $sp = max(1, $page - 2);
                    $ep = min($total_pages, $page + 2);
                    for($i = $sp; $i <= $ep; $i++):
                    ?>
                    <a class="pag-btn <?= $i == $page ? 'active' : '' ?>" href="?page=<?= $i ?>&<?= qs(['page'=>null]) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a class="pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" href="?page=<?= $page+1 ?>&<?= qs(['page'=>null]) ?>"><i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Date filter
    document.getElementById('dateFrom').addEventListener('change', applyDateFilter);
    document.getElementById('dateTo').addEventListener('change', applyDateFilter);
    function applyDateFilter() {
        const from = document.getElementById('dateFrom').value;
        const to   = document.getElementById('dateTo').value;
        const params = new URLSearchParams(window.location.search);
        if(from) params.set('from', from); else params.delete('from');
        if(to) params.set('to', to); else params.delete('to');
        params.delete('page');
        window.location.search = params.toString();
    }

    // User filter
    function applyUserFilter(val) {
        const params = new URLSearchParams(window.location.search);
        if(val && val !== '0') params.set('user_id', val); else params.delete('user_id');
        params.delete('page');
        window.location.search = params.toString();
    }

    // Search on enter
    document.querySelector('#searchForm input[name=search]').addEventListener('keydown', function(e) {
        if(e.key === 'Enter') { e.preventDefault(); this.form.submit(); }
    });
    </script>
<script>
// Mobile Sidebar Toggle
(function(){
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    if(!hamburgerBtn || !sidebar || !sidebarOverlay) return;

    hamburgerBtn.addEventListener('click', function(){
        this.classList.toggle('active');
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    });

    sidebarOverlay.addEventListener('click', function(){
        hamburgerBtn.classList.remove('active');
        sidebar.classList.remove('active');
        this.classList.remove('active');
        document.body.style.overflow = '';
    });

    document.querySelectorAll('.sidebar .nav-link, .sidebar a').forEach(function(link){
        link.addEventListener('click', function(){
            if(window.innerWidth <= 768){
                hamburgerBtn.classList.remove('active');
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
})();</script>
</body>
</html>