<?php
/**
 * actions/add_order.php - ระบบประมวลผลการบันทึกการจอง (ตรวจสอบโควตาตามรุ่นรถและภาคแบบสมบูรณ์)
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/line_notify.php'; 

checkSales();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../sales/dashboard.php");
    exit();
}

$shop_name      = trim($_POST['shop_name'] ?? '');
$car_id         = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
$note           = trim($_POST['notes'] ?? ''); // เปลี่ยนมารับค่าเก็บลงฟิลด์หมายเหตุของพนักงาน
$quantity       = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

// รองรับทั้ง color_name และ color เพื่อป้องกันปัญหาค่าเป็นค่าว่าง
$color_name     = trim($_POST['color_name'] ?? ($_POST['color'] ?? 'ไม่ระบุ')); 

$quantity       = ($quantity && $quantity > 0) ? $quantity : 1; 
$sales_id       = $_SESSION['user_id'] ?? 0;
$sales_name     = $_SESSION['name'] ?? 'ผู้ใช้งานระบบ';

if (empty($shop_name) || !$car_id) {
    $_SESSION['error'] = "❌ กรุณากรอกข้อมูลร้านค้าและเลือกรุ่นรถให้ครบถ้วน";
    header("Location: ../sales/dashboard.php");
    exit();
}

try {
    $conn->beginTransaction();

    // 1. ดึงข้อมูลภาคของพนักงานจากตาราง region_sales_assignment
    $current_month = date('Y-m');
    $stmt_region = $conn->prepare("SELECT region FROM region_sales_assignment WHERE user_id = ? AND assigned_month = ? LIMIT 1");
    $stmt_region->execute([$sales_id, $current_month]);
    $res_region = $stmt_region->fetch(PDO::FETCH_ASSOC);

    if ($res_region && !empty($res_region['region'])) {
        $user_region = trim($res_region['region']);
    } else {
        $stmt_fallback = $conn->prepare("SELECT region FROM region_sales_assignment WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt_fallback->execute([$sales_id]);
        $res_fallback = $stmt_fallback->fetch(PDO::FETCH_ASSOC);
        $user_region = ($res_fallback && !empty($res_fallback['region'])) ? trim($res_fallback['region']) : ""; 
    }

    if (empty($user_region)) {
        throw new Exception("ไม่พบข้อมูลภาคของคุณในระบบสำหรับเดือนนี้ กรุณาติดต่อผู้ดูแลระบบ");
    }

    // 2. ตรวจสอบโควตารวมของรุ่นรถนี้ในภาคของพนักงาน
    $stmt = $conn->prepare("SELECT SUM(max_limit) as total_max FROM car_quotas WHERE car_id = :car_id AND region = :region FOR UPDATE");
    $stmt->execute(['car_id' => $car_id, 'region' => $user_region]);
    $quota = $stmt->fetch(PDO::FETCH_ASSOC);

    $max_limit = (int)($quota['total_max'] ?? 0);

    if ($max_limit <= 0) { 
        throw new Exception("❌ ไม่สามารถจองได้! รถรุ่นนี้ไม่ได้อยู่ในโควตาของภาคของคุณ ({$user_region})"); 
    }

    // 3. คำนวณยอดจองสะสมในเดือนปัจจุบันของภาคนั้นๆ (ที่ไม่ถูกยกเลิก)
    $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) FROM orders WHERE car_id = :car_id AND region = :region AND status != 'ยกเลิก' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $sum_stmt->execute(['car_id' => $car_id, 'region' => $user_region]);
    $booked_sum = (int)$sum_stmt->fetchColumn();

    if (($booked_sum + $quantity) > $max_limit) {
        throw new Exception("❌ โควตาเต็ม! ไม่สามารถจองเพิ่มได้ในเดือนนี้ (โควตารวมจำกัด: {$max_limit} คัน, จองไปแล้ว: {$booked_sum} คัน)");
    }

    // 4. บันทึกข้อมูลการจองลงฐานข้อมูล (บันทึกลงคอลัมน์ note เพื่อให้ตรงกับโครงสร้างตาราง)
    $sql = "INSERT INTO orders (sales_id, car_id, shop_name, color_name, note, region, status, quantity, created_at) 
            VALUES (:sales_id, :car_id, :shop_name, :color_name, :note, :region, 'รอดำเนินการ', :quantity, NOW())";
    
    $insert_stmt = $conn->prepare($sql);
    $insert_stmt->execute([
        ':sales_id'       => $sales_id,
        ':car_id'         => $car_id,
        ':shop_name'      => $shop_name,
        ':color_name'     => $color_name,
        ':note'           => $note,
        ':region'         => $user_region,
        ':quantity'       => $quantity
    ]);

    $conn->commit(); 

    // 5. แจ้งเตือนผ่าน LINE (ถ้ามีตั้งค่าไว้)
    try {
        $car_stmt = $conn->prepare("SELECT model_name FROM cars WHERE id = ?");
        $car_stmt->execute([$car_id]);
        $model_name = $car_stmt->fetchColumn() ?: 'รถยนต์';

        $msg = "\n🚗 มีรายการจองรถใหม่!\n" .
                "---------------------------\n" .
                "รุ่น: $model_name\n" .
                "สี: $color_name\n" . 
                "จำนวน: $quantity คัน\n" .
                "ร้านค้า: $shop_name\n" . 
                "ภาค: $user_region\n" .
                "หมายเหตุ: " . ($note ?: '-') . "\n" .
                "โดยเซลล์: $sales_name\n" .
                "สถานะ: รอดำเนินการ";
                
        sendLineNotify($msg);
    } catch (Exception $lineEx) {}

    $_SESSION['success'] = "✨ บันทึกใบจองเรียบร้อยแล้ว";
    header("Location: ../sales/dashboard.php");
    exit();

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
    
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    header("Location: ../sales/dashboard.php");
    exit();
}