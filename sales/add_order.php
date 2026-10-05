<?php
/**
 * actions/add_order.php - ระบบบันทึกการจองรถ (ส่วนประมวลผลฉบับสมบูรณ์)
 * รายละเอียด: รับค่าจากฟอร์ม, ตรวจสอบโควตาคงเหลือจากฐานข้อมูลจริง, บันทึกรายการลงฐานข้อมูล และแจ้งเตือนผ่าน LINE
 */

// 1. เริ่มต้น Session
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. เรียกใช้ไฟล์ตั้งค่าและฐานข้อมูล
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/line_notify.php'; 

// 3. ตรวจสอบสิทธิ์การใช้งาน (ต้องล็อกอินเป็นพนักงานขาย)
checkSales();

// 4. ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "❌ ไม่อนุญาตให้เข้าถึงหน้านี้โดยตรงครับ";
    header("Location: ../sales/dashboard.php");
    exit();
}

// 5. รับค่าจากฟอร์มและตรวจสอบความถูกต้อง
$customer_name  = "-"; // หากไม่มีฟอร์มกรอกชื่อ
$customer_phone = "-"; // หากไม่มีฟอร์มกรอกเบอร์
$shop_name      = trim($_POST['shop_name'] ?? '');
$car_id         = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
$admin_note     = trim($_POST['notes'] ?? '');
$quantity       = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$color_name     = trim($_POST['color'] ?? 'ไม่ระบุ'); 

// กำหนดค่าเริ่มต้น
$quantity       = ($quantity && $quantity > 0) ? $quantity : 1; 
$sales_id       = $_SESSION['user_id'] ?? 0;
$sales_name     = $_SESSION['name'] ?? 'พนักงานขาย';

// 6. ตรวจสอบข้อมูลบังคับเบื้องต้น
if (empty($shop_name) || !$car_id || !$sales_id) {
    $_SESSION['error'] = "❌ กรุณากรอกชื่อร้านค้าและเลือกรุ่นรถให้ครบถ้วนก่อนทำการจองครับ";
    header("Location: ../sales/add_order.php?car_id=" . ($car_id ?? ''));
    exit();
}

try {
    // เริ่มทำรายการในฐานข้อมูล (ใช้ Transaction ป้องกันการจองซ้ำซ้อน)
    $conn->beginTransaction();

    // 7. ดึงข้อมูลภาค (region) ของพนักงานขายจากฐานข้อมูลโดยตรง เพื่อป้องกันปัญหา Session เป็นค่าว่าง
    $stmt_user = $conn->prepare("SELECT region FROM users WHERE id = :sales_id");
    $stmt_user->execute(['sales_id' => $sales_id]);
    $user_region = trim($stmt_user->fetchColumn());

    if (empty($user_region)) {
        throw new Exception("ไม่พบข้อมูลเขตพื้นที่ (Region) ของคุณในระบบ กรุณาติดต่อผู้ดูแลระบบ");
    }

    // 8. ตรวจสอบโควตาสูงสุดของรถรุ่นนี้ในภาคของเซลล์ (ใช้ FOR UPDATE ล็อกแถวป้องกันข้อมูลชนกัน)
    $stmt = $conn->prepare("SELECT max_limit FROM car_quotas WHERE car_id = :car_id AND region = :region FOR UPDATE");
    $stmt->execute(['car_id' => $car_id, 'region' => $user_region]);
    $quota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quota) { 
        throw new Exception("❌ ไม่พบข้อมูลโควตารถรุ่นนี้ในเขตการขายของคุณ ($user_region) ครับ"); 
    }

    // คำนวณยอดจองรวมในเดือนปัจจุบัน (ที่ไม่ถูกยกเลิก)
    $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) 
                                FROM orders 
                                WHERE car_id = :car_id 
                                AND region = :region 
                                AND status != 'ยกเลิก' 
                                AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                                AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $sum_stmt->execute(['car_id' => $car_id, 'region' => $user_region]);
    $booked_sum = (int)$sum_stmt->fetchColumn();

    // เช็คว่าจองเกินโควตาไหม
    if (($booked_sum + $quantity) > $quota['max_limit']) {
        throw new Exception("❌ โควตารถรุ่นนี้เต็มแล้วครับ! ไม่สามารถจองเพิ่มได้ในเดือนนี้ (โควตาจำกัด: {$quota['max_limit']} คัน)");
    }

    // 9. บันทึกข้อมูลการจองลงฐานข้อมูล (รวมถึงระบุ region ให้ตรงกับเซลล์)
    $sql = "INSERT INTO orders (sales_id, car_id, customer_name, customer_phone, shop_name, color_name, admin_note, region, status, quantity, created_at) 
            VALUES (:sales_id, :car_id, :customer_name, :customer_phone, :shop_name, :color_name, :admin_note, :region, 'รอดำเนินการ', :quantity, NOW())";
    
    $insert_stmt = $conn->prepare($sql);
    $insert_stmt->execute([
        ':sales_id'      => $sales_id,
        ':car_id'        => $car_id,
        ':customer_name' => $customer_name,
        ':customer_phone'=> $customer_phone,
        ':shop_name'     => $shop_name,
        ':color_name'    => $color_name,
        ':admin_note'    => $admin_note,
        ':region'        => $user_region,
        ':quantity'      => $quantity
    ]);

    // บันทึกรายการทั้งหมดลงฐานข้อมูล
    $conn->commit();

    // 10. ดึงชื่อรุ่นรถสำหรับแจ้งเตือนผ่าน LINE
    $car_stmt = $conn->prepare("SELECT model_name FROM cars WHERE id = ?");
    $car_stmt->execute([$car_id]);
    $model_name = $car_stmt->fetchColumn() ?: 'รถยนต์';

    $msg = "\n🚗 มีรายการจองรถใหม่!\n" .
            "---------------------------\n" .
            "รุ่น: $model_name\n" .
            "สี: $color_name\n" . 
            "จำนวน: $quantity คัน\n" .
            "ร้านค้า: $shop_name\n" . 
            "เขตการขาย: $user_region\n" .
            "หมายเหตุ: " . ($admin_note ?: '-') . "\n" .
            "โดยเซลล์: $sales_name\n" .
            "สถานะ: รอดำเนินการ";
         
    if (function_exists('sendLineNotify')) {
        sendLineNotify($msg);
    }

    // แจ้งเตือนสำเร็จและกลับหน้า Dashboard
    $_SESSION['success'] = "✨ บันทึกใบจองเรียบร้อยแล้วครับ";
    header("Location: ../sales/dashboard.php");

} catch (Exception $e) {
    // หากเกิดข้อผิดพลาด ให้ย้อนคืนรายการ (Rollback)
    if (isset($conn) && $conn->inTransaction()) { $conn->rollBack(); }
    
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    header("Location: ../sales/add_order.php?car_id=$car_id");
}
exit();
?>