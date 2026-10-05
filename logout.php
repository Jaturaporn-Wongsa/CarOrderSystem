<?php
// logout.php (ระบบออกจากระบบส่วนกลาง)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ล้างค่าตัวแปรเซสชันทั้งหมด
$_SESSION = array();

// ทำลายเซสชันที่ฝั่งเซิร์ฟเวอร์
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// เริ่มเซสชันใหม่ชั่วคราวเพื่อส่ง Flash Message แจ้งเตือนภาษาไทย
session_start();
$_SESSION['success'] = "🔒 คุณได้ออกจากระบบเรียบร้อยแล้ว พบกันใหม่คราวหน้าครับ";

// ส่งกลับไปยังหน้า Login
header("Location: login.php");
exit();
?>