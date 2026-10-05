<?php
/**
 * sales/save_order.php
 * ไฟล์ประมวลผลการบันทึกข้อมูลการจองรถ (ฉบับสมบูรณ์: ป้องกันการจองข้ามภาคและเช็คโควตา)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php'; 
require_once '../config/auth.php';

// ตรวจสอบสิทธิ์การเข้าใช้งาน
checkSales();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าจากฟอร์ม
    $car_id         = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
    $customer_name  = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $quantity       = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
    $delivery_date  = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
    $remarks        = trim($_POST['remarks'] ?? '');
    $deposit_amount = filter_var($_POST['deposit_amount'] ?? 0, FILTER_VALIDATE_FLOAT); 
    
    $sales_id = $_SESSION['user_id'] ?? null;

    // ตรวจสอบค่าว่างที่จำเป็น
    if (!$sales_id || !$car_id || empty($customer_name) || empty($customer_phone)) {
        $_SESSION['error'] = "❌ กรุณากรอกข้อมูลชื่อลูกค้าและเบอร์โทรศัพท์ให้ครบถ้วน";
        header("Location: add_order.php");
        exit();
    }

    try {
        // เริ่มทำรายการในฐานข้อมูล (Transaction)
        $conn->beginTransaction();

        // 2. ดึงข้อมูลภาคของพนักงานขายจากฐานข้อมูลจริงโดยตรง
        $stmt_user = $conn->prepare("SELECT region FROM users WHERE id = :sales_id");
        $stmt_user->execute(['sales_id' => $sales_id]);
        $user_region = trim($stmt_user->fetchColumn());

        if (empty($user_region)) {
            throw new Exception("ไม่พบข้อมูลภาคของคุณในระบบ กรุณาติดต่อผู้ดูแลระบบ");
        }

        // 3. ตรวจสอบโควตาตามภาคของพนักงาน (ใช้ FOR UPDATE ล็อกแถวป้องกันการจองซ้อนทับ และป้องกันการจองข้ามภาค)
        $stmt_quota = $conn->prepare("SELECT max_limit FROM car_quotas WHERE car_id = :car_id AND region = :region FOR UPDATE");
        $stmt_quota->execute(['car_id' => $car_id, 'region' => $user_region]);
        $quota = $stmt_quota->fetch(PDO::FETCH_ASSOC);

        if (!$quota) {
            throw new Exception("❌ ไม่สามารถจองได้! รถรุ่นนี้ไม่ได้อยู่ในโควตาของภาคของคุณ ($user_region)");
        }

        // 4. คำนวณยอดจองรวมในเดือนปัจจุบันของภาคนั้นๆ (ที่สถานะไม่ถูกยกเลิก)
        $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) FROM orders WHERE car_id = :car_id AND region = :region AND status != 'ยกเลิก' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $sum_stmt->execute(['car_id' => $car_id, 'region' => $user_region]);
        $booked_sum = (int)$sum_stmt->fetchColumn();

        // เช็คว่ายอดจองรวมเกินโควตาของเดือนนี้ไหม
        if (($booked_sum + $quantity) > $quota['max_limit']) {
            throw new Exception("❌ โควตาเต็ม! ไม่สามารถจองเพิ่มได้ในเดือนนี้ (โควตาจำกัด: {$quota['max_limit']} คัน)");
        }

        // 5. บันทึกข้อมูลการจองลงฐานข้อมูล (บันทึก region ของเซลล์ลงไปด้วยเพื่อให้ตรงกับหน้าแดชบอร์ด)
        $sql = "INSERT INTO orders (
                    sales_id, car_id, customer_name, customer_phone, 
                    quantity, delivery_date, deposit_amount, admin_note, region, status, created_at
                ) VALUES (
                    :sales_id, :car_id, :customer_name, :customer_phone, 
                    :quantity, :delivery_date, :deposit_amount, :admin_note, :region, 'รอดำเนินการ', NOW()
                )";
        
        $stmt = $conn->prepare($sql);
        
        $stmt->execute([
            ':sales_id'       => $sales_id,
            ':car_id'         => $car_id,
            ':customer_name'  => $customer_name,
            ':customer_phone' => $customer_phone,
            ':quantity'       => $quantity,
            ':delivery_date'  => $delivery_date,
            ':deposit_amount' => $deposit_amount,
            ':admin_note'     => $remarks,
            ':region'         => $user_region
        ]);

        // ยืนยันการบันทึกข้อมูล
        $conn->commit();

        // 6. บันทึกสำเร็จ
        $_SESSION['success_msg'] = "✨ บันทึกใบจองเรียบร้อยแล้ว";
        header("Location: my_orders.php");
        exit();

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิก Transaction ทั้งหมด
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        header("Location: add_order.php");
        exit();
    }
} else {
    header("Location: add_order.php");
    exit();
}
?>