<?php
// actions/toggle_car_status.php
session_start();
require_once '../config/auth.php';
checkAdmin();
require_once '../config/database.php';

if (isset($_GET['id']) && isset($_GET['status'])) {
    $car_id = intval($_GET['id']);
    $current_status = trim($_GET['status']);
    
    // กำหนดสถานะใหม่ที่จะสลับเปลี่ยน
    $new_status = ($current_status === 'available') ? 'out_of_stock' : 'available';

    try {
        $stmt = $conn->prepare("UPDATE cars SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $new_status,
            ':id' => $car_id
        ]);
        
        $_SESSION['success'] = "🔄 อัปเดตสถานะการวางจำหน่ายของรถยนต์เรียบร้อยแล้ว";
    } catch (PDOException $e) {
        $_SESSION['error'] = "ไม่สามารถเปลี่ยนสถานะได้: " . $e->getMessage();
    }
}

header("Location: ../admin/cars.php");
exit();