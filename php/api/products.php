<?php
// 1. Hubungkan ke file konfigurasi database dan helper functions
require_once '../../config/database.php'; // Hubungkan ke $conn terpusat
require_once '../includes/functions.php';  // Hubungkan ke sendResponse()

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Pastikan koneksi database tersedia
if (!isset($conn)) {
    sendResponse(['success' => false, 'message' => 'Database connection missing', 'products' => []], 500);
}

// Mengambil seluruh produk diurutkan dari harga tertinggi
$stmt = $conn->query("SELECT * FROM products ORDER BY price DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($products as $p) {
    $price = $p['price'];
    
    // Perbaikan format ribuan agar konsisten menggunakan titik (.)
    if ($price >= 1000000) {
        $price_formatted = 'Rp ' . number_format($price / 1000, 0, ',', '.') . 'K';
    } else {
        $price_formatted = 'Rp ' . number_format($price, 0, ',', '.');
    }
    
    $result[] = [
        'id' => (int)$p['id'], // Type-cast ke integer demi akurasi logic frontend
        'name' => $p['name'],
        'category' => $p['category'],
        'icon' => $p['icon'],
        'price' => (int)$price,
        'price_formatted' => $price_formatted,
        'badge' => $p['badge'],
        'hobbies' => json_decode($p['hobbies'], true) ?? [] // Pengaman jika field hobbies NULL
    ];
}

// Kirim response menggunakan helper function terpusat
sendResponse(['success' => true, 'products' => $result]);
?>