<?php
// actions/login_action.php

// 1. ป้องกันเบราว์เซอร์ (โดยเฉพาะมือถือ) จำ Cache การเปลี่ยนหน้า (Redirect)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require_once '../config/database.php';

// รับเฉพาะคำขอแบบ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

// รับค่าและตัดช่องว่างหน้า-หลัง
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// 2. ตรวจสอบว่ากรอกข้อมูลครบถ้วนหรือไม่
if (empty($username) || empty($password)) {
    $_SESSION['error'] = "กรุณากรอกชื่อผู้ใช้งานและรหัสผ่านให้ครบถ้วน";
    header("Location: ../login.php");
    exit();
}

try {
    // 3. ค้นหาข้อมูลผู้ใช้งานในฐานข้อมูล
    $stmt = $conn->prepare("SELECT id, username, password, name, role FROM users WHERE username = :username LIMIT 1");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. ตรวจสอบผู้ใช้งานและรหัสผ่าน
    if ($user && password_verify($password, $user['password'])) {
        
        // ล้างค่า Session เก่าออกทั้งหมด ป้องกัน Session Admin ค้างในเครื่องเดิม
        session_unset();
        
        // สร้าง Session ID ใหม่เพื่อความปลอดภัย (Prevent Session Fixation)
        session_regenerate_id(true);

        // บันทึกข้อมูลผู้ใช้งานลง Session
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['sales_name'] = $user['name']; 
        $_SESSION['role']       = strtolower(trim($user['role'])); // แปลงเป็นตัวพิมพ์เล็กเสมอเพื่อความแม่นยำ

        // 5. แยกเส้นทางเข้าหน้าหลักตามสิทธิ์การใช้งาน (Role)
        switch ($_SESSION['role']) {
            case 'admin':
                header("Location: ../admin/dashboard.php");
                break;
            case 'sales':
                header("Location: ../sales/dashboard.php");
                break;
            default:
                $_SESSION['error'] = "สิทธิ์การใช้งานไม่ถูกต้อง กรุณาติดต่อผู้ดูแลระบบ";
                header("Location: ../login.php");
                break;
        }
        exit();

    } else {
        // กรณีไม่พบผู้ใช้งาน หรือรหัสผ่านไม่ถูกต้อง (ใช้ข้อความเดียวกันเพื่อความปลอดภัย)
        $_SESSION['error'] = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
        header("Location: ../login.php");
        exit();
    }

} catch (PDOException $e) {
    // บันทึก Log ข้อผิดพลาดจริงฝั่ง Server (ไม่แสดง Error ของระบบให้ User เห็น)
    error_log("Login Error: " . $e->getMessage());
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการเชื่อมต่อระบบ กรุณาลองใหม่อีกครั้ง";
    header("Location: ../login.php");
    exit();
}