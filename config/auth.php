<?php
/**
 * config/auth.php
 * ไฟล์จัดการสิทธิ์การเข้าถึงระบบและการตรวจสอบ Session (เวอร์ชันปรับปรุงคำอธิบายภาษาไทย)
 */

// เริ่มต้น Session หากยังไม่มีการเปิดใช้งาน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตรวจสอบระยะเวลา Session (ตั้งค่าให้หมดอายุใน 30 นาที)
$timeout_duration = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['error'] = "เซสชันของคุณหมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง";
    header("Location: ../login.php");
    exit();
}
$_SESSION['last_activity'] = time();

/**
 * ฟังก์ชันตรวจสอบสถานะการล็อกอิน
 */
function checkLogin() {
    // ดึงชื่อไฟล์ปัจจุบันเพื่อใช้ตรวจสอบ
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // ตรวจสอบว่ามีการเข้าสู่ระบบหรือยัง
    if (!isset($_SESSION['user_id'])) {
        // ถ้ายังไม่ล็อกอิน และไม่ใช่หน้า login ให้ดีดกลับไปหน้า login
        if ($current_page !== 'login.php') {
            $_SESSION['error'] = "กรุณาเข้าสู่ระบบก่อนใช้งาน";
            header("Location: ../login.php");
            exit();
        }
    }
}

/**
 * ฟังก์ชันตรวจสอบสิทธิ์ผู้ดูแลระบบ (Admin)
 */
function checkAdmin() {
    checkLogin();
    
    // แปลง role ให้เป็นตัวพิมพ์เล็กเพื่อความแม่นยำ
    $role = strtolower($_SESSION['role'] ?? '');
    
    if ($role !== 'admin') {
        $_SESSION['error'] = "ขออภัย! คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (ต้องการสิทธิ์ผู้ดูแลระบบ)";
        
        // ถ้าไม่ใช่ Admin ให้ส่งกลับไปหน้า Sales (หรือหน้าที่เหมาะสม)
        // ตรวจสอบโฟลเดอร์ปัจจุบันเพื่อป้องกันการวนลูป
        if (basename(dirname($_SERVER['PHP_SELF'])) !== 'sales') {
            header("Location: ../sales/dashboard.php");
            exit();
        }
    }
}

/**
 * ฟังก์ชันตรวจสอบสิทธิ์พนักงานขาย (Sales)
 */
function checkSales() {
    checkLogin();
    
    // แปลง role ให้เป็นตัวพิมพ์เล็กเพื่อความแม่นยำ
    $role = strtolower($_SESSION['role'] ?? '');
    
    if ($role !== 'sales') {
        $_SESSION['error'] = "ขออภัย! คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (ต้องการสิทธิ์พนักงานขาย)";
        
        // ถ้าไม่ใช่ Sales ให้ส่งกลับไปหน้า Admin
        if (basename(dirname($_SERVER['PHP_SELF'])) !== 'admin') {
            header("Location: ../admin/dashboard.php");
            exit();
        }
    }
}
?>