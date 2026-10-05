<?php
/**
 * actions/delete_order.php - ระบบลบรายการจองสำหรับแอดมิน
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 

require_once '../config/auth.php';
require_once '../config/database.php';

checkAdmin();

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($order_id) {
    try {
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        
        $_SESSION['success'] = "🗑️ ลบรายการจองเรียบร้อยแล้ว";
    } catch (PDOException $e) {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "ไม่พบรหัสรายการที่ต้องการลบ";
}

header("Location: ../admin/dashboard.php");
exit();