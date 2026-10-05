<?php
/**
 * admin/view_region.php 
 * แสดงข้อมูลพนักงานขายและรายการจองในภาค (แก้ไขการแสดงผลสถานะ)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/auth.php';
require_once '../config/database.php';

checkAdmin();

$region = $_GET['region'] ?? '';
if (empty($region)) { 
    header("Location: dashboard.php"); 
    exit();  
}

try {
    // 1. ดึงชื่อพนักงานขาย
    $stmt_sales = $conn->prepare("SELECT name FROM users WHERE region LIKE :region AND role = 'Sales'");
    $stmt_sales->execute([':region' => "%$region%"]);
    $sales_list = $stmt_sales->fetchAll(PDO::FETCH_ASSOC);

    // 2. ดึงรายการออเดอร์
    $stmt_orders = $conn->prepare("
        SELECT o.*, c.model_name 
        FROM orders o
        JOIN cars c ON o.car_id = c.id
        JOIN car_quotas q ON c.id = q.car_id
        WHERE q.region LIKE :region
        ORDER BY o.created_at DESC
    ");
    $stmt_orders->execute([':region' => "%$region%"]);
    $order_list = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>📋 ข้อมูลการดูแล: <span class="text-danger"><?= htmlspecialchars($region) ?></span></h3>
        <a href="dashboard.php" class="btn btn-outline-secondary">⬅️ กลับหน้าแดชบอร์ด</a>
    </div>
    
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">👥 พนักงานขาย</div>
                <div class="card-body">
                    <?php if (!empty($sales_list)): ?>
                        <ul class="list-unstyled">
                            <?php foreach($sales_list as $s): ?>
                                <li class="mb-2"><i class="bi bi-person-fill text-primary"></i> <?= htmlspecialchars($s['name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted small">ไม่พบข้อมูลเซลล์</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-9 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">📋 รายการสั่งจองทั้งหมดในภาค</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ลำดับ</th>
                                <th>ร้านค้า</th>
                                <th>รุ่นรถ</th>
                                <th>จำนวน</th>
                                <th>วันที่จอง</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($order_list)): 
                                $i = 1; 
                                foreach($order_list as $o): 
                                    // ดึงค่าจาก Database (จะเป็น Approved, Rejected, หรือ Pending)
                                    $raw_status = $o['status'] ?? 'Pending';
                                    
                                    // กำหนดค่าการแสดงผล
                                    if ($raw_status == 'Approved') {
                                        $status_text = '✅ อนุมัติ';
                                        $badge = 'bg-success text-white';
                                    } elseif ($raw_status == 'Rejected') {
                                        $status_text = '❌ ปฏิเสธ';
                                        $badge = 'bg-danger text-white';
                                    } else {
                                        $status_text = '⏳ รอดำเนินการ';
                                        $badge = 'bg-warning text-dark';
                                    }

                                    $shop_name = $o['shop_name'] ?? ($o['customer_name'] ?? '-');
                            ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($shop_name) ?></td>
                                <td><?= htmlspecialchars($o['model_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($o['quantity'] ?? '1') ?> คัน</td>
                                <td><?= date("d/m/y H:i", strtotime($o['created_at'])) ?></td>
                                <td>
                                    <span class="badge <?= $badge ?>"><?= $status_text ?></span>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted">ยังไม่มีข้อมูลการจองในภาคนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>