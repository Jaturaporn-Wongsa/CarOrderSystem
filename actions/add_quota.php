<?php
/**
 * actions/add_quota.php - ไฟล์ประมวลผลการเพิ่มข้อมูลโควตารถ
 */
session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_id       = $_POST['car_id'] ?? '';
    $sales_person = trim($_POST['sales_person'] ?? '');
    $region       = trim($_POST['region'] ?? '');
    $quota_amount = (int)($_POST['quota_amount'] ?? 0);
    $month        = trim($_POST['month'] ?? '');
    $status       = trim($_POST['status'] ?? 'Pending');

    // ตรวจสอบความถูกต้องของข้อมูล
    if (empty($car_id) || empty($sales_person) || empty($region) || empty($quota_amount) || empty($month)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลโควต้าให้ครบถ้วนทุกช่อง";
        header("Location: ../admin/manage_quotas.php");
        exit();
    }

    try {
        // ดึงชื่อสีจากตาราง cars มาเก็บไว้ในตาราง quotas (ตามโครงสร้างฐานข้อมูลของคุณที่มีฟิลด์ color_name)
        $stmt_car = $conn->prepare("SELECT color FROM cars WHERE id = ?");
        $stmt_car->execute([$car_id]);
        $car_data = $stmt_car->fetch(PDO::FETCH_ASSOC);
        $color_name = $car_data['color'] ?? '-';

        // บันทึกลงตาราง quotas
        $sql = "INSERT INTO quotas (car_id, color_name, sales_person, region, month, quota_amount) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$car_id, $color_name, $sales_person, $region, $month, $quota_amount]);

        $_SESSION['success'] = "บันทึกข้อมูลโควต้าเรียบร้อยแล้ว!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกฐานข้อมูล: " . $e->getMessage();
    }
}

header("Location: ../admin/manage_quotas.php");
exit();