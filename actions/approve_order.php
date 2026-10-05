<?>
<?php
/**
 * actions/approve_order.php
 * ฉบับสมบูรณ์: รองรับการอนุมัติแบบยืดหยุ่น (หากไม่ได้กรอกจำนวน จะอนุมัติเต็มจำนวนเดิมให้อัตโนมัติทันที)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/auth.php';
require_once '../config/database.php';

// ตรวจสอบสิทธิ์ผู้ดูแลระบบ
checkAdmin();

// ตรวจสอบว่าเป็นการส่งข้อมูลแบบ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../admin/dashboard.php");
    exit();
}

$order_id     = $_POST['order_id'] ?? null;
$action       = $_POST['action'] ?? null;
$admin_note   = $_POST['admin_note'] ?? ''; 
$approved_qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT); // จำนวนที่แอดมินกรอกอนุมัติจริง

// ตรวจสอบความถูกต้องของข้อมูลเบื้องต้น
if (!$order_id || !$action) {
    $_SESSION['error'] = "ข้อมูลไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    header("Location: ../admin/dashboard.php");
    exit();
}

$new_status = ($action === 'Approved') ? 'Approved' : 'Rejected';
$display_status = ($action === 'Approved') ? 'อนุมัติ' : 'ปฏิเสธ';

try {
    // 1. ดึงข้อมูลออเดอร์เดิมขึ้นมาตรวจสอบ
    $check_stmt = $conn->prepare("SELECT id, quantity FROM orders WHERE id = :id");
    $check_stmt->execute(['id' => $order_id]);
    $order = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "ไม่พบออเดอร์ ID #{$order_id} ในฐานข้อมูล";
    } 
    else {
        $original_quantity = (int)$order['quantity']; // ยอดที่เซลล์สั่งมาเดิม (เช่น 10 หรือ 20)

        if ($new_status === 'Approved') {
            // จุดที่แก้ไข: หากไม่ได้กรอกจำนวนมา ให้ใช้ยอดที่เซลล์สั่งเป็นค่าเริ่มต้น (ไม่ให้กลายเป็น 0)
            if ($approved_qty === null || $approved_qty === false || $approved_qty === '') {
                $approved_quantity = $original_quantity;
            } else {
                $approved_quantity = $approved_qty;
            }

            // ป้องกันไม่ให้ยอดอนุมัติมากกว่ายอดที่สั่งจริง
            if ($approved_quantity > $original_quantity) {
                $approved_quantity = $original_quantity;
            }

            // คำนวณจำนวนแบ็คออเดอร์อัตโนมัติ (ยอดที่สั่ง - ยอดที่อนุมัติจริง)
            $backorder_quantity = max(0, $original_quantity - $approved_quantity);

            // บันทึกสถานะ, จำนวนอนุมัติ และแบ็คออเดอร์ลงฐานข้อมูล
            $stmt = $conn->prepare("UPDATE orders SET status = :status, approved_quantity = :approved_qty, backorder_quantity = :backorder_qty, admin_note = :admin_note WHERE id = :id");
            $stmt->execute([
                ':status'             => $new_status,
                ':approved_qty'       => $approved_quantity,
                ':backorder_qty'      => $backorder_quantity,
                ':admin_note'         => $admin_note,
                ':id'                 => $order_id
            ]);
        } else {
            // กรณีปฏิเสธ
            $stmt = $conn->prepare("UPDATE orders SET status = :status, approved_quantity = 0, backorder_quantity = 0, admin_note = :admin_note WHERE id = :id");
            $stmt->execute([
                ':status'     => $new_status,
                ':admin_note' => $admin_note,
                ':id'         => $order_id
            ]);
        }

        $_SESSION['success'] = "✅ อัปเดตสถานะออเดอร์ #{$order_id} เป็น '{$display_status}' สำเร็จ";
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage();
}

header("Location: ../admin/dashboard.php");
exit();
?>