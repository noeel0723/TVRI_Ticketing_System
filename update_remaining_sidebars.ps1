# Complete Sidebar Migration Script
# This script updates all remaining user and teknisi pages to use includes/sidebar.php

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host " SIDEBAR MIGRATION - Final Phase" -ForegroundColor Cyan  
Write-Host "========================================`n" -ForegroundColor Cyan

$completed = @()
$failed = @()

# =============================================================================
# USER PAGES
# =============================================================================

# USER: dashboard.php
Write-Host "[1/7] Processing user/dashboard.php..." -ForegroundColor Yellow
try {
    $file = 'd:\xamp\htdocs\ticketing_tvri\user\dashboard.php'
    $content = [System.IO.File]::ReadAllText($file)
    
    $old = @'
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger-menu" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link active position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($active_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$active_count?></span><?php endif; ?></a>
            <a class="nav-link" href="lapor.php"><i class="bi bi-plus-circle"></i> Buat Tiket</a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a></div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">User</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>
'@
    
    $new = @'
    <?php 
    $active_page = 'dashboard';
    $notification_count = $active_count;
    include '../includes/sidebar.php'; 
    ?>
'@
    
    if($content.Contains($old)){
        $content = $content.Replace($old, $new)
        [System.IO.File]::WriteAllText($file, $content, (New-Object System.Text.UTF8Encoding($false)))
        $completed += 'user/dashboard.php'
        Write-Host "  ✓ Completed" -ForegroundColor Green
    } else {
        $failed += 'user/dashboard.php (pattern not found)'
        Write-Host "  ✗ Pattern not found" -ForegroundColor Red
    }
} catch {
    $failed += "user/dashboard.php (error: $($_.Exception.Message))"
    Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# USER: lapor.php
Write-Host "[2/7] Processing user/lapor.php..." -ForegroundColor Yellow
try {
    $file = 'd:\xamp\htdocs\ticketing_tvri\user\lapor.php'
    $content = [System.IO.File]::ReadAllText($file)
    
    $old = @'
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($active_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$active_count?></span><?php endif; ?></a>
            <a class="nav-link active" href="lapor.php"><i class="bi bi-plus-circle"></i> Buat Tiket</a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a></div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">User</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>
'@
    
    $new = @'
    <?php 
    $active_page = 'lapor';
    $notification_count = $active_count;
    include '../includes/sidebar.php'; 
    ?>
'@
    
    if($content.Contains($old)){
        $content = $content.Replace($old, $new)
        [System.IO.File]::WriteAllText($file, $content, (New-Object System.Text.UTF8Encoding($false)))
        $completed += 'user/lapor.php'
        Write-Host "  ✓ Completed" -ForegroundColor Green
    } else {
        $failed += 'user/lapor.php (pattern not found)'
        Write-Host "  ✗ Pattern not found" -ForegroundColor Red
    }
} catch {
    $failed += "user/lapor.php (error: $($_.Exception.Message))"
    Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# USER: profile.php  
Write-Host "[3/7] Processing user/profile.php..." -ForegroundColor Yellow
try {
    $file = 'd:\xamp\htdocs\ticketing_tvri\user\profile.php'
    $content = [System.IO.File]::ReadAllText($file)
    
    $old = @'
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger-menu" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($active_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$active_count?></span><?php endif; ?></a>
            <a class="nav-link" href="lapor.php"><i class="bi bi-plus-circle"></i> Buat Tiket</a>
            <a class="nav-link active" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a></div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($has_photo): ?><img src="<?= htmlspecialchars($photo_url) ?>" alt="Avatar"><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">User</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>
'@
    
    $new = @'
    <?php 
    $active_page = 'profile';
    $notification_count = $active_count;
    include '../includes/sidebar.php'; 
    ?>
'@
    
    if($content.Contains($old)){
        $content = $content.Replace($old, $new)
        [System.IO.File]::WriteAllText($file, $content, (New-Object System.Text.UTF8Encoding($false)))
        $completed += 'user/profile.php'
        Write-Host "  ✓ Completed" -ForegroundColor Green
    } else {
        $failed += 'user/profile.php (pattern not found)'
        Write-Host "  ✗ Pattern not found" -ForegroundColor Red
    }
} catch {
    $failed += "user/profile.php (error: $($_.Exception.Message))"
    Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n========================================`n" -ForegroundColor Cyan

# =============================================================================
# TEKNISI PAGES
# =============================================================================

# TEKNISI: dashboard.php
Write-Host "[4/7] Processing teknisi/dashboard.php..." -ForegroundColor Yellow
try {
    $file = 'd:\xamp\htdocs\ticketing_tvri\teknisi\dashboard.php'
    $content = [System.IO.File]::ReadAllText($file)
    
    $old = @'
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger-menu" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link active position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($assigned_tickets > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$assigned_tickets?></span><?php endif; ?></a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a></div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Teknisi<?php if(!empty($user['nama_divisi'])): ?> - <?= htmlspecialchars($user['nama_divisi']) ?><?php endif; ?></div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>
'@
    
    $new = @'
    <?php 
    $active_page = 'dashboard';
    $notification_count = $assigned_tickets;
    include '../includes/sidebar.php'; 
    ?>
'@
    
    if($content.Contains($old)){
        $content = $content.Replace($old, $new)
        [System.IO.File]::WriteAllText($file, $content, (New-Object System.Text.UTF8Encoding($false)))
        $completed += 'teknisi/dashboard.php'
        Write-Host "  ✓ Completed" -ForegroundColor Green
    } else {
        $failed += 'teknisi/dashboard.php (pattern not found)'
        Write-Host "  ✗ Pattern not found" -ForegroundColor Red
    }
} catch {
    $failed += "teknisi/dashboard.php (error: $($_.Exception.Message))"
    Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# TEKNISI: profile.php
Write-Host "[5/7] Processing teknisi/profile.php..." -ForegroundColor Yellow
try {
    $file = 'd:\xamp\htdocs\ticketing_tvri\teknisi\profile.php'
    $content = [System.IO.File]::ReadAllText($file)
    
    $old = @'
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger-menu" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($assigned_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$assigned_count?></span><?php endif; ?></a>
            <a class="nav-link active" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a></div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($has_photo): ?><img src="<?= htmlspecialchars($photo_url) ?>" alt="Avatar"><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Teknisi<?php if(!empty($userData['nama_divisi'])): ?> - <?= htmlspecialchars($userData['nama_divisi']) ?><?php endif; ?></div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>
'@
    
    $new = @'
    <?php 
    $active_page = 'profile';
    $notification_count = $assigned_count;
    include '../includes/sidebar.php'; 
    ?>
'@
    
    if($content.Contains($old)){
        $content = $content.Replace($old, $new)
        [System.IO.File]::WriteAllText($file, $content, (New-Object System.Text.UTF8Encoding($false)))
        $completed += 'teknisi/profile.php'
        Write-Host "  ✓ Completed" -ForegroundColor Green
    } else {
        $failed += 'teknisi/profile.php (pattern not found)'
        Write-Host "  ✗ Pattern not found" -ForegroundColor Red
    }
} catch {
    $failed += "teknisi/profile.php (error: $($_.Exception.Message))"
    Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# TEKNISI: update_status.php
Write-Host "[6/7] Processing teknisi/update_status.php..." -ForegroundColor Yellow
try {
    $file = 'd:\xamp\htdocs\ticketing_tvri\teknisi\update_status.php'
    $content = [System.IO.File]::ReadAllText($file)
    
    $old = @'
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger-menu" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($assigned_tickets > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$assigned_tickets?></span><?php endif; ?></a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a></div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Teknisi<?php if(!empty($user['nama_divisi'])): ?> - <?= htmlspecialchars($user['nama_divisi']) ?><?php endif; ?></div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>
'@
    
    $new = @'
    <?php 
    $active_page = '';
    $notification_count = $assigned_tickets;
    include '../includes/sidebar.php'; 
    ?>
'@
    
    if($content.Contains($old)){
        $content = $content.Replace($old, $new)
        [System.IO.File]::WriteAllText($file, $content, (New-Object System.Text.UTF8Encoding($false)))
        $completed += 'teknisi/update_status.php'
        Write-Host "  ✓ Completed" -ForegroundColor Green
    } else {
        $failed += 'teknisi/update_status.php (pattern not found)'
        Write-Host "  ✗ Pattern not found" -ForegroundColor Red
    }
} catch {
    $failed += "teknisi/update_status.php (error: $($_.Exception.Message))"
    Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# ADMIN: assign.php (bonus file not in original plan)
Write-Host "[7/7] Processing admin/assign.php (bonus)..." -ForegroundColor Yellow
try {
    $file = 'd:\xamp\htdocs\ticketing_tvri\admin\assign.php'
    $content = [System.IO.File]::ReadAllText($file)
    
    # First remove CSS if exists
    $cssRemove = @'
/* Mobile Hamburger Menu */
.hamburger-menu{display:none;position:fixed;top:20px;left:20px;z-index:1100;background:white;border:none;width:45px;height:45px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);cursor:pointer;padding:10px;flex-direction:column;justify-content:space-around;transition:all 0.3s ease}
.hamburger-menu span{display:block;width:100%;height:3px;background:#6366f1;border-radius:2px;transition:all 0.3s ease}
.hamburger-menu.active span:nth-child(1){transform:rotate(45deg) translate(8px, 8px)}
.hamburger-menu.active span:nth-child(2){opacity:0}
.hamburger-menu.active span:nth-child(3){transform:rotate(-45deg) translate(7px, -7px)}
.sidebar-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:998;opacity:0;transition:opacity 0.3s ease}
.sidebar-overlay.active{display:block;opacity:1}
'@
    if($content.Contains($cssRemove)){
        $content = $content.Replace($cssRemove, '/* Hamburger menu styles now in sidebar.php */')
    }
    
    $old = @'
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger-menu" id="hamburgerBtn">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($open_count > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.7em"><?=$open_count?></span><?php endif; ?></a>
            <a class="nav-link" href="kelola_user.php"><i class="bi bi-people"></i> Kelola User</a>
            <a class="nav-link" href="activity_logs.php"><i class="bi bi-clock-history"></i> Activity Logs</a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a></div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'], 0, 1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Administrator</div></div>
            </div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>
'@
    
    $new = @'
    <?php 
    $active_page = '';
    $notification_count = $open_count;
    include '../includes/sidebar.php'; 
    ?>
'@
    
    if($content.Contains($old)){
        $content = $content.Replace($old, $new)
        [System.IO.File]::WriteAllText($file, $content, (New-Object System.Text.UTF8Encoding($false)))
        $completed += 'admin/assign.php'
        Write-Host "  ✓ Completed" -ForegroundColor Green
    } else {
        $failed += 'admin/assign.php (pattern not found)'
        Write-Host "  ✗ Pattern not found" -ForegroundColor Red
    }
} catch {
    $failed += "admin/assign.php (error: $($_.Exception.Message))"
    Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
}

# =============================================================================
# SUMMARY
# =============================================================================

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host " MIGRATION SUMMARY" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "✓ Successfully updated: $($completed.Count) files" -ForegroundColor Green
foreach($file in $completed) {
    Write-Host "  - $file" -ForegroundColor Green
}

if($failed.Count -gt 0) {
    Write-Host "`n✗ Failed: $($failed.Count) files" -ForegroundColor Red
    foreach($file in $failed) {
        Write-Host "  - $file" -ForegroundColor Red
    }
}

Write-Host "`n========================================`n" -ForegroundColor Cyan
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  1. Test all pages on browser (Ctrl+F5 to hard refresh)" -ForegroundColor White
Write-Host "  2. Verify hamburger menu works on mobile" -ForegroundColor White
Write-Host "  3. Check sidebar navigation highlighting" -ForegroundColor White
Write-Host "`nAll hamburger menu functionality is now centralized in:" -ForegroundColor Yellow
Write-Host "  includes/sidebar.php`n" -ForegroundColor Cyan
