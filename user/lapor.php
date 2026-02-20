<?php
require_once '../config/auth.php';
checkRole('user');
require_once '../config/koneksi.php';

$user = getUserInfo();

$_foto_q = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
mysqli_stmt_bind_param($_foto_q, "i", $user['id']);
mysqli_stmt_execute($_foto_q);
$_foto_r = mysqli_stmt_get_result($_foto_q);
$_foto_d = mysqli_fetch_assoc($_foto_r);
mysqli_stmt_close($_foto_q);
$_user_photo = !empty($_foto_d['foto']) ? '../uploads/profile_photos/' . $_foto_d['foto'] : '';

// Count active tickets untuk notification
$active_query = "SELECT COUNT(*) as total FROM tickets WHERE user_id = ? AND (status = 'Assigned' OR status = 'In Progress')";
$stmt = mysqli_prepare($conn, $active_query);
mysqli_stmt_bind_param($stmt, "i", $user['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$active_count = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

// Ambil riwayat tiket terbaru (5 terakhir)
$history_query = "SELECT t.id, t.judul, t.status, DATE_FORMAT(t.created_at, '%d/%m/%Y') as tgl
                  FROM tickets t
                  WHERE t.user_id = {$user['id']}
                  ORDER BY t.created_at DESC
                  LIMIT 5";
$history_result = mysqli_query($conn, $history_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Tiket Baru - Ticketing TVRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#f8f9fc}
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
        .main-content{margin-left:230px;padding:32px 40px;min-height:100vh}
        .page-title{font-size:1.5rem;font-weight:700;color:#1a1a2e;margin-bottom:4px}
        .page-subtitle{color:#8b8fa3;font-size:.88rem;margin:0}
        .ticket-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #eef0f5;padding:28px 32px;margin-bottom:24px}
        .ticket-card-title{font-size:1.05rem;font-weight:700;color:#1a1a2e;margin-bottom:4px}
        .ticket-card-subtitle{color:#8b8fa3;font-size:.82rem;margin-bottom:24px}
        .form-label{font-weight:500;color:#6b7280;font-size:.78rem;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
        .form-control,.form-select{border:1px solid #e2e5eb;border-radius:10px;padding:10px 14px;font-size:.88rem;font-family:'Inter',sans-serif;transition:all .2s}
        .form-control:focus,.form-select:focus{border-color:#10367D;box-shadow:0 0 0 3px rgba(16,54,125,.08);outline:none}
        .form-control::placeholder{color:#c0c4cc}
        textarea.form-control{resize:vertical;min-height:80px}
        .upload-zone{border:2px dashed #d1d5db;border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:all .2s;background:#fafbfd}
        .upload-zone:hover{border-color:#10367D;background:#f0f3ff}
        .upload-zone.dragover{border-color:#10367D;background:#edf0ff}
        .upload-zone i{font-size:1.8rem;color:#9ca3af;margin-bottom:8px}
        .upload-zone p{color:#6b7280;font-size:.82rem;margin:0}
        .upload-zone .browse{color:#10367D;font-weight:600;cursor:pointer}
        .upload-preview{margin-top:12px;position:relative;display:inline-block}
        .upload-preview img{max-height:80px;border-radius:8px;border:1px solid #e2e5eb}
        .upload-preview .remove-btn{position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:#ef4444;color:#fff;border:none;border-radius:50%;font-size:.65rem;display:flex;align-items:center;justify-content:center;cursor:pointer}
        .btn-submit{background:#10367D;color:#fff;border:none;padding:12px 32px;border-radius:10px;font-size:.9rem;font-weight:600;font-family:'Inter',sans-serif;display:inline-flex;align-items:center;gap:8px;transition:all .2s;cursor:pointer}
        .btn-submit:hover{background:#0c2d68;color:#fff;transform:translateY(-1px);box-shadow:0 4px 12px rgba(16,54,125,.25)}
        .section-title{font-size:1.05rem;font-weight:700;color:#1a1a2e;margin-bottom:4px}
        .section-subtitle{color:#8b8fa3;font-size:.82rem;margin-bottom:16px}
        .history-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #eef0f5;padding:24px 28px}
        .history-table{width:100%;border-collapse:collapse}
        .history-table th{font-size:.72rem;font-weight:500;color:#8b8fa3;text-transform:uppercase;letter-spacing:.5px;padding:8px 0;border-bottom:1px solid #eef0f5}
        .history-table td{padding:12px 0;border-bottom:1px solid #f5f6f8;font-size:.85rem;color:#374151;vertical-align:middle}
        .history-table tr:last-child td{border-bottom:none}
        .history-table .ticket-id{color:#10367D;font-weight:600;font-size:.8rem}
        .badge-status{padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600;display:inline-block}
        .badge-open{background:#fef3c7;color:#d97706}
        .badge-assigned{background:#dbeafe;color:#2563eb}
        .badge-inprogress{background:#e0e7ff;color:#4f46e5}
        .badge-resolved{background:#d1fae5;color:#059669}
        .faq-card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #eef0f5;padding:24px 28px}
        .faq-item{border-bottom:1px solid #f0f1f5}
        .faq-item:last-child{border-bottom:none}
        .faq-question{display:flex;justify-content:space-between;align-items:center;padding:14px 0;cursor:pointer;font-size:.88rem;font-weight:600;color:#1a1a2e;transition:color .2s;user-select:none}
        .faq-question:hover{color:#10367D}
        .faq-question i{font-size:.85rem;color:#9ca3af;transition:transform .25s}
        .faq-question.active i{transform:rotate(180deg);color:#10367D}
        .faq-answer{max-height:0;overflow:hidden;transition:max-height .3s ease;font-size:.84rem;color:#6b7280;line-height:1.7}
        .faq-answer.show{max-height:300px;padding-bottom:14px}
        .btn-submit.is-loading{opacity:.85;pointer-events:none}
        .btn-spinner{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite}
        .btn-submit.is-loading .btn-spinner{display:inline-block}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:992px){.form-grid{grid-template-columns:1fr!important}}
        .hamburger-menu{display:none;position:fixed;top:20px;left:20px;z-index:1100;background:#fff;border:none;width:44px;height:44px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.1);cursor:pointer;padding:10px;flex-direction:column;justify-content:space-around;transition:all .3s}
        .hamburger-menu span{display:block;width:100%;height:3px;background:#10367D;border-radius:2px;transition:all .3s}
        .hamburger-menu.active span:nth-child(1){transform:rotate(45deg) translate(8px,8px)}
        .hamburger-menu.active span:nth-child(2){opacity:0}
        .hamburger-menu.active span:nth-child(3){transform:rotate(-45deg) translate(7px,-7px)}
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:998;opacity:0;transition:opacity .3s}
        .sidebar-overlay.active{display:block;opacity:1}
        @media(max-width:768px){body{overflow-x:hidden}.hamburger-menu{display:flex}.sidebar{position:fixed!important;top:0;left:-100%!important;width:260px!important;height:var(--sidebar-h,100vh);z-index:999;transition:left .3s;overflow-y:auto;-webkit-overflow-scrolling:touch}.sidebar.active{left:0!important}.sidebar-nav{flex:none!important}.main-content{margin-left:0;padding:80px 16px 24px}}
    </style>
</head>
<body>
    <button class="hamburger-menu" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction:column;gap:8px;align-items:center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height:50px;">
            <div style="text-align:center"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($active_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning" style="font-size:0.7em"><?=$active_count?></span><?php endif; ?></a>
            <a class="nav-link active" href="lapor.php"><i class="bi bi-plus-circle"></i> Buat Tiket</a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">User (Pelapor)</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>
    <div class="main-content">
        <div style="margin-bottom:28px">
            <h1 class="page-title"></i>Support Ticket</h1>
            <p class="page-subtitle">Buat tiket baru untuk melaporkan masalah atau kerusakan perangkat</p>
        </div>
        <div class="ticket-card">
            <div class="ticket-card-title">Buat Tiket Baru</div>
            <div class="ticket-card-subtitle">Isi semua informasi di bawah ini, lalu klik tombol Submit Tiket</div>
            <form action="proses_lapor.php" method="POST" id="formLapor" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="form-grid">
                    <div>
                        <label for="judul" class="form-label">Judul Masalah <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" placeholder="Contoh: Komputer Tidak Bisa Booting" required>
                    </div>
                    <div>
                        <label for="lokasi" class="form-label">Lokasi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lokasi" name="lokasi" placeholder="Contoh: Ruang Admin Lantai 2" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="form-grid">
                    <div>
                        <label class="form-label">Nama Pelapor</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" readonly style="background:#f9fafb;color:#6b7280">
                    </div>
                    <div>
                        <label for="deskripsi" class="form-label">Deskripsi Masalah <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" placeholder="Jelaskan masalah secara detail..." required></textarea>
                    </div>
                </div>
                <div style="margin-bottom:24px">
                    <label class="form-label">Lampiran Gambar (Opsional)</label>
                    <div class="upload-zone" id="uploadZone" onclick="document.getElementById('attachment').click()">
                        <i class="bi bi-cloud-arrow-up d-block"></i>
                        <p>Drag & drop gambar di sini atau <span class="browse">pilih file</span></p>
                        <p style="font-size:.74rem;color:#9ca3af;margin-top:4px">Format: JPG, PNG &bull; Maks: 5MB</p>
                    </div>
                    <input type="file" class="d-none" id="attachment" name="attachment" accept="image/jpeg,image/jpg,image/png">
                    <div class="upload-preview d-none" id="uploadPreview">
                        <img id="previewImg" src="" alt="preview">
                        <button type="button" class="remove-btn" id="removeBtn"><i class="bi bi-x"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="btn-spinner" aria-hidden="true"></span>
                    <span class="btn-text"><i class="bi bi-send-fill"></i> Submit Tiket</span>
                </button>
            </form>
        </div>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="history-card">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="section-title">Riwayat Tiket Terbaru</div>
                            <div class="section-subtitle">Tiket terakhir yang Anda buat</div>
                        </div>
                    </div>
                    <?php if(mysqli_num_rows($history_result) > 0): ?>
                    <table class="history-table">
                        <thead><tr><th>ID Tiket</th><th>Judul</th><th>Tanggal</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while($row = mysqli_fetch_assoc($history_result)):
                            $badge = 'badge-open';
                            if($row['status'] == 'Assigned') $badge = 'badge-assigned';
                            elseif($row['status'] == 'In Progress') $badge = 'badge-inprogress';
                            elseif($row['status'] == 'Resolved') $badge = 'badge-resolved';
                        ?>
                        <tr>
                            <td><span class="ticket-id">#<?= $row['id'] ?></span></td>
                            <td><?= htmlspecialchars($row['judul']) ?></td>
                            <td><?= $row['tgl'] ?></td>
                            <td><span class="badge-status <?= $badge ?>"><?= $row['status'] ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align:center;padding:28px 0;color:#9ca3af">
                        <i class="bi bi-inbox" style="font-size:2rem;display:block;margin-bottom:8px"></i>
                        <p style="font-size:.85rem;margin:0">Belum ada tiket yang dibuat</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="faq-card">
                    <div class="section-title">Pertanyaan Umum (FAQ)</div>
                    <div class="section-subtitle">Panduan penggunaan sistem ticketing</div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">Bagaimana cara membuat tiket?<i class="bi bi-chevron-down"></i></div>
                        <div class="faq-answer">Isi formulir di atas dengan judul masalah, lokasi, dan deskripsi detail. Anda juga bisa menambahkan foto. Lalu klik tombol <strong>Submit Tiket</strong>.</div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">Berapa lama tiket diproses?<i class="bi bi-chevron-down"></i></div>
                        <div class="faq-answer">Tiket akan di-review oleh admin dan di-assign ke teknisi yang sesuai. Waktu proses tergantung pada tingkat prioritas dan ketersediaan teknisi.</div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">Bagaimana melihat status tiket saya?<i class="bi bi-chevron-down"></i></div>
                        <div class="faq-answer">Kunjungi halaman <strong>Dashboard</strong> untuk melihat semua tiket Anda beserta statusnya: Open, Assigned, In Progress, atau Resolved.</div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">Apa saja format file yang diterima?<i class="bi bi-chevron-down"></i></div>
                        <div class="faq-answer">Saat ini kami menerima file gambar berformat <strong>JPG</strong> dan <strong>PNG</strong> dengan ukuran maksimal <strong>5 MB</strong>.</div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">Bagaimana jika masalah belum teratasi?<i class="bi bi-chevron-down"></i></div>
                        <div class="faq-answer">Jika tiket sudah di-resolve namun masalah belum selesai, Anda dapat membuat tiket baru dengan menyebutkan referensi tiket sebelumnya.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function toggleFaq(el){
        const answer=el.nextElementSibling;const isOpen=answer.classList.contains('show');
        document.querySelectorAll('.faq-answer').forEach(a=>a.classList.remove('show'));
        document.querySelectorAll('.faq-question').forEach(q=>q.classList.remove('active'));
        if(!isOpen){answer.classList.add('show');el.classList.add('active');}
    }
    const fileInput=document.getElementById('attachment');
    const uploadZone=document.getElementById('uploadZone');
    const previewWrap=document.getElementById('uploadPreview');
    const previewImg=document.getElementById('previewImg');
    const removeBtn=document.getElementById('removeBtn');
    let previewUrl='';

    function showPreview(file){
        if(!file) return;
        if(file.size>5*1024*1024){Swal.fire({icon:'error',title:'Gagal!',text:'Ukuran file terlalu besar. Maksimal 5MB.'});return;}
        if(previewUrl){URL.revokeObjectURL(previewUrl);}
        previewUrl=URL.createObjectURL(file);
        previewImg.src=previewUrl;
        previewWrap.classList.remove('d-none');
        uploadZone.style.display='none';
    }

    function setFileFromDrop(file){
        const dt=new DataTransfer();
        dt.items.add(file);
        fileInput.files=dt.files;
    }

    fileInput.addEventListener('change',function(){if(this.files&&this.files[0]){showPreview(this.files[0]);}});
    removeBtn.addEventListener('click',()=>{fileInput.value='';previewWrap.classList.add('d-none');uploadZone.style.display='';if(previewUrl){URL.revokeObjectURL(previewUrl);previewUrl='';}});
    uploadZone.addEventListener('dragover',e=>{e.preventDefault();uploadZone.classList.add('dragover');});
    uploadZone.addEventListener('dragleave',()=>uploadZone.classList.remove('dragover'));
    uploadZone.addEventListener('drop',e=>{
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        if(e.dataTransfer.files.length){
            const file=e.dataTransfer.files[0];
            setFileFromDrop(file);
            showPreview(file);
        }
    });

    const form=document.getElementById('formLapor');
    const submitBtn=document.getElementById('submitBtn');
    form.addEventListener('submit',function(e){
        if(submitBtn.classList.contains('is-loading')){e.preventDefault();return;}
        submitBtn.classList.add('is-loading');
        submitBtn.disabled=true;
        const text=submitBtn.querySelector('.btn-text');
        if(text){text.textContent=' Mengirim...';}
    });
    // Hamburger menu toggle for mobile
    (function(){
        const hamburgerBtn=document.getElementById('hamburgerBtn');
        const sidebar=document.querySelector('.sidebar');
        const sidebarOverlay=document.getElementById('sidebarOverlay');
        if(!hamburgerBtn||!sidebar||!sidebarOverlay)return;
        hamburgerBtn.addEventListener('click',function(){this.classList.toggle('active');sidebar.classList.toggle('active');sidebarOverlay.classList.toggle('active');document.body.style.overflow=sidebar.classList.contains('active')?'hidden':'';});
        sidebarOverlay.addEventListener('click',function(){hamburgerBtn.classList.remove('active');sidebar.classList.remove('active');this.classList.remove('active');document.body.style.overflow='';});
        document.querySelectorAll('.sidebar .nav-link, .sidebar a').forEach(function(link){link.addEventListener('click',function(){if(window.innerWidth<=768){hamburgerBtn.classList.remove('active');sidebar.classList.remove('active');sidebarOverlay.classList.remove('active');document.body.style.overflow='';}});});  
    })();
    // Form validation removed - no character limits
    </script>
<script>!function(){function s(){document.documentElement.style.setProperty("--sidebar-h",window.innerHeight+"px")}s();window.addEventListener("resize",s);window.addEventListener("orientationchange",function(){setTimeout(s,150)})}();</script>
</body>
</html>

