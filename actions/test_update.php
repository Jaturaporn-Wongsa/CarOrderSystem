<?php
// ไฟล์ test_update.php
// ใช้เพื่อทดสอบว่าฐานข้อมูลยอมรับการ Update หรือไม่
require_once '../config/database.php';

try {
    // ระบุ ID ที่มีอยู่ในตารางของคุณ (เช็คเลข ID ใน phpMyAdmin)
    $target_id = 1; 

    // ทดสอบ Update ด้วย SQL ตรงๆ
    $sql = "UPDATE orders SET status = 'อนุมัติ' WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['id' => $target_id]);
    
    // เช็คจำนวนแถวที่ถูกแก้ไข
    $count = $stmt->rowCount();
    
    echo "<h1>ผลการทดสอบการอัปเดต</h1>";
    if ($count > 0) {
        echo "<h2 style='color:green;'>สำเร็จ! ฐานข้อมูลยอมให้แก้ไขข้อมูลได้</h2>";
    } else {
        echo "<h2 style='color:red;'>ล้มเหลว! ฐานข้อมูลไม่ยอมให้แก้ไข (rowCount เป็น 0)</h2>";
        
        // เช็ค Error จาก Driver
        $error = $stmt->errorInfo();
        echo "<pre>Error Info: " . print_r($error, true) . "</pre>";
        echo "<p>หมายเหตุ: ถ้า Error เป็น [0, , ] แสดงว่า SQL ทำงานปกติแต่หาแถวไม่เจอ หรือข้อมูลซ้ำเดิม</p>";
    }

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</h2>";
}
?>