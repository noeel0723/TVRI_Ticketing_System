<?php
require_once '../config/auth.php';
checkRole('admin');
require_once '../config/koneksi.php';
require_once '../config/logger.php';
require_once '../config/email_helper.php';

$user = getUserInfo();

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Ambil data dari form
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $division_id = isset($_POST['division_id']) ? intval($_POST['division_id']) : 0;
    $teknisi_id = isset($_POST['teknisi_id']) ? intval($_POST['teknisi_id']) : 0;
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : '';
    
    // Validasi input
    if ($ticket_id == 0 || $division_id == 0 || $teknisi_id == 0 || empty($priority)) {
        header('Location: assign.php?id=' . $ticket_id . '&error=empty');
        exit();
    }
    
    // Validasi priority
    $valid_priorities = ['Low', 'Medium', 'High', 'Urgent'];
    if (!in_array($priority, $valid_priorities)) {
        header('Location: assign.php?id=' . $ticket_id . '&error=invalid_priority');
        exit();
    }
    
    // Validasi teknisi: pastikan teknisi tersebut ada dan berada di divisi yang dipilih
    $tek_check = mysqli_prepare($conn, "SELECT id, nama FROM users WHERE id = ? AND role = 'teknisi' AND division_id = ?");
    mysqli_stmt_bind_param($tek_check, "ii", $teknisi_id, $division_id);
    mysqli_stmt_execute($tek_check);
    $tek_result = mysqli_stmt_get_result($tek_check);
    if (mysqli_num_rows($tek_result) == 0) {
        header('Location: assign.php?id=' . $ticket_id . '&error=invalid_teknisi');
        exit();
    }
    $teknisi_data = mysqli_fetch_assoc($tek_result);
    $teknisi_nama = $teknisi_data['nama'];
    mysqli_stmt_close($tek_check);
    
    // Escape data
    $priority = mysqli_real_escape_string($conn, $priority);
    
    // Update tiket: set division, teknisi (handled_by), status, priority
    $query = "UPDATE tickets 
              SET assigned_division_id = $division_id,
                  handled_by = $teknisi_id,
                  status = 'Assigned',
                  priority = '$priority',
                  updated_at = NOW()
              WHERE id = $ticket_id";
    
    $result = mysqli_query($conn, $query);
    
    if ($result) {
        // Ambil nama divisi
        $div_query = "SELECT nama_divisi FROM divisions WHERE id = $division_id";
        $div_result = mysqli_query($conn, $div_query);
        $division_name = mysqli_fetch_assoc($div_result)['nama_divisi'];
        
        // Log aktivitas (sertakan nama teknisi)
        logTicketActivity($conn, $ticket_id, $user['id'], 'assign', 'Open', 'Assigned', "Tiket di-assign ke divisi $division_name, teknisi: $teknisi_nama, priority: $priority");
        
        // Kirim email notifikasi hanya ke teknisi yang dipilih
        $admin_id = $user['id'];
        notifyAssignment($conn, $ticket_id, $teknisi_id, $admin_id);
        
        // Berhasil - redirect ke dashboard dengan notifikasi
        header('Location: dashboard.php?success=assign&id=' . $ticket_id);
        exit();
    } else {
        // Gagal
        header('Location: assign.php?id=' . $ticket_id . '&error=gagal');
        exit();
    }
    
} else {
    // Jika diakses langsung tanpa POST
    header('Location: dashboard.php');
    exit();
}
?>