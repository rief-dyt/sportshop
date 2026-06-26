<?php
// 1. Integrasikan dengan file konfigurasi dan helper yang sudah ada
require_once '../../config/database.php'; // Menghubungkan ke $conn dari database.php
require_once '../includes/functions.php';  // Menghubungkan ke sendResponse()

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Pastikan koneksi database tersedia dari file database.php
if (!isset($conn)) {
    sendResponse(['success' => false, 'message' => 'Database connection missing', 'recommendations' => []], 500);
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['user_id'])) {
    sendResponse(['success' => false, 'message' => 'User ID is required', 'recommendations' => []], 400);
}

// Get user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$input['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    sendResponse(['success' => false, 'message' => 'User not found', 'recommendations' => []], 404);
}

// Pengaman: Jika hobbies di database NULL atau string kosong, ubah jadi array kosong []
$userHobbies = json_decode($user['hobbies'], true) ?? [];

// Get all products
$stmt = $conn->query("SELECT * FROM products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recommendations = [];
foreach ($products as $p) {
    // Pengaman juga untuk produk
    $productHobbies = json_decode($p['hobbies'], true) ?? [];
    
    $matchCount = 0;
    foreach ($userHobbies as $hobby) {
        if (in_array($hobby, $productHobbies)) {
            $matchCount++;
        }
    }
    
    $matchScore = count($userHobbies) > 0 ? round(($matchCount / count($userHobbies)) * 100) : 50;
    
    $price = $p['price'];
    if ($price >= 1000000) {
        // PERBAIKAN: Ditambahkan ', 0, ",", "."' agar separator ribuan tetap menggunakan TITIK (cth: Rp 1.200K)
        $price_formatted = 'Rp ' . number_format($price / 1000, 0, ',', '.') . 'K';
    } else {
        $price_formatted = 'Rp ' . number_format($price, 0, ',', '.');
    }
    
    $recommendations[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'category' => $p['category'],
        'icon' => $p['icon'],
        'price_formatted' => $price_formatted,
        'match_score' => $matchScore
    ];
}

// Sort by match score
usort($recommendations, function($a, $b) {
    return $b['match_score'] - $a['match_score'];
});

// Ganti echo manual dengan fungsi sendResponse bawaan dari functions.php
sendResponse([
    'success' => true,
    'recommendations' => array_slice($recommendations, 0, 6),
    'user' => [
        'name' => $user['name'],
        'email' => $user['email'],
        'gender' => $user['gender'],
        'height' => $user['height_cm'],
        'weight' => $user['weight_kg'],
        'age' => $user['age'],
        'hobbies' => $userHobbies
    ]
]);
?>