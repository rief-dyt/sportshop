-- ============================================================
-- CATATAN: Buat database bernama 'toko_olahraga' terlebih dahulu di UI,
-- lalu klik database tersebut, baru jalankan script di bawah ini.
-- ============================================================

-- Membuat database hanya jika database tersebut belum pernah ada sebelumnya
CREATE DATABASE IF NOT EXISTS toko_olahraga;
USE toko_olahraga;

-- Gabungkan dengan kode perintah CREATE TABLE dan INSERT milik Anda di bawahnya...

-- Users table
CREATE TABLE users (
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
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Untuk menambah kolom role pada tabel yang sudah ada:
-- ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user';

-- Contoh cara menjadikan user sebagai admin (ganti ID sesuai kebutuhan):
-- UPDATE users SET role = 'admin' WHERE id = 1;

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    icon VARCHAR(10) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    badge VARCHAR(20) NULL,
    hobbies JSON NOT NULL,
    stock INT DEFAULT 0
);

-- Orders table
CREATE TABLE orders (
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

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    product_icon VARCHAR(10) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

-- Insert products
INSERT INTO products (id, name, category, icon, price, badge, hobbies, stock) VALUES
(1, 'X-Speed Carbon', 'Lari', '👟', 899000, 'BEST', '["lari"]', 50),
(2, 'NeoDumbbell Set', 'Gym', '🏋️', 590000, NULL, '["gym"]', 30),
(3, 'Strike Pro Ball', 'Sepak Bola', '⚽', 349000, 'NEW', '["sepak-bola"]', 100),
(4, 'AeroRacket', 'Badminton', '🏸', 720000, 'LIMITED', '["badminton"]', 40),
(5, 'EcoGrip Mat', 'Yoga', '🧘', 299000, NULL, '["yoga"]', 60),
(6, 'Helm Aeroflux', 'Bersepeda', '🚴', 520000, NULL, '["bersepeda"]', 45),
(7, 'Thunder Gloves', 'Boxing', '🥊', 450000, 'BEST', '["boxing"]', 35),
(8, 'Hyper Dunk', 'Basket', '🏀', 240000, NULL, '["basket"]', 80),
(9, 'AquaView', 'Renang', '🏊', 210000, NULL, '["renang"]', 70),
(10, 'Graphite Strike', 'Tenis', '🎾', 890000, NULL, '["tenis"]', 30),
(11, 'TrailBreaker', 'Hiking', '🥾', 1190000, 'HOT', '["hiking"]', 25),
(12, 'ResiLoop Bands', 'Yoga', '🔗', 189000, NULL, '["yoga"]', 100);