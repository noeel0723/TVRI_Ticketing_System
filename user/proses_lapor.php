<?php
require_once '../config/auth.php';
checkRole('user');
require_once '../config/koneksi.php';
require_once '../config/logger.php';
require_once '../config/upload_utils.php';
require_once '../config/email_helper.php';

$user = getUserInfo();

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_require();

    // Ambil dan validasi data
    $judul = isset($_POST['judul']) ? trim($_POST['judul']) : '';
    $lokasi = isset($_POST['lokasi']) ? trim($_POST['lokasi']) : '';
    $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';

    // Validasi input kosong
    if (empty($judul) || empty($lokasi) || empty($deskripsi)) {
        header('Location: lapor.php?error=empty');
        exit();
    }

    // Handle file upload
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];

        // Validate file size (max 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            header('Location: lapor.php?error=file_size');
            exit();
        }

        $uploadDir = '../uploads/tickets/';
        ensure_upload_dir($uploadDir);

        $allowedMimes = ['image/jpeg', 'image/png'];
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        $mime = detect_mime($fileTmpName);

        if (!$mime || !in_array($mime, $allowedMimes, true)) {
            header('Location: lapor.php?error=file_type');
            exit();
        }

        $newFileName = 'ticket_' . time() . '_' . uniqid() . '.' . $extMap[$mime];
        $uploadPath = $uploadDir . $newFileName;
        $outMime = '';

        if (!process_uploaded_image($fileTmpName, $uploadPath, 1600, 1600, $allowedMimes, $outMime)) {
            header('Location: lapor.php?error=upload_failed');
            exit();
        }

        $attachment = $newFileName;
    } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        header('Location: lapor.php?error=upload_failed');
        exit();
    }

    $user_id = $user['id'];

    // Insert ke database dengan prepared statement
    $stmt = mysqli_prepare($conn, "INSERT INTO tickets (judul, lokasi, deskripsi, attachment, user_id, status, priority, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'Open', NULL, NOW(), NOW())");
    mysqli_stmt_bind_param($stmt, "ssssi", $judul, $lokasi, $deskripsi, $attachment, $user_id);

    $result = mysqli_stmt_execute($stmt);

    if ($result) {
        $ticket_id = mysqli_insert_id($conn);
        logTicketActivity($conn, $ticket_id, $user_id, 'create', '', 'Open', "Tiket baru dibuat: $judul");
        
        // Send email notification to admin/teknisi
        notifyNewTicket($conn, $ticket_id);

        mysqli_stmt_close($stmt);
        header('Location: dashboard.php?success=lapor&id=' . $ticket_id);
        exit();
    } else {
        if ($attachment && file_exists('../uploads/tickets/' . $attachment)) {
            unlink('../uploads/tickets/' . $attachment);
        }

        mysqli_stmt_close($stmt);
        header('Location: lapor.php?error=gagal');
        exit();
    }

} else {
    header('Location: lapor.php');
    exit();
}
?>
