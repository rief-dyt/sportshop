
<?php
// Menggunakan konfigurasi database terpusat
require_once '../../config/database.php';
require_once '../includes/functions.php';
require_once '../../controllers/MailController.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($conn)) {
    sendResponse(['success' => false, 'message' => 'Database connection missing'], 500);
}

$method = $_SERVER['REQUEST_METHOD'];

// ===========================
// GET: Ambil data user by ID
// ===========================
if ($method === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
        sendResponse(['success' => false, 'message' => 'User ID required'], 400);
    }

    $stmt = $conn->prepare("SELECT id, name, email, gender, height_cm, weight_kg, birth_date, age, hobbies, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Normalkan field agar konsisten dengan frontend (height & weight)
        $user['height'] = $user['height_cm'];
        $user['weight'] = $user['weight_kg'];
        unset($user['height_cm'], $user['weight_kg']);
        $user['hobbies'] = json_decode($user['hobbies'], true) ?? [];
        sendResponse(['success' => true, 'user' => $user]);
    } else {
        sendResponse(['success' => false, 'message' => 'User not found'], 404);
    }
}

// ===========================
// POST: Register & Login
// ===========================
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        sendResponse(['success' => false, 'message' => 'Invalid JSON input'], 400);
    }

    // --- REGISTER ---
    if (($input['action'] ?? '') === 'register') {
        if (empty($input['name']) || empty($input['email']) || empty($input['password']) ||
            empty($input['gender']) || empty($input['height']) || empty($input['weight']) ||
            empty($input['birthDate']) || empty($input['hobbies'])) {
            sendResponse(['success' => false, 'message' => 'Semua field wajib diisi'], 400);
        }

        if (!validateEmail($input['email'])) {
            sendResponse(['success' => false, 'message' => 'Format email tidak valid'], 400);
        }

        if (strlen(trim($input['password'])) < 6) {
            sendResponse(['success' => false, 'message' => 'Password minimal 6 karakter'], 400);
        }

        // Cek email sudah terdaftar
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$input['email']]);
        if ($check->rowCount() > 0) {
            sendResponse(['success' => false, 'message' => 'Email sudah terdaftar'], 400);
        }

        $age          = calculateAge($input['birthDate']);
        $hashedPass   = hashPassword($input['password']);
        $hobbiesJson  = json_encode($input['hobbies']);
        $otpCode      = generateOTP();
        $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, gender, height_cm, weight_kg, birth_date, age, hobbies, is_verified, otp_code, otp_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
        $result = $stmt->execute([
            $input['name'], $input['email'], $hashedPass, $input['gender'],
            $input['height'], $input['weight'], $input['birthDate'], $age, $hobbiesJson,
            $otpCode, $otpExpiresAt
        ]);

        if ($result) {
            $newUserId = (int)$conn->lastInsertId();

            // Kirim email OTP. Jika pengiriman gagal, registrasi tetap dianggap berhasil
            // namun pesan akan memberi tahu agar user mencoba "Kirim ulang OTP".
            $mailSent = false;
            try {
                $mailController = new MailController();
                $subject = "Kode Verifikasi OTP - Toko Olahraga";
                $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                    <h2 style='color: #2c3e50; border-bottom: 2px solid #e74c3c; padding-bottom: 10px;'>Halo, {$input['name']}!</h2>
                    <p>Terima kasih telah mendaftar di <strong>Toko Olahraga</strong>. Gunakan kode OTP di bawah ini untuk memverifikasi akun Anda:</p>
                    <div style='background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #e74c3c; text-align: center;'>
                        <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #e74c3c;'>{$otpCode}</span>
                    </div>
                    <p style='color:#555;'>Kode ini berlaku selama <strong>10 menit</strong>. Jangan bagikan kode ini kepada siapa pun.</p>
                    <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
                    <p style='font-size:12px; color:#7f8c8d; text-align:center;'>Email otomatis dari Toko Olahraga. Mohon tidak membalas email ini.</p>
                </div>";
                $sendResult = $mailController->sendEmail($input['email'], $subject, $body);
                $mailSent = ($sendResult === true);
            } catch (Exception $mailEx) {
                $mailSent = false;
            }

            $message = 'Registrasi berhasil! ';
            if ($mailSent) {
                $message .= 'Kode OTP telah dikirim ke email Anda.';
            } else {
                $message .= 'Gagal mengirimkan email verifikasi. Silakan klik "Kirim ulang OTP".';
                if (defined('DEV_MODE') && DEV_MODE) {
                    $message .= ' (DEV MODE - OTP: ' . $otpCode . ')';
                }
            }

            sendResponse([
                'success' => true,
                'message' => $message,
                'requires_otp' => true,
                'user_id' => $newUserId,
                'email' => $input['email']
            ]);
        } else {
            sendResponse(['success' => false, 'message' => 'Registrasi gagal'], 500);
        }
    }

    // --- VERIFY OTP ---
    elseif (($input['action'] ?? '') === 'verify_otp') {
        if (empty($input['user_id']) || empty($input['otp'])) {
            sendResponse(['success' => false, 'message' => 'User ID dan kode OTP wajib diisi'], 400);
        }

        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$input['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        if ((int)$user['is_verified'] === 1) {
            sendResponse(['success' => false, 'message' => 'Akun sudah terverifikasi, silakan login'], 400);
        }

        if (empty($user['otp_code']) || $user['otp_code'] !== trim((string)$input['otp'])) {
            sendResponse(['success' => false, 'message' => 'Kode OTP salah'], 400);
        }

        if (empty($user['otp_expires_at']) || strtotime($user['otp_expires_at']) < time()) {
            sendResponse(['success' => false, 'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru'], 400);
        }

        $update = $conn->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
        $update->execute([$user['id']]);

        unset($user['password']);
        $user['height'] = (int)$user['height_cm'];
        $user['weight'] = (float)$user['weight_kg'];
        unset($user['height_cm'], $user['weight_kg'], $user['otp_code'], $user['otp_expires_at']);
        $user['hobbies'] = json_decode($user['hobbies'], true) ?? [];
        $user['is_verified'] = 1;
        $user['role'] = $user['role'] ?? 'user';

        sendResponse(['success' => true, 'message' => 'Verifikasi berhasil! Akun Anda kini aktif.', 'user' => $user]);
    }

    // --- RESEND OTP ---
    elseif (($input['action'] ?? '') === 'resend_otp') {
        if (empty($input['user_id'])) {
            sendResponse(['success' => false, 'message' => 'User ID wajib diisi'], 400);
        }

        $stmt = $conn->prepare("SELECT id, name, email, is_verified FROM users WHERE id = ?");
        $stmt->execute([$input['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        if ((int)$user['is_verified'] === 1) {
            sendResponse(['success' => false, 'message' => 'Akun sudah terverifikasi, silakan login'], 400);
        }

        $otpCode      = generateOTP();
        $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $update = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
        $update->execute([$otpCode, $otpExpiresAt, $user['id']]);

        $mailSent = false;
        try {
            $mailController = new MailController();
            $subject = "Kode Verifikasi OTP Baru - Toko Olahraga";
            $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='color: #2c3e50; border-bottom: 2px solid #e74c3c; padding-bottom: 10px;'>Halo, {$user['name']}!</h2>
                <p>Berikut kode OTP baru untuk memverifikasi akun <strong>Toko Olahraga</strong> Anda:</p>
                <div style='background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-left: 4px solid #e74c3c; text-align: center;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #e74c3c;'>{$otpCode}</span>
                </div>
                <p style='color:#555;'>Kode ini berlaku selama <strong>10 menit</strong>. Jangan bagikan kode ini kepada siapa pun.</p>
                <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
                <p style='font-size:12px; color:#7f8c8d; text-align:center;'>Email otomatis dari Toko Olahraga. Mohon tidak membalas email ini.</p>
            </div>";
            $sendResult = $mailController->sendEmail($user['email'], $subject, $body);
            $mailSent = ($sendResult === true);
        } catch (Exception $mailEx) {
            $mailSent = false;
        }

        $message = '';
        if ($mailSent) {
            $message = 'Kode OTP baru telah dikirim ke email Anda.';
        } else {
            $message = 'Gagal mengirimkan email verifikasi. Silakan coba kembali.';
            if (defined('DEV_MODE') && DEV_MODE) {
                $message .= ' (DEV MODE - OTP: ' . $otpCode . ')';
            }
        }

        sendResponse([
            'success' => true,
            'message' => $message
        ]);
    }

    // --- LOGIN ---
    elseif (($input['action'] ?? '') === 'login') {
        if (empty($input['email']) || empty($input['password'])) {
            sendResponse(['success' => false, 'message' => 'Email dan password wajib diisi'], 400);
        }

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (verifyPassword($input['password'], $user['password'])) {

                // Blokir login jika akun belum diverifikasi via OTP
                if ((int)$user['is_verified'] === 0) {
                    sendResponse([
                        'success' => false,
                        'message' => 'Akun belum diverifikasi. Silakan masukkan kode OTP yang dikirim ke email Anda.',
                        'requires_otp' => true,
                        'user_id' => $user['id'],
                        'email' => $user['email']
                    ], 403);
                }

                unset($user['password']);
                // Normalkan field name agar konsisten dengan frontend
                $user['height'] = (int)$user['height_cm'];
                $user['weight'] = (float)$user['weight_kg'];
                unset($user['height_cm'], $user['weight_kg'], $user['otp_code'], $user['otp_expires_at']);
                $user['hobbies'] = json_decode($user['hobbies'], true) ?? [];
                $user['role'] = $user['role'] ?? 'user';
                sendResponse(['success' => true, 'user' => $user]);
            } else {
                sendResponse(['success' => false, 'message' => 'Password salah'], 401);
            }
        } else {
            sendResponse(['success' => false, 'message' => 'Email tidak ditemukan'], 404);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Action tidak valid'], 400);
    }
}

// ===========================
// PUT: Update profil user
// ===========================
elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || empty($input['user_id'])) {
        sendResponse(['success' => false, 'message' => 'User ID required'], 400);
    }

    $updates = [];
    $params  = [];

    if (isset($input['name']))      { $updates[] = "name = ?";       $params[] = $input['name']; }
    if (isset($input['gender']))    { $updates[] = "gender = ?";     $params[] = $input['gender']; }
    if (isset($input['height']))    { $updates[] = "height_cm = ?";  $params[] = $input['height']; }
    if (isset($input['weight']))    { $updates[] = "weight_kg = ?";  $params[] = $input['weight']; }
    if (isset($input['hobbies']))   { $updates[] = "hobbies = ?";    $params[] = json_encode($input['hobbies']); }
    if (isset($input['birthDate'])) {
        $updates[] = "birth_date = ?";
        $updates[] = "age = ?";
        $params[]  = $input['birthDate'];
        $params[]  = calculateAge($input['birthDate']);
    }
    if (!empty($input['password'])) {
        $updates[] = "password = ?";
        $params[]  = hashPassword($input['password']);
    }

    if (empty($updates)) {
        sendResponse(['success' => false, 'message' => 'Tidak ada data yang diubah'], 400);
    }

    $params[] = $input['user_id'];
    $query    = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt     = $conn->prepare($query);

    if ($stmt->execute($params)) {
        $stmt2 = $conn->prepare("SELECT id, name, email, gender, height_cm, weight_kg, birth_date, age, hobbies, role FROM users WHERE id = ?");
        $stmt2->execute([$input['user_id']]);
        $user = $stmt2->fetch(PDO::FETCH_ASSOC);
        $user['height'] = (int)$user['height_cm'];
        $user['weight'] = (float)$user['weight_kg'];
        unset($user['height_cm'], $user['weight_kg']);
        $user['hobbies'] = json_decode($user['hobbies'], true) ?? [];
        sendResponse(['success' => true, 'message' => 'Profil berhasil diperbarui', 'user' => $user]);
    } else {
        sendResponse(['success' => false, 'message' => 'Update gagal'], 500);
    }
}

// ===========================
// DELETE: Hapus akun user
// ===========================
elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || empty($input['user_id'])) {
        sendResponse(['success' => false, 'message' => 'User ID required'], 400);
    }

    $check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $check->execute([$input['user_id']]);
    if ($check->rowCount() === 0) {
        sendResponse(['success' => false, 'message' => 'User tidak ditemukan'], 404);
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$input['user_id']])) {
        sendResponse(['success' => true, 'message' => 'Akun berhasil dihapus']);
    } else {
        sendResponse(['success' => false, 'message' => 'Gagal menghapus akun'], 500);
    }
}
?>
