<?php
session_start();
require_once '../config/auth.php';
require_once '../config/database.php';
checkAdmin();

// ดึงข้อมูลที่จำเป็น
$cars = $conn->query("SELECT id, model_name FROM cars")->fetchAll(PDO::FETCH_ASSOC);
$sales = $conn->query("SELECT id, username FROM users WHERE role = 'sales'")->fetchAll(PDO::FETCH_ASSOC);
$regions = ['เหนือ', 'กลาง', 'อีสาน', 'ใต้', 'ตะวันออก', 'ตะวันตก'];

include_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5>จัดการโควตาประจำเดือน</h5>
        </div>
        <div class="card-body">
            <form action="../actions/save_quota.php" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>เลือกรุ่นรถ</label>
                        <select name="car_id" class="form-select" required>
                            <?php foreach ($cars as $car): ?>
                                <option value="<?= $car['id'] ?>"><?= $car['model_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>เลือกภาค</label>
                        <select name="region" class="form-select" required>
                            <?php foreach ($regions as $reg): ?>
                                <option value="<?= $reg ?>"><?= $reg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>เดือน/ปี</label>
                        <input type="month" name="month_year" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>จำนวนโควตา (คัน)</label>
                        <input type="number" name="max_limit" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>เซลล์ผู้ดูแล</label>
                        <select name="sales_id" class="form-select" required>
                            <?php foreach ($sales as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= $s['username'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-100">บันทึกโควตา</button>
            </form>
        </div>
    </div>
</div>
<?php include_once '../includes/footer.php'; ?>