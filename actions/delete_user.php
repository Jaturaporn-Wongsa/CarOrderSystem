<?php
// actions/delete_user.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/auth.php';
checkAdmin(); // ป้องกันสิทธิ์เฉพาะแอดมินเท่านั้น
require_once '../config/database.php';

if (isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);

    try {
        // 1. ตรวจสอบก่อนว่าพนักงาน ID นี้ เคยมีประวัติการทำออเดอร์ในระบบแล้วหรือยัง
        $check_order = $conn->prepare("SELECT id FROM orders WHERE user_id = :user_id LIMIT 1");
        $check_order->execute([':user_id' => $delete_id]);
        
        if ($check_order->fetch()) {
            // หากมีออเดอร์ผูกอยู่ ห้ามลบเด็ดขาดเพื่อไม่ให้โครงสร้างรายงานพัง
            $_SESSION['error'] = "❌ ไม่สามารถลบพนักงานขายรายนี้ได้ เนื่องจากพนักงานมีประวัติการทำรายการสั่งซื้ออยู่ในระบบแล้ว";
        } else {
            // 2. หากเป็นพนักงานใหม่ที่ไม่มีประวัติออเดอร์ผูกค้างอยู่ สามารถลบออกได้ทันที
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id AND role = 'sales'");
            $stmt->execute([':id' => $delete_id]);
            
            $_SESSION['success'] = "✨ ลบบัญชีผู้ใช้งานพนักงานออกจากระบบเรียบร้อยแล้ว";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในฐานข้อมูล: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "ไม่พบรหัสผู้ใช้งานที่ต้องการลบ";
}

// ส่งแอดมินกลับไปยังหน้าจัดการรายชื่อพนักงาน
header("Location: ../admin/users.php");
exit();
?>