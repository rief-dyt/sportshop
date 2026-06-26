<?php
// Konfigurasi database terpusat - digunakan oleh semua file API
$host   = 'localhost';
$dbname = 'toko_olahraga';
$dbuser = 'root';
$dbpass = '';

$conn  = null;
$error = null;

try {
    // Langkah 1: Koneksi tanpa nama database dulu, lalu buat database jika belum ada
    $tempConn = new PDO("mysql:host=$host;charset=utf8mb4", $dbuser, $dbpass);
    $tempConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tempConn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $tempConn = null; // tutup koneksi sementara

    // Langkah 2: Koneksi ke database yang sudah pasti ada
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Langkah 3: Auto-create tabel jika belum ada (pertama kali setup)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            gender ENUM('Pria', 'Wanita') NOT NULL,
            height_cm INT NOT NULL,
            weight_kg DECIMAL(5,2) NOT NULL,
            birth_date DATE NOT NULL,
            age INT NOT NULL,
            hobbies JSON NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            otp_code VARCHAR(6) NULL,
            otp_expires_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            category VARCHAR(50) NOT NULL,
            icon VARCHAR(10) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            badge VARCHAR(20) NULL,
            hobbies JSON NOT NULL,
            stock INT DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            user_id INT NULL,
            customer_name VARCHAR(100) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            city VARCHAR(50) NOT NULL,
            address TEXT NOT NULL,
            postal_code VARCHAR(10),
            courier_service VARCHAR(100) NOT NULL,
            shipping_cost DECIMAL(10,2) DEFAULT 0,
            payment_method VARCHAR(50) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(100) NOT NULL,
            product_icon VARCHAR(10) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL
        );
    ");

    // Langkah 3b: Auto-migrate kolom OTP untuk database lama yang sudah ada sebelumnya
    $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_code'")->fetchAll();
    if (count($colCheck) === 0) {
        $conn->exec("ALTER TABLE users
            ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN otp_code VARCHAR(6) NULL,
            ADD COLUMN otp_expires_at DATETIME NULL
        ");
        // User lama yang sudah ada dianggap terverifikasi agar tidak terkunci dari akunnya
        $conn->exec("UPDATE users SET is_verified = 1 WHERE is_verified = 0");
    }

    // Langkah 4: Insert produk default jika tabel products masih kosong
    $count = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ((int)$count === 0) {
        $conn->exec("
            INSERT INTO products (id, name, category, icon, price, badge, hobbies, stock) VALUES
            (1,  'X-Speed Carbon',  'Lari',       '👟', 899000,  'BEST',    '[\"lari\"]',       50),
            (2,  'NeoDumbbell Set', 'Gym',        '🏋️', 590000,  NULL,      '[\"gym\"]',        30),
            (3,  'Strike Pro Ball', 'Sepak Bola', '⚽', 349000,  'NEW',     '[\"sepak-bola\"]', 100),
            (4,  'AeroRacket',      'Badminton',  '🏸', 720000,  'LIMITED', '[\"badminton\"]',  40),
            (5,  'EcoGrip Mat',     'Yoga',       '🧘', 299000,  NULL,      '[\"yoga\"]',       60),
            (6,  'Helm Aeroflux',   'Bersepeda',  '🚴', 520000,  NULL,      '[\"bersepeda\"]',  45),
            (7,  'Thunder Gloves',  'Boxing',     '🥊', 450000,  'BEST',    '[\"boxing\"]',     35),
            (8,  'Hyper Dunk',      'Basket',     '🏀', 240000,  NULL,      '[\"basket\"]',     80),
            (9,  'AquaView',        'Renang',     '🏊', 210000,  NULL,      '[\"renang\"]',     70),
            (10, 'Graphite Strike', 'Tenis',      '🎾', 890000,  NULL,      '[\"tenis\"]',      30),
            (11, 'TrailBreaker',    'Hiking',     '🥾', 1190000, 'HOT',     '[\"hiking\"]',     25),
            (12, 'ResiLoop Bands',  'Yoga',       '🔗', 189000,  NULL,      '[\"yoga\"]',       100)
        ");
    }

} catch (PDOException $e) {
    $error = $e->getMessage();

    // Jika diakses dari endpoint API, kirim JSON error langsung
    if (
        isset($_SERVER['REQUEST_URI']) &&
        (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || isset($_GET['api']))
    ) {
        header("Content-Type: application/json");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Koneksi database gagal. Pastikan MySQL (XAMPP) sudah aktif.',
            'detail'  => $e->getMessage()
        ]);
        exit;
    }
}
?>
