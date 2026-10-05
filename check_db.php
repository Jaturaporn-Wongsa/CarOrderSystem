<?php
require_once 'config/database.php'; // ตรวจสอบว่า path ถูกต้องนะครับ

try {
    echo "<h1>ตรวจสอบโครงสร้างฐานข้อมูล</h1>";
    
    $tables = ['users', 'orders'];
    foreach ($tables as $t) {
        echo "<h3>ตาราง: $t</h3><ul>";
        $stmt = $conn->query("DESCRIBE $t");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "<li>" . $col['Field'] . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>