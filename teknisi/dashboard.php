<?php
require_once '../config/auth.php';
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

$division_id = $user['division_id'];

// Get division name
$div_q = mysqli_query($conn, "SELECT nama_divisi FROM divisions WHERE id = $division_id");
$division_name = ($div_q && $r = mysqli_fetch_assoc($div_q)) ? $r['nama_divisi'] : 'N/A';

$query_total = "SELECT COUNT(*) as total FROM tickets WHERE assigned_division_id = $division_id";
$query_assigned = "SELECT COUNT(*) as total FROM tickets WHERE assigned_division_id = $division_id AND status = 'Assigned'";
$query_progress = "SELECT COUNT(*) as total FROM tickets WHERE assigned_division_id = $division_id AND status = 'In Progress'";
$query_resolved = "SELECT COUNT(*) as total FROM tickets WHERE assigned_division_id = $division_id AND status = 'Resolved'";

$total_tickets = mysqli_fetch_assoc(mysqli_query($conn, $query_total))['total'];
$assigned_tickets = mysqli_fetch_assoc(mysqli_query($conn, $query_assigned))['total'];
$progress_tickets = mysqli_fetch_assoc(mysqli_query($conn, $query_progress))['total'];
$resolved_tickets = mysqli_fetch_assoc(mysqli_query($conn, $query_resolved))['total'];

$limit = 15;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_tickets / $limit);

$query_tickets = "SELECT t.*, u.nama as pelapor, u.foto as pelapor_foto, TIMESTAMPDIFF(SECOND, t.updated_at, NOW()) as seconds_ago FROM tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.assigned_division_id = $division_id ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset";
$result_tickets = mysqli_query($conn, $query_tickets);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Teknisi - Ticketing TVRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
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
        .sidebar-footer .user-info{display:flex;align-items:center;gap:10px;margin-bottom:8px}
        .sidebar-footer .user-avatar{width:34px;height:34px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.85rem;overflow:hidden}
        .sidebar-footer .user-avatar img{width:100%;height:100%;object-fit:cover}
        .sidebar-footer .user-name{font-size:.85rem;font-weight:600}
        .sidebar-footer .user-role{font-size:.7rem;opacity:.5}
        .division-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);padding:4px 10px;border-radius:6px;font-size:.72rem;font-weight:500;margin-bottom:12px;margin-left:44px}
        .btn-logout{width:100%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;padding:8px;border-radius:8px;font-size:.82rem;font-weight:500;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .2s}
        .btn-logout:hover{background:rgba(255,255,255,.2);color:#fff}
        .main-content{margin-left:230px;padding:28px 32px;min-height:100vh}
        .page-header{margin-bottom:24px}
        .page-header h1{font-size:1.35rem;font-weight:700;color:#1a1a2e;margin:0 0 4px}
        .page-header p{color:#8b8fa3;font-size:.85rem;margin:0}
        .division-label{display:inline-flex;align-items:center;gap:6px;background:rgba(16,54,125,.08);color:#10367D;padding:5px 14px;border-radius:8px;font-size:.8rem;font-weight:600;margin-top:8px}
        .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
        .stat-card{background:#fff;border-radius:14px;padding:20px;display:flex;align-items:center;gap:16px;border:1px solid #e8eaef;transition:all .25s;position:relative;overflow:hidden}
        .stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.06)}
        .stat-card .sc-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0}
        .stat-card .sc-info{flex:1;min-width:0}
        .stat-card .sc-label{font-size:.72rem;font-weight:600;color:#8b8fa3;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px}
        .stat-card .sc-number{font-size:1.7rem;font-weight:700;color:#1a1a2e;line-height:1}
        .stat-card .sc-bg{position:absolute;right:-6px;bottom:-6px;font-size:3.5rem;opacity:.04}
        .charts-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
        .chart-card{background:#fff;border-radius:14px;border:1px solid #e8eaef;padding:20px;overflow:hidden}
        .chart-card h3{font-size:.92rem;font-weight:700;color:#1a1a2e;margin:0 0 16px;display:flex;align-items:center;gap:8px}
        .chart-card h3 i{color:#10367D;font-size:1rem}
        .chart-container{position:relative;height:220px}
        .table-card{background:#fff;border-radius:14px;border:1px solid #e8eaef;overflow:hidden}
        .table-card-header{padding:18px 22px;border-bottom:1px solid #eef0f5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
        .table-card-header h2{font-size:1rem;font-weight:700;color:#1a1a2e;margin:0}
        .table-card-header .ticket-count{font-size:.78rem;color:#8b8fa3;font-weight:500}
        .cb-cell{width:40px;text-align:center}
        .cb-cell.hidden{display:none!important}
        .cb-cell input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#10367D}
        .bulk-bar{display:none;align-items:center;gap:12px;padding:12px 22px;background:linear-gradient(135deg,#10367D,#1a4fa0);border-radius:12px;margin-bottom:16px;color:#fff;flex-wrap:wrap}
        .bulk-bar.show{display:flex}
        .bulk-bar .bulk-count{font-size:.88rem;font-weight:600;min-width:100px}
        .bulk-bar button{font-family:'Inter',sans-serif}
        .bulk-bar .btn-bulk{padding:7px 16px;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:5px}
        .btn-bulk-delete{background:#dc2626;color:#fff}.btn-bulk-delete:hover{background:#b91c1c}
        .btn-bulk-cancel{background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3)!important}.btn-bulk-cancel:hover{background:rgba(255,255,255,.3)}
        .btn-toggle-checkbox{background:#10367D;color:#fff;border:none;padding:9px 16px;border-radius:10px;font-size:.82rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;font-family:'Inter',sans-serif}
        .btn-toggle-checkbox:hover{background:#0c2d68}
        .table-wrap{overflow-x:auto}
        .ticket-table{width:100%;border-collapse:collapse}
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
        .reporter-cell{font-size:.82rem;color:#374151;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
        .reporter-cell i{font-size:.78rem;color:#8b8fa3}
        .date-cell{font-size:.78rem;color:#8b8fa3;white-space:nowrap}
        .btn-action{padding:5px 14px;border-radius:8px;font-size:.76rem;font-weight:600;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all .2s;white-space:nowrap}
        .btn-update{background:#2563eb;color:#fff}.btn-update:hover{background:#1d4ed8;color:#fff}
        .btn-detail{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}.btn-detail:hover{background:#e5e7eb;color:#1f2937}
        .btn-delete{background:#fee;color:#dc2626;border:1px solid #fecaca}.btn-delete:hover{background:#fecaca;color:#991b1b}
        .empty-state{text-align:center;padding:56px 20px}
        .empty-state i{font-size:2.8rem;color:#d1d5db;display:block;margin-bottom:10px}
        .empty-state p{color:#9ca3af;font-size:.88rem;margin:0}
        .pagination-bar{display:flex;justify-content:center;align-items:center;gap:6px;padding:16px;width:100%;margin-top:16px;clear:both}
        .pagination-bar a,.pagination-bar span{padding:7px 13px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#374151;font-size:.84rem;transition:all .2s}
        .pagination-bar a:hover{border-color:#10367D;color:#10367D;background:#f0f4ff}
        .pagination-bar .active-page{background:#10367D;color:#fff;border-color:#10367D;font-weight:600}
        .greeting-text{font-size:1.75rem;font-weight:700;color:#1a1a2e;margin:0 0 6px;line-height:1.3}
        .greeting-text .wave{display:inline-block;animation:wave-anim 2.5s infinite;transform-origin:70% 70%}
        @keyframes wave-anim{0%{transform:rotate(0)}10%{transform:rotate(14deg)}20%{transform:rotate(-8deg)}30%{transform:rotate(14deg)}40%{transform:rotate(-4deg)}50%{transform:rotate(10deg)}60%,100%{transform:rotate(0)}}
        .greeting-role{font-size:1rem;font-weight:600;color:#10367D;margin:0 0 2px;display:flex;align-items:center;gap:6px}
        .greeting-sub{font-size:.85rem;color:#8b8fa3;margin:0}
        .toast-container{position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:360px}
        .toast-notif{background:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 8px 30px rgba(0,0,0,.12);border-left:4px solid #10367D;display:flex;align-items:flex-start;gap:12px;animation:slideIn .3s ease;font-size:.84rem;color:#374151}
        .toast-notif .toast-icon{font-size:1.2rem;margin-top:1px;flex-shrink:0}
        .toast-notif .toast-body{flex:1}
        .toast-notif .toast-title{font-weight:600;font-size:.85rem;margin-bottom:2px}
        .toast-notif .toast-msg{font-size:.78rem;color:#8b8fa3}
        .toast-notif .toast-close{background:none;border:none;color:#8b8fa3;cursor:pointer;font-size:1rem;padding:0;margin-left:8px}
        @keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
        .hero-banner{background:linear-gradient(135deg,#10367D 0%,#1a4fa0 60%,#2563eb 100%);border-radius:20px;padding:40px;display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;position:relative;overflow:hidden;min-height:180px}
        .hero-banner::before{content:'';position:absolute;top:-50%;right:-10%;width:300px;height:300px;background:rgba(255,255,255,.06);border-radius:50%}
        .hero-banner::after{content:'';position:absolute;bottom:-40%;left:20%;width:250px;height:250px;background:rgba(255,255,255,.04);border-radius:50%}
        .hero-content{position:relative;z-index:1;flex:1}
        .hero-welcome{font-size:.9rem;font-weight:400;color:rgba(255,255,255,.7);margin:0 0 4px;letter-spacing:.3px}
        .hero-name{font-size:1.85rem;font-weight:700;color:#fff;margin:0 0 8px;line-height:1.3}
        .hero-name .wave{display:inline-block;animation:wave-anim 2.5s infinite;transform-origin:70% 70%}
        .hero-subtitle{font-size:.88rem;color:rgba(255,255,255,.75);margin:0 0 18px;max-width:420px;line-height:1.6}
        .hero-division{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);color:#fff;padding:8px 16px;border-radius:10px;font-size:.82rem;font-weight:600}
        .hero-image{position:relative;z-index:1;flex-shrink:0;margin-left:24px}
        .hero-image img{height:120px;opacity:.92;filter:drop-shadow(0 4px 15px rgba(0,0,0,.2));object-fit:contain}
        .section-title{font-size:1rem;font-weight:700;color:#1a1a2e;margin:0 0 16px;display:flex;align-items:center;gap:8px}
        .section-title i{color:#10367D;font-size:.95rem}
        .stat-card.sc-blue{background:linear-gradient(135deg,#10367D,#1a4fa0);border-color:transparent}
        .stat-card.sc-blue .sc-icon{background:rgba(255,255,255,.18);color:#fff}
        .stat-card.sc-blue .sc-label{color:rgba(255,255,255,.7)}
        .stat-card.sc-blue .sc-number{color:#fff}
        .stat-card.sc-red{background:linear-gradient(135deg,#dc2626,#ef4444);border-color:transparent}
        .stat-card.sc-red .sc-icon{background:rgba(255,255,255,.18);color:#fff}
        .stat-card.sc-red .sc-label{color:rgba(255,255,255,.7)}
        .stat-card.sc-red .sc-number{color:#fff}
        .stat-card.sc-amber{background:linear-gradient(135deg,#d97706,#f59e0b);border-color:transparent}
        .stat-card.sc-amber .sc-icon{background:rgba(255,255,255,.18);color:#fff}
        .stat-card.sc-amber .sc-label{color:rgba(255,255,255,.7)}
        .stat-card.sc-amber .sc-number{color:#fff}
        .stat-card.sc-green{background:linear-gradient(135deg,#16a34a,#22c55e);border-color:transparent}
        .stat-card.sc-green .sc-icon{background:rgba(255,255,255,.18);color:#fff}
        .stat-card.sc-green .sc-label{color:rgba(255,255,255,.7)}
        .stat-card.sc-green .sc-number{color:#fff}
        .stat-card.sc-blue .sc-bg,.stat-card.sc-red .sc-bg,.stat-card.sc-amber .sc-bg,.stat-card.sc-green .sc-bg{color:#fff;opacity:.1}
        .stat-card.sc-blue:hover,.stat-card.sc-red:hover,.stat-card.sc-amber:hover,.stat-card.sc-green:hover{box-shadow:0 8px 24px rgba(0,0,0,.2)}
        @media(max-width:992px){.stats-row{grid-template-columns:repeat(2,1fr)}.charts-row{grid-template-columns:1fr}}
        @media(max-width:768px){
            .main-content{margin-left:0;padding:70px 16px 20px}
            .hero-banner{flex-direction:column;text-align:center;padding:28px 24px;min-height:auto;border-radius:16px}
            .hero-content{order:2}
            .hero-image{order:1;margin:0 0 16px}
            .hero-image img{height:80px}
            .hero-name{font-size:1.4rem}
            .hero-subtitle{max-width:none;font-size:.82rem}
            .hero-division{font-size:.78rem;padding:6px 12px}
            .section-title{font-size:.9rem}
            .page-header{flex-direction:column;gap:10px}
            .greeting-text{font-size:1.35rem}
            .greeting-role{font-size:.9rem}
            .division-label{font-size:.75rem;padding:4px 10px}
            .charts-row{grid-template-columns:1fr;gap:12px}
            .chart-card{padding:16px}
            .chart-container{height:180px}
            .toast-container{left:12px;right:12px;max-width:none}
            .stats-row{grid-template-columns:1fr 1fr;gap:10px}
            .stat-card{padding:14px 12px}
            .stat-card .sc-number{font-size:1.4rem}
            .stat-card .sc-icon{width:38px;height:38px;font-size:1rem}
            .stat-card .sc-bg{display:none}
            .table-card{border-radius:10px}
            .table-card-header{flex-direction:column;align-items:stretch;padding:14px 16px;gap:10px}
            .table-card-header h2{font-size:.92rem}
            #searchTicket{width:100%!important;box-sizing:border-box}
            .table-wrap{overflow-x:visible;overflow-y:visible}
            .ticket-table{min-width:0!important}
            .ticket-table thead{display:none}
            .ticket-table tbody{display:flex;flex-direction:column;gap:10px;padding:12px}
            .cb-cell{display:flex!important;align-items:center;justify-content:flex-start;width:auto!important}
            .btn-toggle-checkbox{display:inline-flex!important;width:100%;justify-content:center}
            .bulk-bar{display:none;position:sticky;top:8px;z-index:5}
            .ticket-row{display:flex!important;flex-wrap:wrap;align-items:center;gap:6px 10px;padding:14px!important;border-radius:12px!important;border:1px solid #e8eaef!important;background:#fff;border-left:3px solid #10367D!important}
            .ticket-row:hover{background:#f8faff!important;box-shadow:0 2px 8px rgba(0,0,0,.04)}
            .ticket-row td{padding:0!important;border:none!important}
            .ticket-row td:nth-child(1){order:1;display:flex!important;flex:0 0 auto;margin-right:6px}
            .ticket-row td:nth-child(2){order:2;flex:1 1 calc(100% - 40px);margin-bottom:4px}
            .ticket-row td:nth-child(3){order:3}
            .ticket-row td:nth-child(4){order:4}
            .ticket-row td:nth-child(5){order:5;margin-left:auto}
            .ticket-row td:nth-child(6){display:none!important}
            .ticket-row td:nth-child(7){order:6;flex:0 0 100%;display:flex!important;gap:6px;margin-top:8px;padding-top:10px!important;border-top:1px solid #f0f2f5!important}
            .ticket-row td:nth-child(7) .btn-action{flex:1;justify-content:center;padding:8px 12px;font-size:.78rem}
            .pagination-bar{padding:12px 8px;gap:4px;flex-wrap:wrap}
            .pagination-bar a,.pagination-bar span{padding:6px 10px;font-size:.8rem}
        }
        @media(max-width:576px){
            .stats-row{grid-template-columns:1fr}
            .main-content{padding:70px 12px 16px}
            .hero-banner{padding:20px 16px;border-radius:14px}
            .hero-name{font-size:1.2rem}
            .hero-image img{height:55px}
            .greeting-text{font-size:1.2rem}
            .stat-card .sc-number{font-size:1.3rem}
            .chart-container{height:160px}
            .ticket-row{padding:12px!important}
            .t-title{font-size:.82rem}
            .status-pill{font-size:.7rem;padding:3px 8px}
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
    <div class="toast-container" id="toastContainer"></div>
    <button class="hamburger-menu" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <nav class="sidebar">
        <div class="sidebar-brand" style="flex-direction: column; gap: 8px; align-items: center;">
            <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo" style="height: 50px;">
            <div style="text-align: center;"><h5>TVRI Ticketing</h5><small>Support System</small></div>
        </div>
        <div class="sidebar-nav">
            <a class="nav-link active position-relative" href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard<?php if($assigned_tickets>0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" style="font-size:0.7em"><?=$assigned_tickets?></span><?php endif; ?></a>
            <a class="nav-link" href="profile.php"><i class="bi bi-person-circle"></i> Profil Saya</a>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php if($_user_photo): ?><img src="<?= htmlspecialchars($_user_photo) ?>" alt=""><?php else: ?><?= strtoupper(substr($user['nama'],0,1)) ?><?php endif; ?></div>
                <div><div class="user-name"><?= htmlspecialchars($user['nama']) ?></div><div class="user-role">Teknisi</div></div>
            </div>
            <div class="division-badge"><i class="bi bi-tools"></i> <?= htmlspecialchars($division_name) ?></div>
            <a href="../logout.php" class="btn-logout"><i class="bi bi-box-arrow-left"></i> Logout</a>
        </div>
    </nav>

    <div class="main-content">
        <div class="hero-banner">
            <div class="hero-content">
                <p class="hero-welcome">Welcome back,</p>
                <h1 class="hero-name"><?= htmlspecialchars($user['nama']) ?> <span class="wave">&#x1F44B;</span></h1>
                <p class="hero-subtitle">Kelola dan pantau tiket yang ditugaskan ke divisi Anda</p>
                <div class="hero-division"><i class="bi bi-building"></i> Divisi: <?= htmlspecialchars($division_name) ?></div>
            </div>
            <div class="hero-image">
                <img src="../assets/Logo_TVRI.svg.png" alt="TVRI Logo">
            </div>
        </div>

        <h3 class="section-title"><i class="bi bi-grid-3x3-gap-fill"></i> Overview</h3>
        <div class="stats-row">
            <div class="stat-card sc-blue">
                <div class="sc-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
                <div class="sc-info"><div class="sc-number" id="statTotal"><?= $total_tickets ?></div><div class="sc-label">Total Tiket</div></div>
                <i class="bi bi-ticket-perforated-fill sc-bg"></i>
            </div>
            <div class="stat-card sc-red">
                <div class="sc-icon"><i class="bi bi-inbox-fill"></i></div>
                <div class="sc-info"><div class="sc-number" id="statAssigned"><?= $assigned_tickets ?></div><div class="sc-label">Assigned</div></div>
                <i class="bi bi-inbox-fill sc-bg"></i>
            </div>
            <div class="stat-card sc-amber">
                <div class="sc-icon"><i class="bi bi-arrow-repeat"></i></div>
                <div class="sc-info"><div class="sc-number" id="statProgress"><?= $progress_tickets ?></div><div class="sc-label">In Progress</div></div>
                <i class="bi bi-arrow-repeat sc-bg"></i>
            </div>
            <div class="stat-card sc-green">
                <div class="sc-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="sc-info"><div class="sc-number" id="statResolved"><?= $resolved_tickets ?></div><div class="sc-label">Resolved</div></div>
                <i class="bi bi-check-circle-fill sc-bg"></i>
            </div>
        </div>

        <h3 class="section-title"><i class="bi bi-graph-up"></i> Analitik</h3>
        <div class="charts-row">
            <div class="chart-card">
                <h3><i class="bi bi-pie-chart-fill"></i> Status Tiket Divisi</h3>
                <div class="chart-container"><canvas id="statusChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3><i class="bi bi-bar-chart-fill"></i> Tiket per Bulan</h3>
                <div class="chart-container"><canvas id="monthlyChart"></canvas></div>
            </div>
        </div>

        <h3 class="section-title"><i class="bi bi-list-ul"></i> Daftar Tiket</h3>
        <form id="bulkForm" method="POST" action="../admin/proses_bulk.php">
        <div class="bulk-bar" id="bulkBar">
            <span class="bulk-count"><span id="bulkCount">0</span> tiket dipilih</span>
            <input type="hidden" name="bulk_action" id="bulkAction" value="delete">
            <button type="button" class="btn-bulk btn-bulk-delete" onclick="submitBulkDelete()"><i class="bi bi-trash"></i> Hapus Tiket</button>
            <button type="button" class="btn-bulk btn-bulk-cancel" onclick="clearBulk()"><i class="bi bi-x-lg"></i> Batal</button>
        </div>

        <div class="table-card">
            <div class="table-card-header"><h2><i class="bi bi-list-ul" style="margin-right:6px"></i>Ticket Divisi</h2><div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><button type="button" class="btn-toggle-checkbox" id="btnToggleCheckbox" onclick="toggleCheckboxes()"><i class="bi bi-check-square"></i> Pilih Cepat</button><input type="text" id="searchTicket" placeholder="Cari tiket..." style="padding:8px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:0.85rem;width:280px;font-family:'Inter',sans-serif" onkeyup="filterTickets()"><span class="ticket-count"><?= $total_tickets ?> tiket &middot; Hal <?= $page ?>/<?= max(1,$total_pages) ?></span></div>
            </div>
            <?php if(mysqli_num_rows($result_tickets) > 0): ?>
            <div class="table-wrap">
            <table class="ticket-table">
                <thead><tr><th class="cb-cell hidden"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor:pointer"></th><th>Title</th><th>Status</th><th>Priority</th><th>Updated</th><th>Created</th><th>Action</th></tr></thead>
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
                    $can_update = ($t['status']=='Assigned' || $t['status']=='In Progress');
                    $url = $can_update ? 'update_status.php?id='.$t['id'] : '../detail.php?id='.$t['id'];
                ?>
                <tr class="ticket-row" tabindex="0" onclick="handleRowClick(event,this,'<?= $url ?>')" onkeydown="handleRowKeydown(event,this,'<?= $url ?>')" role="link" aria-label="Tiket #<?= $t['id'] ?>">
                    <td class="cb-cell hidden"><input type="checkbox" name="ticket_ids[]" value="<?= $t['id'] ?>" class="ticket-cb" onclick="event.stopPropagation()" onchange="updateBulkBar()"></td>
                    <td>
                        <span class="t-title"><?= htmlspecialchars($t['judul']) ?></span>
                        <span class="t-id">#<?= $t['id'] ?></span>
                        <span class="reporter-cell"><i class="bi bi-person"></i> <?= htmlspecialchars($t['pelapor'] ?? '-') ?></span>
                    </td>
                    <td><span class="status-pill <?= $sp ?>"><span class="status-dot <?= $sd ?>"></span> <?= $t['status'] ?></span></td>
                    <td><span class="priority-pill <?= $pp ?>"><span class="pp-dot"></span> <?= $t['priority'] ?? '-' ?></span></td>
                    <td><span class="date-cell"><?= $updated ?></span></td>
                    <td><span class="date-cell"><?= $created ?></span></td>
                    <td>
                        <?php if($can_update): ?>
                        <a href="update_status.php?id=<?= $t['id'] ?>" class="btn-action btn-update" onclick="event.stopPropagation()"><i class="bi bi-pencil-square"></i> Update</a>
                        <?php else: ?>
                        <a href="../detail.php?id=<?= $t['id'] ?>" class="btn-action btn-detail" onclick="event.stopPropagation()"><i class="bi bi-eye"></i> Detail</a>
                        <?php endif; ?>
                        <?php if($t['status']=='Resolved'): ?>
                        <a href="#" class="btn-action btn-delete" onclick="deleteTicket(event, <?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['judul'])) ?>')"><i class="bi bi-trash"></i> Hapus</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            </div>
            <?php if($total_pages > 1): ?>
            <div class="pagination-bar">
                <?php if($page > 1): ?><a href="?page=<?= $page-1 ?>">&laquo;</a><?php endif; ?>
                <?php for($i=1;$i<=$total_pages;$i++): ?>
                    <?php if($i==$page): ?><span class="active-page"><?= $i ?></span>
                    <?php else: ?><a href="?page=<?= $i ?>"><?= $i ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if($page < $total_pages): ?><a href="?page=<?= $page+1 ?>">&raquo;</a><?php endif; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state"><i class="bi bi-inbox"></i><p>Belum ada tiket untuk divisi ini.</p></div>
            <?php endif; ?>
        </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    function filterTickets() {
        const input = document.getElementById('searchTicket');
        if (!input) return;
        const filter = input.value.toUpperCase();
        const table = document.querySelector('.ticket-table');
        if (!table) return;
        const rows = table.getElementsByClassName('ticket-row');
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const text = row.textContent || row.innerText;
            if (text.toUpperCase().indexOf(filter) > -1) {
                if (window.innerWidth <= 768) {
                    row.style.display = 'flex';
                } else {
                    row.style.display = '';
                }
            } else {
                row.style.display = 'none';
            }
        }
    }

    // Charts
    fetch('../api/chart_data.php').then(r=>r.json()).then(data=>{
        if(data.status_distribution){new Chart(document.getElementById('statusChart'),{type:'doughnut',data:{labels:data.status_distribution.labels,datasets:[{data:data.status_distribution.data,backgroundColor:data.status_distribution.colors,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,cutout:'60%',plugins:{legend:{position:'bottom',labels:{padding:14,usePointStyle:true,pointStyle:'circle',font:{size:11,family:'Inter'}}}}}});}
        if(data.monthly_tickets){new Chart(document.getElementById('monthlyChart'),{type:'bar',data:{labels:data.monthly_tickets.labels,datasets:[{label:'Tiket',data:data.monthly_tickets.data,backgroundColor:'rgba(16,54,125,0.75)',borderRadius:8,borderSkipped:false,barThickness:28}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{stepSize:1,font:{size:11,family:'Inter'}},grid:{color:'#f0f2f5'}},x:{ticks:{font:{size:11,family:'Inter'}},grid:{display:false}}},plugins:{legend:{display:false}}}});}
    }).catch(e=>console.warn('Chart error:',e));

    // Polling
    let lastPoll=new Date().toISOString().slice(0,19).replace('T',' ');
    let prev={total:<?= $total_tickets ?>,assigned:<?= $assigned_tickets ?>,progress:<?= $progress_tickets ?>,resolved:<?= $resolved_tickets ?>};
    function poll(){fetch('../api/check_tickets.php?since='+encodeURIComponent(lastPoll)).then(r=>r.json()).then(d=>{lastPoll=d.timestamp;const c=d.counts;if(c.total!==undefined){anim('statTotal',prev.total,c.total);anim('statAssigned',prev.assigned,c.assigned||c.open||0);anim('statProgress',prev.progress,c.progress);anim('statResolved',prev.resolved,c.resolved);}if(d.new_items&&d.new_items.length>0&&(c.total!==prev.total||c.resolved!==prev.resolved)){d.new_items.forEach(it=>{toast('bi-bell-fill text-primary','Tiket #'+it.id,it.judul+'  '+it.status);});}prev={total:c.total||0,assigned:c.assigned||c.open||0,progress:c.progress||0,resolved:c.resolved||0};}).catch(()=>{});}
    setInterval(poll,15000);
    function anim(id,f,t){const e=document.getElementById(id);if(!e||f===t)return;e.textContent=t;e.style.transition='transform .3s';e.style.transform='scale(1.15)';setTimeout(()=>{e.style.transform='scale(1)';},300);}
    function toast(ic,ti,msg){const c=document.getElementById('toastContainer');const t=document.createElement('div');t.className='toast-notif';t.innerHTML='<i class="bi '+ic+' toast-icon"></i><div class="toast-body"><div class="toast-title">'+ti+'</div><div class="toast-msg">'+msg+'</div></div><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>';c.appendChild(t);setTimeout(()=>{if(t.parentElement)t.remove();},6000);}

    // Bulk Delete Functions
    function toggleCheckboxes(){const cells=document.querySelectorAll('.cb-cell');const btn=document.getElementById('btnToggleCheckbox');const isHidden=cells[0]&&cells[0].classList.contains('hidden');cells.forEach(c=>isHidden?c.classList.remove('hidden'):c.classList.add('hidden'));if(btn){btn.innerHTML=isHidden?'<i class="bi bi-x-square"></i> Sembunyikan':'<i class="bi bi-check-square"></i> Pilih Cepat';}if(!isHidden){clearBulk();}}
    function toggleSelectAll(checkbox){const checkboxes=document.querySelectorAll('.ticket-cb');checkboxes.forEach(cb=>cb.checked=checkbox.checked);updateBulkBar();}
    function updateBulkBar(){const checked=document.querySelectorAll('.ticket-cb:checked');const bulkBar=document.getElementById('bulkBar');const bulkCount=document.getElementById('bulkCount');if(checked.length>0){bulkBar.classList.add('show');bulkCount.textContent=checked.length;}else{bulkBar.classList.remove('show');}}
    function isBulkSelectMode(){const anyCell=document.querySelector('.cb-cell');return !!(anyCell && !anyCell.classList.contains('hidden'));}
    function handleRowClick(event,row,url){if(event.target.closest('a,button,input,label,select,textarea'))return;if(isBulkSelectMode()){const cb=row.querySelector('.ticket-cb');if(cb){cb.checked=!cb.checked;updateBulkBar();}return;}window.location=url;}
    function handleRowKeydown(event,row,url){if(event.key!=='Enter'&&event.key!==' ')return;event.preventDefault();if(isBulkSelectMode()){const cb=row.querySelector('.ticket-cb');if(cb){cb.checked=!cb.checked;updateBulkBar();}return;}window.location=url;}
    function submitBulkDelete(){const checked=document.querySelectorAll('.ticket-cb:checked');if(checked.length===0){Swal.fire({icon:'info',title:'Pilih Tiket',text:'Silakan pilih minimal satu tiket untuk dihapus.',timer:2500,showConfirmButton:false});return;}const count=checked.length;const ticketWord=count===1?'tiket':'tiket';Swal.fire({title:'Hapus '+count+' '+ticketWord+'?',html:'Anda akan menghapus <strong>'+count+' '+ticketWord+'</strong>.<br><br><small style="color:#dc2626"> Pastikan Anda memiliki hak akses untuk menghapus tiket ini.</small><br><br>Tindakan ini tidak dapat dibatalkan!',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#6b7280',confirmButtonText:'Ya, Hapus',cancelButtonText:'Batal'}).then((result)=>{if(result.isConfirmed){document.getElementById('bulkForm').submit();}});}
    function clearBulk(){const checkboxes=document.querySelectorAll('.ticket-cb');checkboxes.forEach(cb=>cb.checked=false);const selectAll=document.getElementById('selectAll');if(selectAll)selectAll.checked=false;updateBulkBar();}

    // Hamburger
    (function(){const h=document.getElementById('hamburgerBtn');const s=document.querySelector('.sidebar');const o=document.getElementById('sidebarOverlay');if(!h||!s||!o)return;h.addEventListener('click',function(){this.classList.toggle('active');s.classList.toggle('active');o.classList.toggle('active');document.body.style.overflow=s.classList.contains('active')?'hidden':'';});o.addEventListener('click',function(){h.classList.remove('active');s.classList.remove('active');this.classList.remove('active');document.body.style.overflow='';});document.querySelectorAll('.sidebar .nav-link, .sidebar a').forEach(function(l){l.addEventListener('click',function(){if(window.innerWidth<=768){h.classList.remove('active');s.classList.remove('active');o.classList.remove('active');document.body.style.overflow='';}});});})();

    // Delete ticket
    function deleteTicket(event,id,title){event.preventDefault();event.stopPropagation();Swal.fire({title:'Hapus Tiket?',html:'Apakah Anda yakin ingin menghapus tiket:<br><strong>'+title+'</strong><br><br>Tindakan ini tidak dapat dibatalkan.',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',cancelButtonColor:'#6b7280',confirmButtonText:'Ya, Hapus',cancelButtonText:'Batal',reverseButtons:true}).then(r=>{if(r.isConfirmed){window.location.href='../admin/proses_delete_ticket.php?id='+id;}});}

    // Session messages
    <?php if(isset($_SESSION['success'])): ?>
    Swal.fire({icon:'success',title:'Berhasil!',text:'<?= htmlspecialchars($_SESSION["success"]) ?>',confirmButtonColor:'#10367D',timer:3000,timerProgressBar:true});
    <?php unset($_SESSION['success']); endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
    Swal.fire({icon:'error',title:'Gagal!',text:'<?= htmlspecialchars($_SESSION["error"]) ?>',confirmButtonColor:'#dc2626'});
    <?php unset($_SESSION['error']); endif; ?>

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'bulk_delete') {
        const count = urlParams.get('count') || '0';
        const fail = urlParams.get('fail') || '0';
        Swal.fire({icon:'success',title:'Bulk delete selesai',text:'Berhasil hapus '+count+' tiket. Gagal: '+fail+'.',timer:3500,showConfirmButton:false});
    } else if (urlParams.get('error') === 'bulk_empty') {
        Swal.fire({icon:'info',title:'Pilih tiket dulu',text:'Belum ada tiket yang dipilih untuk dihapus.',timer:3000,showConfirmButton:false});
    } else if (urlParams.get('error') === 'bulk_query_failed') {
        Swal.fire({icon:'error',title:'Gagal memproses',text:'Gagal mengambil data tiket untuk bulk delete.',timer:3000,showConfirmButton:false});
    }
    </script>
</body>
</html>