<?php
// 1. Import class PHPMailer ke dalam namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 2. Arahkan ke file autoload.php di folder vendor (naik 1 folder dari 'controllers')
require_once __DIR__ . '/../vendor/autoload.php';

class MailController {
    
    public function sendEmail($to, $subject, $body) {
        // Membuat instance PHPMailer
        $mail = new PHPMailer(true);

        try {
            // --- Konfigurasi Server SMTP ---
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Aktifkan jika ingin melihat log error detail
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';         // Ganti dengan server SMTP Anda
            $mail->SMTPAuth   = true;
            $mail->Username   = 'syrfleooo@gmail.com';   // Email pengirim
            $mail->Password   = 'wfmn nxoo outa pjyg'; // Password/App Password email pengirim
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = 587;

            // --- Pengaturan Penerima ---
            $mail->setFrom('syrfleooo@gmail.com', 'Toko Olahraga');
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