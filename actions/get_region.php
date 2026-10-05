<?php
// actions/get_user_region.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';
header('Content-Type: application/json');

// เช็คว่ามีการส่งชื่อมาไหม
if (isset($_GET['name'])) {
    $name = $_GET['name'];
    
    // ดึงภาคจากฐานข้อมูล
    $stmt = $conn->prepare("SELECT region FROM users WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $name]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['region'])) {
        echo json_encode(['region' => $user['region']]);
    } else {
        echo json_encode(['region' => '']); // ถ้าไม่เจอให้คืนค่าว่าง
    }
} else {
    echo json_encode(['region' => '']);
}
?>