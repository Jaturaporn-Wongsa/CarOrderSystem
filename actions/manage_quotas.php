<?php
/**
 * admin/manage_quotas.php - หน้าจัดการคลังรถและโควต้า (ฉบับสมบูรณ์)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
checkAdmin();
require_once '../config/database.php';

try {
    // 1. ดึงข้อมูลรถทั้งหมด (เพื่อไปใส่ใน Dropdown) - ปรับชื่อฟิลด์สีตามโครงสร้างจริง
    $stmt_cars = $conn->prepare("SELECT id, brand, model_name, color FROM cars ORDER BY brand ASC, model_name ASC");
    $stmt_cars->execute();
    $cars = $stmt_cars->fetchAll(PDO::FETCH_ASSOC);

    // 2. ดึงข้อมูลโควต้าทั้งหมด + ข้อมูลรถ (ใช้ JOIN เพื่อเอาชื่อรถและสีมาแสดง)
    $sql_quotas = "SELECT q.*, c.brand, c.model_name, c.color 
                   FROM quotas q 
                   JOIN cars c ON q.car_id = c.id 
                   ORDER BY q.id DESC";
    $stmt_quotas = $conn->prepare($sql_quotas);
    $stmt_quotas->execute();
    $quotas = $stmt_quotas->fetchAll(PDO::FETCH_ASSOC); 

} catch (PDOException $e) {
    die("<div class='alert alert-danger m-4'>เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage() . "</div>");
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="d-flex my-3">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4 bg-light ms-3 rounded-3 shadow-sm">
        <h3 class="fw-bold text-primary mb-4"><i class="bi bi-car-front-fill"></i> จัดการคลังรถและโควต้า</h3>

        <!-- ส่วนแสดงแจ้งเตือน -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- ฟอร์มเพิ่มโควต้า -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold py-3"><i class="bi bi-plus-circle"></i> เพิ่มข้อมูลรถและกำหนดโควต้า</div>
            <div class="card-body">
                <form action="../actions/add_quota.php" method="POST" class="row g-3">
                    <!-- Dropdown ดึงข้อมูลจากตาราง cars -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">เลือกรถ (ยี่ห้อ/รุ่น/สี)</label>
                        <select name="car_id" class="form-select" required>
                            <option value="">--- เลือกรถ ---</option>
                            <?php foreach ($cars as $car): ?>
                                <option value="<?= $car['id'] ?>">
                                    <?= htmlspecialchars($car['brand']) ?> - <?= htmlspecialchars($car['model_name']) ?> (<?= htmlspecialchars($car['color'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold">ชื่อเซลล์</label>
                        <input type="text" name="sales_person" class="form-control" placeholder="ระบุชื่อเซลล์" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">ภาค</label>
                        <select name="region" class="form-select" required>
                            <option value="">--- เลือกภาค ---</option>
                            <option value="เหนือ">เหนือ</option>
                            <option value="กลาง">กลาง</option>
                            <option value="อีสาน">อีสาน</option>
                            <option value="ใต้">ใต้</option>
                            <option value="ตะวันตก">ตะวันตก</option>
                            <option value="ตะวันออก">ตะวันออก</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">โควต้า (จำนวนคัน)</label>
                        <input type="number" name="quota_amount" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">เดือน</label>
                        <select name="month" class="form-select" required>
                            <?php foreach (['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'] as $m): ?>
                                <option value="<?= $m ?>" <?= ($m == date('F') ? '' : '') ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">สถานะ</label>
                        <select name="status" class="form-select">
                            <option value="Pending">รอตรวจสอบ</option>
                            <option value="Approved">อนุมัติแล้ว</option>
                        </select>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-success px-4"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ตารางแสดงรายการ -->
        <div class="table-responsive bg-white shadow-sm rounded-3 p-3 border">
            <h5 class="fw-bold mb-3 text-secondary"><i class="bi bi-list-ul"></i> รายการโควต้าปัจจุบัน</h5>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center ps-4">ลำดับ</th>
                        <th>ยี่ห้อ/รุ่น/สี</th>
                        <th>เซลล์</th>
                        <th>ภาค</th>
                        <th>เดือน</th>
                        <th class="text-center">โควต้า</th>
                        <th>สถานะ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($quotas) > 0): ?>
                        <?php $no = 1; foreach ($quotas as $q): ?>
                        <tr>
                            <td class="text-center ps-4 fw-bold text-muted"><?= $no++ ?></td>
                            <td>
                                <b><?= htmlspecialchars($q['brand']) ?></b> <?= htmlspecialchars($q['model_name']) ?><br>
                                <small class="text-muted">สี: <?= htmlspecialchars($q['color'] ?? '-') ?></small>
                            </td>
                            <td><?= htmlspecialchars($q['sales_person'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($q['region'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($q['month'] ?? '-') ?></td>
                            <td class="text-center fw-bold"><?= number_format($q['quota_amount'] ?? 0) ?> คัน</td>
                            <td>
                                <?php 
                                    $status = $q['status'] ?? 'Pending';
                                    if ($status == 'Approved' || $status == 'อนุมัติแล้ว') {
                                        echo '<span class="badge bg-success">อนุมัติแล้ว</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">รอตรวจสอบ</span>';
                                    }
                                ?>
                            </td>
                            <td class="text-center">
                                <a href="../actions/delete_quota.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('คุณต้องการลบข้อมูลโควต้านี้ใช่หรือไม่?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">ยังไม่มีข้อมูลโควต้าในระบบ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>