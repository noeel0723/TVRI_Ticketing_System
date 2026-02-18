<?php
/**
 * Skrip reset password (development only)
 * Usage:
 *  - Via browser: http://localhost/ticketing_tvri/scripts/reset_passwords.php?run=1
 *  - Via CLI: php scripts/reset_passwords.php
 *
 * Akan meng-update semua user.password ke password_hash('123456', PASSWORD_DEFAULT)
 * WARNING: Jangan jalankan ini di lingkungan produksi.
 */

// Jika diakses via web, butuh parameter run=1
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['run']) || $_GET['run'] != '1') {
        echo "<h3>Reset Password (Development)</h3>";
        echo "<p>Untuk menjalankan, tambahkan <code>?run=1</code> pada URL.</p>";
        echo "<p>Contoh: <a href=\"?run=1\">?run=1</a></p>";
        exit;
    }
}

require_once __DIR__ . '/../config/koneksi.php';

$new_plain = '123456';
$new_hash = password_hash($new_plain, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = '" . mysqli_real_escape_string($conn, $new_hash) . "'";
$res = mysqli_query($conn, $sql);

if ($res) {
    $affected = mysqli_affected_rows($conn);
    $msg = "Berhasil mengupdate password untuk $affected user. Password baru: $new_plain";
    if (php_sapi_name() !== 'cli') {
        echo "<p>$msg</p>";
        echo "<p>Hapus file ini setelah selesai untuk keamanan.</p>";
    } else {
        echo $msg . PHP_EOL;
    }
} else {
    $err = mysqli_error($conn);
    if (php_sapi_name() !== 'cli') {
        echo "<p>Gagal: $err</p>";
    } else {
        echo "Gagal: $err" . PHP_EOL;
    }
}

?>