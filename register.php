<?php
/**
 * Registration Handler
 * - POST: Register new user (role='user' only)
 * - GET with check_username: Check username availability (AJAX)
 * 
 * Security: CSRF token, prepared statements, bcrypt password hashing,
 *           input validation, rate limiting via session
 */

require_once __DIR__ . '/config/bootstrap.php';
startSecureSession();
require_once __DIR__ . '/config/koneksi.php';

header('Content-Type: application/json; charset=utf-8');

// Check database connection
if (!isset($conn) || !$conn || mysqli_connect_errno()) {
    echo json_encode([
        'success' => false,
        'message' => 'Database tidak terhubung. Pastikan MySQL/MariaDB berjalan.'
    ]);
    exit;
}

// Ensure email column exists
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
if (mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER password");
}

// ─── GET: Check username availability ───
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_username'])) {
    $username = trim($_GET['check_username']);
    
    if (empty($username) || strlen($username) < 4) {
        echo json_encode(['available' => false]);
        exit;
    }
    
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $available = mysqli_num_rows($result) === 0;
    mysqli_stmt_close($stmt);
    
    echo json_encode(['available' => $available]);
    exit;
}

// ─── POST: Register new user ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    // Rate limiting: max 5 registration attempts per minute
    $now = time();
    if (!isset($_SESSION['reg_attempts'])) {
        $_SESSION['reg_attempts'] = [];
    }
    // Clean old attempts (older than 60 seconds)
    $_SESSION['reg_attempts'] = array_filter($_SESSION['reg_attempts'], function($t) use ($now) {
        return ($now - $t) < 60;
    });
    if (count($_SESSION['reg_attempts']) >= 5) {
        echo json_encode(['success' => false, 'message' => 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.']);
        exit;
    }
    $_SESSION['reg_attempts'][] = $now;
    
    // CSRF validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!csrf_validate($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan refresh halaman.']);
        exit;
    }
    
    // Collect & sanitize input
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    
    // ─── Validation ───
    $errors = [];
    
    // Nama: required, 3-100 chars
    if ($nama === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
        $errors[] = 'Nama harus 3-100 karakter.';
    }
    
    // Username: required, 4-50 chars, alphanumeric + underscore only
    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
        $errors[] = 'Username harus 4-50 karakter, hanya huruf, angka, dan underscore.';
    }
    
    // Email: required, valid format
    if ($email === '') {
        $errors[] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    } elseif (mb_strlen($email) > 100) {
        $errors[] = 'Email maksimal 100 karakter.';
    }
    
    // Password: required, min 8 chars, max 72 (bcrypt limit)
    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password maksimal 72 karakter.';
    }
    
    // Password strength: at least lowercase + uppercase OR number
    if ($password !== '' && strlen($password) >= 8) {
        $hasLower = preg_match('/[a-z]/', $password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasDigit = preg_match('/\d/', $password);
        if (!$hasLower || (!$hasUpper && !$hasDigit)) {
            $errors[] = 'Password harus mengandung huruf kecil dan (huruf besar atau angka).';
        }
    }
    
    // Confirm password
    if ($password !== $confirm) {
        $errors[] = 'Password dan konfirmasi password tidak cocok.';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }
    
    // ─── Check uniqueness ───
    // Check username
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        echo json_encode(['success' => false, 'message' => 'Username sudah digunakan. Pilih username lain.']);
        mysqli_stmt_close($stmt);
        exit;
    }
    mysqli_stmt_close($stmt);
    
    // Check email
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND email != '' LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar. Gunakan email lain.']);
        mysqli_stmt_close($stmt);
        exit;
    }
    mysqli_stmt_close($stmt);
    
    // ─── Create user ───
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $role = 'user'; // Only user role can register
    
    $stmt = mysqli_prepare($conn, "INSERT INTO users (nama, username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "sssss", $nama, $username, $email, $hashed_password, $role);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Regenerate CSRF token after successful registration
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        echo json_encode([
            'success' => true,
            'message' => 'Akun berhasil dibuat! Silakan login dengan username dan password Anda.'
        ]);
    } else {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Gagal membuat akun. Silakan coba lagi.']);
    }
    exit;
}

// If accessed without proper params
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;
?>
