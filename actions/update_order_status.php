<?php
/**
 * actions/update_order_status.php
 * ระบบอัปเดตสถานะออเดอร์ (Approved/Rejected) สำหรับ Admin
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../config/database.php';
require_once '../config/auth.php';

// ตรวจสอบสิทธิ์เฉพาะ Admin เท่านั้น
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $action   = trim($_POST['status'] ?? ''); // รับค่าจากฟอร์ม: Approved หรือ Rejected

    // ตรวจสอบความถูกต้องของสถานะที่ส่งมาเทียบกับฐานข้อมูล
    $valid_statuses = ['Approved', 'Rejected'];
    
    if ($order_id > 0 && in_array($action, $valid_statuses)) {
        try {
            // อัปเดตสถานะลงฐานข้อมูล
            $sql = "UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :order_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':status'   => $action, 
                ':order_id' => $order_id
            ]);

            // กำหนดข้อความแจ้งเตือนเป็นภาษาไทยสำหรับผู้ใช้งาน
            $status_th = ($action === 'Approved') ? 'อนุมัติ' : 'ปฏิเสธ';
            $_SESSION['success'] = "✅ อัปเดตสถานะออเดอร์ #$order_id เป็น '$status_th' เรียบร้อยแล้ว";
            
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $_SESSION['error'] = "❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล";
        }
    } else {
        $_SESSION['error'] = "❌ สถานะไม่ถูกต้อง";
    }
}

// กลับไปยังหน้าคำร้องขอที่รอการอนุมัติ
header("Location: ../admin/pending_orders.php");
exit();