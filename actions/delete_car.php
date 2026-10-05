<?php
/**
 * actions/delete_car.php - ลบข้อมูลโควตาเฉพาะแถว
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
require_once '../config/database.php';

// ตรวจสอบสิทธิ์ Admin
checkAdmin();

// เตรียม URL สำหรับย้อนกลับ
$redirect_url = $_SERVER['HTTP_REFERER'] ?? '../admin/cars.php';

// รับ ID ของตาราง car_quotas
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['error'] = "❌ ไม่พบรหัสข้อมูลที่ต้องการลบ";
    header("Location: " . $redirect_url);
    exit();
}

try {
    // ลบข้อมูลเฉพาะแถวในตาราง car_quotas ตาม ID ที่ส่งมา
    $stmt = $conn->prepare("DELETE FROM car_quotas WHERE id = ?");
    $stmt->execute([$id]);

    $_SESSION['success'] = "🗑️ ลบข้อมูลเรียบร้อยแล้ว";

} catch (Exception $e) {
    error_log("Delete Error: " . $e->getMessage());
    $_SESSION['error'] = "❌ เกิดข้อผิดพลาดในการลบข้อมูล: " . $e->getMessage();
}

// ย้อนกลับไปยังหน้าที่กดเข้ามา
header("Location: " . $redirect_url);
exit();
?>