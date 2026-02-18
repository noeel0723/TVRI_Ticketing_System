<?php
/**
 * Email Helper untuk TVRI Ticketing System
 * Fungsi untuk mengirim email notifikasi menggunakan PHPMailer
 */

require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';
require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email notification
 * 
 * @param string $to Recipient email
 * @param string $to_name Recipient name
 * @param string $template Template name (new_ticket, status_update, assignment, resolved)
 * @param array $data Data untuk replace template placeholders
 * @return bool Success status
 */
function sendEmailNotification($to, $to_name, $template, $data) {
    global $email_templates;
    
    // Skip if email is empty or SMTP disabled
    if (!SMTP_ENABLED || empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Check if template exists
    if (!isset($email_templates[$template])) {
        error_log("Email template '$template' not found");
        return false;
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Sender & Recipient
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $to_name);
        
        // Content
        $mail->isHTML(true);
        
        // Process template
        $subject = $email_templates[$template]['subject'];
        $body = $email_templates[$template]['body'];
        
        // Add recipient name to data
        $data['recipient_name'] = $to_name;
        
        // Replace placeholders
        foreach ($data as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        
        // Handle conditional blocks (simple {{#if key}}...{{/if}})
        $body = preg_replace_callback('/\{\{#if (\w+)\}\}(.*?)\{\{\/if\}\}/s', function($matches) use ($data) {
            $key = $matches[1];
            $content = $matches[2];
            return !empty($data[$key]) ? $content : '';
        }, $body);
        
        $mail->Subject = $subject;
        $mail->Body = getEmailLayout($body);
        $mail->AltBody = strip_tags($body);
        
        // Send
        $result = $mail->send();
        
        if ($result) {
            error_log("Email sent to $to: $subject");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Get email HTML layout wrapper
 */
function getEmailLayout($content) {
    return '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TVRI Ticketing</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #374151; margin: 0; padding: 0; background: #f3f4f6; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #10367D 0%, #1e3a8a 100%); padding: 30px 20px; text-align: center; }
        .header img { height: 50px; margin-bottom: 10px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; }
        .content { padding: 40px 30px; }
        .footer { background: #f9fafb; padding: 20px 30px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 5px 0; font-size: 12px; color: #6b7280; }
        a { color: #10367D; text-decoration: none; }
        @media only screen and (max-width: 600px) {
            .content { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎬 TVRI Ticketing System</h1>
            <p style="color: rgba(255,255,255,0.8); margin: 5px 0 0; font-size: 14px;">Support System Sulawesi Utara</p>
        </div>
        <div class="content">
            ' . $content . '
        </div>
        <div class="footer">
            <p><strong>TVRI Sulawesi Utara</strong></p>
            <p>Jangan balas email ini. Untuk pertanyaan, silakan login ke sistem ticketing.</p>
            <p>&copy; ' . date('Y') . ' TVRI Ticketing System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
    ';
}

/**
 * Send notification when new ticket is created
 */
function notifyNewTicket($conn, $ticket_id) {
    // Get ticket details
    $stmt = mysqli_prepare($conn, "
        SELECT t.*, u.nama as pelapor, u.email as pelapor_email, 
               d.nama_divisi, tek.nama as teknisi, tek.email as teknisi_email
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN divisions d ON t.assigned_division_id = d.id
        LEFT JOIN users tek ON t.handled_by = tek.id
        WHERE t.id = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $ticket_id);
    mysqli_stmt_execute($stmt);
    $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$ticket) return false;
    
    $data = [
        'ticket_id' => $ticket['id'],
        'judul' => $ticket['judul'],
        'pelapor' => $ticket['pelapor'],
        'divisi' => $ticket['nama_divisi'] ?? 'Tidak ada divisi',
        'status' => $ticket['status'],
        'created_at' => date('d M Y H:i', strtotime($ticket['created_at'])),
        'deskripsi' => $ticket['deskripsi'],
        'ticket_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/ticketing_tvri/detail.php?id=' . $ticket['id']
    ];
    
    // Send to admins
    $admins = mysqli_query($conn, "SELECT nama, email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''");
    while ($admin = mysqli_fetch_assoc($admins)) {
        sendEmailNotification($admin['email'], $admin['nama'], 'new_ticket', $data);
    }
    
    // Send to assigned teknisi if any
    if (!empty($ticket['teknisi_email'])) {
        sendEmailNotification($ticket['teknisi_email'], $ticket['teknisi'], 'new_ticket', $data);
    }
    
    return true;
}

/**
 * Send notification when ticket status is updated
 */
function notifyStatusUpdate($conn, $ticket_id, $old_status, $new_status, $updated_by_id, $catatan = '') {
    // Get ticket and user details
    $stmt = mysqli_prepare($conn, "
        SELECT t.*, u.nama as pelapor, u.email as pelapor_email,
               upd.nama as updated_by
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN users upd ON upd.id = ?
        WHERE t.id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ii", $updated_by_id, $ticket_id);
    mysqli_stmt_execute($stmt);
    $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$ticket || empty($ticket['pelapor_email'])) return false;
    
    // Determine status color
    $status_colors = [
        'Open' => '#3b82f6',
        'In Progress' => '#f59e0b',
        'Resolved' => '#22c55e'
    ];
    
    $data = [
        'ticket_id' => $ticket['id'],
        'judul' => $ticket['judul'],
        'old_status' => $old_status,
        'status' => $new_status,
        'status_color' => $status_colors[$new_status] ?? '#6b7280',
        'updated_by' => $ticket['updated_by'],
        'update_time' => date('d M Y H:i'),
        'catatan' => $catatan,
        'ticket_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/ticketing_tvri/detail.php?id=' . $ticket['id']
    ];
    
    // Choose template based on status
    $template = ($new_status === 'Resolved') ? 'resolved' : 'status_update';
    if ($new_status === 'Resolved') {
        $data['teknisi'] = $ticket['updated_by'];
        $data['resolve_time'] = $data['update_time'];
    }
    
    // Send to reporter
    sendEmailNotification($ticket['pelapor_email'], $ticket['pelapor'], $template, $data);
    
    return true;
}

/**
 * Send notification when ticket is assigned to teknisi
 */
function notifyAssignment($conn, $ticket_id, $teknisi_id, $assigned_by_id) {
    // Get ticket and teknisi details
    $stmt = mysqli_prepare($conn, "
        SELECT t.*, u.nama as pelapor, u.email as pelapor_email,
               tek.nama as teknisi, tek.email as teknisi_email,
               d.nama_divisi, assigner.nama as assigned_by
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN users tek ON tek.id = ?
        LEFT JOIN divisions d ON t.assigned_division_id = d.id
        LEFT JOIN users assigner ON assigner.id = ?
        WHERE t.id = ?
    ");
    mysqli_stmt_bind_param($stmt, "iii", $teknisi_id, $assigned_by_id, $ticket_id);
    mysqli_stmt_execute($stmt);
    $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$ticket || empty($ticket['teknisi_email'])) return false;
    
    $data = [
        'ticket_id' => $ticket['id'],
        'judul' => $ticket['judul'],
        'pelapor' => $ticket['pelapor'],
        'divisi' => $ticket['nama_divisi'] ?? 'Tidak ada divisi',
        'status' => $ticket['status'],
        'assigned_by' => $ticket['assigned_by'],
        'assign_time' => date('d M Y H:i'),
        'deskripsi' => $ticket['deskripsi'],
        'ticket_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/ticketing_tvri/detail.php?id=' . $ticket['id']
    ];
    
    // Send to teknisi
    sendEmailNotification($ticket['teknisi_email'], $ticket['teknisi'], 'assignment', $data);
    
    return true;
}

/**
 * Test email configuration
 */
function testEmailConfig($test_email, $test_name = 'Test User') {
    $data = [
        'ticket_id' => '123',
        'judul' => 'Test Email Configuration',
        'pelapor' => 'Test User',
        'divisi' => 'Test Division',
        'status' => 'Open',
        'created_at' => date('d M Y H:i'),
        'deskripsi' => 'This is a test email to verify SMTP configuration is working correctly.',
        'ticket_url' => 'http://localhost/ticketing_tvri'
    ];
    
    return sendEmailNotification($test_email, $test_name, 'new_ticket', $data);
}
