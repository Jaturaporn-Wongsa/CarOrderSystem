<?php
/**
 * ==============================================================================
 * File: admin/profile.php - จัดการข้อมูลส่วนตัวผู้ดูแลระบบ (Guaranteed 50/50 Grid Layout)
 * Description: บังคับแบ่งครึ่ง ซ้าย (โปรไฟล์) - ขวา (ฟอร์ม) 50:50 สมบูรณ์แบบ
 * ==============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// 1. INCLUDE CONFIG & AUTHENTICATION
// ==========================================
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

checkAdmin();

$is_in_modal = isset($_GET['in_modal']) && $_GET['in_modal'] == '1';

$admin_id = $_SESSION['sales_id'] ?? $_SESSION['user_id'] ?? 0;
$admin_info = null;
$error_msg = null;

try {
    $stmt = $conn->prepare("SELECT id, username, name, role, created_at FROM users WHERE id = :id AND role = 'admin' LIMIT 1");
    $stmt->execute([':id' => $admin_id]);
    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_info) {
        $error_msg = "ไม่พบข้อมูลบัญชีผู้ดูแลระบบในระบบสั่งจองรถมอเตอร์ไซค์";
    }
} catch (PDOException $e) {
    $error_msg = "เกิดข้อผิดพลาดทางฐานข้อมูล: " . $e->getMessage();
}

if (!function_exists('cleanEmoji')) {
    function cleanEmoji($text) {
        if (empty($text)) return '';
        return trim(preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{2705}]/u', '', $text));
    }
}

if (!$is_in_modal && file_exists(__DIR__ . '/../includes/header.php')) {
    include_once __DIR__ . '/../includes/header.php';
}
?>

<?php if ($is_in_modal): ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลส่วนตัว</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php endif; ?>

<style>
    :root {
        --font-kanit: 'Kanit', sans-serif;
        --bg-light: #f8fafc;
        --card-border-radius: 16px;
    }

    body, html, button, input, select, textarea, table {
        font-family: var(--font-kanit) !important;
    }

    body {
        background-color: var(--bg-light);
        overflow-x: hidden;
    }

    <?php if (!$is_in_modal): ?>
    #mainContentArea,
    .main-content-layout {
        margin-left: 260px !important;
        width: calc(100% - 260px) !important;
        min-height: 100vh;
        padding: 24px 28px !important;
        box-sizing: border-box !important;
        position: relative;
        z-index: 1;
    }
    <?php else: ?>
    .modal-embedded-content {
        padding: 12px !important;
        background-color: #ffffff;
    }
    <?php endif; ?>

    /* บังคับแบ่ง 2 คอลัมน์ (50% / 50%) สำหรับการ์ดข้อมูลส่วนตัว และ ฟอร์ม */
    .profile-split-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 1rem !important;
        align-items: stretch !important;
    }

    .main-card {
        border-radius: var(--card-border-radius);
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }

    .form-control, .form-select {
        border-radius: 10px !important;
        border: 1px solid #cbd5e1;
        padding: 0.55rem 0.85rem;
        font-size: 0.88rem;
        color: #1e293b;
    }

    .input-group-text {
        border-radius: 10px 0 0 10px !important;
        border: 1px solid #cbd5e1;
        border-right: none;
        background-color: #f8fafc;
        color: #64748b;
    }

    .input-group .form-control {
        border-radius: 0 10px 10px 0 !important;
    }

    .form-label {
        font-weight: 500;
        font-size: 0.85rem;
        color: #334155;
        margin-bottom: 0.25rem;
    }

    .profile-avatar-circle {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        border: 3px solid #ffffff;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
    }
</style>

<?php if ($is_in_modal): ?>
</head>
<body>
<?php endif; ?>

<div class="<?= !$is_in_modal ? 'position-relative dashboard-container' : 'modal-embedded-content' ?>">
    
    <?php 
    if (!$is_in_modal && file_exists(__DIR__ . '/../includes/sidebar.php')) {
        include_once __DIR__ . '/../includes/sidebar.php'; 
    }
    ?>

    <div class="<?= !$is_in_modal ? 'main-content-layout' : '' ?>" id="<?= !$is_in_modal ? 'mainContentArea' : '' ?>">
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show rounded-3 mb-3">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars(cleanEmoji($_SESSION['success'])) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show rounded-3 mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars(cleanEmoji($_SESSION['error'])) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4">
                <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= htmlspecialchars(cleanEmoji($error_msg)) ?>
            </div>
        <?php else: ?>

        <!-- หัวข้อหน้าเว็บ -->
        <div class="mb-3">
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-person-gear text-primary fs-4"></i>
                <span>ตั้งค่าบัญชีและรหัสผ่าน</span>
            </h4>
            <p class="text-muted mb-0 small">จัดการข้อมูลชื่อผู้ใช้งาน สิทธิ์บัญชีผู้ใช้ และตั้งค่าเปลี่ยนรหัสผ่านระบบสั่งจองรถมอเตอร์ไซค์</p>
        </div>

        <!-- โครงสร้างบังคับแบ่งครึ่ง 50/50 ซ้าย-ขวา -->
        <div class="profile-split-grid">
            
            <!-- ฝั่งซ้าย (50%): ข้อมูลส่วนตัวผู้ดูแลระบบ -->
            <div class="main-card p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-center mb-2">
                        <div class="profile-avatar-circle">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                    <div class="text-center">
                        <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($admin_info['name'] ?? 'ผู้ดูแลระบบ') ?></h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill fw-normal mb-3" style="font-size: 0.75rem;">
                            <i class="bi bi-shield-lock-fill me-1"></i> Administrator Account
                        </span>
                    </div>
                    
                    <div class="border-top pt-2 mt-1">
                        <div class="d-flex align-items-center gap-2 text-muted mb-1 small">
                            <i class="bi bi-card-heading text-primary"></i>
                            <span>รหัสอ้างอิง: <strong>#<?= sprintf('%04d', $admin_info['id']) ?></strong></span>
                        </div>
                        <div class="d-flex align-items-center gap-2 text-muted mb-1 small">
                            <i class="bi bi-person-check text-primary"></i>
                            <span>บัญชีระบบสั่งจองรถมอเตอร์ไซค์</span>
                        </div>
                    </div>
                </div>

                <div class="bg-light p-2.5 rounded-3 mt-3">
                    <div class="d-flex align-items-center gap-2 mb-1 text-dark fw-bold small">
                        <i class="bi bi-shield-exclamation text-warning"></i>
                        <span>คำแนะนำความปลอดภัย</span>
                    </div>
                    <p class="text-muted small mb-0" style="font-size: 0.78rem; line-height: 1.35;">
                        ควรกำหนดรหัสผ่านที่มีความยาวอย่างน้อย 6 ตัวอักษร โดยผสมผสานตัวอักษรและตัวเลขเพื่อความปลอดภัยสูงสุด
                    </p>
                </div>
            </div>

            <!-- ฝั่งขวา (50%): ข้อมูลทั่วไป & เปลี่ยนรหัสผ่าน -->
            <div class="main-card p-3">
                <form action="../actions/update_profile.php" method="POST">
                    <input type="hidden" name="user_id" value="<?= (int)$admin_info['id']; ?>">

                    <!-- ข้อมูลทั่วไป -->
                    <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                        <i class="bi bi-person-vcard text-primary fs-6"></i>
                        <h6 class="fw-bold m-0 text-dark" style="font-size: 0.9rem;">ข้อมูลทั่วไป</h6>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($admin_info['username']); ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ชื่อ - นามสกุล <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin_info['name']); ?>" required placeholder="กรอกชื่อ-นามสกุล">
                        </div>
                    </div>

                    <!-- เปลี่ยนรหัสผ่าน -->
                    <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                        <i class="bi bi-key text-primary fs-6"></i>
                        <h6 class="fw-bold m-0 text-dark" style="font-size: 0.9rem;">เปลี่ยนรหัสผ่านใหม่</h6>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">รหัสผ่านใหม่</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="new_password" class="form-control" minlength="6" placeholder="รหัสผ่านใหม่ (อย่างน้อย 6 ตัว)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
                        </div>
                    </div>

                    <div class="pt-2 border-top d-flex justify-content-end gap-2">
                        <?php if (!$is_in_modal): ?>
                            <a href="dashboard.php" class="btn btn-light btn-sm px-3 rounded-3 fw-medium text-dark border-0">ยกเลิก</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm px-3 rounded-3 fw-medium d-inline-flex align-items-center gap-1 border-0 w-100 justify-content-center" style="background-color: #2563eb;">
                            <i class="bi bi-floppy"></i> บันทึกการเปลี่ยนแปลง
                        </button>
                    </div>

                </form>
            </div>

        </div>

        <?php endif; ?>
    </div>
</div>

<?php 
if (!$is_in_modal) {
    if (file_exists(__DIR__ . '/../includes/footer.php')) {
        include_once __DIR__ . '/../includes/footer.php'; 
    }
} else {
?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php } ?>