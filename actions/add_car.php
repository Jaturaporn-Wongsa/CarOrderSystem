<?php
/**
 * actions/add_car.php - บันทึกข้อมูลรถและโควตา
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
require_once '../config/database.php';

// ตรวจสอบสิทธิ์
checkAdmin();

// ตรวจสอบการส่งค่าแบบ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin/cars.php");
    exit();
}

// 1. รับค่าและทำความสะอาดข้อมูล
$brand      = trim($_POST['brand'] ?? '');
$model_name = trim($_POST['model_name'] ?? '');
$color_name = trim($_POST['color_name'] ?? '');
$status     = trim($_POST['status'] ?? '');
$region     = trim($_POST['region'] ?? '');
$max_limit  = intval($_POST['max_limit'] ?? 0);
$sales_name = trim($_POST['sales_name'] ?? '');
$month      = trim($_POST['month'] ?? '');

// ตรวจสอบข้อมูลบังคับ (รวมสีรถด้วย)
if (empty($brand) || empty($model_name) || empty($color_name) || empty($region) || empty($sales_name) || empty($month) || empty($status)) {
    $_SESSION['error'] = "❌ กรุณากรอกข้อมูลให้ครบถ้วน";
    header("Location: ../admin/cars.php");
    exit();
}

try {
    $conn->beginTransaction();

    // 2. ค้นหา car_id จากยี่ห้อและรุ่นรถ
    $stmt_check = $conn->prepare("SELECT id FROM cars WHERE brand = ? AND model_name = ? LIMIT 1");
    $stmt_check->execute([$brand, $model_name]);
    $car = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($car) {
        $car_id = $car['id'];
    } else {
        $_SESSION['error'] = "❌ ไม่พบข้อมูลรุ่นรถนี้ในระบบคลังรถ";
        header("Location: ../admin/cars.php");
        exit();
    }

    // 3. บันทึกข้อมูลโควตาพร้อมสีที่เลือก
    $sql_quota = "INSERT INTO car_quotas (car_id, color_name, region, max_limit, sales_name, month, status) 
                  VALUES (:car_id, :color_name, :region, :max_limit, :sales_name, :month, :status)";
    
    $q_stmt = $conn->prepare($sql_quota);
    $q_stmt->execute([
        ':car_id'     => $car_id,
        ':color_name' => $color_name,
        ':region'     => $region,
        ':max_limit'  => $max_limit,
        ':sales_name' => $sales_name,
        ':month'      => $month,
        ':status'     => $status
    ]);

    $conn->commit();
    $_SESSION['success'] = "✅ บันทึกข้อมูลเรียบร้อยแล้ว";

} catch (PDOException $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    error_log("Database Error: " . $e->getMessage());
    $_SESSION['error'] = "❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
}

header("Location: ../admin/cars.php");
exit();