<?php
require_once '../config/auth.php';
require_once '../config/logger.php';
require_once '../config/email_helper.php';
checkRole('teknisi');
require_once '../config/koneksi.php';

$user = getUserInfo();

$_foto_q = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
mysqli_stmt_bind_param($_foto_q, "i", $user['id']);
mysqli_stmt_execute($_foto_q);
$_foto_r = mysqli_stmt_get_result($_foto_q);
$_foto_d = mysqli_fetch_assoc($_foto_r);
mysqli_stmt_close($_foto_q);
$_user_photo = !empty($_foto_d['foto']) ? '../uploads/profile_photos/' . $_foto_d['foto'] : '';

$assigned_query = "SELECT COUNT(*) as total FROM tickets WHERE handled_by = ? AND status = 'Assigned'";
$stmt_count = mysqli_prepare($conn, $assigned_query);
mysqli_stmt_bind_param($stmt_count, "i", $user['id']);
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$assigned_tickets = mysqli_fetch_assoc($count_result)['total'];
mysqli_stmt_close($stmt_count);

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ticket_id == 0) {
    header('Location: dashboard.php');
    exit();
}

$division_id = $user['division_id'];
$query_ticket = "SELECT t.*, u.nama as pelapor, u.email as pelapor_email, d.nama_divisi
                 FROM tickets t
                 LEFT JOIN users u ON t.user_id = u.id
                 LEFT JOIN divisions d ON t.assigned_division_id = d.id
                 WHERE t.id = $ticket_id AND t.assigned_division_id = $division_id AND t.handled_by = {$user['id']}";
$result_ticket = mysqli_query($conn, $query_ticket);

if (mysqli_num_rows($result_ticket) == 0) {
    header('Location: dashboard.php?error=not_found');
    exit();
}

$ticket = mysqli_fetch_assoc($result_ticket);

$status_config = [
    'Open'        => ['bg' => '#fef2f2', 'color' => '#dc2626', 'label' => 'Open'],
    'Assigned'    => ['bg' => '#eff6ff', 'color' => '#2563eb', 'label' => 'Assigned'],
    'In Progress' => ['bg' => '#fffbeb', 'color' => '#d97706', 'label' => 'In Progress'],
    'Resolved'    => ['bg' => '#f0fdf4', 'color' => '#16a34a', 'label' => 'Resolved']
];
$st = $status_config[$ticket['status']] ?? ['bg'=>'#f3f4f6','color'=>'#6b7280','label'=>$ticket['status']];

$priority_config = [
    'Low'    => ['color' => '#6b7280', 'icon' => 'bi-dash-circle'],
    'Medium' => ['color' => '#2563eb', 'icon' => 'bi-exclamation-circle'],
    'High'   => ['color' => '#d97706', 'icon' => 'bi-exclamation-triangle'],
    'Urgent' => ['color' => '#dc2626', 'icon' => 'bi-exclamation-octagon-fill']
];
$pr = !empty($ticket['priority']) ? ($priority_config[$ticket['priority']] ?? ['color'=>'#6b7280','icon'=>'bi-flag']) : ['color'=>'#9ca3af','icon'=>'bi-dash'];

$created = new DateTime($ticket['created_at']);
$now = new DateTime();
$diff = $created->diff($now);
if ($diff->days > 0) { $time_ago = $diff->days . ' hari lalu'; }
elseif ($diff->h > 0) { $time_ago = $diff->h . ' jam lalu'; }
else { $time_ago = $diff->i . ' menit lalu'; }

// Handle POST - update status
$update_error = '';
$update_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');
    
    if (!in_array($new_status, ['In Progress', 'Resolved'])) {
        $update_error = 'Status tidak valid.';
    } else {
        // Handle photo upload for Resolved
        $foto_perbaikan = null;
        if ($new_status === 'Resolved' && isset($_FILES['foto_perbaikan']) && $_FILES['foto_perbaikan']['error'] === 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['foto_perbaikan']['type'], $allowed)) {
                $ext = pathinfo($_FILES['foto_perbaikan']['name'], PATHINFO_EXTENSION);
                $filename = 'perbaikan_' . $ticket_id . '_' . time() . '.' . $ext;
                $upload_dir = '../uploads/perbaikan/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if (move_uploaded_file($_FILES['foto_perbaikan']['tmp_name'], $upload_dir . $filename)) {
                    $foto_perbaikan = $filename;
                }
            }
        }
        
        // Update ticket and set handled_by to current user
        if ($foto_perbaikan) {
            $stmt = mysqli_prepare($conn, "UPDATE tickets SET status = ?, catatan_teknisi = ?, foto_perbaikan = ?, handled_by = ?, updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssii", $new_status, $catatan, $foto_perbaikan, $user['id'], $ticket_id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE tickets SET status = ?, catatan_teknisi = ?, handled_by = ?, updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssii", $new_status, $catatan, $user['id'], $ticket_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            // Log activity using proper function
            $log_description = "Status diubah menjadi $new_status" . ($catatan ? " - Catatan: $catatan" : "");
            logTicketActivity($conn, $ticket_id, $user['id'], 'status_update', $ticket['status'], $new_status, $log_description);
            
            // Send email notification to reporter
            notifyStatusUpdate($conn, $ticket_id, $ticket['status'], $new_status, $user['id'], $catatan);
            
            $update_success = "Status tiket berhasil diubah menjadi $new_status.";
            // Refresh ticket data
            $result_ticket = mysqli_query($conn, $query_ticket);
            $ticket = mysqli_fetch_assoc($result_ticket);
            $st = $status_config[$ticket['status']] ?? ['bg'=>'#f3f4f6','color'=>'#6b7280','label'=>$ticket['status']];
        } else {
            $update_error = 'Gagal mengupdate status tiket.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiket #<?= $ticket['id'] ?> - Ticketing TVRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#f8f9fc}
        .sidebar{background:#10367D;min-height:100vh;color:#fff;position:fixed;top:0;left:0;width:230px;z-index:100;display:flex;flex-direction:column}
        .sidebar-brand{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,.08)}
        .sidebar-brand h5{font-size:1rem;font-weight:700;margin:0;letter-spacing:-.3px}
        .sidebar-brand small{display:block;font-size:.7rem;opacity:.5;margin-top:2px}
        .sidebar-nav{padding:16px 12px;flex:1}
        .sidebar .nav-link{color:rgba(255,255,255,.65);padding:10px 16px;font-size:.88rem;font-weight:500;border-radius:10px;margin-bottom:4px;display:flex;align-items:center;gap:12px;transition:all .2s;text-decoration:none}
        .sidebar .nav-link:hover{background:rgba(255,255,255,.1);color:#fff}
        .sidebar .nav-link.active{background:rgba(255,255,255,.18);color:#fff}
        .sidebar .nav-link i{font-size:1.05rem;width:20px;text-align:center}
        .sidebar-footer{padding:20px;border-top:1px solid rgba(255,255,255,.08)}
        .sidebar-footer .user-info{display:flex;align-items:center;gap:10px;margin-bottom:8px}
        .sidebar-footer .user-avatar{width:34px;height:34px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.85rem;overflow:hidden}
        .sidebar-footer .user-avatar img{width:100%;height:100%;object-fit:cover}
        .sidebar-footer .user-name{font-size:.85rem;font-weight:600}
        .sidebar-footer .user-role{font-size:.7rem;opacity:.5}
        .division-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:500;margin-bottom:12px;margin-left:44px}
        .btn-logout{width:100%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;padding:8px;border-radius:8px;font-size:.82rem;font-weight:500;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .2s}
        .btn-logout:hover{background:rgba(255,255,255,.2);color:#fff}
        .main-content{margin-left:230px;padding:28px 36px;min-height:100vh}
        .breadcrumb-bar{display:flex;align-items:center;gap:8px;margin-bottom:24px;font-size:.85rem;color:#6b7280}
        .breadcrumb-bar a{color:#10367D;text-decoration:none;font-weight:500}
        .breadcrumb-bar a:hover{text-decoration:underline}
        .breadcrumb-bar .sep{color:#d1d5db}
        .content-grid{display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start}
        .ticket-card{background:#fff;border-radius:16px;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.04)}
        .ticket-header{padding:28px 32px 20px;border-bottom:1px solid #f0f1f5}
        .ticket-header h2{font-size:1.3rem;font-weight:700;color:#1a1a2e;margin-bottom:6px}
        .ticket-id-row{display:flex;align-items:center;gap:10px}
        .ticket-id{font-size:.92rem;font-weight:600;color:#4b5563}
        .status-pill{display:inline-flex;align-items:center;padding:4px 14px;border-radius:20px;font-size:.75rem;font-weight:600}
        .ticket-body{padding:28px 32px}
        .info-row{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:28px;padding-bottom:28px;border-bottom:1px solid #f0f1f5}
        .info-block-label{font-size:.7rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px}
        .info-block-value{font-size:.88rem;font-weight:600;color:#1a1a2e;line-height:1.6}
        .info-block-value .sub{font-weight:400;color:#6b7280;font-size:.82rem}
        .desc-box{background:#f8f9fc;border-radius:12px;padding:20px;margin-bottom:24px;border:1px solid #eef0f5}
        .desc-box h4{font-size:.85rem;font-weight:700;color:#1a1a2e;margin-bottom:8px}
        .desc-box p{font-size:.85rem;color:#4b5563;line-height:1.7;margin:0}
        .attachment-box{margin-top:20px;padding:16px;background:#f8f9fc;border-radius:12px;border:1px solid #eef0f5}
        .attachment-box h4{font-size:.78rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
        .attachment-box img{width:100%;max-width:400px;border-radius:8px;cursor:pointer}
        .side-card{background:#fff;border-radius:16px;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,.04);padding:28px}
        .side-card h3{font-size:1rem;font-weight:700;color:#1a1a2e;margin-bottom:20px}
        .status-indicator{display:flex;align-items:center;gap:12px;padding:16px;border-radius:12px;margin-bottom:20px}
        .status-indicator .status-icon{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .status-indicator .status-text{font-size:.82rem}
        .status-indicator .status-text strong{display:block;font-size:.88rem;margin-bottom:2px}
        .update-form .form-label-sm{font-size:.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px}
        .update-form .form-select,.update-form .form-control{border-radius:10px;padding:10px 14px;font-size:.88rem;border:1px solid #e2e5eb;font-family:'Inter',sans-serif}
        .update-form .form-select:focus,.update-form .form-control:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08)}
        .update-form textarea{resize:vertical;min-height:100px}
        .btn-update{width:100%;background:#10367D;color:#fff;border:none;padding:12px;border-radius:10px;font-size:.88rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;justify-content:center;gap:8px}
        .btn-update:hover{background:#0c2d68}
        .btn-back-outline{width:100%;background:transparent;color:#6b7280;border:1px solid #e2e5eb;padding:10px;border-radius:10px;font-size:.85rem;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:6px;margin-top:10px}
        .btn-back-outline:hover{background:#f8f9fc;color:#374151;border-color:#d1d5db}
        .resolved-card{text-align:center;padding:24px}
        .resolved-card .check-icon{width:56px;height:56px;background:#f0fdf4;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;color:#16a34a;margin-bottom:12px}
        .resolved-card h4{font-size:.95rem;font-weight:700;color:#1a1a2e;margin-bottom:4px}
        .resolved-card p{font-size:.82rem;color:#9ca3af}
        .photo-upload-area{border:2px dashed #e2e5eb;border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:all .15s;margin-bottom:16px}
        .photo-upload-area:hover{border-color:#10367D;background:rgba(16,54,125,.02)}
        .photo-upload-area i{font-size:2rem;color:#d1d5db;display:block;margin-bottom:8px}
        .photo-upload-area p{font-size:.82rem;color:#9ca3af;margin:0}
        .photo-upload-area .file-name{font-size:.78rem;color:#10367D;font-weight:600;margin-top:8px;display:none}
        @media(max-width:1100px){.content-grid{grid-template-columns:1fr}.info-row{grid-template-columns:1fr}}
        @media(max-width:768px){.main-content{margin-left:0;padding:20px 16px}.ticket-header{padding:20px}.ticket-body{padding:20px}.side-card{padding:20px}}
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
    <button class="hamburger-menu" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <nav class="sidebar">
        <div class="sidebar-brand"><img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height:50px;display:block;margin:0 auto 8px;"><div style="text-align:center"><h5>TVRI Ticketing</h5><small>Support System</small></div></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($assigned_tickets>0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:0.7em"><?=$assigned_tickets?></span><?php endif; ?></a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'],0,1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Teknisi</div></div>
            </div>
            <div class="division-badge"><i class="bi bi-building"></i> <?= htmlspecialchars($ticket['nama_divisi'] ?? '') ?></div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>

    <div class="main-content">
        <div class="breadcrumb-bar">
            <a href="dashboard.php">Dashboard</a>
            <span class="sep">/</span>
            <span>Tiket #<?= $ticket['id'] ?></span>
        </div>

        <div class="content-grid">
            <!-- Ticket Detail -->
            <div class="ticket-card">
                <div class="ticket-header">
                    <h2><?= htmlspecialchars($ticket['judul']) ?></h2>
                    <div class="ticket-id-row">
                        <span class="ticket-id">Tiket #<?= $ticket['id'] ?></span>
                        <span class="status-pill" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>"><?= $st['label'] ?></span>
                        <?php if(!empty($ticket['priority'])): ?>
                        <span style="color:<?= $pr['color'] ?>;font-size:.82rem;font-weight:500"><i class="bi <?= $pr['icon'] ?>"></i> <?= $ticket['priority'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ticket-body">
                    <div class="info-row">
                        <div>
                            <div class="info-block-label">Pelapor</div>
                            <div class="info-block-value"><?= htmlspecialchars($ticket['pelapor'] ?? '-') ?><br><span class="sub"><?= htmlspecialchars($ticket['pelapor_email'] ?? '') ?></span></div>
                        </div>
                        <div>
                            <div class="info-block-label">Divisi Tujuan</div>
                            <div class="info-block-value"><?= htmlspecialchars($ticket['nama_divisi'] ?? '-') ?></div>
                        </div>
                        <div>
                            <div class="info-block-label">Dibuat</div>
                            <div class="info-block-value"><?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?><br><span class="sub"><?= $time_ago ?></span></div>
                        </div>
                        <div>
                            <div class="info-block-label">Terakhir Update</div>
                            <div class="info-block-value"><?= date('d M Y, H:i', strtotime($ticket['updated_at'])) ?></div>
                        </div>
                    </div>

                    <div class="desc-box">
                        <h4><i class="bi bi-text-paragraph" style="margin-right:6px"></i>Deskripsi</h4>
                        <p><?= nl2br(htmlspecialchars($ticket['deskripsi'] ?? '')) ?></p>
                    </div>

                    <?php if(!empty($ticket['lampiran'])): ?>
                    <div class="attachment-box">
                        <h4><i class="bi bi-paperclip" style="margin-right:4px"></i>Lampiran</h4>
                        <img src="../uploads/<?= htmlspecialchars($ticket['lampiran']) ?>" alt="Lampiran" onclick="window.open(this.src,'_blank')">
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($ticket['catatan_teknisi'])): ?>
                    <div class="desc-box" style="margin-top:20px;border-left:3px solid #10367D">
                        <h4><i class="bi bi-chat-left-text" style="margin-right:6px"></i>Catatan Teknisi</h4>
                        <p><?= nl2br(htmlspecialchars($ticket['catatan_teknisi'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($ticket['foto_perbaikan'])): ?>
                    <div class="attachment-box" style="margin-top:20px">
                        <h4><i class="bi bi-camera" style="margin-right:4px"></i>Foto Penyelesaian</h4>
                        <img src="../uploads/perbaikan/<?= htmlspecialchars($ticket['foto_perbaikan']) ?>" alt="Foto Resolved" onclick="window.open(this.src,'_blank')">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Side Panel -->
            <div>
                <div class="side-card">
                    <h3>Update Status</h3>

                    <!-- Current Status -->
                    <div class="status-indicator" style="background:<?= $st['bg'] ?>">
                        <div class="status-icon" style="background:<?= $st['color'] ?>;color:#fff"><i class="bi bi-info-lg"></i></div>
                        <div class="status-text"><strong>Status: <?= $st['label'] ?></strong><span style="color:<?= $st['color'] ?>">Tiket #<?= $ticket['id'] ?></span></div>
                    </div>

                    <?php if($ticket['status'] === 'Resolved'): ?>
                    <div class="resolved-card">
                        <div class="check-icon"><i class="bi bi-check-lg"></i></div>
                        <h4>Tiket Selesai</h4>
                        <p>Tiket ini sudah diselesaikan</p>
                    </div>
                    <?php elseif($ticket['status'] === 'Open'): ?>
                    <div style="text-align:center;padding:20px;color:#9ca3af">
                        <i class="bi bi-hourglass" style="font-size:2rem;display:block;margin-bottom:8px"></i>
                        <p style="font-size:.85rem">Tiket belum di-assign. Hubungi admin.</p>
                    </div>
                    <?php else: ?>
                    <form method="POST" enctype="multipart/form-data" class="update-form">
                        <div class="mb-3">
                            <label class="form-label-sm">Ubah Status</label>
                            <select name="new_status" class="form-select" id="statusSelect" required>
                                <option value="">-- Pilih Status --</option>
                                <?php if($ticket['status'] === 'Assigned'): ?>
                                <option value="In Progress">In Progress</option>
                                <option value="Resolved">Resolved</option>
                                <?php elseif($ticket['status'] === 'In Progress'): ?>
                                <option value="Resolved">Resolved</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-sm">Catatan (opsional)</label>
                            <textarea name="catatan" class="form-control" placeholder="Tambahkan catatan penyelesaian..."></textarea>
                        </div>

                        <div class="mb-3" id="photoSection" style="display:none">
                            <label class="form-label-sm">Foto Penyelesaian (opsional)</label>
                            <div class="photo-upload-area" onclick="document.getElementById('fotoResolved').click()">
                                <i class="bi bi-cloud-upload"></i>
                                <p>Klik untuk upload foto</p>
                                <div class="file-name" id="fileName"></div>
                            </div>
                            <div id="imagePreview" style="display:none;margin-top:12px;text-align:center">
                                <img id="previewImg" src="" alt="Preview" style="max-width:100%;max-height:300px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
                                <button type="button" onclick="clearPreview()" style="display:block;margin:10px auto 0;padding:6px 14px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;font-size:.82rem;cursor:pointer"><i class="bi bi-x-circle"></i> Hapus Foto</button>
                            </div>
                            <input type="file" name="foto_perbaikan" id="fotoResolved" accept="image/*" style="display:none" onchange="showFileName(this)">
                        </div>

                        <button type="submit" name="update_status" value="1" class="btn-update"><i class="bi bi-check-circle"></i> Update Status</button>
                    </form>
                    <?php endif; ?>

                    <a href="dashboard.php" class="btn-back-outline"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Show photo upload when Resolved selected
    document.getElementById('statusSelect')?.addEventListener('change', function(){
        var photoSection = document.getElementById('photoSection');
        if(photoSection) photoSection.style.display = this.value === 'Resolved' ? 'block' : 'none';
    });

    function showFileName(input){
        var fn = document.getElementById('fileName');
        var preview = document.getElementById('imagePreview');
        var previewImg = document.getElementById('previewImg');
        
        if(input.files && input.files[0]){
            fn.textContent = input.files[0].name;
            fn.style.display = 'block';
            
            // Show image preview
            var reader = new FileReader();
            reader.onload = function(e){
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function clearPreview(){
        document.getElementById('fotoResolved').value = '';
        document.getElementById('fileName').textContent = '';
        document.getElementById('fileName').style.display = 'none';
        document.getElementById('imagePreview').style.display = 'none';
        document.getElementById('previewImg').src = '';
    }

    <?php if($update_success): ?>
    Swal.fire({icon:'success',title:'Berhasil',text:'<?= addslashes($update_success) ?>',timer:2000,showConfirmButton:false}).then(()=>{window.location.href='dashboard.php';});
    <?php endif; ?>
    <?php if($update_error): ?>
    Swal.fire({icon:'error',title:'Gagal',text:'<?= addslashes($update_error) ?>'});
    <?php endif; ?>

    (function(){
        const hamburgerBtn=document.getElementById('hamburgerBtn');const sidebar=document.querySelector('.sidebar');const sidebarOverlay=document.getElementById('sidebarOverlay');
        if(!hamburgerBtn||!sidebar||!sidebarOverlay)return;
        hamburgerBtn.addEventListener('click',function(){this.classList.toggle('active');sidebar.classList.toggle('active');sidebarOverlay.classList.toggle('active');document.body.style.overflow=sidebar.classList.contains('active')?'hidden':'';});
        sidebarOverlay.addEventListener('click',function(){hamburgerBtn.classList.remove('active');sidebar.classList.remove('active');this.classList.remove('active');document.body.style.overflow='';});
        document.querySelectorAll('.sidebar .nav-link, .sidebar a').forEach(function(link){link.addEventListener('click',function(){if(window.innerWidth<=768){hamburgerBtn.classList.remove('active');sidebar.classList.remove('active');sidebarOverlay.classList.remove('active');document.body.style.overflow='';}});});
    })();
    </script>
</body>
</html>
