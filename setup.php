<?php
/**
 * ============================================================
 * SETUP.PHP — Auto-installer TokoOlahraga
 * Akses sekali saja: http://localhost/TokoOlahraga/setup.php
 * ============================================================
 */

$host   = 'localhost';
$dbname = 'toko_olahraga';
$dbuser = 'root';
$dbpass = '';

$steps  = [];
$success = true;

function addStep(&$steps, $label, $ok, $detail = '') {
    $steps[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

try {
    // 1. Test koneksi MySQL
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    addStep($steps, 'Koneksi ke MySQL berhasil', true);

    // 2. Buat database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    addStep($steps, "Database '$dbname' siap", true);

    // 3. Pilih database
    $pdo->exec("USE `$dbname`");

    // 4. Buat tabel users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
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
    )");
    addStep($steps, 'Tabel users siap', true);

    // 5. Buat tabel products
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category VARCHAR(50) NOT NULL,
        icon VARCHAR(10) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        badge VARCHAR(20) NULL,
        hobbies JSON NOT NULL,
        stock INT DEFAULT 0
    )");
    addStep($steps, 'Tabel products siap', true);

    // 6. Buat tabel orders
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
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
    )");
    addStep($steps, 'Tabel orders siap', true);

    // 7. Buat tabel order_items
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(100) NOT NULL,
        product_icon VARCHAR(10) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL
    )");
    addStep($steps, 'Tabel order_items siap', true);

    // 8. Insert data produk jika belum ada
    $count = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO products (id, name, category, icon, price, badge, hobbies, stock) VALUES
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
        addStep($steps, '12 produk berhasil di-insert', true);
    } else {
        addStep($steps, "Produk sudah ada ($count item), skip insert", true);
    }

} catch (PDOException $e) {
    $success = false;
    addStep($steps, 'ERROR: ' . $e->getMessage(), false,
        'Pastikan XAMPP MySQL sudah diaktifkan di XAMPP Control Panel');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Setup — TokoOlahraga</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; padding: 0 20px; }
  h1   { color: #e85d04; }
  .step { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid #eee; }
  .ok  { color: #16a34a; font-size:1.3em; }
  .err { color: #dc2626; font-size:1.3em; }
  .detail { font-size:.85em; color:#666; margin-top:4px; }
  .box { border:2px solid; border-radius:8px; padding:20px; margin-top:24px; text-align:center; }
  .box.success { border-color:#16a34a; background:#f0fdf4; color:#15803d; }
  .box.fail    { border-color:#dc2626; background:#fef2f2; color:#b91c1c; }
  a.btn { display:inline-block; margin-top:16px; padding:10px 24px; background:#e85d04;
          color:#fff; text-decoration:none; border-radius:6px; font-weight:bold; }
</style>
</head>
<body>
  <h1>⚙️ Setup Database TokoOlahraga</h1>
  <p>Proses setup database otomatis...</p>

  <?php foreach ($steps as $s): ?>
  <div class="step">
    <span class="<?= $s['ok'] ? 'ok' : 'err' ?>"><?= $s['ok'] ? '✅' : '❌' ?></span>
    <div>
      <div><?= htmlspecialchars($s['label']) ?></div>
      <?php if ($s['detail']): ?>
        <div class="detail"><?= htmlspecialchars($s['detail']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if ($success): ?>
  <div class="box success">
    <strong>🎉 Setup berhasil!</strong><br>
    Database dan semua tabel sudah siap digunakan.
    <br><a class="btn" href="index.html">→ Buka SportVibe</a>
  </div>
  <?php else: ?>
  <div class="box fail">
    <strong>❌ Setup gagal</strong><br>
    Pastikan <b>XAMPP → MySQL</b> sudah dinyalakan, lalu refresh halaman ini.
  </div>
  <?php endif; ?>
</body>
</html>
