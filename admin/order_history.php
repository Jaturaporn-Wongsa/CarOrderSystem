<?php
/**
 * admin/order_history.php
 * หน้าแสดงประวัติการทำรายการออเดอร์ทั้งหมด
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/auth.php';
require_once '../config/database.php';

checkAdmin();

// ดึงข้อมูลออเดอร์ที่ 'Approved' หรือ 'Rejected' แล้ว
$sql = "SELECT o.*, c.model_name, u.name as sales_name 
        FROM orders o 
        JOIN cars c ON o.car_id = c.id 
        LEFT JOIN users u ON o.sales_id = u.id 
        WHERE o.status IN ('Approved', 'Rejected') 
        ORDER BY o.updated_at DESC";
$history = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="d-flex my-3">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4 bg-light shadow-sm rounded-3 ms-3">
        <h3 class="fw-bold mb-4"><i class="bi bi-clock-history"></i> ประวัติการดำเนินการออเดอร์</h3>
        
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">ออเดอร์</th><th>รุ่นรถ</th><th>ลูกค้า</th><th>สถานะ</th><th>วันที่ดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($history)): foreach ($history as $row): ?>
                            <tr>
                                <td class="ps-4">#<?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['model_name']) ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td>
                                    <span class="badge <?= ($row['status'] == 'Approved') ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $row['status'] == 'Approved' ? 'อนุมัติแล้ว' : 'ปฏิเสธแล้ว' ?>
                                    </span>
                                </td>
                                <td><?= $row['updated_at'] ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="text-center py-4">ยังไม่มีประวัติการดำเนินการ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>