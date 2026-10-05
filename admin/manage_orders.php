<?php
// admin/manage_orders.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
require_once '../config/database.php';
checkAdmin(); // ตรวจสอบสิทธิ์ Admin

$stmt = $conn->query("SELECT o.*, c.model_name FROM orders o JOIN cars c ON o.car_id = c.id ORDER BY o.created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include_once '../includes/header.php';
?>

<div class="container mt-4">
    <h3>จัดการออเดอร์ (สำหรับผู้ดูแลระบบ)</h3>
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ลูกค้า</th><th>รุ่นรถ</th><th>ภาค</th><th>สถานะ</th><th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= htmlspecialchars($o['customer_name']) ?></td>
                <td><?= htmlspecialchars($o['model_name']) ?></td>
                <td><?= htmlspecialchars($o['region']) ?></td>
                <td><?= $o['status'] ?></td>
                <td>
                    <?php if ($o['status'] == 'รอดำเนินการ'): ?>
                        <a href="../actions/update_order_status.php?id=<?= $o['id'] ?>&status=อนุมัติ" class="btn btn-sm btn-success">อนุมัติ</a>
                        <a href="../actions/update_order_status.php?id=<?= $o['id'] ?>&status=ปฏิเสธ" class="btn btn-sm btn-danger">ปฏิเสธ</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div> 