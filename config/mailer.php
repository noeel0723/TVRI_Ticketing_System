<?php
/**
 * Email Notification Helper
 * Sends email notifications when tickets are assigned or status changes.
 * Uses PHP mail() — for production, replace with PHPMailer/SMTP.
 */

/**
 * Send email notification
 * @param string $to      Recipient email
 * @param string $subject Email subject
 * @param string $body    HTML body content
 * @return bool
 */
function sendTicketEmail($to, $subject, $body) {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: TVRI Ticketing <noreply@tvri.go.id>\r\n";
    $headers .= "Reply-To: noreply@tvri.go.id\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $htmlBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Inter,Arial,sans-serif;background:#f0f2f5;margin:0;padding:20px">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e8eaef">'
        . '<div style="background:#10367D;padding:24px 28px;text-align:center">'
        . '<img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/14/TVRI_logo_2019.svg/200px-TVRI_logo_2019.svg.png" alt="TVRI" style="height:40px;margin-bottom:8px"><br>'
        . '<span style="color:#fff;font-size:1.1rem;font-weight:700">TVRI Ticketing System</span>'
        . '</div>'
        . '<div style="padding:28px 28px 20px">' . $body . '</div>'
        . '<div style="padding:16px 28px;background:#fafbfd;border-top:1px solid #eef0f5;text-align:center;font-size:0.75rem;color:#8b8fa3">'
        . 'Email ini dikirim otomatis oleh sistem TVRI Ticketing. Jangan membalas email ini.'
        . '</div></div></body></html>';

    return @mail($to, $subject, $htmlBody, $headers);
}

/**
 * Notify teknisi division when a ticket is assigned to them
 */
function notifyTicketAssigned($conn, $ticket_id, $division_id, $priority) {
    $ticket_id = intval($ticket_id);
    $division_id = intval($division_id);
    $priority = htmlspecialchars($priority);

    // Get ticket details
    $tq = mysqli_query($conn, "SELECT t.judul, t.lokasi, t.deskripsi, u.nama as pelapor 
        FROM tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = $ticket_id");
    if (!$tq || mysqli_num_rows($tq) == 0) return;
    $ticket = mysqli_fetch_assoc($tq);

    // Get division name
    $dq = mysqli_query($conn, "SELECT nama_divisi FROM divisions WHERE id = $division_id");
    $div_name = ($dq && $d = mysqli_fetch_assoc($dq)) ? $d['nama_divisi'] : 'N/A';

    // Get all teknisi emails in this division
    $uq = mysqli_query($conn, "SELECT email FROM users WHERE role = 'teknisi' AND division_id = $division_id AND email IS NOT NULL AND email != ''");
    if (!$uq) return;

    $priority_colors = ['Low' => '#9ca3af', 'Medium' => '#3b82f6', 'High' => '#f59e0b', 'Urgent' => '#ef4444'];
    $pc = $priority_colors[$priority] ?? '#6b7280';

    $subject = "[Tiket Baru] #{$ticket_id} - " . $ticket['judul'];
    $body = '<h2 style="color:#1a1a2e;font-size:1.15rem;margin:0 0 16px">🎫 Tiket Baru Di-assign ke Divisi Anda</h2>'
        . '<table style="width:100%;border-collapse:collapse;font-size:0.88rem">'
        . '<tr><td style="padding:8px 0;color:#8b8fa3;width:100px">ID Tiket</td><td style="padding:8px 0;font-weight:600">#' . $ticket_id . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#8b8fa3">Judul</td><td style="padding:8px 0;font-weight:600">' . htmlspecialchars($ticket['judul']) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#8b8fa3">Pelapor</td><td style="padding:8px 0">' . htmlspecialchars($ticket['pelapor'] ?? '-') . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#8b8fa3">Lokasi</td><td style="padding:8px 0">' . htmlspecialchars($ticket['lokasi']) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#8b8fa3">Divisi</td><td style="padding:8px 0">' . htmlspecialchars($div_name) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#8b8fa3">Prioritas</td><td style="padding:8px 0"><span style="background:' . $pc . ';color:#fff;padding:3px 12px;border-radius:12px;font-size:0.78rem;font-weight:600">' . $priority . '</span></td></tr>'
        . '</table>'
        . '<p style="margin:20px 0 0;font-size:0.85rem;color:#374151">Silakan login ke sistem untuk memproses tiket ini.</p>';

    while ($u = mysqli_fetch_assoc($uq)) {
        sendTicketEmail($u['email'], $subject, $body);
    }
}

/**
 * Notify ticket creator (user) when ticket status changes
 */
function notifyStatusChanged($conn, $ticket_id, $old_status, $new_status, $teknisi_name = '') {
    $ticket_id = intval($ticket_id);

    $tq = mysqli_query($conn, "SELECT t.judul, u.email, u.nama 
        FROM tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = $ticket_id");
    if (!$tq || mysqli_num_rows($tq) == 0) return;
    $ticket = mysqli_fetch_assoc($tq);

    if (empty($ticket['email'])) return;

    $status_colors = ['Open' => '#dc2626', 'Assigned' => '#2563eb', 'In Progress' => '#d97706', 'Resolved' => '#16a34a'];
    $nc = $status_colors[$new_status] ?? '#6b7280';

    $subject = "[Update] Tiket #{$ticket_id} - " . htmlspecialchars($old_status) . " → " . htmlspecialchars($new_status);
    $body = '<h2 style="color:#1a1a2e;font-size:1.15rem;margin:0 0 16px">🔔 Status Tiket Anda Berubah</h2>'
        . '<p style="font-size:0.9rem;color:#374151;margin:0 0 16px">Halo <strong>' . htmlspecialchars($ticket['nama']) . '</strong>,</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:0.88rem">'
        . '<tr><td style="padding:8px 0;color:#8b8fa3;width:100px">ID Tiket</td><td style="padding:8px 0;font-weight:600">#' . $ticket_id . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#8b8fa3">Judul</td><td style="padding:8px 0;font-weight:600">' . htmlspecialchars($ticket['judul']) . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#8b8fa3">Status</td><td style="padding:8px 0">'
        . '<span style="text-decoration:line-through;color:#8b8fa3;margin-right:8px">' . htmlspecialchars($old_status) . '</span>'
        . '<span style="background:' . $nc . ';color:#fff;padding:3px 12px;border-radius:12px;font-size:0.78rem;font-weight:600">' . htmlspecialchars($new_status) . '</span></td></tr>';
    if ($teknisi_name) {
        $body .= '<tr><td style="padding:8px 0;color:#8b8fa3">Diproses oleh</td><td style="padding:8px 0">' . htmlspecialchars($teknisi_name) . '</td></tr>';
    }
    $body .= '</table>'
        . '<p style="margin:20px 0 0;font-size:0.85rem;color:#374151">Silakan login ke sistem untuk melihat detail tiket.</p>';

    sendTicketEmail($ticket['email'], $subject, $body);
}
