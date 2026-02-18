<?php
/**
 * Email Configuration for TVRI Ticketing System
 * 
 * Konfigurasi SMTP untuk mengirim email notifikasi
 * Gunakan Gmail, Outlook, atau SMTP server lainnya
 */

// SMTP Configuration
define('SMTP_ENABLED', true);  // Set false untuk menonaktifkan email
define('SMTP_HOST', 'smtp.gmail.com');  // SMTP server
define('SMTP_PORT', 587);  // Port: 587 (TLS) atau 465 (SSL)
define('SMTP_ENCRYPTION', 'tls');  // 'tls' atau 'ssl'
define('SMTP_USERNAME', 'your-email@gmail.com');  // Email pengirim
define('SMTP_PASSWORD', 'your-app-password');  // App password (bukan password biasa!)
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');  // Email pengirim
define('SMTP_FROM_NAME', 'TVRI Ticketing System');  // Nama pengirim

/**
 * CARA SETUP GMAIL:
 * 1. Buka Google Account Settings
 * 2. Aktifkan 2-Step Verification
 * 3. Generate App Password di Security > App passwords
 * 4. Gunakan app password (16 karakter) di SMTP_PASSWORD
 * 
 * CARA SETUP OUTLOOK/HOTMAIL:
 * SMTP_HOST: smtp-mail.outlook.com
 * SMTP_PORT: 587
 * SMTP_ENCRYPTION: tls
 * Gunakan email dan password Outlook Anda
 */

// Email Templates
$email_templates = [
    'new_ticket' => [
        'subject' => 'Tiket Baru #{{ticket_id}} - {{judul}}',
        'body' => '
            <h2 style="color:#10367D">Tiket Baru Diterima</h2>
            <p>Halo <strong>{{recipient_name}}</strong>,</p>
            <p>Tiket baru telah dibuat di sistem TVRI Ticketing:</p>
            
            <div style="background:#f8f9fc;padding:20px;border-radius:8px;margin:20px 0">
                <table style="width:100%;border-collapse:collapse">
                    <tr><td style="padding:8px 0"><strong>ID Tiket:</strong></td><td>#{{ticket_id}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Judul:</strong></td><td>{{judul}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Pelapor:</strong></td><td>{{pelapor}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Divisi:</strong></td><td>{{divisi}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Status:</strong></td><td><span style="background:#3b82f6;color:#fff;padding:4px 12px;border-radius:6px">{{status}}</span></td></tr>
                    <tr><td style="padding:8px 0"><strong>Dibuat:</strong></td><td>{{created_at}}</td></tr>
                </table>
            </div>
            
            <p><strong>Deskripsi:</strong></p>
            <p style="background:#fff;padding:15px;border-left:4px solid #10367D;margin:15px 0">{{deskripsi}}</p>
            
            <a href="{{ticket_url}}" style="display:inline-block;background:#10367D;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;margin:20px 0">Lihat Detail Tiket</a>
            
            <hr style="margin:30px 0;border:none;border-top:1px solid #e5e7eb">
            <p style="font-size:12px;color:#6b7280">Email otomatis dari TVRI Ticketing System. Jangan balas email ini.</p>
        '
    ],
    
    'status_update' => [
        'subject' => 'Update Status Tiket #{{ticket_id}} - {{status}}',
        'body' => '
            <h2 style="color:#10367D">Status Tiket Diperbarui</h2>
            <p>Halo <strong>{{recipient_name}}</strong>,</p>
            <p>Status tiket Anda telah diperbarui:</p>
            
            <div style="background:#f8f9fc;padding:20px;border-radius:8px;margin:20px 0">
                <table style="width:100%;border-collapse:collapse">
                    <tr><td style="padding:8px 0"><strong>ID Tiket:</strong></td><td>#{{ticket_id}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Judul:</strong></td><td>{{judul}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Status Lama:</strong></td><td><span style="background:#94a3b8;color:#fff;padding:4px 12px;border-radius:6px">{{old_status}}</span></td></tr>
                    <tr><td style="padding:8px 0"><strong>Status Baru:</strong></td><td><span style="background:{{status_color}};color:#fff;padding:4px 12px;border-radius:6px">{{status}}</span></td></tr>
                    <tr><td style="padding:8px 0"><strong>Diupdate oleh:</strong></td><td>{{updated_by}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Waktu Update:</strong></td><td>{{update_time}}</td></tr>
                </table>
            </div>
            
            {{#if catatan}}
            <p><strong>Catatan Teknisi:</strong></p>
            <p style="background:#fff;padding:15px;border-left:4px solid #10367D;margin:15px 0">{{catatan}}</p>
            {{/if}}
            
            <a href="{{ticket_url}}" style="display:inline-block;background:#10367D;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;margin:20px 0">Lihat Detail Tiket</a>
            
            <hr style="margin:30px 0;border:none;border-top:1px solid #e5e7eb">
            <p style="font-size:12px;color:#6b7280">Email otomatis dari TVRI Ticketing System. Jangan balas email ini.</p>
        '
    ],
    
    'assignment' => [
        'subject' => 'Tiket #{{ticket_id}} Telah Di-assign kepada Anda',
        'body' => '
            <h2 style="color:#10367D">Tiket Baru Di-assign</h2>
            <p>Halo <strong>{{recipient_name}}</strong>,</p>
            <p>Tiket berikut telah di-assign kepada Anda untuk ditangani:</p>
            
            <div style="background:#f8f9fc;padding:20px;border-radius:8px;margin:20px 0">
                <table style="width:100%;border-collapse:collapse">
                    <tr><td style="padding:8px 0"><strong>ID Tiket:</strong></td><td>#{{ticket_id}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Judul:</strong></td><td>{{judul}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Pelapor:</strong></td><td>{{pelapor}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Divisi:</strong></td><td>{{divisi}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Status:</strong></td><td><span style="background:#f59e0b;color:#fff;padding:4px 12px;border-radius:6px">{{status}}</span></td></tr>
                    <tr><td style="padding:8px 0"><strong>Di-assign oleh:</strong></td><td>{{assigned_by}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Waktu:</strong></td><td>{{assign_time}}</td></tr>
                </table>
            </div>
            
            <p><strong>Deskripsi:</strong></p>
            <p style="background:#fff;padding:15px;border-left:4px solid #10367D;margin:15px 0">{{deskripsi}}</p>
            
            <a href="{{ticket_url}}" style="display:inline-block;background:#10367D;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;margin:20px 0">Lihat & Tangani Tiket</a>
            
            <hr style="margin:30px 0;border:none;border-top:1px solid #e5e7eb">
            <p style="font-size:12px;color:#6b7280">Email otomatis dari TVRI Ticketing System. Jangan balas email ini.</p>
        '
    ],
    
    'resolved' => [
        'subject' => 'Tiket #{{ticket_id}} Telah Diselesaikan',
        'body' => '
            <h2 style="color:#22c55e">Tiket Diselesaikan</h2>
            <p>Halo <strong>{{recipient_name}}</strong>,</p>
            <p>Kabar baik! Tiket Anda telah diselesaikan oleh teknisi kami:</p>
            
            <div style="background:#f0fdf4;padding:20px;border-radius:8px;margin:20px 0;border:2px solid #22c55e">
                <table style="width:100%;border-collapse:collapse">
                    <tr><td style="padding:8px 0"><strong>ID Tiket:</strong></td><td>#{{ticket_id}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Judul:</strong></td><td>{{judul}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Teknisi:</strong></td><td>{{teknisi}}</td></tr>
                    <tr><td style="padding:8px 0"><strong>Status:</strong></td><td><span style="background:#22c55e;color:#fff;padding:4px 12px;border-radius:6px">Resolved</span></td></tr>
                    <tr><td style="padding:8px 0"><strong>Waktu Selesai:</strong></td><td>{{resolve_time}}</td></tr>
                </table>
            </div>
            
            {{#if catatan}}
            <p><strong>Catatan Penyelesaian:</strong></p>
            <p style="background:#fff;padding:15px;border-left:4px solid #22c55e;margin:15px 0">{{catatan}}</p>
            {{/if}}
            
            <a href="{{ticket_url}}" style="display:inline-block;background:#10367D;color:#fff;padding:12px 24px;text-decoration:none;border-radius:8px;margin:20px 0">Lihat Detail Penyelesaian</a>
            
            <hr style="margin:30px 0;border:none;border-top:1px solid #e5e7eb">
            <p style="font-size:12px;color:#6b7280">Email otomatis dari TVRI Ticketing System. Jangan balas email ini.</p>
        '
    ]
];
