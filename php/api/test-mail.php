<?php
require_once '../../controllers/MailController.php';

$mailController = new MailController();
$prosesKirim = $mailController->sendEmail(
    'email_tujuan_anda@gmail.com', 
    'Halo dari Toko Olahraga!', 
    '<h1>Pesanan Berhasil!</h1><p>Terima kasih telah berbelanja.</p>'
);

if ($prosesKirim === true) {
    echo "Kirim email sukses!";
} else {
    echo $prosesKirim; 
}