<?php
/**
 * actions/update_sales.php
 */
session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = $_POST['id'];
    $name     = trim($_POST['name']);
    $username = trim($_POST['username']);
    $region   = trim($_POST['region']);

    try {
        $sql = "UPDATE users SET name = ?, username = ?, region = ? WHERE id = ? AND role = 'sales'";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $username, $region, $id]);
        
        $_SESSION['success_msg'] = "✅ อัปเดตข้อมูลพนักงานเรียบร้อยแล้ว";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

header("Location: ../admin/manage_users.php");
exit();
?>