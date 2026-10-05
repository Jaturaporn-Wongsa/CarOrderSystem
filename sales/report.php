<?php
/**
 * admin/reports.php
 * หน้าสรุปรายงานยอดขายแยกตามพนักงาน
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/auth.php';
checkAdmin();
require_once '../config/database.php';
require_once '../config/config.php';

try {
    // นำ SQL ของคุณมาใส่ที่นี่
    $sql = "SELECT u.name, COUNT(o.id) as total_sales, SUM(c.price) as total_revenue
            FROM users u
            LEFT JOIN orders o ON u.id = o.sales_id
            JOIN cars c ON o.car_id = c.id
            WHERE o.status = 'Approved'
            GROUP BY u.id
            ORDER BY total_sales DESC";
            
    $stmt = $conn->query($sql);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="d-flex my-3">
    <?php include_once '../includes/sidebar.php'; ?>
    <div class="flex-grow-1 p-4 bg-white shadow-sm rounded-3 ms-3">
        <h3>📊 รายงานยอดขายแยกตามพนักงาน</h3>
        <table class="table table-bordered mt-4">
            <thead class="table-dark">
                <tr>
                    <th>ชื่อพนักงานขาย</th>
                    <th>จำนวนที่ขายได้ (คัน)</th>
                    <th>ยอดขายรวม (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']); ?></td>
                    <td><?= htmlspecialchars($row['total_sales']); ?></td>
                    <td><?= number_format($row['total_revenue'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>