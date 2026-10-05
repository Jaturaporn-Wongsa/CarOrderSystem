<?php
/**
 * admin/manage_cars.php - จัดการสต็อกรถยนต์ (ภาษาไทย)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
checkAdmin();
require_once '../config/database.php';

// ดึงข้อมูลรถทั้งหมด (เรียงตามลำดับล่าสุด)
$sql = "SELECT * FROM cars ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="d-flex my-3">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4 bg-light ms-3">
        
        <!-- หัวข้อ -->
        <h3 class="fw-bold text-primary mb-4"><i class="bi bi-car-front-fill"></i> จัดการสต็อกรถยนต์</h3>

        <!-- แสดงผลข้อความแจ้งเตือน (กรณีบันทึกสำเร็จ/ผิดพลาด) -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ฟอร์มเพิ่มข้อมูล -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold py-3">
                <i class="bi bi-plus-circle"></i> เพิ่มรถใหม่เข้าสต็อก
            </div>
            <div class="card-body">
                <form action="../actions/add_car.php" method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">ยี่ห้อ</label>
                        <input type="text" name="brand" class="form-control" placeholder="เช่น Lambretta" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">รุ่นรถ</label>
                        <input type="text" name="model_name" class="form-control" placeholder="เช่น X300 GP" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">สีรถ</label>
                        <input type="text" name="color_name" class="form-control" placeholder="เช่น ขาว, ดำ, เขียว" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-save"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ตารางแสดงผล -->
        <div class="table-responsive bg-white shadow-sm rounded p-3">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 80px;">ลำดับ</th>
                        <th>ยี่ห้อ</th>
                        <th>รุ่นรถ</th>
                        <th>สีรถ</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($cars) > 0): ?>
                        <?php $no = 1; foreach ($cars as $car): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= htmlspecialchars($car['brand'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($car['model_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($car['color_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <a href="edit_car.php?id=<?= $car['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil-square"></i></a>
                                <a href="../actions/delete_car.php?id=<?= $car['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันการลบรถคันนี้ออกจากสต็อก?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">ไม่พบข้อมูลรถในระบบ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>