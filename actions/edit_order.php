<?php
/**
 * actions/edit_order.php - จัดการการแก้ไขรายการจองของพนักงานขาย (ฉบับแก้ไขสมบูรณ์)
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 

require_once '../config/auth.php';
require_once '../config/database.php';

checkSales();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าจากฟอร์ม (รองรับทั้ง order_id/id และ note/notes)
    $order_id  = intval($_POST['order_id'] ?? $_POST['id'] ?? 0);
    $shop_name = trim($_POST['shop_name'] ?? '');
    $quantity  = max(1, intval($_POST['quantity'] ?? 1)); // กำหนดขั้นต่ำอย่างน้อย 1 คัน
    $note      = trim($_POST['note'] ?? $_POST['notes'] ?? '');
    $sales_id  = $_SESSION['user_id'] ?? 0;

    // ตรวจสอบว่าส่ง ID และข้อมูลสำคัญมาหรือไม่
    if ($order_id <= 0 || empty($shop_name)) {
        $_SESSION['error'] = "❌ ข้อมูลที่ส่งมาไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง";
        $redirect = $_SERVER['HTTP_REFERER'] ?? '../sales/dashboard.php';
        header("Location: " . $redirect);
        exit();
    }

    try {
        // 2. ตรวจสอบว่ารายการนี้เป็นของพนักงานคนนี้จริง และเช็คสถานะปัจจุบันจากฐานข้อมูล
        $stmt_check = $conn->prepare("SELECT id, status FROM orders WHERE id = ? AND sales_id = ? LIMIT 1");
        $stmt_check->execute([$order_id, $sales_id]);
        $order = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $_SESSION['error'] = "❌ ไม่พบรายการจองนี้ หรือคุณไม่มีสิทธิ์แก้ไข";
            $redirect = $_SERVER['HTTP_REFERER'] ?? '../sales/dashboard.php';
            header("Location: " . $redirect);
            exit();
        }

        $current_status = trim($order['status'] ?? 'รอดำเนินการ');

        // 3. เงื่อนไขบล็อก: อนุญาตให้แก้ไขเฉพาะสถานะ "รอดำเนินการ" หรือค่าว่างเท่านั้น
        $allowed_statuses = ['', 'รอดำเนินการ', 'Pending'];
        if (!in_array($current_status, $allowed_statuses, true)) {
            $_SESSION['error'] = "❌ ไม่สามารถแก้ไขได้ เนื่องจากสถานะปัจจุบันคือ '{$current_status}' (ถูกดำเนินการไปแล้ว)";
            $redirect = $_SERVER['HTTP_REFERER'] ?? '../sales/dashboard.php';
            header("Location: " . $redirect);
            exit();
        }

        // 4. ทำการอัปเดตข้อมูลลงฐานข้อมูล (แก้ไขอัปเดตลงคอลัมน์ note ที่ถูกต้อง)
        $stmt_update = $conn->prepare("UPDATE orders SET shop_name = ?, quantity = ?, note = ? WHERE id = ? AND sales_id = ?");
        $stmt_update->execute([$shop_name, $quantity, $note, $order_id, $sales_id]);

        $_SESSION['success'] = "✅ แก้ไขรายการจองเรียบร้อยแล้ว";

    } catch (PDOException $e) {
        $_SESSION['error'] = "❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }

    // ส่งกลับไปยังหน้าที่กดแก้ไขมา (หรือ dashboard ถ้าไม่มีข้อมูล referer)
    $redirect = $_SERVER['HTTP_REFERER'] ?? '../sales/dashboard.php';
    header("Location: " . $redirect);
    exit();

} else {
    header("Location: ../sales/dashboard.php");
    exit();
}