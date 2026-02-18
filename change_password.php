<?php
require_once __DIR__ . '/config/auth.php';
requireLogin();
require_once __DIR__ . '/config/koneksi.php';

$user = getUserInfo();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $current = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    if ($current === '' || $new === '' || $confirm === '') {
        $errors[] = 'Semua field wajib diisi.';
    }
    if ($new !== $confirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }
    if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $errors[] = 'Password minimal 8 karakter dan kombinasi huruf dan angka.';
    }

    if (!$errors) {
        $stmt = mysqli_prepare($conn, 'SELECT password FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $user['id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if (!$row || !password_verify($current, $row['password'])) {
                $errors[] = 'Password saat ini salah.';
            }
        } else {
            $errors[] = 'Gagal memvalidasi user.';
        }
    }

    if (!$errors) {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $new_hash, $user['id']);
            if (mysqli_stmt_execute($stmt)) {
                if (mysqli_stmt_affected_rows($stmt) === 1) {
                    session_regenerate_id(true);
                    $success = 'Password berhasil diubah. Silakan login ulang menggunakan password baru.';
                } else {
                    $errors[] = 'Password tidak berubah. Silakan coba lagi.';
                }
            } else {
                $errors[] = 'Gagal menyimpan password baru.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Gagal menyiapkan query update.';
        }
    }
}

$back_url = 'index.php';
if ($user['role'] === 'admin') {
    $back_url = 'admin/dashboard.php';
} elseif ($user['role'] === 'user') {
    $back_url = 'user/dashboard.php';
} elseif ($user['role'] === 'teknisi') {
    $back_url = 'teknisi/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - Ticketing TVRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8f9fc; }
        .page-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; }
        .card-wrap { width: 100%; max-width: 520px; background: #fff; border: 1px solid #eef0f5; border-radius: 18px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,.05); }
        .card-title { font-size: 1.35rem; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; }
        .card-sub { color: #8b8fa3; font-size: .9rem; margin-bottom: 22px; }
        .form-label { font-size: .82rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .form-control { border-radius: 10px; padding: 10px 12px; font-size: .9rem; }
        .btn-primary { background: #10367D; border-color: #10367D; border-radius: 10px; font-weight: 600; }
        .btn-primary:hover { background: #0c2d68; border-color: #0c2d68; }
        .btn-light { border-radius: 10px; font-weight: 600; }
        .hint { font-size: .8rem; color: #9ca3af; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="card-wrap">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-shield-lock" style="color:#10367D;font-size:1.3rem"></i>
                <div class="card-title">Ganti Password</div>
            </div>
            <div class="card-sub">Pastikan password baru minimal 8 karakter dan kombinasi huruf serta angka.</div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form id="changePasswordForm" action="change_password.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="mb-3">
                    <label class="form-label" for="current_password">Password Saat Ini</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_password">Password Baru</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <div class="hint">Minimal 8 karakter, kombinasi huruf dan angka.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="confirm_password">Konfirmasi Password Baru</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Simpan</button>
                    <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-light w-100">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>




