-- 1. สร้างฐานข้อมูล
CREATE DATABASE IF NOT EXISTS car_order_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE car_order_db;

-- 2. ตารางผู้ใช้งาน
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'sales') DEFAULT 'sales'
);

-- 3. ตารางรถยนต์ (รองรับฟีเจอร์รูปภาพและคำอธิบาย)
CREATE TABLE cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    color_name VARCHAR(50),
    status ENUM('Available', 'Sold', 'Out_of_stock') DEFAULT 'Available',
    image_url VARCHAR(255),
    description TEXT
);

-- 4. ตารางออเดอร์ (รองรับฟีเจอร์การเงิน)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(10) NOT NULL,
    car_id INT,
    user_id INT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    payment_status ENUM('Pending', 'DepositPaid', 'FullPaid') DEFAULT 'Pending',
    deposit_amount DECIMAL(10, 2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (car_id) REFERENCES cars(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 5. ตารางเก็บประวัติ (Audit Log)
CREATE TABLE order_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    action VARCHAR(100),
    changed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);