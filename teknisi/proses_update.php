<?php
require_once '../config/auth.php';
checkRole('teknisi');
require_once '../config/koneksi.php';
require_once '../config/logger.php';
require_once '../config/upload_utils.php';
require_once '../config/mailer.php';

$user = getUserInfo();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_require();

    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
    $catatan_teknisi = isset($_POST['catatan_teknisi']) ? trim($_POST['catatan_teknisi']) : '';

    if ($ticket_id == 0 || empty($new_status)) {
        header('Location: update_status.php?id=' . $ticket_id . '&error=empty');
        exit();
    }

    if ($new_status == 'Resolved') {
        if (empty($catatan_teknisi)) {
            header('Location: update_status.php?id=' . $ticket_id . '&error=catatan_required');
            exit();
        }
        if (!isset($_FILES['foto_perbaikan']) || $_FILES['foto_perbaikan']['error'] == UPLOAD_ERR_NO_FILE) {
            header('Location: update_status.php?id=' . $ticket_id . '&error=foto_required');
            exit();
        }
    }

    $valid_statuses = ['In Progress', 'Resolved'];
    if (!in_array($new_status, $valid_statuses)) {
        header('Location: update_status.php?id=' . $ticket_id . '&error=invalid_status');
        exit();
    }

    $division_id = $user['division_id'];
    $check_query = "SELECT id, status FROM tickets WHERE id = $ticket_id AND assigned_division_id = $division_id";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) == 0) {
        header('Location: dashboard.php?error=not_authorized');
        exit();
    }

    $current_ticket = mysqli_fetch_assoc($check_result);
    $current_status = $current_ticket['status'];
    $valid_transition = false;

    if ($current_status == 'Assigned' && $new_status == 'In Progress') {
        $valid_transition = true;
    } elseif ($current_status == 'In Progress' && $new_status == 'Resolved') {
        $valid_transition = true;
    }

    if (!$valid_transition) {
        header('Location: update_status.php?id=' . $ticket_id . '&error=invalid_transition');
        exit();
    }

    $foto_perbaikan_name = null;
    if ($new_status == 'Resolved' || (isset($_FILES['foto_perbaikan']) && $_FILES['foto_perbaikan']['error'] == UPLOAD_ERR_OK)) {
        if (!isset($_FILES['foto_perbaikan']) || $_FILES['foto_perbaikan']['error'] != UPLOAD_ERR_OK) {
            if ($new_status == 'Resolved') {
                $upload_error = isset($_FILES['foto_perbaikan']) ? $_FILES['foto_perbaikan']['error'] : 'no_file';
                error_log("Upload error for ticket $ticket_id: $upload_error");
                header('Location: update_status.php?id=' . $ticket_id . '&error=foto_upload_error');
                exit();
            }
        } else {
            $upload_dir = '../uploads/perbaikan/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    error_log("Failed to create upload directory: $upload_dir");
                    header('Location: update_status.php?id=' . $ticket_id . '&error=upload_dir_failed');
                    exit();
                }
            }

            $file_tmp = $_FILES['foto_perbaikan']['tmp_name'];
            $file_size = $_FILES['foto_perbaikan']['size'];
            $file_name = $_FILES['foto_perbaikan']['name'];

            // Validasi file exists
            if (!file_exists($file_tmp)) {
                error_log("Temp file not found: $file_tmp");
                header('Location: update_status.php?id=' . $ticket_id . '&error=temp_file_missing');
                exit();
            }

            // Validasi ukuran file (max 5MB)
            if ($file_size > 5242880) {
                error_log("File too large: $file_size bytes");
                header('Location: update_status.php?id=' . $ticket_id . '&error=file_too_large');
                exit();
            }

            // Deteksi MIME type
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/jpg'];
            $ext_map = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png'];
            
            $mime = detect_mime($file_tmp);
            if (!$mime) {
                // Fallback: cek dari extension
                $path_info = pathinfo($file_name);
                $ext = strtolower($path_info['extension'] ?? '');
                if ($ext == 'jpg' || $ext == 'jpeg') {
                    $mime = 'image/jpeg';
                } elseif ($ext == 'png') {
                    $mime = 'image/png';
                } else {
                    error_log("Cannot detect MIME type for: $file_name");
                    header('Location: update_status.php?id=' . $ticket_id . '&error=invalid_mime');
                    exit();
                }
            }

            if (!in_array($mime, $allowed_mimes, true)) {
                error_log("Invalid MIME type: $mime (file: $file_name)");
                header('Location: update_status.php?id=' . $ticket_id . '&error=invalid_file');
                exit();
            }

            $foto_perbaikan_name = 'perbaikan_' . $ticket_id . '_' . time() . '.' . $ext_map[$mime];
            $upload_path = $upload_dir . $foto_perbaikan_name;
            $out_mime = '';

            // Coba proses dengan GD (resize)
            $use_gd = is_gd_available();
            $upload_success = false;

            if ($use_gd) {
                $upload_success = process_uploaded_image($file_tmp, $upload_path, 1600, 1600, $allowed_mimes, $out_mime);
                if (!$upload_success) {
                    error_log("GD processing failed for ticket $ticket_id, trying fallback");
                }
            }

            // Fallback: langsung copy file tanpa resize jika GD gagal atau tidak tersedia
            if (!$upload_success) {
                $upload_success = move_uploaded_file($file_tmp, $upload_path);
                if ($upload_success) {
                    $out_mime = $mime;
                    error_log("File uploaded without GD processing: $foto_perbaikan_name");
                } else {
                    error_log("move_uploaded_file failed for ticket $ticket_id");
                }
            }

            if (!$upload_success) {
                header('Location: update_status.php?id=' . $ticket_id . '&error=upload_failed');
                exit();
            }
        }
    }

    $new_status = mysqli_real_escape_string($conn, $new_status);
    $update_fields = "status = '$new_status', updated_at = NOW(), handled_by = " . $user['id'];

    if (!empty($catatan_teknisi)) {
        $catatan_teknisi = mysqli_real_escape_string($conn, $catatan_teknisi);
        $update_fields .= ", catatan_teknisi = '$catatan_teknisi'";
    }

    if ($foto_perbaikan_name) {
        $foto_perbaikan_name = mysqli_real_escape_string($conn, $foto_perbaikan_name);
        $update_fields .= ", foto_perbaikan = '$foto_perbaikan_name'";
    }

    $query = "UPDATE tickets SET $update_fields WHERE id = $ticket_id AND assigned_division_id = $division_id";
    $result = mysqli_query($conn, $query);

    if ($result) {
        $log_description = "Status diubah dari $current_status menjadi $new_status";
        if (!empty($catatan_teknisi)) {
            $log_description .= ". Catatan: " . substr($catatan_teknisi, 0, 100);
        }
        if ($foto_perbaikan_name) {
            $log_description .= " (dengan foto dokumentasi)";
        }
        logTicketActivity($conn, $ticket_id, $user['id'], 'status_update', $current_status, $new_status, $log_description);

        // Kirim email notifikasi ke pelapor
        notifyStatusChanged($conn, $ticket_id, $current_status, $new_status, $user['nama']);

        header('Location: dashboard.php?success=update&id=' . $ticket_id . '&status=' . urlencode($new_status));
        exit();
    } else {
        if ($foto_perbaikan_name && file_exists('../uploads/perbaikan/' . $foto_perbaikan_name)) {
            unlink('../uploads/perbaikan/' . $foto_perbaikan_name);
        }
        header('Location: update_status.php?id=' . $ticket_id . '&error=gagal');
        exit();
    }

} else {
    header('Location: dashboard.php');
    exit();
}
?>