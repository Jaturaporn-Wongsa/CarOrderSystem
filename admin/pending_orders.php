<?php
/**
 * admin/pending_orders.php
 * หน้าจัดการรายการจองที่รอการอนุมัติสำหรับ Admin
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/database.php';
require_once '../config/auth.php';

// ตรวจสอบสิทธิ์ Admin 
checkAdmin();

try {
    // แก้ไข Query: ใช้ 'Pending' ให้ตรงกับค่าในฐานข้อมูล ENUM
    $sql = "SELECT o.*, c.model_name 
            FROM orders o 
            JOIN cars c ON o.car_id = c.id 
            WHERE o.status = 'Pending' 
            ORDER BY o.created_at ASC";
    $stmt = $conn->query($sql);
    $pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="d-flex my-3">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4 bg-white shadow-sm rounded-3 ms-3">
        <h3 class="fw-bold text-primary mb-3">📋 รายการสั่งจองที่รอการอนุมัติ</h3>
        <hr>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>รุ่นรถ</th>
                        <th>ลูกค้า</th>
                        <th>วันที่จอง</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pending_orders) > 0): ?>
                        <?php foreach ($pending_orders as $order): ?>
                        <tr>
                            <td>#<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($order['model_name']); ?></td>
                            <td><?= htmlspecialchars($order['customer_name']); ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($order['created_at'])); ?> น.</td>
                            <td class="text-center">
                                <form action="../actions/update_order_status.php" method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?= $order['id']; ?>">
                                    
                                    <button type="submit" name="status" value="Approved" class="btn btn-success btn-sm px-3 rounded-pill">
                                        ✅ อนุมัติ
                                    </button>
                                    
                                    <button type="submit" name="status" value="Rejected" class="btn btn-danger btn-sm px-3 rounded-pill">
                                        ❌ ปฏิเสธ
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                🎉 ไม่มีรายการรออนุมัติในขณะนี้
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>