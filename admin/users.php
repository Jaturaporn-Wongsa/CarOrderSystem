<?php
/**
 * admin/users.php
 * หน้าจัดการบัญชีพนักงานขาย (Sales Management)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/config.php';

// ตรวจสอบสิทธิ์เฉพาะแอดมิน
checkAdmin();

try {
    $stmt = $conn->query("SELECT id, username, name, role FROM users WHERE role = 'sales' ORDER BY name ASC");
    $sales_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "ไม่สามารถโหลดข้อมูลพนักงานได้";
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="d-flex my-3">
    <?php include_once '../includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4 bg-white shadow-sm rounded-3 ms-3">
        <div class="mb-4">
            <h3 class="mb-1 text-primary fw-bold">👥 จัดการบัญชีพนักงานขาย</h3>
            <p class="text-muted mb-0">ระบบลงทะเบียนและจัดการรายชื่อพนักงานในโชว์รูม</p>
        </div>

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

        <div class="row">
            <div class="col-xl-4 col-lg-5 mb-4">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="card-title mb-0 fw-bold">➕ เพิ่มบัญชีพนักงานใหม่</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="../actions/add_user.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อ-นามสกุล</label>
                                <input type="text" name="name" class="form-control" placeholder="ระบุชื่อพนักงาน" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">ชื่อผู้ใช้งาน (Username)</label>
                                <input type="text" name="username" class="form-control" placeholder="สำหรับเข้าสู่ระบบ" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">รหัสผ่าน</label>
                                <input type="password" name="password" class="form-control" minlength="6" placeholder="รหัสผ่าน (อย่างน้อย 6 ตัว)" required>
                            </div>
                            <input type="hidden" name="role" value="sales">
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-success rounded-pill py-2">💾 บันทึกข้อมูลพนักงาน</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-lg-7">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-secondary text-white py-3">
                        <h5 class="card-title mb-0 fw-bold">📋 รายชื่อพนักงานขายในระบบ</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ลำดับ</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>Username</th>
                                    <th class="text-center">ตำแหน่ง</th>
                                    <th class="text-center">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sales_users)): foreach ($sales_users as $index => $user): ?>
                                    <tr>
                                        <td class="ps-4"><?= $index + 1; ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($user['name']); ?></td>
                                        <td><code class="text-secondary"><?= htmlspecialchars($user['username']); ?></code></td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark rounded-pill px-3">พนักงานขาย</span>
                                        </td>
                                        <td class="text-center">
                                            <a href="../actions/delete_user.php?id=<?= $user['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger rounded-pill px-3" 
                                               onclick="return confirm('ยืนยันการลบบัญชีนี้? ข้อมูลจะไม่สามารถกู้คืนได้')">
                                               🗑️ ลบ
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">❌ ยังไม่มีข้อมูลพนักงานขายในระบบ</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>