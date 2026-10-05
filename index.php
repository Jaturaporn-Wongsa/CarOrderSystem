<?php
// index.php (หน้าแรกสุดส่วนกลางของระบบ)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่าผู้ใช้งานเข้าสู่ระบบไว้แล้วหรือไม่
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // ถ้าเป็นผู้ดูแลระบบ (Admin) ให้ส่งไปหน้าแดชบอร์ดแอดมิน
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
        exit();
    } 
    // ถ้าเป็นพนักงานขาย (Sales) ให้ส่งไปหน้าแดชบอร์ดเซลล์
    elseif ($_SESSION['role'] === 'sales') {
        header("Location: sales/dashboard.php");
        exit();
    }
}

// หากยังไม่ได้เข้าสู่ระบบ ให้เปลี่ยนเส้นทางไปยังหน้า Login อัตโนมัติ
header("Location: login.php");
exit();
?>