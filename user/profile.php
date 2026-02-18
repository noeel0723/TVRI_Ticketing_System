<?php
require_once '../config/auth.php';
checkRole('user');
require_once '../config/koneksi.php';

$user = getUserInfo();
$errors = [];
$success = '';

$col_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'foto'");
if (mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN foto VARCHAR(255) DEFAULT NULL");
}

$active_query = "SELECT COUNT(*) as total FROM tickets WHERE user_id = ? AND (status = 'Assigned' OR status = 'In Progress')";
$stmt = mysqli_prepare($conn, $active_query);
mysqli_stmt_bind_param($stmt, "i", $user['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$active_count = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

$_foto_q = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
mysqli_stmt_bind_param($_foto_q, "i", $user['id']);
mysqli_stmt_execute($_foto_q);
$_foto_r = mysqli_stmt_get_result($_foto_q);
$_foto_d = mysqli_fetch_assoc($_foto_r);
mysqli_stmt_close($_foto_q);
$_user_photo = !empty($_foto_d['foto']) ? '../uploads/profile_photos/' . $_foto_d['foto'] : '';

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$userData = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    if ($nama === '' || $username === '') { $errors[] = 'Nama dan username wajib diisi.'; }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Format email tidak valid.'; }
    if (!$errors) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
        mysqli_stmt_bind_param($stmt, "si", $username, $user['id']);
        mysqli_stmt_execute($stmt);
        $check = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($check) > 0) { $errors[] = 'Username sudah digunakan oleh user lain.'; }
        mysqli_stmt_close($stmt);
    }
    if (!$errors) {
        $prev_profile = ['nama' => $userData['nama'], 'username' => $userData['username'], 'email' => $userData['email'] ?? ''];
        $hasEmail = false;
        $cols = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
        if (mysqli_num_rows($cols) > 0) $hasEmail = true;
        if ($hasEmail) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, username = ?, email = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $nama, $username, $email, $user['id']);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET nama = ?, username = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $nama, $username, $user['id']);
        }
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Profil berhasil diperbarui.';
            $_SESSION['undo_profile'] = ['user_id' => $user['id'], 'prev' => $prev_profile, 'expires' => time() + 5];
            $_SESSION['nama'] = $nama; $_SESSION['username'] = $username;
            $user['nama'] = $nama; $user['username'] = $username;
            $userData['nama'] = $nama; $userData['username'] = $username;
            if ($hasEmail) $userData['email'] = $email;
        } else { $errors[] = 'Gagal memperbarui profil.'; }
        mysqli_stmt_close($stmt);
    }
}

$has_photo = !empty($userData['foto']);
$photo_url = $has_photo ? '../uploads/profile_photos/' . $userData['foto'] : '';

$user_id = $user['id'];
$total_tickets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE user_id = $user_id"))['c'];
$open_tickets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE user_id = $user_id AND status = 'Open'"))['c'];
$resolved_tickets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM tickets WHERE user_id = $user_id AND status = 'Resolved'"))['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Ticketing TVRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        html{width:100%}
        body{font-family:'Inter',sans-serif;background:#f0f2f5}
        .sidebar{background:#10367D;min-height:100vh;color:#fff;position:fixed;top:0;left:0;width:230px;z-index:100;display:flex;flex-direction:column}
        .sidebar-brand{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:12px}
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
        .breadcrumb-nav{display:flex;align-items:center;gap:8px;margin-bottom:24px;font-size:.88rem}
        .breadcrumb-nav a{color:#10367D;text-decoration:none;font-weight:600}
        .breadcrumb-nav a:hover{text-decoration:underline}
        .breadcrumb-nav .sep{color:#c0c4cc}
        .breadcrumb-nav .current{color:#1a1a2e;font-weight:600}
        .profile-layout{display:grid;grid-template-columns:360px 1fr;gap:24px;align-items:start}
        .profile-card{background:#fff;border-radius:16px;border:1px solid #e8eaef;overflow:hidden}
        .profile-card-header{background:linear-gradient(135deg,#10367D 0%,#1a4fa0 100%);padding:32px 28px 48px;text-align:center;position:relative}
        .profile-card-header::after{content:'';position:absolute;bottom:-1px;left:0;right:0;height:28px;background:#fff;border-radius:20px 20px 0 0}
        .avatar-container{position:relative;display:inline-block;z-index:2}
        .avatar-lg{width:100px;height:100px;border-radius:50%;border:4px solid #fff;box-shadow:0 4px 20px rgba(0,0,0,.2);overflow:hidden;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;font-size:2.4rem;font-weight:700;color:#fff}
        .avatar-lg img{width:100%;height:100%;object-fit:cover}
        .avatar-badge{position:absolute;bottom:2px;right:2px;width:32px;height:32px;background:#10367D;border:3px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;font-size:.8rem;transition:all .2s}
        .avatar-badge:hover{background:#0c2d68;transform:scale(1.1)}
        .profile-card-body{padding:0 28px 28px;position:relative;z-index:2;margin-top:-12px}
        .profile-name{font-size:1.15rem;font-weight:700;color:#1a1a2e;text-align:center;margin-bottom:2px}
        .profile-email{font-size:.82rem;color:#8b8fa3;text-align:center;margin-bottom:10px}
        .role-badge{display:flex;align-items:center;gap:5px;background:#eff6ff;color:#10367D;border:1px solid #dbeafe;padding:4px 14px;border-radius:20px;font-size:.76rem;font-weight:600;width:fit-content;margin:0 auto 18px}
        .photo-actions{display:flex;gap:8px;justify-content:center;margin-bottom:22px}
        .btn-upload{background:#10367D;color:#fff;border:none;padding:8px 16px;border-radius:10px;font-size:.8rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;font-family:'Inter',sans-serif}
        .btn-upload:hover{background:#0c2d68}
        .btn-del-photo{background:#fff;color:#ef4444;border:1px solid #fecaca;padding:8px 14px;border-radius:10px;font-size:.8rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .15s;font-family:'Inter',sans-serif}
        .btn-del-photo:hover{background:#fef2f2;border-color:#ef4444}
        .upload-spinner{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .info-divider{height:1px;background:#f0f2f5;margin:0 0 20px}
        .info-section-title{font-size:.92rem;font-weight:700;color:#1a1a2e;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between}
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
        .info-item{padding:14px 4px;border-bottom:1px solid #f3f4f8}
        .info-item:nth-last-child(-n+2){border-bottom:none}
        .info-item .info-label{font-size:.7rem;font-weight:600;color:#8b8fa3;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
        .info-item .info-value{font-size:.86rem;font-weight:600;color:#1a1a2e}
        .info-item .info-value.empty{color:#d1d5db;font-style:italic;font-weight:400}
        .btn-edit-info{background:none;border:1px solid #e2e5eb;border-radius:8px;padding:5px 14px;font-size:.78rem;font-weight:500;color:#374151;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:all .15s;font-family:'Inter',sans-serif}
        .btn-edit-info:hover{border-color:#10367D;color:#10367D;background:rgba(16,54,125,.04)}
        .edit-section{display:none;padding-top:4px}.edit-section.show{display:block}
        .view-section.hide{display:none}
        .form-label-sm{font-size:.75rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px}
        .form-control{border-radius:10px;padding:10px 14px;font-size:.86rem;border:1px solid #e2e5eb;font-family:'Inter',sans-serif;width:100%}
        .form-control:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08);outline:none}
        .btn-save{background:#10367D;color:#fff;border:none;padding:10px 24px;border-radius:10px;font-size:.84rem;font-weight:600;font-family:'Inter',sans-serif;cursor:pointer;transition:all .15s}
        .btn-save:hover{background:#0c2d68;color:#fff}
        .btn-cancel{background:#fff;border:1px solid #e2e5eb;color:#6b7280;padding:10px 20px;border-radius:10px;font-size:.84rem;font-weight:500;font-family:'Inter',sans-serif;cursor:pointer}
        .btn-cancel:hover{background:#f8f9fc}
        .right-col .card-panel{background:#fff;border-radius:16px;border:1px solid #e8eaef;padding:24px;margin-bottom:20px}
        .card-panel-title{font-size:.95rem;font-weight:700;color:#1a1a2e;margin-bottom:18px;display:flex;align-items:center;gap:8px}
        .card-panel-title i{color:#10367D;font-size:1.1rem}
        .stats-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}
        .stat-box{background:#f8f9fc;border-radius:14px;padding:18px 16px;text-align:center;border:1px solid #eef0f5;transition:all .2s}
        .stat-box:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.04)}
        .stat-box .stat-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:1.1rem}
        .stat-box .stat-num{font-size:1.5rem;font-weight:700;line-height:1.2}
        .stat-box .stat-lbl{font-size:.72rem;font-weight:500;color:#8b8fa3;margin-top:4px}
        .pwd-link{display:flex;align-items:center;gap:12px;padding:16px;background:#f8f9fc;border-radius:12px;border:1px solid #eef0f5;text-decoration:none;color:#374151;font-size:.88rem;font-weight:500;transition:all .2s}
        .pwd-link:hover{border-color:#10367D;color:#10367D;background:rgba(16,54,125,.03)}
        .pwd-link i{color:#10367D;font-size:1.15rem}
        .pwd-link .arrow{margin-left:auto;color:#9ca3af}
        .crop-modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center}
        .crop-modal-overlay.show{display:flex}
        .crop-modal{background:#fff;border-radius:16px;padding:24px;max-width:520px;width:90%;max-height:90vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
        .crop-modal h3{font-size:1.1rem;font-weight:700;color:#1a1a2e;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between}
        .crop-modal h3 button{background:none;border:none;font-size:1.2rem;color:#9ca3af;cursor:pointer;padding:4px}
        .crop-modal h3 button:hover{color:#1a1a2e}
        .crop-container{width:100%;max-height:400px;background:#f1f3f5;border-radius:10px;overflow:hidden;margin-bottom:16px}
        .crop-container img{display:block;max-width:100%}
        .crop-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
        .crop-actions .btn-tool{background:#f3f4f6;border:1px solid #e2e5eb;border-radius:8px;padding:8px 12px;font-size:.8rem;cursor:pointer;display:inline-flex;align-items:center;gap:4px;color:#374151;font-family:'Inter',sans-serif;transition:all .15s}
        .crop-actions .btn-tool:hover{background:#e8edf5;border-color:#10367D;color:#10367D}
        .crop-actions .btn-crop-save{background:#10367D;color:#fff;border:none;border-radius:8px;padding:8px 20px;font-size:.85rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:6px}
        .crop-actions .btn-crop-save:hover{background:#0c2d68}
        .crop-actions .btn-crop-cancel{background:#fff;border:1px solid #e2e5eb;border-radius:8px;padding:8px 16px;font-size:.85rem;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;color:#6b7280}
        .crop-actions .btn-crop-cancel:hover{background:#f8f9fc}
        @media(max-width:1100px){.profile-layout{grid-template-columns:1fr}}
        @media(max-width:768px){
            html,body{overflow-x:hidden;width:100%}
            .main-content{margin-left:0;padding:20px 16px}
            .profile-layout{grid-template-columns:1fr;gap:16px}
            .profile-card-header{padding:24px 20px 40px}
            .profile-card-body{padding:0 20px 24px}
            .info-grid{grid-template-columns:1fr}
            .info-item{border-bottom:1px solid #f3f4f8!important}
            .info-item:last-child{border-bottom:none!important}
            .stats-grid{grid-template-columns:1fr 1fr 1fr;gap:10px}
            .photo-actions{flex-wrap:wrap}
            .right-col .card-panel{padding:20px}
            .edit-section form > div:first-child{grid-template-columns:1fr!important}
        }
        .hamburger-menu{display:none;position:fixed;top:20px;left:20px;z-index:1100;background:#fff;border:none;width:44px;height:44px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);cursor:pointer;padding:10px;flex-direction:column;justify-content:space-around;transition:all .3s}
        .hamburger-menu span{display:block;width:100%;height:3px;background:#10367D;border-radius:2px;transition:all .3s}
        .hamburger-menu.active span:nth-child(1){transform:rotate(45deg) translate(8px,8px)}
        .hamburger-menu.active span:nth-child(2){opacity:0}
        .hamburger-menu.active span:nth-child(3){transform:rotate(-45deg) translate(7px,-7px)}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:998;opacity:0;transition:opacity .3s}
        .sidebar-overlay.active{display:block;opacity:1}
        @media(max-width:768px){.hamburger-menu{display:flex}.sidebar{position:fixed!important;top:0;left:-100%!important;width:260px!important;height:100vh;z-index:999;transition:left .3s;overflow-y:auto}.sidebar.active{left:0!important}}
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
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($active_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning" style="font-size:0.7em"><?=$active_count?></span><?php endif; ?></a>
            <a class="nav-link" href="lapor.php"><i class="bi bi-plus-circle"></i> Buat Tiket</a>
            <a class="nav-link active" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">User (Pelapor)</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>

    <div class="crop-modal-overlay" id="cropModal">
        <div class="crop-modal">
            <h3>Sesuaikan Foto Profil <button onclick="closeCropModal()"><i class="bi bi-x-lg"></i></button></h3>
            <div class="crop-container"><img id="cropImage" src="" alt="Crop"></div>
            <div class="crop-actions">
                <button class="btn-tool" onclick="cropper.rotate(-90)" title="Putar Kiri"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button class="btn-tool" onclick="cropper.rotate(90)" title="Putar Kanan"><i class="bi bi-arrow-clockwise"></i></button>
                <button class="btn-tool" onclick="cropper.scaleX(cropper.getData().scaleX===-1?1:-1)"><i class="bi bi-symmetry-horizontal"></i></button>
                <button class="btn-tool" onclick="cropper.zoom(0.1)"><i class="bi bi-zoom-in"></i></button>
                <button class="btn-tool" onclick="cropper.zoom(-0.1)"><i class="bi bi-zoom-out"></i></button>
                <div style="flex:1"></div>
                <button class="btn-crop-cancel" onclick="closeCropModal()">Batal</button>
                <button class="btn-crop-save" onclick="saveCroppedPhoto()"><i class="bi bi-check-lg"></i> Simpan</button>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="breadcrumb-nav">
            <a href="dashboard.php">Dashboard</a>
            <span class="sep"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Profil Saya</span>
        </div>

        <div class="profile-layout">
            <div class="profile-card">
                <div class="profile-card-header">
                    <div class="avatar-container">
                        <div class="avatar-lg" id="avatarDisplay">
                            <?php if($has_photo): ?><img src="<?= htmlspecialchars($photo_url) ?>" alt="Profile" id="avatarImg"><?php else: ?><span id="avatarInitial"><?= strtoupper(substr($userData['nama'], 0, 1)) ?></span><?php endif; ?>
                        </div>
                        <div class="avatar-badge" onclick="document.getElementById('photoInput').click()" title="Ganti Foto"><i class="bi bi-camera-fill"></i></div>
                    </div>
                </div>
                <div class="profile-card-body">
                    <div class="profile-name"><?= htmlspecialchars($userData['nama']) ?></div>
                    <div class="profile-email"><?= !empty($userData['email']) ? htmlspecialchars($userData['email']) : '@' . htmlspecialchars($userData['username']) ?></div>
                    <div class="role-badge"><i class="bi bi-person-check"></i> User (Pelapor)</div>

                    <div class="photo-actions">
                        <button class="btn-upload" onclick="document.getElementById('photoInput').click()"><i class="bi bi-cloud-arrow-up"></i> Upload Photo <span class="upload-spinner" id="uploadSpinner"></span></button>
                        <?php if($has_photo): ?><button class="btn-del-photo" onclick="deletePhoto()"><i class="bi bi-trash3"></i> Hapus</button><?php endif; ?>
                    </div>
                    <input type="file" id="photoInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="openCropModal(this)">

                    <div class="info-divider"></div>

                    <div class="view-section" id="viewPersonal">
                        <div class="info-section-title">Informasi Pribadi <button class="btn-edit-info" onclick="toggleEdit('personal')"><i class="bi bi-pencil"></i> Edit</button></div>
                        <div class="info-grid">
                            <div class="info-item"><div class="info-label">Username</div><div class="info-value">@<?= htmlspecialchars($userData['username']) ?></div></div>
                            <div class="info-item"><div class="info-label">Email</div><div class="info-value <?= empty($userData['email'])?'empty':'' ?>"><?= !empty($userData['email']) ? htmlspecialchars($userData['email']) : 'Belum diisi' ?></div></div>
                            <div class="info-item"><div class="info-label">Role</div><div class="info-value">User (Pelapor)</div></div>
                            <div class="info-item"><div class="info-label">Bergabung</div><div class="info-value"><?= date('d M Y', strtotime($userData['created_at'])) ?></div></div>
                        </div>
                    </div>

                    <div class="edit-section" id="editPersonal">
                        <div class="info-section-title">Edit Profil <button class="btn-edit-info" onclick="toggleEdit('personal')"><i class="bi bi-x-lg"></i> Batal</button></div>
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                                <div><label class="form-label-sm">Nama Lengkap</label><input type="text" class="form-control" name="nama" value="<?= htmlspecialchars($userData['nama']) ?>" required></div>
                                <div><label class="form-label-sm">Username</label><input type="text" class="form-control" name="username" value="<?= htmlspecialchars($userData['username']) ?>" required></div>
                            </div>
                            <div style="margin-bottom:18px"><label class="form-label-sm">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" placeholder="contoh@email.com"></div>
                            <div class="d-flex gap-2"><button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Simpan</button><button type="button" class="btn-cancel" onclick="toggleEdit('personal')">Batal</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="right-col">
                <div class="card-panel">
                    <div class="card-panel-title"><i class="bi bi-ticket-perforated"></i> Statistik Tiket Saya</div>
                    <div class="stats-grid">
                        <div class="stat-box"><div class="stat-icon" style="background:#eff6ff;color:#10367D"><i class="bi bi-ticket-perforated-fill"></i></div><div class="stat-num" style="color:#10367D"><?= $total_tickets ?></div><div class="stat-lbl">Total Tiket</div></div>
                        <div class="stat-box"><div class="stat-icon" style="background:#fef2f2;color:#ef4444"><i class="bi bi-exclamation-circle-fill"></i></div><div class="stat-num" style="color:#ef4444"><?= $open_tickets ?></div><div class="stat-lbl">Open</div></div>
                        <div class="stat-box"><div class="stat-icon" style="background:#f0fdf4;color:#22c55e"><i class="bi bi-check-circle-fill"></i></div><div class="stat-num" style="color:#22c55e"><?= $resolved_tickets ?></div><div class="stat-lbl">Resolved</div></div>
                    </div>
                </div>
                <div class="card-panel">
                    <div class="card-panel-title"><i class="bi bi-shield-lock"></i> Keamanan</div>
                    <a href="../change_password.php" class="pwd-link"><i class="bi bi-key-fill"></i> Ganti Password <span class="arrow"><i class="bi bi-chevron-right"></i></span></a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <script>
    let cropper = null;
    function toggleEdit(section) {
        const view = document.getElementById('view' + section.charAt(0).toUpperCase() + section.slice(1));
        const edit = document.getElementById('edit' + section.charAt(0).toUpperCase() + section.slice(1));
        view.classList.toggle('hide');
        edit.classList.toggle('show');
    }
    function openCropModal(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) { Swal.fire({icon:'error',title:'Gagal!',text:'Ukuran file maks 5MB.'}); input.value = ''; return; }
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('cropImage');
            img.src = e.target.result;
            document.getElementById('cropModal').classList.add('show');
            if (cropper) cropper.destroy();
            setTimeout(() => { cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: 0.85, cropBoxResizable: true, cropBoxMovable: true, guides: true, center: true, highlight: true, background: true, responsive: true }); }, 100);
        };
        reader.readAsDataURL(file);
        input.value = '';
    }
    function closeCropModal() { document.getElementById('cropModal').classList.remove('show'); if (cropper) { cropper.destroy(); cropper = null; } }
    function saveCroppedPhoto() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
        const dataURL = canvas.toDataURL('image/jpeg', 0.9);
        closeCropModal();
        document.getElementById('uploadSpinner').style.display = 'inline-block';
        const fd = new FormData(); fd.append('cropped_image', dataURL);
        fetch('../upload_photo.php', { method: 'POST', body: fd })
            .then(async r => { const t = await r.text(); const c = t.replace(/^\uFEFF/, '').trim(); const m = c.match(/\{[\s\S]*\}/); if (m) return JSON.parse(m[0]); throw new Error(c); })
            .then(d => { document.getElementById('uploadSpinner').style.display='none'; if(d.success){Swal.fire({icon:'success',title:'Berhasil!',text:d.message,timer:1500,showConfirmButton:false}).then(()=>location.reload());}else{Swal.fire({icon:'error',title:'Gagal!',text:d.message});} })
            .catch(() => { document.getElementById('uploadSpinner').style.display='none'; Swal.fire({icon:'error',title:'Error!',text:'Terjadi kesalahan saat upload.'}); });
    }
    function showUndoToast(msg, field, endpoint, reload) {
        Swal.fire({toast:true,position:'bottom-end',icon:'success',title:msg,showConfirmButton:true,confirmButtonText:'Undo',timer:5000,timerProgressBar:true,didOpen:t=>{t.addEventListener('mouseenter',Swal.stopTimer);t.addEventListener('mouseleave',Swal.resumeTimer);}}).then(r=>{
            if(r.isConfirmed){const fd=new FormData();fd.append(field,'1');fetch(endpoint,{method:'POST',body:fd}).then(async r=>{const t=await r.text();const c=t.replace(/^\uFEFF/,'').trim();const m=c.match(/\{[\s\S]*\}/);if(m)return JSON.parse(m[0]);throw new Error(c);}).then(d=>{if(d.success){Swal.fire({icon:'success',title:'Undo berhasil',text:d.message,timer:1500,showConfirmButton:false}).then(()=>location.reload());}else{Swal.fire({icon:'error',title:'Undo gagal',text:d.message});}}).catch(()=>{Swal.fire({icon:'error',title:'Error!',text:'Gagal memproses undo.'});});}else if(reload){location.reload();}
        });
    }
    function deletePhoto() {
        Swal.fire({title:'Hapus Foto Profil?',text:'Foto akan dihapus.',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',cancelButtonColor:'#6b7280',confirmButtonText:'Ya, Hapus',cancelButtonText:'Batal'}).then(r=>{
            if(r.isConfirmed){const fd=new FormData();fd.append('delete_photo','1');fetch('../upload_photo.php',{method:'POST',body:fd}).then(async r=>{const t=await r.text();const c=t.replace(/^\uFEFF/,'').trim();const m=c.match(/\{[\s\S]*\}/);if(m)return JSON.parse(m[0]);throw new Error(c);}).then(d=>{if(d.success){Swal.fire({icon:'success',title:'Berhasil!',text:d.message,timer:1500,showConfirmButton:false}).then(()=>showUndoToast('Foto profil dihapus','undo_photo','../upload_photo.php',true));}else{Swal.fire({icon:'error',title:'Gagal!',text:d.message});}});}
        });
    }
    document.getElementById('cropModal').addEventListener('click', function(e) { if (e.target === this) closeCropModal(); });
    <?php if (!empty($success)): ?>Swal.fire({icon:'success',title:'Berhasil!',text:'<?= addslashes($success) ?>',timer:2000,showConfirmButton:false}).then(()=>{showUndoToast('Perubahan profil disimpan','undo_profile','../undo_profile.php',false);});<?php endif; ?>
    <?php if (!empty($errors)): ?>Swal.fire({icon:'error',title:'Gagal!',html:'<?= implode("<br>", array_map("addslashes", $errors)) ?>'});<?php endif; ?>
    (function(){const h=document.getElementById('hamburgerBtn');const s=document.querySelector('.sidebar');const o=document.getElementById('sidebarOverlay');if(!h||!s||!o)return;h.addEventListener('click',function(){this.classList.toggle('active');s.classList.toggle('active');o.classList.toggle('active');document.body.style.overflow=s.classList.contains('active')?'hidden':'';});o.addEventListener('click',function(){h.classList.remove('active');s.classList.remove('active');this.classList.remove('active');document.body.style.overflow='';});document.querySelectorAll('.sidebar .nav-link, .sidebar a').forEach(function(l){l.addEventListener('click',function(){if(window.innerWidth<=768){h.classList.remove('active');s.classList.remove('active');o.classList.remove('active');document.body.style.overflow='';}});});})();
    </script>
</body>
</html>
