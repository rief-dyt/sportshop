<?php
require_once '../../config/database.php';
require_once '../includes/functions.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-ID");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($conn)) {
    sendResponse(['success' => false, 'message' => 'Database connection missing'], 500);
}

// ===========================
// Verifikasi Admin
// ===========================
function verifyAdmin($conn) {
    $adminId = $_SERVER['HTTP_X_ADMIN_ID'] ?? $_GET['admin_id'] ?? null;
    if (!$adminId) return false;

    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$adminId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user && $user['role'] === 'admin';
}

if (!verifyAdmin($conn)) {
    sendResponse(['success' => false, 'message' => 'Akses ditolak. Hanya admin yang diizinkan.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'overview';

// ===========================
// GET: Ambil data
// ===========================
if ($method === 'GET') {

    // --- Overview / statistik ringkas ---
    if ($resource === 'overview') {
        $totalUsers    = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $totalOrders   = $conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $totalRevenue  = $conn->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
        $pendingOrders = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

        sendResponse([
            'success'        => true,
            'total_users'    => (int)$totalUsers,
            'total_products' => (int)$totalProducts,
            'total_orders'   => (int)$totalOrders,
            'total_revenue'  => (float)$totalRevenue,
            'pending_orders' => (int)$pendingOrders,
        ]);
    }

    // --- Daftar Users ---
    elseif ($resource === 'users') {
        $stmt = $conn->query("SELECT id, name, email, gender, height_cm, weight_kg, age, hobbies, role, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as &$u) {
            $u['hobbies'] = json_decode($u['hobbies'], true) ?? [];
        }
        sendResponse(['success' => true, 'users' => $users]);
    }

    // --- Daftar Products ---
    elseif ($resource === 'products') {
        $stmt = $conn->query("SELECT * FROM products ORDER BY id ASC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as &$p) {
            $p['hobbies'] = json_decode($p['hobbies'], true) ?? [];
            $p['price']   = (int)$p['price'];
            $p['stock']   = (int)$p['stock'];
        }
        sendResponse(['success' => true, 'products' => $products]);
    }

    // --- Daftar Orders ---
    elseif ($resource === 'orders') {
        $stmt = $conn->query("SELECT o.*, GROUP_CONCAT(oi.product_name ORDER BY oi.id SEPARATOR ', ') as items_summary
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            GROUP BY o.id
            ORDER BY o.created_at DESC");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendResponse(['success' => true, 'orders' => $orders]);
    }

    else {
        sendResponse(['success' => false, 'message' => 'Resource tidak dikenal'], 400);
    }
}

// ===========================
// PUT: Update data
// ===========================
elseif ($method === 'PUT') {
    $input    = json_decode(file_get_contents("php://input"), true);
    $resource = $input['resource'] ?? '';

    // Update role user
    if ($resource === 'user_role') {
        if (empty($input['user_id']) || empty($input['role'])) {
            sendResponse(['success' => false, 'message' => 'user_id dan role diperlukan'], 400);
        }
        if (!in_array($input['role'], ['user', 'admin'])) {
            sendResponse(['success' => false, 'message' => 'Role tidak valid'], 400);
        }
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        if ($stmt->execute([$input['role'], $input['user_id']])) {
            sendResponse(['success' => true, 'message' => 'Role berhasil diperbarui']);
        } else {
            sendResponse(['success' => false, 'message' => 'Gagal memperbarui role'], 500);
        }
    }

    // Update status pesanan
    elseif ($resource === 'order_status') {
        if (empty($input['order_id']) || empty($input['status'])) {
            sendResponse(['success' => false, 'message' => 'order_id dan status diperlukan'], 400);
        }
        $validStatus = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($input['status'], $validStatus)) {
            sendResponse(['success' => false, 'message' => 'Status tidak valid'], 400);
        }
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($stmt->execute([$input['status'], $input['order_id']])) {
            sendResponse(['success' => true, 'message' => 'Status pesanan diperbarui']);
        } else {
            sendResponse(['success' => false, 'message' => 'Gagal memperbarui status'], 500);
        }
    }

    // Update stok produk
    elseif ($resource === 'product_stock') {
        if (!isset($input['product_id']) || !isset($input['stock'])) {
            sendResponse(['success' => false, 'message' => 'product_id dan stock diperlukan'], 400);
        }
        $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
        if ($stmt->execute([(int)$input['stock'], $input['product_id']])) {
            sendResponse(['success' => true, 'message' => 'Stok produk diperbarui']);
        } else {
            sendResponse(['success' => false, 'message' => 'Gagal memperbarui stok'], 500);
        }
    }

    else {
        sendResponse(['success' => false, 'message' => 'Resource tidak dikenal'], 400);
    }
}

// ===========================
// DELETE: Hapus data
// ===========================
elseif ($method === 'DELETE') {
    $input    = json_decode(file_get_contents("php://input"), true);
    $resource = $input['resource'] ?? '';

    // Hapus user
    if ($resource === 'user') {
        if (empty($input['user_id'])) {
            sendResponse(['success' => false, 'message' => 'user_id diperlukan'], 400);
        }
        // Cegah admin hapus dirinya sendiri
        $adminId = $_SERVER['HTTP_X_ADMIN_ID'] ?? null;
        if ($adminId && (int)$adminId === (int)$input['user_id']) {
            sendResponse(['success' => false, 'message' => 'Tidak bisa menghapus akun Anda sendiri'], 400);
        }
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$input['user_id']])) {
            sendResponse(['success' => true, 'message' => 'User berhasil dihapus']);
        } else {
            sendResponse(['success' => false, 'message' => 'Gagal menghapus user'], 500);
        }
    }

    else {
        sendResponse(['success' => false, 'message' => 'Resource tidak dikenal'], 400);
    }
}

else {
    sendResponse(['success' => false, 'message' => 'Method tidak diizinkan'], 405);
}
?>
