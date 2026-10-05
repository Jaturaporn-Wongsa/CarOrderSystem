<?php
/**
 * actions/update_car.php
 * ไฟล์สำหรับอัปเดตข้อมูลโควตาผ่าน Modal
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
require_once '../config/database.php';

checkAdmin();

// ตรวจสอบว่าเป็น POST method เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin/cars.php");
    exit();
}

// รับค่าจากฟอร์มใน Modal
$id         = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$sales_name = trim($_POST['sales_name'] ?? '');
$region     = trim($_POST['region'] ?? '');
$max_limit  = filter_input(INPUT_POST, 'max_limit', FILTER_VALIDATE_INT) ?: 0;
$month      = trim($_POST['month'] ?? '');
$status     = trim($_POST['status'] ?? '');

// ตรวจสอบข้อมูลเบื้องต้น
if (!$id || empty($sales_name) || empty($month) || empty($status)) {
    $_SESSION['error'] = "❌ ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบอีกครั้ง";
    header("Location: ../admin/cars.php");
    exit();
}

try {
    // อัปเดตข้อมูลในตาราง car_quotas โดยตรง
    $sql = "UPDATE car_quotas 
            SET sales_name = :sales_name, 
                region = :region, 
                max_limit = :max_limit, 
                month = :month, 
                status = :status 
            WHERE id = :id";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':sales_name' => $sales_name,
        ':region'     => $region,
        ':max_limit'  => $max_limit,
        ':month'      => $month,
        ':status'     => $status,
        ':id'         => $id
    ]);

    $_SESSION['success'] = "✅ อัปเดตข้อมูลโควตาเรียบร้อยแล้ว";
    
} catch (PDOException $e) {
    $_SESSION['error'] = "❌ เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
}

// ย้อนกลับไปยังหน้าจัดการโควตา
header("Location: ../admin/cars.php");
exit();