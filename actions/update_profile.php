<?php
// actions/update_profile.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/auth.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $name = trim($_POST['name']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($user_id > 0 && !empty($name)) {
        try {
            // 1. ตรวจสอบก่อนว่ามีการกรอกรหัสผ่านใหม่เข้ามาด้วยหรือไม่
            if (!empty($new_password)) {
                // ตรวจสอบความตรงกันของรหัสผ่านทั้งสองช่อง
                if ($new_password !== $confirm_password) {
                    $_SESSION['error'] = "❌ รหัสผ่านใหม่และรหัสผ่านยืนยันไม่ตรงกัน กรุณาลองใหม่อีกครั้ง";
                    header("Location: " . $_SERVER['HTTP_REFERER']);
                    exit();
                }
                
                // เข้ารหัสรหัสผ่านใหม่เพื่อความปลอดภัยระดับสากล
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                // อัปเดตทั้งชื่อและรหัสผ่านใหม่
                $sql = "UPDATE users SET name = :name, password = :password WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':password' => $hashed_password,
                    ':id' => $user_id
                ]);
            } else {
                // อัปเดตเฉพาะชื่ออย่างเดียว (ในกรณีที่ปล่อยช่องรหัสผ่านว่างไว้)
                $sql = "UPDATE users SET name = :name WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':id' => $user_id
                ]);
            }

            // อัปเดตค่าชื่อในเซสชันปัจจุบันทันที เพื่อให้แสดงผลบน Navbar ได้ถูกต้อง
            $_SESSION['sales_name'] = $name;

            $_SESSION['success'] = "✨ อัปเดตข้อมูลส่วนตัวและรหัสผ่านของคุณเรียบร้อยแล้ว";

        } catch (PDOException $e) {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "❌ ข้อมูลไม่ถูกต้อง กรุณากรอกชื่อ-นามสกุลให้ครบถ้วน";
    }
}

// ส่งผู้ใช้งานกลับไปยังหน้าประวัติส่วนตัวหน้าเดิม (รองรับทั้ง Admin และ Sales)
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();