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

// Pagination setup
$users_per_page = 15;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $users_per_page;

// Count total users
$count_query = "SELECT COUNT(*) as total FROM users";
$count_result = mysqli_query($conn, $count_query);
$total_users_count = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_users_count / $users_per_page);

// Ambil users dengan pagination
$query_users = "SELECT u.*, d.nama_divisi 
                FROM users u
                LEFT JOIN divisions d ON u.division_id = d.id
                ORDER BY u.role, u.nama
                LIMIT $users_per_page OFFSET $offset";
$result_users = mysqli_query($conn, $query_users);

// Hitung statistik dari semua users
$stats_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$stats_result = mysqli_query($conn, $stats_query);
$admin_count = 0; $user_count = 0; $teknisi_count = 0;
while($stat = mysqli_fetch_assoc($stats_result)) {
    if($stat['role'] == 'admin') $admin_count = $stat['count'];
    elseif($stat['role'] == 'user') $user_count = $stat['count'];
    elseif($stat['role'] == 'teknisi') $teknisi_count = $stat['count'];
}
$total_users = $admin_count + $user_count + $teknisi_count;

// Get paginated users
$all_users = [];
while($row = mysqli_fetch_assoc($result_users)) {
    $all_users[] = $row;
}

// Ambil daftar divisi untuk form
$divisions_query = "SELECT * FROM divisions ORDER BY nama_divisi";
$divisions_result = mysqli_query($conn, $divisions_query);
$divisions = [];
while($div = mysqli_fetch_assoc($divisions_result)) { $divisions[] = $div; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Ticketing TVRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        html{width:100%}
        body{font-family:'Inter',sans-serif;background:#f8f9fc}
        /* ---- Sidebar (consistent) ---- */
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

        /* Make desktop sidebar menu align exactly like admin dashboard
           (keep mobile layout unchanged) */
        /* ---- Main ---- */
        .main-content{margin-left:230px;padding:32px 40px;min-height:100vh}
        /* ---- Top bar ---- */
        .top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:16px}
        .top-bar h1{font-size:1.5rem;font-weight:700;color:#1a1a2e;margin:0}
        .top-bar-actions{display:flex;align-items:center;gap:10px}
        .btn-add{background:#10367D;color:#fff;border:none;padding:10px 22px;border-radius:10px;font-size:.85rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:all .2s;text-decoration:none}
        .btn-add:hover{background:#0c2d68;color:#fff;transform:translateY(-1px);box-shadow:0 4px 12px rgba(16,54,125,.25)}
        /* ---- Search & filter bar ---- */
        .filter-row{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
        .search-box{position:relative;flex:1;min-width:200px;max-width:360px}
        .search-box input{width:100%;border:1px solid #e2e5eb;border-radius:10px;padding:10px 14px 10px 38px;font-size:.85rem;font-family:'Inter',sans-serif;background:#fff;transition:all .2s}
        .search-box input:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08);outline:none}
        .search-box i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.9rem}
        .sort-label{font-size:.82rem;color:#8b8fa3;display:flex;align-items:center;gap:6px}
        .sort-label select{border:1px solid #e2e5eb;border-radius:8px;padding:6px 10px;font-size:.82rem;font-family:'Inter',sans-serif;background:#fff;cursor:pointer}
        .sort-label select:focus{outline:none;border-color:#10367D}
        /* ---- Layout grid ---- */
        .content-grid{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start}
        /* ---- Table card ---- */
        .table-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #eef0f5;overflow:hidden}
        .tbl{width:100%;border-collapse:collapse}
        .tbl thead th{font-size:.72rem;font-weight:500;color:#8b8fa3;text-transform:uppercase;letter-spacing:.5px;padding:12px 20px;border-bottom:1px solid #eef0f5;background:#fafbfd;text-align:left;white-space:nowrap}
        .tbl tbody td{padding:14px 20px;border-bottom:1px solid #f5f6f8;font-size:.85rem;color:#374151;vertical-align:middle}
        .tbl tbody tr{transition:background .15s}
        .tbl tbody tr:hover{background:#f8f9ff}
        .tbl tbody tr:last-child td{border-bottom:none}
        .user-cell{display:flex;align-items:center;gap:12px}
        .user-avatar-sm{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.8rem;color:#fff;flex-shrink:0}
        .avatar-admin{background:#ef4444}
        .avatar-user{background:#10367D}
        .avatar-teknisi{background:#f59e0b;color:#1a1a2e}
        .user-cell-info{line-height:1.3}
        .user-cell-name{font-weight:600;color:#1a1a2e;font-size:.85rem}
        .user-cell-username{font-size:.75rem;color:#9ca3af}
        .role-tag{padding:4px 12px;border-radius:20px;font-size:.73rem;font-weight:600;display:inline-block}
        .role-admin{background:#fef2f2;color:#dc2626}
        .role-user{background:#eff6ff;color:#2563eb}
        .role-teknisi{background:#fffbeb;color:#d97706}
        .division-text{color:#6b7280;font-size:.83rem}
        .action-dots{background:none;border:none;font-size:1.1rem;color:#9ca3af;cursor:pointer;padding:4px 8px;border-radius:6px;transition:all .15s}
        .action-dots:hover{background:#f3f4f6;color:#374151}
        .dropdown-menu{border:1px solid #eef0f5;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.1);padding:6px;min-width:160px}
        .dropdown-menu .dropdown-item{font-size:.84rem;padding:8px 14px;border-radius:8px;display:flex;align-items:center;gap:8px;font-weight:500}
        .dropdown-menu .dropdown-item:hover{background:#f3f4f6}
        .dropdown-menu .dropdown-item.text-danger:hover{background:#fef2f2}`n        .pagination a:hover{background:#f9fafb;border-color:#10367D!important;color:#10367D!important}
        /* ---- Right panel ---- */
        .right-panel{display:flex;flex-direction:column;gap:20px}
        /* Stats card */
        .stats-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #eef0f5;padding:24px 28px}
        .stats-title{font-size:1rem;font-weight:700;color:#1a1a2e;margin-bottom:4px}
        .stats-sub{font-size:.78rem;color:#8b8fa3;margin-bottom:20px}
        .stat-ring-wrap{display:flex;align-items:center;gap:24px;margin-bottom:20px}
        .stat-ring{position:relative;width:120px;height:120px;flex-shrink:0}
        .stat-ring svg{transform:rotate(-90deg)}
        .stat-ring-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center}
        .stat-ring-center .num{font-size:1.3rem;font-weight:700;color:#1a1a2e;display:block;line-height:1}
        .stat-ring-center .lbl{font-size:.68rem;color:#8b8fa3;margin-top:2px}
        .stat-legend{display:flex;flex-direction:column;gap:10px}
        .stat-legend-item{display:flex;align-items:center;gap:8px;font-size:.82rem;color:#374151;font-weight:500}
        .stat-legend-item .dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
        .stat-legend-item .count{margin-left:auto;font-weight:700;color:#1a1a2e}
        /* Quick add card */
        .quick-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #eef0f5;padding:24px 28px}
        .quick-title{font-size:1rem;font-weight:700;color:#1a1a2e;margin-bottom:16px}
        .quick-card .form-label{font-weight:500;color:#6b7280;font-size:.78rem;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
        .quick-card .form-control,.quick-card .form-select{border:1px solid #e2e5eb;border-radius:10px;padding:9px 14px;font-size:.85rem;font-family:'Inter',sans-serif}
        .quick-card .form-control:focus,.quick-card .form-select:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08);outline:none}
        .btn-save{background:#10367D;color:#fff;border:none;padding:10px 0;border-radius:10px;font-size:.88rem;font-weight:600;font-family:'Inter',sans-serif;width:100%;cursor:pointer;transition:all .2s}
        .btn-save:hover{background:#0c2d68}
        /* ---- Modal ---- */
        .modal-content{border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15)}
        .modal-header{border-bottom:1px solid #eef0f5;padding:20px 24px}
        .modal-header .modal-title{font-weight:700;font-size:1.05rem;color:#1a1a2e}
        .modal-body{padding:24px}
        .modal-body .form-label{font-weight:500;color:#6b7280;font-size:.78rem;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
        .modal-body .form-control,.modal-body .form-select{border:1px solid #e2e5eb;border-radius:10px;padding:10px 14px;font-size:.85rem;font-family:'Inter',sans-serif}
        .modal-body .form-control:focus,.modal-body .form-select:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08);outline:none}
        .modal-footer{border-top:1px solid #eef0f5;padding:16px 24px}
        .modal-footer .btn{font-family:'Inter',sans-serif;font-weight:600;border-radius:10px;padding:9px 22px;font-size:.85rem}
        .btn-cancel{background:#f3f4f6;color:#6b7280;border:none}
        .btn-cancel:hover{background:#e5e7eb;color:#374151}
        .btn-modal-save{background:#10367D;color:#fff;border:none}
        .btn-modal-save:hover{background:#0c2d68;color:#fff}
        .empty-state{text-align:center;padding:48px 20px;color:#9ca3af}
        .empty-state i{font-size:2.5rem;display:block;margin-bottom:10px}
        @media(max-width:1100px){.content-grid{grid-template-columns:1fr}}
        @media(max-width:768px){html,body{overflow-x:hidden;width:100%}
            /* .sidebar{width:100%;position:relative;min-height:auto} */ /* Removed - using hamburger menu */
            .main-content{margin-left:0;padding:56px 16px 20px}
            .table-wrapper{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -16px;padding:0 16px}
            .user-table{min-width:800px}
            .user-table th,.user-table td{font-size:.8rem;padding:10px 8px}
            .page-header h2{font-size:1.3rem}
            .btn-tambah{font-size:.85rem;padding:10px 16px}
        }
        @media(max-width:576px){html,body{overflow-x:hidden;width:100%}
            .main-content{padding:16px 12px}
            .table-wrapper{margin:0 -12px;padding:0 12px}
            .user-table{min-width:700px;font-size:.75rem}
            .user-table th{font-size:.72rem;padding:8px 6px}
            .user-table td{padding:8px 6px}
            .page-header h2{font-size:1.1rem}
            .btn-tambah{font-size:.8rem;padding:8px 14px}
            .user-avatar{width:32px;height:32px;font-size:.8rem}
            .action-btn{font-size:.75rem;padding:4px 8px}
        }
/* Mobile Hamburger Menu */
        .hamburger-menu{display:none;position:fixed;top:20px;left:20px;z-index:1100;background:#fff;border:none;width:44px;height:44px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);cursor:pointer;padding:10px;flex-direction:column;justify-content:space-around;transition:all .3s}
        .hamburger-menu span{display:block;width:100%;height:3px;background:#10367D;border-radius:2px;transition:all .3s}
        .hamburger-menu.active span:nth-child(1){transform:rotate(45deg) translate(8px,8px)}
        .hamburger-menu.active span:nth-child(2){opacity:0}
        .hamburger-menu.active span:nth-child(3){transform:rotate(-45deg) translate(7px,-7px)}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:998;opacity:0;transition:opacity .3s}
        .sidebar-overlay.active{display:block;opacity:1}
        @media(max-width:768px){.hamburger-menu{display:flex}.sidebar{position:fixed!important;top:0;left:-100%!important;width:260px!important;height:var(--sidebar-h,100vh);z-index:999;transition:left .3s;overflow-y:auto;-webkit-overflow-scrolling:touch}.sidebar.active{left:0!important}.sidebar-nav{flex:none!important}}
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
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($open_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$open_count?></span><?php endif; ?></a>
            <a class="nav-link active" href="kelola_user.php"><i class="bi bi-people"></i> Kelola User</a>
            <a class="nav-link" href="activity_logs.php"><i class="bi bi-clock-history"></i> Activity Logs</a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Administrator</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>

    <!-- Main content -->
    <div class="main-content">
        <!-- Top bar -->
        <div class="top-bar">
            <h1><i class="bi bi-people" style="margin-right:8px"></i>Kelola User</h1>
            <div class="top-bar-actions">
                <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-plus-lg"></i> Tambah User
                </button>
            </div>
        </div>

        <!-- Filter row -->
        <div class="filter-row">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Cari nama atau username...">
            </div>
            <div class="sort-label">
                Filter Role
                <select id="roleFilter">
                    <option value="all">Semua</option>
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                    <option value="teknisi">Teknisi</option>
                </select>
            </div>
            <button type="button" id="toggleCheckboxUser" class="btn" style="background:#fff;color:#374151;padding:8px 16px;border-radius:8px;font-size:0.85rem;font-weight:600;border:1px solid #e5e7eb;cursor:pointer;display:flex;align-items:center;gap:6px;transition:all 0.2s"><i class="bi bi-check2-square"></i> Pilih User</button>
            <button type="button" id="deleteSelected" class="btn" style="background:#dc2626;color:#fff;padding:8px 16px;border-radius:8px;font-size:0.85rem;font-weight:600;border:none;cursor:pointer;display:none;align-items:center;gap:6px" onclick="deleteSelectedUsers()"><i class="bi bi-trash"></i> Hapus Terpilih</button>
        </div>

        <!-- Content grid -->
        <div class="content-grid">
            <!-- LEFT: Table -->
            <div class="table-card">
                <?php if(count($all_users) > 0): ?>
                <div class="table-wrapper">
                <table class="tbl user-table" id="userTable">
                    <thead>
                        <tr>
                            <th class="chk-cell-header" style="width:40px;display:none"><input type="checkbox" id="checkAll" style="cursor:pointer"></th>
                            <th>Nama</th>
                            <th>Role</th>
                            <th>Divisi</th>
                            <th style="width:50px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_users as $u):
                            $avatar_class = 'avatar-user';
                            if($u['role'] == 'admin') $avatar_class = 'avatar-admin';
                            elseif($u['role'] == 'teknisi') $avatar_class = 'avatar-teknisi';
                            $role_class = 'role-user';
                            if($u['role'] == 'admin') $role_class = 'role-admin';
                            elseif($u['role'] == 'teknisi') $role_class = 'role-teknisi';
                        ?>
                        <tr data-name="<?= strtolower(htmlspecialchars($u['nama'])) ?>" data-username="<?= strtolower(htmlspecialchars($u['username'])) ?>" data-role="<?= $u['role'] ?>">
                            <td class="chk-cell-row" style="display:none"><input type="checkbox" class="row-check" data-user-id="<?= $u['id'] ?>" style="cursor:pointer"></td>
                            <td>
                                <div class="user-cell">
                                    <?php if(!empty($u['foto'])): ?>
                                        <div class="user-avatar-sm <?= $avatar_class ?>"><img src="../uploads/profile_photos/<?= htmlspecialchars($u['foto']) ?>" alt="<?= htmlspecialchars($u['nama']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
                                    <?php else: ?>
                                        <div class="user-avatar-sm <?= $avatar_class ?>"><?= strtoupper(substr($u['nama'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                    <div class="user-cell-info">
                                        <div class="user-cell-name"><?= htmlspecialchars($u['nama']) ?></div>
                                        <div class="user-cell-username">@<?= htmlspecialchars($u['username']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="role-tag <?= $role_class ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><span class="division-text"><?= htmlspecialchars($u['nama_divisi'] ?? '-') ?></span></td>
                            <td>
                                <div class="dropdown">
                                    <button class="action-dots" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="editUser(<?= $u['id'] ?>, '<?= addslashes($u['nama']) ?>', '<?= addslashes($u['username']) ?>', '<?= $u['role'] ?>', '<?= $u['division_id'] ?? '' ?>'); return false;"><i class="bi bi-pencil"></i> Edit</a></li>
                                        <?php if($u['id'] != $user['id']): ?>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes($u['nama']) ?>'); return false;"><i class="bi bi-trash"></i> Hapus</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination" id="userPagination" style="display:flex;justify-content:center;align-items:center;gap:8px;margin-top:20px;padding:0 20px">
                    <?php if($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?>" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#374151;font-size:0.9rem;transition:all .2s">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if($i == $current_page): ?>
                        <span style="padding:8px 14px;background:#10367D;color:#fff;border-radius:8px;font-weight:500;font-size:0.9rem"><?= $i ?></span>
                        <?php else: ?>
                        <a href="?page=<?= $i ?>" style="padding:8px 14px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#374151;font-size:0.9rem;transition:all .2s;display:inline-block"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?>" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#374151;font-size:0.9rem;transition:all .2s">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <p>Belum ada user terdaftar</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Panels -->
            <div class="right-panel">
                <!-- Statistics -->
                <div class="stats-card">
                    <div class="stats-title">Statistik User</div>
                    <div class="stats-sub">Total <?= $total_users ?> user terdaftar</div>

                    <div class="stat-ring-wrap">
                        <div class="stat-ring">
                            <?php
                            $r = 52; $c = 2 * M_PI * $r;
                            $p_admin = $total_users > 0 ? ($admin_count / $total_users) : 0;
                            $p_user  = $total_users > 0 ? ($user_count / $total_users) : 0;
                            $p_tech  = $total_users > 0 ? ($teknisi_count / $total_users) : 0;
                            $off1 = 0;
                            $off2 = $off1 + $p_admin * $c;
                            $off3 = $off2 + $p_user * $c;
                            ?>
                            <svg width="120" height="120" viewBox="0 0 120 120">
                                <circle cx="60" cy="60" r="<?= $r ?>" fill="none" stroke="#eef0f5" stroke-width="14"/>
                                <circle cx="60" cy="60" r="<?= $r ?>" fill="none" stroke="#ef4444" stroke-width="14"
                                    stroke-dasharray="<?= $p_admin*$c ?> <?= $c - $p_admin*$c ?>" stroke-dashoffset="-<?= $off1 ?>"/>
                                <circle cx="60" cy="60" r="<?= $r ?>" fill="none" stroke="#10367D" stroke-width="14"
                                    stroke-dasharray="<?= $p_user*$c ?> <?= $c - $p_user*$c ?>" stroke-dashoffset="-<?= $off2 ?>"/>
                                <circle cx="60" cy="60" r="<?= $r ?>" fill="none" stroke="#f59e0b" stroke-width="14"
                                    stroke-dasharray="<?= $p_tech*$c ?> <?= $c - $p_tech*$c ?>" stroke-dashoffset="-<?= $off3 ?>"/>
                            </svg>
                            <div class="stat-ring-center">
                                <span class="num"><?= $total_users ?></span>
                                <span class="lbl">Total</span>
                            </div>
                        </div>
                        <div class="stat-legend">
                            <div class="stat-legend-item"><span class="dot" style="background:#ef4444"></span> Admin <span class="count"><?= $admin_count ?></span></div>
                            <div class="stat-legend-item"><span class="dot" style="background:#10367D"></span> User <span class="count"><?= $user_count ?></span></div>
                            <div class="stat-legend-item"><span class="dot" style="background:#f59e0b"></span> Teknisi <span class="count"><?= $teknisi_count ?></span></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Add -->
                <div class="quick-card">
                    <div class="quick-title"><i class="bi bi-person-plus" style="margin-right:6px"></i>Tambah Cepat</div>
<form method="POST" action="proses_user.php" id="quickAddForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" placeholder="Nama lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Username login" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Min 6 karakter" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" id="quickRoleSelect" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                                <option value="teknisi">Teknisi</option>
                            </select>
                        </div>
                        <div class="mb-3 d-none" id="quickDivField">
                            <label class="form-label">Divisi</label>
                            <select name="division_id" class="form-select">
                                <option value="">Pilih Divisi</option>
                                <?php foreach($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Simpan User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Tambah User ===== -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus" style="margin-right:6px"></i>Tambah User Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="proses_user.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted" style="font-size:.75rem">Minimal 6 karakter</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" id="modalRoleSelect" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="user">User (Pelapor)</option>
                                <option value="teknisi">Teknisi</option>
                            </select>
                        </div>
                        <div class="mb-3 d-none" id="modalDivField">
                            <label class="form-label">Divisi</label>
                            <select name="division_id" class="form-select">
                                <option value="">Pilih Divisi</option>
                                <?php foreach($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-modal-save">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== MODAL: Edit User ===== -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square" style="margin-right:6px"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="proses_user.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" id="editNama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="editUsername" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru (kosongkan jika tidak diubah)</label>
                            <input type="password" name="password" class="form-control" minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" id="editRoleSelect" required>
                                <option value="admin">Admin</option>
                                <option value="user">User (Pelapor)</option>
                                <option value="teknisi">Teknisi</option>
                            </select>
                        </div>
                        <div class="mb-3 d-none" id="editDivField">
                            <label class="form-label">Divisi</label>
                            <select name="division_id" id="editDivision" class="form-select">
                                <option value="">Pilih Divisi</option>
                                <?php foreach($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['nama_divisi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-modal-save">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // URL Parameter Notifications
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'delete_multi') {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: 'User terpilih berhasil dihapus.',
            confirmButtonColor: '#10367D'
        });
    }
    if (urlParams.get('error') === 'self_delete') {
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: 'Tidak dapat menghapus akun Anda sendiri.',
            confirmButtonColor: '#dc2626'
        });
    }
    if (urlParams.get('error') === 'empty') {
        Swal.fire({
            icon: 'info',
            title: 'Pilih User',
            text: 'Pilih minimal 1 user untuk dihapus.',
            confirmButtonColor: '#10367D'
        });
    }
    
    // ---- Role -> Division toggle ----
    function setupRoleDivToggle(selectId, fieldId) {
        const sel = document.getElementById(selectId);
        const field = document.getElementById(fieldId);
        if(!sel || !field) return;
        sel.addEventListener('change', function() {
            if(this.value === 'teknisi') { field.classList.remove('d-none'); field.querySelector('select').required = true; }
            else { field.classList.add('d-none'); field.querySelector('select').required = false; field.querySelector('select').value = ''; }
        });
    }
    setupRoleDivToggle('modalRoleSelect', 'modalDivField');
    setupRoleDivToggle('quickRoleSelect', 'quickDivField');
    setupRoleDivToggle('editRoleSelect', 'editDivField');

    // ---- Check all ----
    document.getElementById('checkAll')?.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
        toggleDeleteButton();
    });

    // Show/hide delete button based on checkbox selection
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', toggleDeleteButton);
    });

    function toggleDeleteButton() {
        const checkedBoxes = document.querySelectorAll('.row-check:checked');
        const deleteBtn = document.getElementById('deleteSelected');
        if(deleteBtn) {
            deleteBtn.style.display = checkedBoxes.length > 0 ? 'flex' : 'none';
        }
    }

    // Mass delete function
    function deleteSelectedUsers() {
        const checkedBoxes = document.querySelectorAll('.row-check:checked');
        if(checkedBoxes.length === 0) {
            Swal.fire({icon:'info',title:'Pilih User',text:'Pilih minimal 1 user untuk dihapus',confirmButtonColor:'#10367D'});
            return;
        }
        
        const userIds = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-user-id')).filter(id => id);
        
        Swal.fire({
            title: 'Hapus ' + userIds.length + ' User?',
            text: 'Yakin menghapus ' + userIds.length + ' user terpilih? Tindakan ini tidak dapat dibatalkan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="bi bi-trash"></i> Ya, Hapus Semua',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'proses_user.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_multiple';
                form.appendChild(actionInput);
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?= htmlspecialchars(csrf_token()) ?>';
                form.appendChild(csrfInput);
                
                userIds.forEach(id => {
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'user_ids[]';
                    idInput.value = id;
                    form.appendChild(idInput);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // ---- Search & Role Filter ----
    (function(){
        const searchInput = document.getElementById('searchInput');
        const roleSelect = document.getElementById('roleFilter');
        if(searchInput) searchInput.addEventListener('input', filterTable);
        if(roleSelect) roleSelect.addEventListener('change', filterTable);

        function filterTable() {
            const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const role = (roleSelect ? roleSelect.value.toLowerCase().trim() : 'all');
            let visibleCount = 0;
            document.querySelectorAll('#userTable tbody tr').forEach(tr => {
                const name = (tr.dataset.name || '').toLowerCase().trim();
                const uname = (tr.dataset.username || '').toLowerCase().trim();
                const r = (tr.dataset.role || '').toLowerCase().trim();
                const matchSearch = q === '' || name.includes(q) || uname.includes(q);
                const matchRole = role === 'all' || r === role;
                const shouldShow = matchSearch && matchRole;
                tr.style.display = shouldShow ? '' : 'none';
                if(shouldShow) visibleCount++;
            });
            const pagination = document.getElementById('userPagination');
            const isFiltering = q !== '' || role !== 'all';
            if(pagination) {
                // Hide pagination while filtering to avoid confusion; show again when not filtering
                pagination.style.display = isFiltering ? 'none' : 'flex';
            }
        }
    })();

    // ---- Delete ----
    function deleteUser(id, nama) {
        Swal.fire({
            title: 'Hapus User?',
            html: 'Yakin hapus user <b>' + nama + '</b>?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="bi bi-trash"></i> Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteUserId').value = id;
                document.getElementById('deleteUserForm').submit();
            }
        });
    }

    // ---- Edit ----
    function editUser(id, nama, username, role, divisionId) {
        document.getElementById('editUserId').value = id;
        document.getElementById('editNama').value = nama;
        document.getElementById('editUsername').value = username;
        document.getElementById('editRoleSelect').value = role;
        const divField = document.getElementById('editDivField');
        if(role === 'teknisi') {
            divField.classList.remove('d-none');
            divField.querySelector('select').required = true;
            document.getElementById('editDivision').value = divisionId;
        } else {
            divField.classList.add('d-none');
            divField.querySelector('select').required = false;
        }
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }

    // ---- SweetAlert notifications ----
    <?php if(isset($_GET['success'])): ?>
    Swal.fire({ icon:'success', title:'Berhasil!', text:'<?= $_GET["success"] == "add" ? "User berhasil ditambahkan" : ($_GET["success"] == "edit" ? "User berhasil diupdate" : "User berhasil dihapus") ?>', confirmButtonColor:'#10367D', timer:2500 });
    <?php endif; ?>
    <?php if(isset($_GET['error'])): ?>
    Swal.fire({ icon:'error', title:'Gagal!', text:'<?php
        $e = $_GET["error"];
        if($e == "empty") echo "Semua field wajib diisi";
        elseif($e == "username_exist") echo "Username sudah digunakan";
        elseif($e == "self_delete") echo "Tidak dapat menghapus akun sendiri";
        elseif($e == "db") echo "Terjadi kesalahan database";
        else echo "Terjadi kesalahan";
    ?>', confirmButtonColor:'#10367D' });
    <?php endif; ?>

    </script>
<script>(function(){const h=document.getElementById('hamburgerBtn');const s=document.querySelector('.sidebar');const o=document.getElementById('sidebarOverlay');if(!h||!s||!o)return;h.addEventListener('click',function(){this.classList.toggle('active');s.classList.toggle('active');o.classList.toggle('active');document.body.style.overflow=s.classList.contains('active')?'hidden':'';});o.addEventListener('click',function(){h.classList.remove('active');s.classList.remove('active');this.classList.remove('active');document.body.style.overflow='';});document.querySelectorAll('.sidebar .nav-link, .sidebar a').forEach(function(l){l.addEventListener('click',function(){if(window.innerWidth<=768){h.classList.remove('active');s.classList.remove('active');o.classList.remove('active');document.body.style.overflow='';}});});})();</script>

<script>
    // Hidden delete form for single deletions
    (function(){
        const form = document.createElement('form');
        form.id = 'deleteUserForm';
        form.method = 'POST';
        form.action = 'proses_user.php';
        form.style.display = 'none';

        const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = 'csrf_token'; csrf.value = '<?= htmlspecialchars(csrf_token()) ?>'; form.appendChild(csrf);
        const act = document.createElement('input'); act.type = 'hidden'; act.name = 'action'; act.value = 'delete'; form.appendChild(act);
        const id = document.createElement('input'); id.type = 'hidden'; id.name = 'id'; id.id = 'deleteUserId'; form.appendChild(id);
        document.body.appendChild(form);
    })();

    // Toggle Checkbox for Users
    const toggleChkUser = document.getElementById('toggleCheckboxUser');
    const chkHeaderCells = document.querySelectorAll('.chk-cell-header');
    const chkRowCells = document.querySelectorAll('.chk-cell-row');
    let userCheckboxVisible = false;
    if (toggleChkUser) {
        toggleChkUser.addEventListener('click', function() {
            userCheckboxVisible = !userCheckboxVisible;
            chkHeaderCells.forEach(cell => cell.style.display = userCheckboxVisible ? '' : 'none');
            chkRowCells.forEach(cell => {
                cell.style.display = userCheckboxVisible ? '' : 'none';
                if (!userCheckboxVisible) {
                    const cb = cell.querySelector('input[type=checkbox]');
                    if (cb) cb.checked = false;
                }
            });
            this.innerHTML = userCheckboxVisible ? '<i class="bi bi-x-square"></i> Tutup Pilih' : '<i class="bi bi-check2-square"></i> Pilih User';
            this.style.background = userCheckboxVisible ? '#eff6ff' : '#fff';
            this.style.borderColor = userCheckboxVisible ? '#10367D' : '#e5e7eb';
            this.style.color = userCheckboxVisible ? '#10367D' : '#374151';
            if (!userCheckboxVisible) {
                const delBtn = document.getElementById('deleteBtnContainer');
                if (delBtn) delBtn.style.display = 'none';
                const checkAll = document.getElementById('checkAll');
                if (checkAll) checkAll.checked = false;
            }
        });
    }

    // Fallback: ensure delete and checkbox listeners attach
    document.addEventListener('DOMContentLoaded', function(){
        try{
            const deleteBtn = document.getElementById('deleteSelected');
            if(deleteBtn && !deleteBtn._attached) { deleteBtn.addEventListener('click', deleteSelectedUsers); deleteBtn._attached = true; }
            const checkAll = document.getElementById('checkAll');
            if(checkAll && !checkAll._attached) {
                checkAll.addEventListener('change', function(){ document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked); toggleDeleteButton(); });
                checkAll._attached = true;
            }
            document.querySelectorAll('.row-check').forEach(cb => {
                if(!cb._attached) { cb.addEventListener('change', toggleDeleteButton); cb._attached = true; }
            });
        } catch(e) { console && console.error(e); }
    });
</script>

<script>!function(){function s(){document.documentElement.style.setProperty("--sidebar-h",window.innerHeight+"px")}s();window.addEventListener("resize",s);window.addEventListener("orientationchange",function(){setTimeout(s,150)})}();</script>
</body>
</html>

