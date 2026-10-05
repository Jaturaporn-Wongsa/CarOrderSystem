<?>
<?php
/**
 * actions/update_master_car.php - อัปเดตข้อมูลรถและสีรถในระบบ Master
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
require_once '../config/database.php';

checkAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $brand      = trim($_POST['brand'] ?? '');
    $model_name = trim($_POST['model_name'] ?? '');
    $color_name = trim($_POST['color_name'] ?? '');

    if (!$id || empty($brand) || empty($model_name)) {
        $_SESSION['error'] = "❌ กรุณากรอกข้อมูลยี่ห้อและรุ่นรถให้ครบถ้วน";
        header("Location: ../admin/add_master_car.php");
        exit();
    }

    try {
        $conn->beginTransaction();

        // 1. อัปเดตข้อมูลหลักในตาราง cars (ยี่ห้อและรุ่น)
        $sql_car = "UPDATE cars SET brand = ?, model_name = ? WHERE id = ?";
        $stmt_car = $conn->prepare($sql_car);
        $stmt_car->execute([$brand, $model_name, $id]);

        // 2. ลบสีเดิมทั้งหมดของรถคันนี้ออกก่อน
        $sql_delete_colors = "DELETE FROM car_colors WHERE car_id = ?";
        $stmt_del = $conn->prepare($sql_delete_colors);
        $stmt_del->execute([$id]);

        // 3. เพิ่มสีใหม่ที่กรอกเข้ามา (แยกด้วยเครื่องหมายจุลภาค ,)
        if (!empty($color_name)) {
            $colors = explode(',', $color_name);
            $sql_color = "INSERT INTO car_colors (car_id, color_name) VALUES (?, ?)";
            $stmt_stmt_color = $conn->prepare($sql_color);
            
            foreach ($colors as $color) {
                $c = trim($color);
                if (!empty($c)) {
                    $stmt_stmt_color->execute([$id, $c]);
                }
            }
        }

        $conn->commit();
        $_SESSION['success'] = "✅ อัปเดตข้อมูลรถเรียบร้อยแล้ว";

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

header("Location: ../admin/add_master_car.php");
exit();
?>