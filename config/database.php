<?php
/**
 * config/database.php
 * ไฟล์สำหรับเชื่อมต่อฐานข้อมูลด้วย PDO (ฉบับสมบูรณ์)
 */

// ข้อมูลการเชื่อมต่อฐานข้อมูล
$host     = "localhost";
$db_name  = "car_order"; // ตรวจสอบให้แน่ใจว่าชื่อนี้ตรงกับใน phpMyAdmin
$username = "root"; 
$password = ""; // ใส่รหัสผ่านของคุณที่นี่ (ถ้าตั้งไว้)

try {
    // 1. สร้างการเชื่อมต่อฐานข้อมูลด้วย PDO
    // ใช้ charset=utf8mb4 เพื่อรองรับภาษาไทยอย่างสมบูรณ์
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    
    // 2. ตั้งค่าการแสดง Error (ให้ระบบแจ้งเตือนแบบ Exception เมื่อเกิดปัญหา SQL)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 3. ตั้งค่าโหมดการดึงข้อมูลเริ่มต้นเป็นแบบ Associative Array (เข้าถึงข้อมูลด้วยชื่อคอลัมน์)
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 4. ตั้งค่า Timezone ให้เป็นเวลาประเทศไทย (UTC+7)
    $conn->exec("SET time_zone = '+07:00'");

} catch(PDOException $e) {
    // กรณีเชื่อมต่อไม่สำเร็จ ให้หยุดการทำงานและแสดงข้อความผิดพลาด
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}

// หมายเหตุ: ไฟล์นี้พร้อมใช้งานแล้ว คุณสามารถเรียกใช้ $conn ได้ในทุกไฟล์ที่ require ไฟล์นี้เข้ามา
?>