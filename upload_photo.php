<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(0);

require_once 'config/auth.php';
require_once 'config/koneksi.php';
require_once 'config/upload_utils.php';

function send_json($payload) {
    if (ob_get_length()) { ob_clean(); }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    send_json(['success' => false, 'message' => 'Unauthorized']);
}

$user_id = $_SESSION['user_id'];

// Ensure foto column exists
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'foto'");
if (mysqli_num_rows($col_check) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN foto VARCHAR(255) DEFAULT NULL");
}

$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$upload_dir = __DIR__ . '/uploads/profile_photos/';
ensure_upload_dir($upload_dir);
$trash_dir = $upload_dir . '.trash/';
ensure_upload_dir($trash_dir);

function move_to_trash($src, $trash_dir) {
    if (!file_exists($src)) {
        return null;
    }
    $base = basename($src);
    $trash_name = 'trash_' . time() . '_' . $base;
    $dest = $trash_dir . $trash_name;
    if (@rename($src, $dest)) {
        return $trash_name;
    }
    return null;
}

// Handle undo photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo_photo'])) {
    if (empty($_SESSION['undo_photo'])) {
        send_json(['success' => false, 'message' => 'Undo tidak tersedia.']);
    }
    $undo = $_SESSION['undo_photo'];
    if (($undo['user_id'] ?? 0) !== $user_id || time() > ($undo['expires'] ?? 0)) {
        unset($_SESSION['undo_photo']);
        send_json(['success' => false, 'message' => 'Undo sudah kedaluwarsa.']);
    }
    $trash_path = $undo['trash_path'] ?? '';
    $restore_name = $undo['filename'] ?? '';
    if ($trash_path === '' || $restore_name === '' || !file_exists($trash_path)) {
        unset($_SESSION['undo_photo']);
        send_json(['success' => false, 'message' => 'File undo tidak ditemukan.']);
    }
    $restore_path = $upload_dir . $restore_name;
    if (!@rename($trash_path, $restore_path)) {
        send_json(['success' => false, 'message' => 'Gagal memulihkan foto.']);
    }
    $stmt = mysqli_prepare($conn, "UPDATE users SET foto = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $restore_name, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    unset($_SESSION['undo_photo']);
    send_json(['success' => true, 'message' => 'Foto profil dipulihkan.']);
}

// Handle delete photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    $stmt = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!empty($user['foto'])) {
        $filepath = __DIR__ . '/uploads/profile_photos/' . $user['foto'];
        if (file_exists($filepath)) {
            $trash_name = move_to_trash($filepath, $trash_dir);
            if ($trash_name) {
                $_SESSION['undo_photo'] = [
                    'user_id' => $user_id,
                    'filename' => $user['foto'],
                    'trash_path' => $trash_dir . $trash_name,
                    'expires' => time() + 5
                ];
            }
        }
        $stmt = mysqli_prepare($conn, "UPDATE users SET foto = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    send_json(['success' => true, 'message' => 'Foto profil berhasil dihapus', 'undo_available' => !empty($_SESSION['undo_photo'])]);
}

// Handle cropped photo upload (base64)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cropped_image'])) {
    $data = $_POST['cropped_image'];

    if (!preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/', $data, $m)) {
        send_json(['success' => false, 'message' => 'Format data tidak valid.']);
    }

    $mime = 'image/' . $m[1];

    // Extract only base64 payload (avoid decoding huge payloads unnecessarily)
    $base64_only = preg_replace('/^data:image\/(jpeg|png|gif|webp);base64,/', '', $data);

    // Quick size check on base64 string to short-circuit very large payloads
    // Base64 inflates size by ~4/3, so allow a slightly larger threshold here
    $max_base64_len = 3 * 1024 * 1024; // ~3MB base64 -> ~2.25MB binary
    if (strlen($base64_only) > $max_base64_len) {
        send_json(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal sekitar 2MB.']);
    }

    $data = base64_decode($base64_only);

    if ($data === false) {
        send_json(['success' => false, 'message' => 'Data gambar tidak valid.']);
    }

    if (strlen($data) > 2 * 1024 * 1024) {
        send_json(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 2MB.']);
    }

    if (!$mime || !isset($ext_map[$mime])) {
        send_json(['success' => false, 'message' => 'Format file tidak didukung.']);
    }

    // Delete old photo
    $stmt = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $old = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!empty($old['foto'])) {
        $old_path = $upload_dir . $old['foto'];
        if (file_exists($old_path)) unlink($old_path);
    }

    $filename = 'user_' . $user_id . '_' . time() . '.' . $ext_map[$mime];
    $dest = $upload_dir . $filename;
    $out_mime = '';

    if (process_base64_image($data, $dest, 600, 600, $allowed_mimes, $out_mime)) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET foto = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $filename, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        send_json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui',
            'filename' => $filename,
            'url' => 'uploads/profile_photos/' . $filename
        ]);
    }

    send_json(['success' => false, 'message' => 'Gagal menyimpan file.']);
}

// Handle regular file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $max_size = 2 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        send_json(['success' => false, 'message' => 'Upload gagal. Error code: ' . $file['error']]);
    }
    if ($file['size'] > $max_size) {
        send_json(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 2MB.']);
    }

    $mime = detect_mime($file['tmp_name']);
    if (!$mime || !isset($ext_map[$mime])) {
        send_json(['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WebP.']);
    }

    // Delete old photo
    $stmt = mysqli_prepare($conn, "SELECT foto FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $old = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!empty($old['foto'])) {
        $old_path = $upload_dir . $old['foto'];
        if (file_exists($old_path)) unlink($old_path);
    }

    $filename = 'user_' . $user_id . '_' . time() . '.' . $ext_map[$mime];
    $dest = $upload_dir . $filename;
    $out_mime = '';

    if (process_uploaded_image($file['tmp_name'], $dest, 600, 600, $allowed_mimes, $out_mime)) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET foto = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $filename, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        send_json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui',
            'filename' => $filename,
            'url' => 'uploads/profile_photos/' . $filename
        ]);
    }

    send_json(['success' => false, 'message' => 'Gagal menyimpan file.']);
}

send_json(['success' => false, 'message' => 'Invalid request']);
?>
