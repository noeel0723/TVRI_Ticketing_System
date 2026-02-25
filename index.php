<?php
require_once __DIR__ . '/config/bootstrap.php';
startSecureSession();

// Cek session lengkap sebelum redirect
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && !empty($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role == 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    } elseif ($role == 'user') {
        header('Location: user/dashboard.php');
        exit();
    } elseif ($role == 'teknisi') {
        header('Location: teknisi/dashboard.php');
        exit();
    } else {
        // Role tidak dikenal, bersihkan session
        session_unset();
        session_destroy();
    }
} elseif (isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    // Session corrupt: user_id ada tapi role tidak ada
    session_unset();
    session_destroy();
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ticketing System TVRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: -200px; right: -200px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(16, 54, 125, 0.05) 0%, transparent 70%);
            border-radius: 50%;
        }
        body::after {
            content: '';
            position: absolute;
            bottom: -150px; left: -150px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(165, 206, 0, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .auth-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(16, 54, 125, 0.1);
            padding: 35px 40px 30px;
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
        }
        .logo-container { text-align: center; margin-bottom: 20px; }
        .logo-container img { width: 120px; height: auto; margin-bottom: 12px; }
        .auth-header { text-align: center; margin-bottom: 24px; }
        .auth-header h1 { font-size: 1.55rem; font-weight: 700; color: #10367D; margin-bottom: 6px; }
        .auth-header p { font-size: 0.88rem; color: #6b7280; line-height: 1.4; }
        .auth-tabs {
            display: flex; background: #f3f4f6; border-radius: 12px;
            padding: 4px; margin-bottom: 24px; gap: 4px;
        }
        .auth-tab {
            flex: 1; padding: 10px; border: none; background: none;
            border-radius: 9px; font-size: 0.88rem; font-weight: 600;
            font-family: 'Inter', sans-serif; color: #6b7280; cursor: pointer;
            transition: all .25s ease;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .auth-tab.active { background: #10367D; color: #fff; box-shadow: 0 2px 8px rgba(16, 54, 125, 0.25); }
        .auth-tab:not(.active):hover { color: #10367D; background: rgba(16, 54, 125, 0.05); }
        .auth-tab i { font-size: 1rem; }
        .auth-panel { display: none; }
        .auth-panel.active { display: block; animation: fadeIn .3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: flex; align-items: center; gap: 6px;
            font-weight: 500; font-size: 0.85rem; color: #374151; margin-bottom: 6px;
        }
        .form-group label i { font-size: 0.92rem; color: #10367D; }
        .input-wrapper { position: relative; }
        .input-wrapper input {
            width: 100%; padding: 11px 15px;
            border: 2px solid #e5e7eb; border-radius: 11px;
            font-size: 0.9rem; font-family: 'Inter', sans-serif;
            transition: all 0.3s ease; background: #f9fafb; color: #1a1a2e;
        }
        .input-wrapper input:focus {
            outline: none; border-color: #10367D; background: white;
            box-shadow: 0 0 0 3px rgba(16, 54, 125, 0.08);
        }
        .input-wrapper input::placeholder { color: #9ca3af; }
        .input-wrapper input.is-invalid { border-color: #ef4444; }
        .input-wrapper input.is-valid { border-color: #22c55e; }
        .input-wrapper .toggle-password {
            position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #9ca3af; cursor: pointer;
            font-size: 1.1rem; padding: 4px; transition: color 0.2s;
        }
        .input-wrapper .toggle-password:hover { color: #10367D; }
        .field-hint { font-size: 0.74rem; color: #9ca3af; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
        .field-hint.error { color: #ef4444; }
        .field-hint.success { color: #22c55e; }
        .password-strength { display: flex; gap: 4px; margin-top: 8px; }
        .strength-bar { flex: 1; height: 4px; background: #e5e7eb; border-radius: 2px; transition: background .3s; }
        .strength-bar.weak { background: #ef4444; }
        .strength-bar.fair { background: #f59e0b; }
        .strength-bar.good { background: #22c55e; }
        .strength-bar.strong { background: #10367D; }
        .strength-label { font-size: 0.72rem; font-weight: 600; margin-top: 4px; text-align: right; }
        .btn-submit {
            width: 100%; padding: 12px; background: #10367D; color: white;
            border: none; border-radius: 11px; font-size: 0.95rem; font-weight: 600;
            font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.3s ease;
            margin-top: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { background: #0d2d68; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(16, 54, 125, 0.25); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
        .btn-submit i { font-size: 1.05rem; }
        .btn-submit .spinner {
            display: none; width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
            border-radius: 50%; animation: spin .6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .copyright { text-align: center; margin-top: 20px; color: #9ca3af; font-size: 0.8rem; }
        @media (max-height: 750px) {
            .auth-card { padding: 24px 32px 22px; }
            .logo-container { margin-bottom: 14px; }
            .logo-container img { width: 90px; margin-bottom: 8px; }
            .auth-header { margin-bottom: 16px; }
            .auth-header h1 { font-size: 1.4rem; }
            .form-group { margin-bottom: 12px; }
            .copyright { margin-top: 14px; }
        }
        @media (max-width: 480px) {
            .auth-card { padding: 28px 24px; max-width: 100%; }
            .logo-container img { width: 90px; }
            .auth-header h1 { font-size: 1.35rem; }
            .row-2col { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="logo-container">
            <img src="assets/Logo_TVRI.svg.png" alt="Logo TVRI">
        </div>
        <div class="auth-header" id="authHeader">
            <h1 id="authTitle">Sign in with account</h1>
            <p id="authDesc">Masuk untuk mengakses sistem pelaporan<br>dan penanganan gangguan teknis TVRI</p>
        </div>
        <div class="auth-tabs">
            <button class="auth-tab active" data-panel="login" onclick="switchTab('login')"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
            <button class="auth-tab" data-panel="register" onclick="switchTab('register')"><i class="bi bi-person-plus"></i> Sign Up</button>
        </div>
        <!-- LOGIN PANEL -->
        <div class="auth-panel active" id="panelLogin">
            <form id="loginForm" action="cek_login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="form-group">
                    <label for="login_username"><i class="bi bi-person-circle"></i> Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="login_username" name="username" placeholder="Masukkan username Anda" required autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label for="login_password"><i class="bi bi-shield-lock"></i> Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="login_password" name="password" placeholder="Masukkan password Anda" required autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePw('login_password','loginEyeIcon')" aria-label="Toggle password">
                            <i class="bi bi-eye" id="loginEyeIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
            </form>
        </div>

        <!-- REGISTER PANEL -->
        <div class="auth-panel" id="panelRegister">
            <form id="registerForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="row-2col">
                    <div class="form-group">
                        <label for="reg_nama"><i class="bi bi-person"></i> Nama Lengkap</label>
                        <div class="input-wrapper">
                            <input type="text" id="reg_nama" name="nama" placeholder="Nama lengkap" required minlength="3" maxlength="100">
                        </div>
                        <div class="field-hint" id="hintNama"></div>
                    </div>
                    <div class="form-group">
                        <label for="reg_username"><i class="bi bi-at"></i> Username</label>
                        <div class="input-wrapper">
                            <input type="text" id="reg_username" name="username" placeholder="Username unik" required minlength="4" maxlength="50" autocomplete="off">
                        </div>
                        <div class="field-hint" id="hintUsername"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reg_email"><i class="bi bi-envelope"></i> Email <small style="opacity:.6;font-weight:400">(opsional)</small></label>
                    <div class="input-wrapper">
                        <input type="email" id="reg_email" name="email" placeholder="contoh@email.com (opsional)" maxlength="100">
                    </div>
                    <div class="field-hint" id="hintEmail"></div>
                </div>
                <div class="row-2col">
                    <div class="form-group">
                        <label for="reg_password"><i class="bi bi-lock"></i> Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="reg_password" name="password" placeholder="Min. 8 karakter" required minlength="8" maxlength="72" autocomplete="new-password">
                            <button type="button" class="toggle-password" onclick="togglePw('reg_password','regEyeIcon')" aria-label="Toggle password">
                                <i class="bi bi-eye" id="regEyeIcon"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="strengthBars">
                            <div class="strength-bar" id="str1"></div>
                            <div class="strength-bar" id="str2"></div>
                            <div class="strength-bar" id="str3"></div>
                            <div class="strength-bar" id="str4"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel"></div>
                    </div>
                    <div class="form-group">
                        <label for="reg_confirm"><i class="bi bi-lock-fill"></i> Konfirmasi</label>
                        <div class="input-wrapper">
                            <input type="password" id="reg_confirm" name="confirm_password" placeholder="Ulangi password" required autocomplete="new-password">
                        </div>
                        <div class="field-hint" id="hintConfirm"></div>
                    </div>
                </div>
                <button type="submit" class="btn-submit" id="btnRegister">
                    <i class="bi bi-person-plus"></i>
                    <span>Sign Up</span>
                    <div class="spinner" id="regSpinner"></div>
                </button>
            </form>
        </div>
        <div class="copyright">&copy; 2026 TVRI - Ticketing System</div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function switchTab(panel) {
        document.querySelectorAll('.auth-tab').forEach(function(t) { t.classList.remove('active'); });
        document.querySelector('.auth-tab[data-panel="' + panel + '"]').classList.add('active');
        document.querySelectorAll('.auth-panel').forEach(function(p) { p.classList.remove('active'); });
        document.getElementById(panel === 'login' ? 'panelLogin' : 'panelRegister').classList.add('active');
        var title = document.getElementById('authTitle');
        var desc = document.getElementById('authDesc');
        if (panel === 'login') {
            title.textContent = 'Sign in with account';
            desc.innerHTML = 'Masuk untuk mengakses sistem pelaporan<br>dan penanganan gangguan teknis TVRI';
        } else {
            title.textContent = 'Sign up for an account';
            desc.innerHTML = 'Daftar sebagai user pelapor untuk<br>melaporkan gangguan teknis TVRI';
        }
    }

    function togglePw(inputId, iconId) {
        var pw = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        if (pw.type === 'password') { pw.type = 'text'; icon.classList.replace('bi-eye', 'bi-eye-slash'); }
        else { pw.type = 'password'; icon.classList.replace('bi-eye-slash', 'bi-eye'); }
    }

    // Password strength meter
    var regPw = document.getElementById('reg_password');
    regPw.addEventListener('input', function() {
        var val = this.value;
        var score = 0;
        if (val.length >= 8) score++;
        if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
        if (/\d/.test(val)) score++;
        if (/[^a-zA-Z0-9]/.test(val)) score++;
        var bars = ['str1','str2','str3','str4'];
        var cls = ['weak','fair','good','strong'];
        var labels = ['','Lemah','Cukup','Bagus','Kuat'];
        var colors = ['','#ef4444','#f59e0b','#22c55e','#10367D'];
        bars.forEach(function(b, i) {
            var el = document.getElementById(b);
            el.className = 'strength-bar';
            if (i < score) el.classList.add(cls[score-1]);
        });
        var lbl = document.getElementById('strengthLabel');
        lbl.textContent = val.length > 0 ? labels[score] : '';
        lbl.style.color = colors[score] || '#9ca3af';
        checkConfirmMatch();
    });

    // Confirm password match
    var regConfirm = document.getElementById('reg_confirm');
    regConfirm.addEventListener('input', checkConfirmMatch);
    function checkConfirmMatch() {
        var pw = regPw.value;
        var cf = regConfirm.value;
        var hint = document.getElementById('hintConfirm');
        if (cf.length === 0) { hint.textContent = ''; regConfirm.classList.remove('is-invalid','is-valid'); return; }
        if (pw === cf) { hint.innerHTML = '&#10003; Password cocok'; hint.className = 'field-hint success'; regConfirm.classList.remove('is-invalid'); regConfirm.classList.add('is-valid'); }
        else { hint.innerHTML = '&#10007; Password tidak cocok'; hint.className = 'field-hint error'; regConfirm.classList.remove('is-valid'); regConfirm.classList.add('is-invalid'); }
    }

    // Username availability check
    var usernameTimer;
    var regUsername = document.getElementById('reg_username');
    regUsername.addEventListener('input', function() {
        var val = this.value.trim();
        var hint = document.getElementById('hintUsername');
        if (val.length === 0) { hint.textContent = ''; this.classList.remove('is-invalid','is-valid'); return; }
        if (!/^[a-zA-Z0-9_]+$/.test(val)) { hint.innerHTML = '&#10007; Hanya huruf, angka, underscore'; hint.className = 'field-hint error'; this.classList.add('is-invalid'); this.classList.remove('is-valid'); return; }
        if (val.length < 4) { hint.innerHTML = '&#10007; Minimal 4 karakter'; hint.className = 'field-hint error'; this.classList.add('is-invalid'); this.classList.remove('is-valid'); return; }
        hint.textContent = 'Memeriksa...'; hint.className = 'field-hint';
        clearTimeout(usernameTimer);
        usernameTimer = setTimeout(function() {
            fetch('register.php?check_username=' + encodeURIComponent(val))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.available) { hint.innerHTML = '&#10003; Username tersedia'; hint.className = 'field-hint success'; regUsername.classList.remove('is-invalid'); regUsername.classList.add('is-valid'); }
                    else { hint.innerHTML = '&#10007; Username sudah digunakan'; hint.className = 'field-hint error'; regUsername.classList.add('is-invalid'); regUsername.classList.remove('is-valid'); }
                }).catch(function() { hint.textContent = ''; hint.className = 'field-hint'; });
        }, 400);
    });
    // Nama validation
    document.getElementById('reg_nama').addEventListener('input', function() {
        var hint = document.getElementById('hintNama');
        if (this.value.trim().length > 0 && this.value.trim().length < 3) { hint.innerHTML = '&#10007; Minimal 3 karakter'; hint.className = 'field-hint error'; }
        else { hint.textContent = ''; }
    });

    // Email validation
    document.getElementById('reg_email').addEventListener('input', function() {
        var hint = document.getElementById('hintEmail');
        var val = this.value.trim();
        if (val.length === 0) { hint.textContent = ''; this.classList.remove('is-invalid','is-valid'); return; }
        var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
        if (!emailOk) { hint.innerHTML = '&#10007; Format email tidak valid'; hint.className = 'field-hint error'; this.classList.add('is-invalid'); this.classList.remove('is-valid'); }
        else { hint.textContent = ''; this.classList.remove('is-invalid'); this.classList.add('is-valid'); }
    });

    // Form submit
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var nama = document.getElementById('reg_nama').value.trim();
        var username = document.getElementById('reg_username').value.trim();
        var email = document.getElementById('reg_email').value.trim();
        var password = document.getElementById('reg_password').value;
        var confirm = document.getElementById('reg_confirm').value;
        var csrf = this.querySelector('[name="csrf_token"]').value;

        if (nama.length < 3) { Swal.fire({icon:'warning',title:'Validasi',text:'Nama minimal 3 karakter.',confirmButtonColor:'#10367D'}); return; }
        if (!/^[a-zA-Z0-9_]{4,50}$/.test(username)) { Swal.fire({icon:'warning',title:'Validasi',text:'Username 4-50 karakter, hanya huruf/angka/underscore.',confirmButtonColor:'#10367D'}); return; }
        if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { Swal.fire({icon:'warning',title:'Validasi',text:'Format email tidak valid.',confirmButtonColor:'#10367D'}); return; }
        if (password.length < 8) { Swal.fire({icon:'warning',title:'Validasi',text:'Password minimal 8 karakter.',confirmButtonColor:'#10367D'}); return; }
        if (password !== confirm) { Swal.fire({icon:'warning',title:'Validasi',text:'Password dan konfirmasi tidak cocok.',confirmButtonColor:'#10367D'}); return; }

        var btn = document.getElementById('btnRegister');
        var spinner = document.getElementById('regSpinner');
        btn.disabled = true;
        spinner.style.display = 'inline-block';

        var fd = new FormData();
        fd.append('nama', nama);
        fd.append('username', username);
        fd.append('email', email);
        fd.append('password', password);
        fd.append('confirm_password', confirm);
        fd.append('csrf_token', csrf);
        fd.append('register', '1');

        fetch('register.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                spinner.style.display = 'none';
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registrasi Berhasil!',
                        text: data.message || 'Akun berhasil dibuat. Silakan login.',
                        confirmButtonColor: '#10367D',
                        confirmButtonText: 'Login Sekarang'
                    }).then(function() {
                        switchTab('login');
                        document.getElementById('login_username').value = username;
                        document.getElementById('login_password').focus();
                        document.getElementById('registerForm').reset();
                        document.querySelectorAll('#panelRegister .field-hint').forEach(function(h) { h.textContent = ''; });
                        document.querySelectorAll('#panelRegister input').forEach(function(i) { i.classList.remove('is-valid','is-invalid'); });
                        document.querySelectorAll('.strength-bar').forEach(function(b) { b.className = 'strength-bar'; });
                        document.getElementById('strengthLabel').textContent = '';
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: data.message || 'Registrasi gagal.', confirmButtonColor: '#10367D' });
                }
            })
            .catch(function() {
                btn.disabled = false;
                spinner.style.display = 'none';
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Terjadi kesalahan server.', confirmButtonColor: '#10367D' });
            });
    });

    // URL params handling
    var urlParams = new URLSearchParams(window.location.search);
    var error = urlParams.get('error');
    if (error === 'invalid') {
        Swal.fire({ icon: 'error', title: 'Login Gagal', text: 'Username atau password salah!', confirmButtonColor: '#10367D' });
    } else if (error === 'empty') {
        Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Username dan password harus diisi!', confirmButtonColor: '#10367D' });
    }
    if (urlParams.get('tab') === 'register') { switchTab('register'); }
    var regSuccess = urlParams.get('registered');
    if (regSuccess === '1') {
        Swal.fire({ icon: 'success', title: 'Registrasi Berhasil!', text: 'Akun berhasil dibuat. Silakan login.', confirmButtonColor: '#10367D' });
    }
    </script>
</body>
</html>