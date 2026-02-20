<?php
require_once '../config/auth.php';
checkRole('admin');
require_once '../config/koneksi.php';

$open_count_query = "SELECT COUNT(*) as total FROM tickets WHERE status = 'Open'";
$open_count_result = mysqli_query($conn, $open_count_query);
$open_count = mysqli_fetch_assoc($open_count_result)['total'];

$user = getUserInfo();

$_foto_q = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
mysqli_stmt_bind_param($_foto_q, "i", $user['id']);
mysqli_stmt_execute($_foto_q);
$_foto_r = mysqli_stmt_get_result($_foto_q);
$_foto_d = mysqli_fetch_assoc($_foto_r);
mysqli_stmt_close($_foto_q);
$_user_photo = !empty($_foto_d['foto']) ? '../uploads/profile_photos/' . $_foto_d['foto'] : '';

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($ticket_id == 0) { header('Location: dashboard.php'); exit(); }

$query_ticket = "SELECT t.*, u.nama as pelapor, u.email as pelapor_email
                 FROM tickets t LEFT JOIN users u ON t.user_id = u.id
                 WHERE t.id = $ticket_id";
$result_ticket = mysqli_query($conn, $query_ticket);
if (mysqli_num_rows($result_ticket) == 0) { header('Location: dashboard.php?error=not_found'); exit(); }
$ticket = mysqli_fetch_assoc($result_ticket);

$query_divisions = "SELECT * FROM divisions ORDER BY nama_divisi ASC";
$result_divisions = mysqli_query($conn, $query_divisions);

// Load semua teknisi beserta division_id-nya
$query_teknisi = "SELECT id, nama, division_id FROM users WHERE role = 'teknisi' ORDER BY nama ASC";
$result_teknisi = mysqli_query($conn, $query_teknisi);
$all_teknisi = [];
while ($tek = mysqli_fetch_assoc($result_teknisi)) {
    $all_teknisi[] = $tek;
}

// Teknisi yang sedang busy (Assigned atau In Progress) — tidak tersedia untuk assign baru
$busy_ids = [];
$busy_q = mysqli_query($conn, "SELECT DISTINCT handled_by FROM tickets WHERE status IN ('Assigned','In Progress') AND handled_by IS NOT NULL");
while ($brow = mysqli_fetch_assoc($busy_q)) { $busy_ids[] = intval($brow['handled_by']); }
foreach ($all_teknisi as &$tek) { $tek['busy'] = in_array(intval($tek['id']), $busy_ids); }
unset($tek);

// Assigned division name
$assigned_div_name = '-';
if (!empty($ticket['assigned_division_id'])) {
    $dq = mysqli_query($conn, "SELECT nama_divisi FROM divisions WHERE id = " . intval($ticket['assigned_division_id']));
    if ($dq && mysqli_num_rows($dq) > 0) $assigned_div_name = mysqli_fetch_assoc($dq)['nama_divisi'];
}

$pr_config = [
    'Low'    => ['color'=>'#6b7280','bg'=>'#f3f4f6','icon'=>'bi-dash-circle'],
    'Medium' => ['color'=>'#2563eb','bg'=>'#eff6ff','icon'=>'bi-exclamation-circle'],
    'High'   => ['color'=>'#d97706','bg'=>'#fffbeb','icon'=>'bi-exclamation-triangle'],
    'Urgent' => ['color'=>'#dc2626','bg'=>'#fef2f2','icon'=>'bi-exclamation-octagon-fill']
];
$st_config = [
    'Open'        => ['color'=>'#dc2626','bg'=>'#fef2f2','label'=>'Open'],
    'Assigned'    => ['color'=>'#16a34a','bg'=>'#f0fdf4','label'=>'Assigned'],
    'In Progress' => ['color'=>'#d97706','bg'=>'#fffbeb','label'=>'In Progress'],
    'Resolved'    => ['color'=>'#16a34a','bg'=>'#f0fdf4','label'=>'Resolved']
];
$pr = !empty($ticket['priority']) ? ($pr_config[$ticket['priority']] ?? ['color'=>'#6b7280','bg'=>'#f3f4f6','icon'=>'bi-flag']) : ['color'=>'#9ca3af','bg'=>'#f3f4f6','icon'=>'bi-dash','label'=>'Belum diset'];
$st = $st_config[$ticket['status']] ?? ['color'=>'#6b7280','bg'=>'#f3f4f6','label'=>$ticket['status']];


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Tiket #<?= $ticket['id'] ?> - TVRI Ticketing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        html{width:100%}
        body{font-family:'Inter',sans-serif;background:#eef1f8;font-size:14px;color:#1a1a2e}

        /* Sidebar - same as dashboard */
        .sidebar{background:#10367D;min-height:100vh;color:#fff;position:fixed;top:0;left:0;width:230px;z-index:100;display:flex;flex-direction:column}
        .sidebar-brand{padding:16px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;align-items:center;gap:8px}

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
        .page-wrapper{margin-left:230px;min-height:100vh;padding:32px 40px 60px}


        /* Content Grid */
        .content-grid{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start}

        /* Cards */
        .card-box{background:#fff;border:1px solid #e2e5eb;border-radius:12px;overflow:hidden}

        /* Status Banner */
        .status-banner{display:flex;align-items:flex-start;gap:16px;padding:20px 24px;border-bottom:1px solid #e2e5eb}
        .status-banner .sb-icon{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem}
        .status-banner .sb-icon.open{background:#fef2f2;color:#dc2626}
        .status-banner .sb-icon.assigned{background:#f0fdf4;color:#16a34a}
        .status-banner .sb-title{font-size:.95rem;font-weight:700;margin-bottom:4px}
        .status-banner .sb-desc{font-size:.82rem;color:#6b7280;line-height:1.6}

        /* Detail Section */
        .detail-section{padding:24px}
        .detail-section h3{font-size:1.05rem;font-weight:700;color:#1a1a2e;margin-bottom:20px}

        /* Info Grid */
        .info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0;margin-bottom:20px}
        .info-cell{padding:0 0 18px}
        .info-cell .ic-label{font-size:.68rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px}
        .info-cell .ic-value{font-size:.88rem;font-weight:600;color:#1a1a2e}
        .info-cell .ic-sub{font-size:.78rem;color:#6b7280;margin-top:1px}
        .info-divider{border-top:1px solid #eef0f5;margin:4px 0 18px;grid-column:1/-1}

        .ticket-number{padding:0 0 4px}
        .ticket-number .tn-label{font-size:.68rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px}
        .ticket-number .tn-value{font-size:1.1rem;font-weight:700;color:#1a1a2e}

        /* Deskripsi Box */
        .desc-box{background:#f8f9fc;border:1px solid #e2e5eb;border-radius:10px;padding:20px 24px;margin-top:24px}
        .desc-box h4{font-size:.92rem;font-weight:700;color:#1a1a2e;text-align:center;margin-bottom:6px}
        .desc-box .db-sub{font-size:.78rem;color:#6b7280;text-align:center;margin-bottom:16px;line-height:1.6}
        .desc-box .db-content{display:flex;gap:20px;align-items:flex-start}
        .desc-box .db-thumb{width:100px;height:100px;border-radius:8px;overflow:hidden;border:1px solid #e2e5eb;flex-shrink:0;background:#f3f4f6;display:flex;align-items:center;justify-content:center}
        .desc-box .db-thumb img{width:100%;height:100%;object-fit:cover}
        .desc-box .db-thumb i{font-size:2rem;color:#d1d5db}
        .desc-box .db-info{flex:1}
        .desc-box .db-info .dbi-label{font-size:.68rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
        .desc-box .db-info .dbi-title{font-size:.92rem;font-weight:700;color:#1a1a2e;margin-bottom:4px}
        .desc-box .db-info .dbi-text{font-size:.82rem;color:#6b7280;line-height:1.6;margin-bottom:10px}
        .btn-view{display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border:2px solid #10367D;border-radius:8px;background:transparent;color:#10367D;font-size:.82rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;transition:all .15s;text-decoration:none}
        .btn-view:hover{background:#10367D;color:#fff}

        /* Right Sidebar Cards */
        .rs-card{background:#fff;border:1px solid #e2e5eb;border-radius:12px;margin-bottom:16px;overflow:hidden}
        .rs-card h4{font-size:.95rem;font-weight:700;color:#1a1a2e;padding:20px 20px 0;margin-bottom:16px}
        .rs-card-body{padding:0 20px 20px}

        /* Assign Info Grid */
        .assign-info{display:grid;grid-template-columns:1fr 1fr;gap:0;margin-bottom:16px}
        .ai-cell{padding:0;margin-bottom:14px}
        .ai-cell .ai-label{font-size:.68rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
        .ai-cell .ai-value{font-size:.85rem;font-weight:600;color:#1a1a2e}
        .ai-cell .ai-sub{font-size:.75rem;color:#6b7280;margin-top:1px}

        .ai-divider{border-top:1px solid #eef0f5;margin:4px 0 14px;grid-column:1/-1}

        /* Summary Table */
        .summary-tbl{width:100%;margin-bottom:16px}
        .summary-tbl tr td{padding:5px 0;font-size:.84rem}
        .summary-tbl tr td:first-child{color:#6b7280}
        .summary-tbl tr td:last-child{text-align:right;font-weight:600;color:#1a1a2e}
        .summary-tbl .total-row td{padding-top:12px;border-top:1px solid #e2e5eb;font-size:.92rem;font-weight:700}
        .summary-tbl .total-row td:first-child{color:#10367D}
        .summary-tbl .total-row td:last-child{color:#10367D}

        .form-label-sm{font-size:.68rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;display:block}
        .form-select-custom{width:100%;border:1px solid #e2e5eb;border-radius:8px;padding:9px 12px;font-size:.84rem;font-family:'Inter',sans-serif;color:#1a1a2e;background:#fff;cursor:pointer}
        .form-select-custom:focus{outline:none;border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08)}

        .btn-assign{width:100%;background:#10367D;color:#fff;border:none;padding:12px;border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px}
        .btn-assign:hover{background:#0c2d68}
        .rs-footer{font-size:.72rem;color:#9ca3af;text-align:center;padding:8px 20px 16px}


        /* Responsive for Mobile */
        @media(max-width:1100px){
            .content-grid{grid-template-columns:1fr}
            .info-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:768px){
            .hamburger-menu{display:flex}
            .sidebar{position:fixed!important;top:0;left:-100%!important;width:260px!important;height:100vh;z-index:999;transition:left .3s;overflow-y:auto}
            .sidebar.active{left:0!important}
            html{overflow-x:hidden}

            .page-wrapper{margin-left:0;padding:56px 16px 20px}
            .breadcrumb-bar{font-size:.8rem;padding:12px 0}
            .ticket-header h2{font-size:1.1rem}
            .ticket-id-row{flex-direction:column;align-items:flex-start;gap:8px}
            .info-grid{grid-template-columns:1fr;gap:12px}
            .info-block{padding:12px}
            .desc-box{padding:16px}
            .assign-form{padding:20px 16px}
            .form-group label{font-size:.82rem}
            .form-select{font-size:.85rem;padding:10px 12px}
            .btn-assign{padding:12px;font-size:.88rem}
            .nav-link .badge{font-size:0.6em!important;padding:3px 6px}
        }
        @media(max-width:576px){
            .page-wrapper{padding:16px 12px}
            .breadcrumb-bar{font-size:.75rem;padding:10px 0}
            .ticket-header{padding:16px}
            .ticket-header h2{font-size:1rem}
            .ticket-id{font-size:.75rem}
            .status-pill{font-size:.7rem;padding:3px 8px}
            .ticket-body{padding:16px}
            .info-block-label{font-size:.7rem}
            .info-block-value{font-size:.82rem}
            .desc-box{padding:12px}
            .desc-box h4{font-size:.82rem}
            .desc-box p{font-size:.8rem}
            .assign-form{padding:16px 12px}
            .form-group{margin-bottom:14px}
            .btn-assign{padding:10px;font-size:.82rem}
            .btn-cancel{font-size:.82rem;padding:10px}
            .nav-link .badge{font-size:0.55em!important;padding:2px 5px;transform:translate(-50%,-50%)!important}
        }
/* Mobile Hamburger Menu */
        .hamburger-menu{display:none;position:fixed;top:20px;left:20px;z-index:1100;background:#fff;border:none;width:44px;height:44px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);cursor:pointer;padding:10px;flex-direction:column;justify-content:space-around;transition:all .3s}
        .hamburger-menu span{display:block;width:100%;height:3px;background:#10367D;border-radius:2px;transition:all .3s}
        .hamburger-menu.active span:nth-child(1){transform:rotate(45deg) translate(8px,8px)}
        .hamburger-menu.active span:nth-child(2){opacity:0}
        .hamburger-menu.active span:nth-child(3){transform:rotate(-45deg) translate(7px,-7px)}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:998;opacity:0;transition:opacity .3s}
        .sidebar-overlay.active{display:block;opacity:1}
    </style>
</head>
<body>
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger-menu" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-brand">
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
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Administrator</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="page-wrapper">

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- LEFT: Main -->
            <div>
                <div class="card-box">
                    <!-- Status Banner -->
                    <div class="status-banner">
                        <?php if($ticket['status'] == 'Open'): ?>
                        <div class="sb-icon open"><i class="bi bi-exclamation-circle-fill"></i></div>
                        <div>
                            <div class="sb-title">Tiket belum di-assign</div>
                            <div class="sb-desc">Tiket ini masih berstatus Open dan belum ditugaskan ke divisi manapun. Silakan review detail di bawah, lalu assign ke divisi yang sesuai melalui panel di sebelah kanan.</div>
                        </div>
                        <?php else: ?>
                        <div class="sb-icon assigned"><i class="bi bi-check-circle-fill"></i></div>
                        <div>
                            <div class="sb-title">Tiket sudah di-assign</div>
                            <div class="sb-desc">Tiket ini sudah ditugaskan ke divisi <strong><?= htmlspecialchars($assigned_div_name) ?></strong> dan sedang dalam proses penanganan.</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Detail Section -->
                    <div class="detail-section">
                        <h3>Detail Tiket</h3>
                        <div class="info-grid">
                            <div class="info-cell">
                                <div class="ic-label">Pelapor</div>
                                <div class="ic-value"><?= htmlspecialchars($ticket['pelapor']) ?></div>
                            </div>
                            <div class="info-cell">
                                <div class="ic-label">Tanggal Masuk</div>
                                <div class="ic-value"><?= date('D, d M Y', strtotime($ticket['created_at'])) ?></div>
                                <div class="ic-sub">pukul <?= date('H:i', strtotime($ticket['created_at'])) ?></div>
                            </div>
                            <div class="info-cell">
                                <div class="ic-label">Terakhir Update</div>
                                <div class="ic-value"><?= date('D, d M Y', strtotime($ticket['updated_at'])) ?></div>
                                <div class="ic-sub">pukul <?= date('H:i', strtotime($ticket['updated_at'])) ?></div>
                            </div>

                            <div class="info-divider"></div>

                            <div class="info-cell">
                                <div class="ic-label">Lokasi</div>
                                <div class="ic-value"><?= htmlspecialchars($ticket['lokasi']) ?></div>
                            </div>
                            <div class="info-cell">
                                <div class="ic-label">Email</div>
                                <div class="ic-value"><?= htmlspecialchars($ticket['pelapor_email'] ?? '-') ?></div>
                            </div>
                            <div class="info-cell">
                                <div class="ic-label">Priority</div>
                                <div class="ic-value" style="color:<?= $pr['color'] ?>"><i class="bi <?= $pr['icon'] ?>" style="font-size:.8rem"></i> <?= !empty($ticket['priority']) ? $ticket['priority'] : 'Belum diset' ?></div>
                            </div>

                            <div class="info-divider"></div>
                        </div>

                        <div class="ticket-number">
                            <div class="tn-label">Nomor Tiket</div>
                            <div class="tn-value">#<?= str_pad($ticket['id'], 7, '0', STR_PAD_LEFT) ?></div>
                        </div>
                    </div>

                    <!-- Deskripsi Box -->
                    <div style="padding:0 24px 24px">
                        <div class="desc-box">
                            <h4><?= htmlspecialchars($ticket['judul']) ?></h4>
                            <div class="db-sub"><?= htmlspecialchars(mb_strimwidth($ticket['deskripsi'], 0, 120, '...')) ?></div>
                            <div class="db-content">
                                <?php if(!empty($ticket['attachment'])): ?>
                                <div class="db-thumb">
                                    <img src="../uploads/tickets/<?= htmlspecialchars($ticket['attachment']) ?>" alt="Lampiran">
                                </div>
                                <?php else: ?>
                                <div class="db-thumb"><i class="bi bi-file-earmark-text"></i></div>
                                <?php endif; ?>
                                <div class="db-info">
                                    <div class="dbi-label">Deskripsi Masalah</div>
                                    <div class="dbi-text"><?= nl2br(htmlspecialchars(mb_strimwidth($ticket['deskripsi'], 0, 300, '...'))) ?></div>
                                    <?php if(!empty($ticket['attachment'])): ?>
                                    <a href="../uploads/tickets/<?= htmlspecialchars($ticket['attachment']) ?>" target="_blank" class="btn-view"><i class="bi bi-eye"></i> Lihat Lampiran</a>
                                    <?php else: ?>
                                    <button class="btn-view" onclick="Swal.fire({icon:'info',title:'Info',text:'Tiket ini tidak memiliki lampiran.'})"><i class="bi bi-paperclip"></i> Tidak ada lampiran</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>

            <!-- RIGHT: Sidebar -->
            <div>
                <!-- Assign Summary -->
                <div class="rs-card">
                    <h4>Assign Tiket</h4>
                    <div class="rs-card-body">
                        <div class="assign-info">
                            <div class="ai-cell">
                                <div class="ai-label">Status</div>
                                <div class="ai-value"><span style="background:<?=$st['bg']?>;color:<?=$st['color']?>;padding:2px 10px;border-radius:4px;font-size:.75rem"><?=$st['label']?></span></div>
                            </div>
                            <div class="ai-cell">
                                <div class="ai-label">Priority</div>
                                <div class="ai-value" style="color:<?=$pr['color']?>"><i class="bi <?=$pr['icon']?>" style="font-size:.75rem"></i> <?=!empty($ticket['priority']) ? $ticket['priority'] : 'Belum diset'?></div>
                            </div>
                            <div class="ai-divider"></div>
                            <div class="ai-cell" style="grid-column:1/-1">
                                <div class="ai-label">Divisi Saat Ini</div>
                                <div class="ai-value"><?= $ticket['status'] == 'Open' ? '<span style="color:#9ca3af;font-weight:400">Belum di-assign</span>' : htmlspecialchars($assigned_div_name) ?></div>
                            </div>
                        </div>

                        <form action="proses_assign.php" method="POST" id="formAssign">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="ticket_id" value="<?=$ticket['id']?>">
                            <div style="margin-bottom:14px">
                                <label class="form-label-sm">Divisi Tujuan</label>
                                <select class="form-select-custom" name="division_id" id="divisionSelect" required onchange="loadTeknisi(this.value)">
                                    <option value="">-- Pilih Divisi --</option>
                                    <?php while($division = mysqli_fetch_assoc($result_divisions)): ?>
                                    <option value="<?=$division['id']?>" <?= ($ticket['assigned_division_id'] == $division['id']) ? 'selected' : '' ?>><?=htmlspecialchars($division['nama_divisi'])?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div style="margin-bottom:14px">
                                <label class="form-label-sm">Teknisi yang Ditugaskan <span style="color:#dc2626">*</span></label>
                                <select class="form-select-custom" name="teknisi_id" id="teknisiSelect" required>
                                    <option value="">-- Pilih Divisi dahulu --</option>
                                </select>
                                <div id="teknisiHint" style="font-size:.72rem;color:#9ca3af;margin-top:4px"></div>
                            </div>
                            <div style="margin-bottom:6px">
                                <label class="form-label-sm">Priority</label>
                                <select class="form-select-custom" name="priority" id="prioritySelect" required>
                                    <option value="Low" <?=$ticket['priority']=='Low'?'selected':''?>>Low</option>
                                    <option value="Medium" <?=(empty($ticket['priority']) || $ticket['priority']=='Medium')?'selected':''?>>Medium</option>
                                    <option value="High" <?=$ticket['priority']=='High'?'selected':''?>>High</option>
                                    <option value="Urgent" <?=$ticket['priority']=='Urgent'?'selected':''?>>Urgent</option>
                                </select>
                            </div>
                        </form>
                        <button type="button" class="btn-assign" onclick="submitAssign()"><i class="bi bi-person-plus"></i> Assign Tiket</button>
                    </div>
                    <div class="rs-footer">Pastikan data sudah benar sebelum assign</div>
                </div>

                <!-- Ticket Info Summary -->
                <div class="rs-card">
                    <h4>Ringkasan Tiket</h4>
                    <div class="rs-card-body">
                        <table class="summary-tbl">
                            <tr><td>Pelapor</td><td><?= htmlspecialchars($ticket['pelapor']) ?></td></tr>
                            <tr><td>Lokasi</td><td><?= htmlspecialchars(mb_strimwidth($ticket['lokasi'],0,25,'...')) ?></td></tr>
                            <tr><td>Priority</td><td style="color:<?=$pr['color']?>"><?=!empty($ticket['priority']) ? $ticket['priority'] : 'Belum diset'?></td></tr>
                            <tr><td>Lampiran</td><td><?= !empty($ticket['attachment']) ? 'Ada' : 'Tidak ada' ?></td></tr>
                            <tr class="total-row"><td>Status</td><td><?=$st['label']?></td></tr>
                        </table>
                        <a href="dashboard.php" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border:1px solid #e2e5eb;border-radius:8px;color:#6b7280;font-size:.82rem;font-weight:500;text-decoration:none;transition:all .15s;margin-top:8px"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Data tecnisi per divisi dari PHP
    const teknisiData = <?= json_encode(array_map(function($t){ return ['id'=>$t['id'],'nama'=>$t['nama'],'div'=>$t['division_id'],'busy'=>(bool)$t['busy']]; }, $all_teknisi)) ?>;

    function loadTeknisi(divId) {
        const sel = document.getElementById('teknisiSelect');
        const hint = document.getElementById('teknisiHint');
        sel.innerHTML = '<option value="">-- Pilih Teknisi --</option>';
        if (!divId) { sel.innerHTML = '<option value="">-- Pilih Divisi dahulu --</option>'; hint.textContent = ''; return; }
        const filtered = teknisiData.filter(t => String(t.div) === String(divId));
        if (filtered.length === 0) {
            sel.innerHTML = '<option value="">Tidak ada teknisi di divisi ini</option>';
            hint.textContent = 'Tambahkan teknisi ke divisi ini terlebih dahulu.';
            hint.style.color = '#dc2626';
        } else {
            filtered.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.busy ? t.nama + ' (Sedang Bertugas)' : t.nama;
                if (t.busy) { opt.disabled = true; opt.style.color = '#9ca3af'; }
                sel.appendChild(opt);
            });
            const available = filtered.filter(t => !t.busy).length;
            hint.textContent = available + ' dari ' + filtered.length + ' teknisi tersedia';
            hint.style.color = available > 0 ? '#16a34a' : '#dc2626';
        }
    }

    // Trigger on page load jika divisi sudah terisi
    window.addEventListener('DOMContentLoaded', function(){
        const divId = document.getElementById('divisionSelect').value;
        if (divId) loadTeknisi(divId);
    });

    function submitAssign() {
        const div = document.getElementById('divisionSelect');
        const tek = document.getElementById('teknisiSelect');
        if (!div.value) {
            Swal.fire({icon:'warning',title:'Pilih Divisi',text:'Silakan pilih divisi tujuan terlebih dahulu.'});
            return;
        }
        if (!tek.value) {
            Swal.fire({icon:'warning',title:'Pilih Teknisi',text:'Silakan pilih teknisi yang akan ditugaskan.'});
            return;
        }
        const divText = div.selectedOptions[0].text;
        const tekText = tek.selectedOptions[0].text;
        const prText = document.getElementById('prioritySelect').value;
        Swal.fire({
            title:'Konfirmasi Assign',
            html:'Assign tiket <strong>#<?=$ticket['id']?></strong> ke divisi <strong>'+divText+'</strong><br>Teknisi: <strong>'+tekText+'</strong><br>Priority: <strong>'+prText+'</strong>',
            icon:'question',
            showCancelButton:true,
            confirmButtonColor:'#10367D',
            cancelButtonColor:'#6b7280',
            confirmButtonText:'<i class="bi bi-check-lg"></i> Ya, Assign',
            cancelButtonText:'Batal'
        }).then((result) => {
            if (result.isConfirmed) document.getElementById('formAssign').submit();
        });
    }
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('error') === 'empty') Swal.fire({icon:'error',title:'Gagal',text:'Semua field wajib diisi termasuk teknisi.'});
    else if (urlParams.get('error') === 'invalid_teknisi') Swal.fire({icon:'error',title:'Gagal',text:'Teknisi tidak valid atau tidak sesuai divisi.'});
    else if (urlParams.get('error') === 'invalid_priority') Swal.fire({icon:'error',title:'Gagal',text:'Priority tidak valid.'});
    else if (urlParams.get('error') === 'gagal') Swal.fire({icon:'error',title:'Gagal',text:'Terjadi kesalahan saat assign.'});
    </script>
<script>(function(){const h=document.getElementById('hamburgerBtn');const s=document.querySelector('.sidebar');const o=document.getElementById('sidebarOverlay');if(!h||!s||!o)return;h.addEventListener('click',function(){this.classList.toggle('active');s.classList.toggle('active');o.classList.toggle('active');document.body.style.overflow=s.classList.contains('active')?'hidden':'';});o.addEventListener('click',function(){h.classList.remove('active');s.classList.remove('active');this.classList.remove('active');document.body.style.overflow='';});document.querySelectorAll('.sidebar .nav-link, .sidebar a').forEach(function(l){l.addEventListener('click',function(){if(window.innerWidth<=768){h.classList.remove('active');s.classList.remove('active');o.classList.remove('active');document.body.style.overflow='';}});});})();</script>
</body>
</html>