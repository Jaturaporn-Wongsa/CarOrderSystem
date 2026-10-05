<?php
/**
 * actions/add_user.php
 * ไฟล์ประมวลผลการเพิ่มบัญชีพนักงานใหม่ (ฉบับสมบูรณ์รองรับตาราง region_sales_assignment)
 */
session_start();
require_once '../config/auth.php';
require_once '../config/database.php';

// 1. ตรวจสอบสิทธิ์ Admin
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $region   = trim($_POST['region'] ?? ''); // รับค่าภาคที่เลือกจากฟอร์ม
    $role     = 'sales'; // กำหนดให้เป็น sales เสมอ 

    // 2. ตรวจสอบความถูกต้องของข้อมูล (เพิ่มการเช็ค region ด้วย)
    if (empty($name) || empty($username) || empty($password) || empty($region)) {
        $_SESSION['error'] = "กรุณากรอกข้อมูลและเลือกภาคให้ครบถ้วน";
        header("Location: ../admin/users.php");
        exit();
    }

    try {
        // 3. ตรวจสอบว่า Username ซ้ำหรือไม่
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        
        if ($check->fetch()) {
            $_SESSION['error'] = "Username นี้มีผู้ใช้งานแล้ว กรุณาใช้ชื่ออื่น";
        } else {
            // 4. เข้ารหัสรหัสผ่าน (Password Hashing)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // เริ่ม Transaction (เพื่อให้มั่นใจว่าข้อมูลเข้าทั้ง 2 ตารางพร้อมกัน)
            $conn->beginTransaction();

            // 5. บันทึกลงตาราง users (รวมถึงฟิลด์ region)
            $sql = "INSERT INTO users (name, username, password, role, region) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$name, $username, $hashed_password, $role, $region]);

            // ดึง ID ของพนักงานที่เพิ่งถูกเพิ่มเข้าไปล่าสุด
            $user_id = $conn->lastInsertId();

            // 6. บันทึกลงตาราง region_sales_assignment (ประวัติการจับคู่ภาคกับพนักงาน)
            $assigned_month = date('Y-m'); // ดึงเดือนปัจจุบัน เช่น '2026-07'
            $sql_assignment = "INSERT INTO region_sales_assignment (region, user_id, assigned_month) VALUES (?, ?, ?)";
            $stmt_assignment = $conn->prepare($sql_assignment);
            $stmt_assignment->execute([$region, $user_id, $assigned_month]);

            // ยืนยันการบันทึกข้อมูลทั้งหมด
            $conn->commit();

            $_SESSION['success'] = "เพิ่มบัญชีพนักงาน '$name' เรียบร้อยแล้ว!";
        }
    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกการบันทึกทั้งหมด (Rollback)
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }
}

header("Location: ../admin/users.php");
exit();