<?php
/**
 * admin/edit_user.php - หน้าแก้ไขข้อมูลผู้ใช้งาน (ฉบับสมบูรณ์รองรับตาราง region_sales_assignment)
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../config/auth.php';
require_once '../config/database.php';
checkAdmin();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    die("<div class='alert alert-danger m-5'>❌ ไม่พบรหัสพนักงานที่ระบุ</div>");
}

// เปลี่ยนชื่อตัวแปรเป็น $edit_target เพื่อป้องกันการทับซ้อนกับตัวแปรระบบ
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$edit_target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$edit_target) {
    die("<div class='alert alert-danger m-5'>❌ ไม่พบข้อมูลพนักงานในระบบ</div>");
}

// จัดการการบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $name   = trim($_POST['name'] ?? '');
    $role   = trim($_POST['role'] ?? '');
    $region = trim($_POST['region'] ?? '');

    try {
        // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูลทั้ง 2 ตาราง
        $conn->beginTransaction();

        // 1. อัปเดตข้อมูลในตาราง users
        $stmt = $conn->prepare("UPDATE users SET name = ?, role = ?, region = ? WHERE id = ?");
        $stmt->execute([$name, $role, $region, $user_id]);

        // 2. บันทึกหรืออัปเดตประวัติลงตาราง region_sales_assignment สำหรับเดือนปัจจุบัน
        $assigned_month = date('Y-m'); // เช่น '2026-07'

        // เช็คก่อนว่ามีประวัติของ user คนนี้ในเดือนนี้หรือยัง
        $check_assign = $conn->prepare("SELECT id FROM region_sales_assignment WHERE user_id = ? AND assigned_month = ?");
        $check_assign->execute([$user_id, $assigned_month]);
        
        if ($check_assign->fetch()) {
            // ถ้ามีแล้วให้อัปเดตภาคใหม่ลงไป
            $update_assign = $conn->prepare("UPDATE region_sales_assignment SET region = ? WHERE user_id = ? AND assigned_month = ?");
            $update_assign->execute([$region, $user_id, $assigned_month]);
        } else {
            // ถ้ายังไม่มีให้ INSERT ใหม่
            $insert_assign = $conn->prepare("INSERT INTO region_sales_assignment (region, user_id, assigned_month) VALUES (?, ?, ?)");
            $insert_assign->execute([$region, $user_id, $assigned_month]);
        }

        // ยืนยันการทำงานทั้งหมด
        $conn->commit();

        $success = "✅ บันทึกข้อมูลและอัปเดตพื้นที่เรียบร้อยแล้ว";
        
        // อัปเดตข้อมูลในหน้าจอหลังบันทึก
        $edit_target['name'] = $name;
        $edit_target['role'] = $role;
        $edit_target['region'] = $region;

    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาด ให้ยกเลิกการเปลี่ยนแปลงทั้งหมด
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error_msg = "❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="d-flex my-3">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4 bg-light shadow-sm rounded-4 ms-3">
        <div class="card p-4 border-0 shadow-sm rounded-4">
            <h4 class="fw-bold mb-4">แก้ไขข้อมูล: <?php echo htmlspecialchars($edit_target['name'] ?? 'ผู้ใช้งาน'); ?></h4>
            
            <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
            <?php if (isset($error_msg)) echo "<div class='alert alert-danger'>$error_msg</div>"; ?>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">รหัสผู้ใช้งาน (ID)</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($edit_target['id'] ?? ''); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ชื่อผู้ใช้งาน</label>
                        <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($edit_target['username'] ?? ''); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_target['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">เขตพื้นที่ (ภาค)</label>
                        <input type="text" name="region" class="form-control" value="<?php echo htmlspecialchars($edit_target['region'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">ตำแหน่ง / สิทธิ์</label>
                        <select name="role" class="form-select" required>
                            <option value="admin" <?php echo ($edit_target['role'] === 'admin') ? 'selected' : ''; ?>>ผู้ดูแลระบบ (Admin)</option>
                            <option value="sales" <?php echo ($edit_target['role'] === 'sales') ? 'selected' : ''; ?>>พนักงานขาย (Sales)</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary px-4">บันทึกข้อมูล</button>
                    <a href="manage_users.php" class="btn btn-secondary px-4">ย้อนกลับ</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>