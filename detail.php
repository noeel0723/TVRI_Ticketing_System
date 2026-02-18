<?php
require_once __DIR__ . '/config/bootstrap.php';
startSecureSession();
require_once 'config/koneksi.php';
require_once 'config/logger.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$user_division = isset($_SESSION['division_id']) ? $_SESSION['division_id'] : null;

// Ambil ID tiket
$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ticket_id == 0) {
    header('Location: ' . $user_role . '/dashboard.php');
    exit();
}

// Ambil data tiket + teknisi yang menangani (dari kolom handled_by)
$query = "SELECT t.*, 
          u.nama as pelapor, u.username as username_pelapor, u.email as email_pelapor, u.foto as pelapor_foto,
          d.nama_divisi,
          tek.nama as teknisi_nama, tek.email as teknisi_email, tek.foto as teknisi_foto
          FROM tickets t
          LEFT JOIN users u ON t.user_id = u.id
          LEFT JOIN divisions d ON t.assigned_division_id = d.id
          LEFT JOIN users tek ON t.handled_by = tek.id
          WHERE t.id = $ticket_id
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    header('Location: ' . $user_role . '/dashboard.php');
    exit();
}

$ticket = mysqli_fetch_assoc($result);

// Authorization check
if ($user_role == 'user' && $ticket['user_id'] != $user_id) {
    die('Anda tidak memiliki akses untuk melihat tiket ini');
}

if ($user_role == 'teknisi' && $ticket['assigned_division_id'] != $user_division) {
    die('Anda tidak memiliki akses untuk melihat tiket ini');
}

// Ambil riwayat log
$logs = getTicketLogs($conn, $ticket_id);

// Status & Priority config
$st_config = [
    'Open'        => ['color'=>'#dc2626','bg'=>'#fef2f2','label'=>'Open'],
    'Assigned'    => ['color'=>'#16a34a','bg'=>'#f0fdf4','label'=>'Assigned'],
    'In Progress' => ['color'=>'#d97706','bg'=>'#fffbeb','label'=>'In Progress'],
    'Resolved'    => ['color'=>'#16a34a','bg'=>'#f0fdf4','label'=>'Resolved']
];
$pr_config = [
    'Low'    => ['color'=>'#6b7280','bg'=>'#f3f4f6'],
    'Medium' => ['color'=>'#2563eb','bg'=>'#eff6ff'],
    'High'   => ['color'=>'#d97706','bg'=>'#fffbeb'],
    'Urgent' => ['color'=>'#dc2626','bg'=>'#fef2f2']
];
$st = $st_config[$ticket['status']] ?? ['color'=>'#6b7280','bg'=>'#f3f4f6','label'=>$ticket['status']];
$pr = !empty($ticket['priority']) ? ($pr_config[$ticket['priority']] ?? ['color'=>'#6b7280','bg'=>'#f3f4f6']) : ['color'=>'#9ca3af','bg'=>'#f3f4f6'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tiket #<?= $ticket['id'] ?> - TVRI Ticketing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        html{width:100%}
        body{font-family:'Inter',sans-serif;background:#eef1f8;font-size:14px;color:#1a1a2e}

        .page-wrapper{max-width:1200px;margin:0 auto;padding:32px 24px}

        /* Top Bar */
        .top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
        .breadcrumb-nav{display:flex;align-items:center;gap:8px;font-size:.85rem;color:#6b7280}
        .breadcrumb-nav a{color:#10367D;text-decoration:none;font-weight:500}
        .breadcrumb-nav a:hover{text-decoration:underline}
        .breadcrumb-nav i{font-size:.7rem}
        .btn-back{background:#fff;border:1px solid #e2e5eb;padding:8px 18px;border-radius:8px;font-size:.84rem;font-weight:500;color:#4a4a4a;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .15s}
        .btn-back:hover{background:#f8f9fc;color:#1a1a2e}

        /* Main Grid */
        .main-grid{display:grid;grid-template-columns:1fr 320px;gap:24px}

        /* Card */
        .card-box{background:#fff;border:1px solid #e2e5eb;border-radius:12px;overflow:hidden}

        /* Header Section */
        .invoice-header{padding:28px 32px;background:linear-gradient(135deg, #10367D 0%, #0c2d68 100%);color:#fff}
        .ih-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px}
        .ih-brand{display:flex;align-items:center;gap:12px}
        .ih-logo{width:48px;height:48px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.4rem}
        .ih-info h3{font-size:1.1rem;font-weight:700;margin-bottom:2px}
        .ih-info p{font-size:.8rem;opacity:.7;margin:0}
        .ih-bill h4{font-size:.75rem;font-weight:600;opacity:.7;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
        .ih-bill p{font-size:.88rem;margin:0}
        .ih-bottom{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
        .ih-item h5{font-size:.7rem;font-weight:600;opacity:.7;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
        .ih-item p{font-size:.92rem;font-weight:600;margin:0}
        .ih-item .pill{display:inline-block;font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:4px;margin-top:2px}

        /* Content Section */
        .invoice-content{padding:28px 32px}
        .section-title{font-size:.85rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.6px;margin-bottom:16px}

        .item-table{width:100%;border-collapse:collapse;margin-bottom:24px}
        .item-table thead th{font-size:.7rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;padding:10px 12px;border-bottom:2px solid #e2e5eb;text-align:left}
        .item-table tbody td{padding:14px 12px;border-bottom:1px solid #f3f4f8;font-size:.85rem;color:#374151}
        .item-table tbody td:last-child{text-align:right}
        .item-title{font-weight:600;color:#1a1a2e}
        .item-desc{font-size:.78rem;color:#6b7280;margin-top:2px}

        /* Description Box */
        .desc-box{background:#f8f9fc;border:1px solid #e2e5eb;border-radius:10px;padding:20px;margin-bottom:24px}
        .desc-box h4{font-size:.88rem;font-weight:700;color:#1a1a2e;margin-bottom:10px}
        .desc-box p{font-size:.84rem;color:#4a4a4a;line-height:1.7;margin:0;white-space:pre-wrap}

        /* Attachment */
        .attach-section{margin-bottom:24px;max-width:520px}
        .attach-section img{width:100%;max-width:520px;max-height:320px;height:auto;object-fit:contain;border-radius:10px;border:1px solid #e2e5eb;cursor:pointer;transition:transform .15s}
        .attach-section img:hover{transform:scale(1.01)}
        .attach-label{font-size:.72rem;color:#9ca3af;text-align:center;margin-top:8px}

        .attachment-display{margin-top:12px;margin-bottom:16px;border-radius:8px;overflow:hidden;border:1px solid #e2e5eb;max-width:520px}
        .attachment-display img{max-width:100%;max-height:320px;height:auto;display:block;object-fit:contain}

        .zoom-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);display:none;align-items:center;justify-content:center;z-index:9999}
        .zoom-overlay.show{display:flex}
        .zoom-img{max-width:92vw;max-height:92vh;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
        .zoom-close{position:absolute;top:20px;right:24px;background:rgba(255,255,255,.9);border:none;border-radius:999px;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:1rem;cursor:pointer}
        .zoomable-image{cursor:zoom-in}

        /* Payment Method Section */
        .payment-section{background:#f8f9fc;border:1px solid #e2e5eb;border-radius:10px;padding:20px}
        .payment-section h4{font-size:.88rem;font-weight:700;color:#1a1a2e;margin-bottom:14px}
        .pm-row{display:flex;align-items:center;gap:12px;margin-bottom:10px}
        .pm-icon{width:36px;height:36px;background:#10367D;color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
        .pm-info .pm-label{font-size:.72rem;color:#6b7280}
        .pm-info .pm-value{font-size:.88rem;font-weight:600;color:#1a1a2e}

        /* Summary Table */
        .summary-table{margin-top:24px;border-top:2px solid #e2e5eb;padding-top:16px}
        .summary-table .sum-row{display:flex;justify-content:space-between;padding:8px 0;font-size:.85rem}
        .sum-row .sum-label{color:#6b7280}
        .sum-row .sum-value{font-weight:600;color:#1a1a2e}
        .sum-row.total{border-top:1px solid #e2e5eb;padding-top:12px;margin-top:8px}
        .sum-row.total .sum-label{font-size:.92rem;font-weight:700;color:#10367D}
        .sum-row.total .sum-value{font-size:1.1rem;font-weight:700;color:#10367D}

        /* Right Sidebar */
        .rs-card{background:#fff;border:1px solid #e2e5eb;border-radius:12px;margin-bottom:16px;overflow:hidden}
        .rs-card h4{font-size:.95rem;font-weight:700;color:#1a1a2e;padding:20px 20px 0}
        .rs-card-body{padding:16px 20px 20px}

        .client-info{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
        .client-avatar{width:42px;height:42px;background:#10367D;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.95rem;flex-shrink:0}
        .client-details h5{font-size:.92rem;font-weight:700;color:#1a1a2e;margin-bottom:2px}
        .client-details p{font-size:.78rem;color:#6b7280;margin:0}

        .info-line{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:.82rem}
        .info-line i{color:#9ca3af;font-size:.85rem;width:16px}
        .info-line span{color:#4a4a4a}

        .rs-divider{border-top:1px solid #eef0f5;margin:14px 0}

        .amount-box{background:linear-gradient(135deg, #10367D 0%, #0c2d68 100%);border-radius:10px;padding:20px;text-align:center;margin-bottom:16px}
        .amount-box .ab-label{font-size:.72rem;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
        .amount-box .ab-amount{font-size:1.8rem;font-weight:700;color:#fff;margin-bottom:4px}
        .amount-box .ab-sub{font-size:.78rem;color:rgba(255,255,255,.7)}

        .btn-action{width:100%;padding:12px;border-radius:10px;font-size:.88rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;border:none}
        .btn-primary-action{background:#10367D;color:#fff;margin-bottom:10px}
        .btn-primary-action:hover{background:#0c2d68;color:#fff}
        .btn-outline-action{background:#fff;color:#10367D;border:2px solid #10367D}
        .btn-outline-action:hover{background:#f0f4ff}

        .switch-label{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;font-size:.82rem}
        .switch-label input{width:40px;height:22px}

        /* Responsive for Mobile */
        @media(max-width:992px){
            .main-grid{grid-template-columns:1fr}
            .page-wrapper{padding:20px 16px}
            .top-bar{flex-direction:column;align-items:flex-start;gap:12px}
            .ih-bottom{grid-template-columns:repeat(2,1fr);gap:12px}
            .item-table thead{display:none}
            .item-table tbody td{display:block;text-align:left!important;padding:8px 12px}
            .item-table tbody td:first-child{font-weight:700;padding-top:12px}
            .item-table tbody td:last-child{padding-bottom:12px;border-bottom:2px solid #e2e5eb}
            .summary-table .sum-row{font-size:.82rem}
        }
        @media(max-width:768px){html,body{overflow-x:hidden;width:100%}
            .page-wrapper{padding:16px 12px}
            .invoice-header{padding:20px 16px}
            .invoice-content{padding:20px 16px}
            .ih-top{flex-direction:column;gap:16px;align-items:flex-start}
            .ih-bottom{grid-template-columns:1fr;gap:10px}
            .ih-logo{width:40px;height:40px;font-size:1.2rem}
            .ih-info h3{font-size:1rem}
            .breadcrumb-nav{font-size:.78rem}
            .btn-back{padding:6px 14px;font-size:.8rem}
            .desc-box{padding:16px}
            .payment-section{padding:16px}
            .rs-card h4{font-size:.88rem;padding:16px 16px 0}
            .rs-card-body{padding:12px 16px 16px}
            .amount-box .ab-amount{font-size:1.5rem}
            .attach-section img,.attachment-display{max-width:100%}
            .pm-row{gap:10px}
            .pm-icon{width:32px;height:32px;font-size:.85rem}
        }
        @media(max-width:576px){html,body{overflow-x:hidden;width:100%}
            .page-wrapper{padding:12px 8px}
            .invoice-header{padding:16px 12px}
            .invoice-content{padding:16px 12px}
            .top-bar{gap:8px}
            .breadcrumb-nav{font-size:.75rem}
            .btn-back{font-size:.75rem;padding:6px 12px}
            .ih-info h3{font-size:.92rem}
            .ih-item h5{font-size:.65rem}
            .ih-item p{font-size:.82rem}
            .section-title{font-size:.78rem}
            .desc-box{padding:12px}
            .desc-box h4{font-size:.82rem}
            .desc-box p{font-size:.8rem}
            .payment-section{padding:12px}
            .payment-section h4{font-size:.82rem}
            .pm-info .pm-value{font-size:.8rem}
            .sum-row{font-size:.78rem}
            .rs-card h4{font-size:.82rem;padding:12px 12px 0}
            .rs-card-body{padding:10px 12px 12px}
            .client-avatar{width:36px;height:36px;font-size:.85rem}
            .client-details h5{font-size:.85rem}
            .client-details p{font-size:.72rem}
            .info-line{font-size:.76rem}
            .amount-box{padding:16px}
            .amount-box .ab-amount{font-size:1.3rem}
            .btn-action{padding:10px;font-size:.82rem}
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="breadcrumb-nav">
                <a href="<?= $user_role ?>/dashboard.php">Dashboard</a>
                <i class="bi bi-chevron-right"></i>
                <span>Detail Tiket</span>
            </div>
            <a href="<?= $user_role ?>/dashboard.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- LEFT: Main Content -->
            <div>
                <div class="card-box">
                    <!-- Invoice Header -->
                    <div class="invoice-header">
                        <div class="ih-top">
                            <div class="ih-brand">
                                <div class="ih-logo"><i class="bi bi-broadcast-pin"></i></div>
                                <div class="ih-info">
                                    <h3>TVRI Ticketing</h3>
                                    <p>Sistem Pengelolaan Tiket Internal</p>
                                </div>
                            </div>
                            <div class="ih-bill">
                                <h4>Ditangani Oleh</h4>
                                <p><?= !empty($ticket['nama_divisi']) ? htmlspecialchars($ticket['nama_divisi']) : 'Belum di-assign' ?></p>
                            </div>
                        </div>
                        <div class="ih-bottom">
                            <div class="ih-item">
                                <h5>Nomor Tiket</h5>
                                <p>TK-<?= str_pad($ticket['id'], 7, '0', STR_PAD_LEFT) ?></p>
                            </div>
                            <div class="ih-item">
                                <h5>Tanggal Dibuat</h5>
                                <p><?= date('d M Y', strtotime($ticket['created_at'])) ?></p>
                            </div>
                            <div class="ih-item">
                                <h5>Status</h5>
                                <span class="pill" style="background:<?=$st['bg']?>;color:<?=$st['color']?>"><?=$st['label']?></span>
                            </div>
                            <div class="ih-item">
                                <h5>Priority</h5>
                                <span class="pill" style="background:<?=$pr['bg']?>;color:<?=$pr['color']?>"><?=!empty($ticket['priority']) ? $ticket['priority'] : 'Belum diset'?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Content -->
                    <div class="invoice-content">
                        <div class="section-title">Detail Masalah</div>
                        <table class="item-table">
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Lokasi</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="item-title"><?= htmlspecialchars($ticket['judul']) ?></div>
                                        <div class="item-desc">ID: #<?= $ticket['id'] ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($ticket['lokasi']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($ticket['created_at'])) ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="desc-box">
                            <h4>Deskripsi Lengkap</h4>
                            <p><?= htmlspecialchars($ticket['deskripsi']) ?></p>
                        </div>

                        <?php if(!empty($ticket['attachment'])): ?>
                        <div class="section-title">Lampiran</div>
                        <div class="attach-section">
                            <img src="uploads/tickets/<?= htmlspecialchars($ticket['attachment']) ?>" 
                                 alt="Lampiran" 
                                 class="zoomable-image">
                            <div class="attach-label"><i class="bi bi-info-circle"></i> Klik untuk memperbesar</div>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($ticket['catatan_teknisi'])): ?>
                        <div class="section-title">Catatan Teknisi</div>
                        <div class="desc-box">
                            <h4><i class="bi bi-wrench"></i> Catatan Penanganan</h4>
                            <p><?= htmlspecialchars($ticket['catatan_teknisi']) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty($ticket['foto_perbaikan'])): ?>
                        <div class="section-title">Foto Perbaikan</div>
                        <div class="attachment-display">
                            <img class="zoomable-image" src="uploads/perbaikan/<?= htmlspecialchars($ticket['foto_perbaikan']) ?>" alt="Foto Perbaikan">
                        </div>
                        <?php endif; ?>

                        <div class="section-title">Informasi Penanganan</div>
                        <div class="payment-section">
                            <h4>Status Penanganan</h4>
                            <div class="pm-row">
                                <div class="pm-icon"><i class="bi bi-clipboard-check"></i></div>
                                <div class="pm-info">
                                    <div class="pm-label">Status Saat Ini</div>
                                    <div class="pm-value" style="color:<?=$st['color']?>"><?=$st['label']?></div>
                                </div>
                            </div>
                            <div class="pm-row">
                                <div class="pm-icon"><i class="bi bi-building"></i></div>
                                <div class="pm-info">
                                    <div class="pm-label">Divisi Penanganan</div>
                                    <div class="pm-value"><?= !empty($ticket['nama_divisi']) ? htmlspecialchars($ticket['nama_divisi']) : 'Belum di-assign' ?></div>
                                </div>
                            </div>

                            <div class="summary-table">
                                <div class="sum-row">
                                    <span class="sum-label">Tanggal Dibuat</span>
                                    <span class="sum-value"><?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></span>
                                </div>
                                <div class="sum-row">
                                    <span class="sum-label">Terakhir Update</span>
                                    <span class="sum-value"><?= date('d M Y, H:i', strtotime($ticket['updated_at'])) ?></span>
                                </div>
                                <div class="sum-row total">
                                    <span class="sum-label">Status Tiket</span>
                                    <span class="sum-value" style="color:<?=$st['color']?>"><?=$st['label']?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Sidebar -->
            <div>

            <div class="zoom-overlay" id="zoomOverlay" aria-hidden="true">
                <button class="zoom-close" id="zoomClose" aria-label="Close zoom"><i class="bi bi-x"></i></button>
                <img class="zoom-img" id="zoomImage" src="" alt="Zoomed image">
            </div>

            <script>
            (function() {
                const overlay = document.getElementById('zoomOverlay');
                const zoomImg = document.getElementById('zoomImage');
                const closeBtn = document.getElementById('zoomClose');
                const zoomables = document.querySelectorAll('.zoomable-image');

                function openZoom(src) {
                    zoomImg.src = src;
                    overlay.classList.add('show');
                    overlay.setAttribute('aria-hidden', 'false');
                }

                function closeZoom() {
                    overlay.classList.remove('show');
                    overlay.setAttribute('aria-hidden', 'true');
                    zoomImg.src = '';
                }

                zoomables.forEach(img => {
                    img.addEventListener('click', () => openZoom(img.src));
                });

                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        closeZoom();
                    }
                });

                closeBtn.addEventListener('click', closeZoom);
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        closeZoom();
                    }
                });
            })();
            </script>
                <!-- Client Details -->
                <div class="rs-card">
                    <h4>Pelapor</h4>
                    <div class="rs-card-body">
                        <div class="client-info">
                            <?php if(!empty($ticket['pelapor_foto'])): ?>
                                <div class="client-avatar"><img src="uploads/profile_photos/<?= htmlspecialchars($ticket['pelapor_foto']) ?>" alt="Pelapor" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
                            <?php else: ?>
                                <div class="client-avatar"><?= strtoupper(substr($ticket['pelapor'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="client-details">
                                <h5><?= htmlspecialchars($ticket['pelapor']) ?></h5>
                                <p><?= htmlspecialchars($ticket['username_pelapor']) ?></p>
                            </div>
                        </div>
                        <div class="rs-divider"></div>
                        <div class="info-line">
                            <i class="bi bi-envelope"></i>
                            <span><?= htmlspecialchars($ticket['email_pelapor'] ?? 'Tidak ada email') ?></span>
                        </div>
                        <?php if(!empty($ticket['lokasi'])): ?>
                        <div class="info-line">
                            <i class="bi bi-geo-alt"></i>
                            <span><?= htmlspecialchars($ticket['lokasi']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Teknisi Penanganan (jika sudah assigned) -->
                <?php if($ticket['status'] != 'Open' && !empty($ticket['teknisi_nama'])): ?>
                <div class="rs-card">
                    <h4>Teknisi Penanganan</h4>
                    <div class="rs-card-body">
                        <div class="client-info">
                            <?php if(!empty($ticket['teknisi_foto'])): ?>
                                <div class="client-avatar"><img src="uploads/profile_photos/<?= htmlspecialchars($ticket['teknisi_foto']) ?>" alt="Teknisi" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
                            <?php else: ?>
                                <div class="client-avatar"><?= strtoupper(substr($ticket['teknisi_nama'], 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="client-details">
                                <h5><?= htmlspecialchars($ticket['teknisi_nama']) ?></h5>
                                <p><?= htmlspecialchars($ticket['nama_divisi'] ?? 'Teknisi') ?></p>
                            </div>
                        </div>
                        <?php if(!empty($ticket['teknisi_email'])): ?>
                        <div class="rs-divider"></div>
                        <div class="info-line">
                            <i class="bi bi-envelope"></i>
                            <span><?= htmlspecialchars($ticket['teknisi_email']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Amount Due -->
                <div class="rs-card">
                    <div class="rs-card-body" style="padding-top:20px">
                        <div class="amount-box">
                            <div class="ab-label">Status Tiket</div>
                            <div class="ab-amount"><?=$st['label']?></div>
                            <div class="ab-sub">Priority: <?=!empty($ticket['priority']) ? $ticket['priority'] : 'Belum diset'?></div>
                        </div>

                        <a href="cetak.php?id=<?= $ticket['id'] ?>" target="_blank" class="btn-action btn-primary-action">
                            <i class="bi bi-file-pdf"></i> Download PDF
                        </a>
                        <a href="<?= $user_role ?>/dashboard.php" class="btn-action btn-outline-action">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="rs-card">
                    <h4>Riwayat Aktivitas</h4>
                    <div class="rs-card-body" style="max-height:400px;overflow-y:auto">
                        <?php if(mysqli_num_rows($logs) > 0): ?>
                            <?php 
                            mysqli_data_seek($logs, 0);
                            while($log = mysqli_fetch_assoc($logs)): 
                            ?>
                            <div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #eef0f5">
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                                    <div style="width:6px;height:6px;border-radius:50%;background:#10367D;flex-shrink:0"></div>
                                    <strong style="font-size:.82rem;color:#1a1a2e"><?= htmlspecialchars($log['nama']) ?></strong>
                                    <span style="font-size:.7rem;color:#9ca3af;margin-left:auto"><?= date('d/m H:i', strtotime($log['created_at'])) ?></span>
                                </div>
                                <div style="font-size:.78rem;color:#6b7280;margin-left:14px"><?= htmlspecialchars($log['description']) ?></div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                        <p style="text-align:center;color:#9ca3af;padding:20px 0;font-size:.82rem">
                            <i class="bi bi-clock-history" style="font-size:1.2rem;display:block;margin-bottom:8px;color:#d1d5db"></i>
                            Belum ada riwayat aktivitas
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





