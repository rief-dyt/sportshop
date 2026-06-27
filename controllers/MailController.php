<?php
// 1. Import class PHPMailer ke dalam namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 2. Arahkan ke file autoload.php di folder vendor (naik 1 folder dari 'controllers')
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

class MailController {
    
    public function sendEmail($to, $subject, $body) {
        // Membuat instance PHPMailer
        $mail = new PHPMailer(true);

        try {
            // --- Konfigurasi Server SMTP ---
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Aktifkan jika ingin melihat log error detail
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;         // Menggunakan konfigurasi global
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;         // Email pengirim
            $mail->Password   = SMTP_PASS;         // Password/App Password email pengirim
            $mail->SMTPSecure = SMTP_PORT === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = SMTP_PORT;

            // --- Pengaturan Penerima ---
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to); // Email tujuan

            // --- Konten Email ---
            $mail->isHTML(true); // Set format email ke HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;

            // Eksekusi kirim
            $mail->send();
            return true;
        } catch (Exception $e) {
            return "Email gagal dikirim. Error: {$mail->ErrorInfo}";
        }
    }
}