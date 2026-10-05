<?php
// ไฟล์: actions/get_user_region.php

// 1. เรียกฐานข้อมูล (ต้องเช็คว่า path ไป config ถูกต้อง)
// ถ้าไฟล์นี้อยู่ใน folder actions/ ก็ต้องถอยหลัง 1 ชั้นเพื่อไปหา config
require_once '../config/database.php';

// 2. ตั้งค่าหัวกระดาษให้เป็น JSON เพื่อให้ JavaScript รับค่าได้
header('Content-Type: application/json');

// 3. รับชื่อที่ส่งมาจาก JavaScript
$name = isset($_GET['name']) ? $_GET['name'] : '';

if (!empty($name)) {
    try {
        // 4. ค้นหาภาคของเซลล์คนนี้จากฐานข้อมูล
        $stmt = $conn->prepare("SELECT region FROM users WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['region'])) {
            // ส่งค่ากลับไปเป็น JSON
            echo json_encode(['region' => $user['region']]);
        } else {
            // ไม่พบข้อมูล
            echo json_encode(['region' => '']);
        }
    } catch (PDOException $e) {
        // กรณี Database มีปัญหา
        echo json_encode(['region' => '']);
    }
} else {
    echo json_encode(['region' => '']);
}
?>