<?php
/**
 * admin/export_preview.php - หน้าแสดงตัวอย่างข้อมูลก่อน Export Excel
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/config.php';

checkAdmin();

try {
    // ดึงข้อมูลและจัดกลุ่มตามรุ่นรถ (เรียงตามรุ่น และ ID จากมากไปน้อย หรือตามต้องการ)
    $sql = "SELECT o.*, u.name as sales_name, c.model_name 
            FROM orders o 
            LEFT JOIN users u ON o.sales_id = u.id 
            LEFT JOIN cars c ON o.car_id = c.id 
            ORDER BY c.model_name ASC, o.id DESC";
            
    $stmt = $conn->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($orders as $row) {
        $model = $row['model_name'] ?? 'ไม่ระบุรุ่น';
        $grouped[$model][] = $row;
    }
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

// อาเรย์เดือนภาษาไทย สำหรับแปลงค่าเดือน (ถ้าเดือนเก็บเป็นตัวเลข 01-12)
$thai_months = [
    '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
    '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
    '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
    '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
];

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="d-flex my-3">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4 bg-white shadow-sm rounded-3 ms-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1 text-primary fw-bold">📊 ตัวอย่างรายงานสรุปคำสั่งซื้อรถจักรยานยนต์</h3>
                <p class="text-muted mb-0">ตรวจสอบความถูกต้องของข้อมูลด้านล่างนี้ ก่อนกดปุ่มดาวน์โหลดไฟล์ Excel จริง</p>
            </div>
            
            <!-- กลุ่มปุ่มจัดการ -->
            <div>
                <a href="orders.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> กลับหน้าจัดการ
                </a>
                <!-- ปุ่มยืนยันดาวน์โหลดจริง จะลิงก์ไปที่ไฟล์ action สำหรับดาวน์โหลด Excel -->
                <a href="../actions/export_excel_process.php" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> ยืนยันดาวน์โหลด Excel
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message); ?></div>
        <?php elseif (empty($grouped)): ?>
            <div class="alert alert-warning text-center py-4">ไม่พบข้อมูลสำหรับ Export</div>
        <?php else: ?>
            <?php foreach ($grouped as $model => $rows): ?>
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">🚗 รุ่นรถ: <?php echo htmlspecialchars($model); ?></h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0 align-middle">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>ภาค</th>
                                        <th>เดือน</th>
                                        <th>ร้านค้า</th>
                                        <th>จำนวน</th>
                                        <th>จำนวนสั่ง (อนุมัติ)</th>
                                        <th>แบ็คออเดอร์</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $i = 1;
                                    foreach ($rows as $r): 
                                        // แปลงเดือน (รองรับกรณีเก็บมาเป็น 01-12 หรือเป็นชื่อเดือนอยู่แล้ว)
                                        $m_val = $r['month'] ?? '';
                                        $display_month = $thai_months[$m_val] ?? ($m_val !== '' ? $m_val : 'มกราคม');
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $i; ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($r['region'] ?? 'กลาง'); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($display_month); ?></td>
                                        <td><?php echo htmlspecialchars($r['shop_name'] ?? '-'); ?></td>
                                        <td class="text-center"><?php echo $r['quantity']; ?> คัน</td>
                                        <td class="text-center"><?php echo $r['approved_quantity']; ?></td>
                                        <td class="text-center"><?php echo $r['backorder_quantity']; ?></td>
                                    </tr>
                                    <?php 
                                    $i++;
                                    endforeach; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>