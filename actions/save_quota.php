<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? ''; 
    $brand = $_POST['brand'] ?? '';
    $model_name = $_POST['model_name'] ?? '';
    $color_name = $_POST['color_name'] ?? '';
    $sales_name = $_POST['sales_name'] ?? '';
    $region = $_POST['region'] ?? '';
    $max_limit = $_POST['max_limit'] ?? 0;
    $month = $_POST['month'] ?? '';
    $status = $_POST['status'] ?? 'ไม่จอง';

    try {
        // ค้นหา car_id โดยอิงจาก ยี่ห้อ, รุ่น และ "สี" ที่เลือก
        $stmt_car = $conn->prepare("SELECT id FROM cars WHERE brand = ? AND model_name = ? AND color_name = ? LIMIT 1");
        $stmt_car->execute([$brand, $model_name, $color_name]);
        $car = $stmt_car->fetch(PDO::FETCH_ASSOC);

        if (!$car) {
            throw new Exception("ไม่พบข้อมูลรถรุ่นนี้ร่วมกับสีที่เลือกในระบบ กรุณาไปเพิ่มข้อมูลรถและสีนี้ในหน้าจัดการรถก่อน");
        }
        $car_id = $car['id'];

        if (!empty($id)) {
            $sql = "UPDATE car_quotas SET 
                        car_id = ?, 
                        region = ?, 
                        sales_name = ?, 
                        month = ?, 
                        max_limit = ?, 
                        status = ? 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$car_id, $region, $sales_name, $month, $max_limit, $status, $id]);
            $_SESSION['success'] = "✅ แก้ไขข้อมูลโควตาเรียบร้อย";
        } else {
            $sql = "INSERT INTO car_quotas (car_id, region, sales_name, month, max_limit, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        sales_name = VALUES(sales_name),
                        month = VALUES(month),
                        max_limit = VALUES(max_limit),
                        status = VALUES(status)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$car_id, $region, $sales_name, $month, $max_limit, $status]);
            $_SESSION['success'] = "✅ บันทึกข้อมูลโควตาเรียบร้อย";
        }

        header("Location: ../admin/cars.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
        header("Location: ../admin/cars.php");
        exit();
    }
} else {
    header("Location: ../admin/cars.php");
    exit();
}