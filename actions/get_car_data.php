<?php
/**
 * actions/get_car_data.php - ดึงข้อมูลแบบ Ajax (JSON)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
require_once '../config/database.php';

// ตรวจสอบสิทธิ์ (ถ้าไม่ใช่ Admin ให้หยุดการทำงานและคืนค่าสถานะ 403)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // 1. ดึงรุ่นรถตามยี่ห้อ
        case 'getModels':
            $brand = $_GET['brand'] ?? '';
            if (empty($brand)) {
                echo json_encode([]);
                break;
            }
            $stmt = $conn->prepare("SELECT DISTINCT model_name FROM cars WHERE brand = ? ORDER BY model_name ASC");
            $stmt->execute([$brand]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
            break;

        // 2. ดึงสีตามยี่ห้อและรุ่น
        case 'getColors':
            $brand = $_GET['brand'] ?? '';
            $model = $_GET['model'] ?? '';
            if (empty($brand) || empty($model)) {
                echo json_encode([]);
                break;
            }
            $stmt = $conn->prepare("SELECT cc.color_name 
                                    FROM car_colors cc 
                                    INNER JOIN cars c ON cc.car_id = c.id 
                                    WHERE c.brand = ? AND c.model_name = ?");
            $stmt->execute([$brand, $model]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
            break;

        // 3. ดึงภาคตามชื่อเซลล์
        case 'getRegion':
            $name = $_GET['name'] ?? '';
            if (empty($name)) {
                echo json_encode(['region' => '']);
                break;
            }
            $stmt = $conn->prepare("SELECT region FROM users WHERE name = ? LIMIT 1");
            $stmt->execute([$name]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['region' => $result['region'] ?? '']);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    // ใน production ไม่ควรแสดงข้อความ error ตรงๆ แก่ผู้ใช้ เพื่อความปลอดภัย
    error_log("Ajax Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}