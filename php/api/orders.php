<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/functions.php';
require_once '../../config/database.php';
require_once '../../controllers/MailController.php';

if (!isset($conn)) {
    sendResponse(['success' => false, 'message' => 'Database connection missing'], 500);
}

// GET: Ambil pesanan by user_id
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = $_GET['user_id'] ?? null;
    if (!$userId) {
        sendResponse(['success' => false, 'message' => 'User ID required'], 400);
    }
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    sendResponse(['success' => true, 'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// POST: Buat pesanan baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input['action']) || $input['action'] !== 'create_order') {
        sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }

    $conn->beginTransaction();

    try {
        $orderNumber = generateOrderNumber();

        $stmt = $conn->prepare("INSERT INTO orders (order_number, user_id, customer_name, customer_phone, city, address, postal_code, courier_service, shipping_cost, payment_method, subtotal, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $orderNumber,
            $input['user_id'],
            $input['customer_name'],
            $input['customer_phone'],
            $input['city'],
            $input['address'],
            $input['postal_code'] ?? '',
            $input['courier_service'],
            $input['shipping_cost'],
            $input['payment_method'],
            $input['subtotal'],
            $input['total']
        ]);

        $orderId = $conn->lastInsertId();

        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_icon, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($input['items'] as $item) {
            $itemStmt->execute([$orderId, $item['id'], $item['name'], $item['icon'], $item['quantity'], $item['price']]);
        }

        $conn->commit();

        // Kirim email konfirmasi jika ada email pembeli
        $customerEmail = $input['email'] ?? null;
        if ($customerEmail) {
            try {
                $mailController = new MailController();
                $subject = "Konfirmasi Pesanan #{$orderNumber} - Toko Olahraga";
                $body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                    <h2 style='color: #2c3e50; border-bottom: 2px solid #e74c3c; padding-bottom: 10px;'>Halo, {$input['customer_name']}!</h2>
                    <p>Terima kasih telah berbelanja di <strong>Toko Olahraga</strong>. Pesanan Anda telah diterima dan sedang diproses.</p>
                    <div style='background-color: #f8f9fa; padding: 15px; margin: 20px 0; border-left: 4px solid #e74c3c; line-height: 1.7;'>
                        <p style='margin: 5px 0;'><strong>Nomor Pesanan:</strong> {$orderNumber}</p>
                        <p style='margin: 5px 0;'><strong>Metode Pembayaran:</strong> " . strtoupper($input['payment_method']) . "</p>
                        <p style='margin: 5px 0;'><strong>Kurir:</strong> " . strtoupper($input['courier_service']) . "</p>
                        <p style='margin: 5px 0;'><strong>Total Pembayaran:</strong> Rp " . number_format($input['total'], 0, ',', '.') . "</p>
                    </div>
                    <h3 style='color: #2c3e50;'>Alamat Pengiriman:</h3>
                    <p style='color:#555; background:#fafafa; padding:10px; border:1px dashed #ccc; border-radius:4px;'>
                        {$input['address']}, {$input['city']} {$input['postal_code']}
                    </p>
                    <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
                    <p style='font-size:12px; color:#7f8c8d; text-align:center;'>Email otomatis dari Toko Olahraga. Mohon tidak membalas email ini.</p>
                </div>";
                $mailController->sendEmail($customerEmail, $subject, $body);
            } catch (Exception $mailEx) {
                // Email gagal tidak membatalkan pesanan
            }
        }

        sendResponse(['success' => true, 'order_number' => $orderNumber]);

    } catch (Exception $e) {
        $conn->rollBack();
        sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
?>
