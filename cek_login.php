<?php
/**
 * File Cek Login
 * Memproses username & password, validasi ke database
 * Set session jika berhasil, redirect ke dashboard sesuai role
 */

require_once __DIR__ . '/config/bootstrap.php';
startSecureSession();
require_once __DIR__ . '/config/koneksi.php';

// Cek apakah form sudah disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validasi input kosong
    if (empty($username) || empty($password)) {
        header('Location: index.php?error=empty');
        exit();
    }

    // Query cari user berdasarkan username (prepared statement)
    $stmt = mysqli_prepare($conn, "SELECT u.*, d.nama_divisi
              FROM users u
              LEFT JOIN divisions d ON u.division_id = d.id
              WHERE u.username = ?
              LIMIT 1");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        // Cek apakah username ditemukan
        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);

            // Verifikasi password menggunakan password_verify
            if (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['division_id'] = $user['division_id'];
                $_SESSION['nama_divisi'] = $user['nama_divisi'];
                $_SESSION['login_time'] = date('Y-m-d H:i:s');

                // Redirect sesuai role
                if ($user['role'] == 'admin') {
                    header('Location: admin/dashboard.php');
                } elseif ($user['role'] == 'user') {
                    header('Location: user/dashboard.php');
                } elseif ($user['role'] == 'teknisi') {
                    header('Location: teknisi/dashboard.php');
                } else {
                    header('Location: index.php?error=invalid');
                }
                mysqli_stmt_close($stmt);
                exit();
            }
        }
        mysqli_stmt_close($stmt);
    }

    header('Location: index.php?error=invalid');
    exit();

} else {
    // Jika diakses langsung tanpa POST, redirect kembali ke login
    header('Location: index.php');
    exit();
}

?>


