<?php
/**
 * ==============================================================================
 * File: sales/profile.php - จัดการข้อมูลส่วนตัวพนักงานขาย (Compact Clean Layout)
 * Description: ถอดแถบ Header ซ้ำออก ปรับขนาดกระชับ รองรับทั้ง Modal และ Desktop View
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

// ตรวจสอบสิทธิ์พนักงานขาย (Sales)
checkSales();

$is_in_modal = isset($_GET['in_modal']) && $_GET['in_modal'] == '1';

$sales_id = $_SESSION['sales_id'] ?? $_SESSION['user_id'] ?? 0;
$sales_info = null;
$error_msg = null;

try {
    $stmt = $conn->prepare("SELECT id, username, name, role, created_at FROM users WHERE id = :id AND role = 'sales' LIMIT 1");
    $stmt->execute([':id' => $sales_id]);
    $sales_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sales_info) {
        $error_msg = "ไม่พบข้อมูลบัญชีพนักงานขายในระบบสั่งจองรถมอเตอร์ไซค์";
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
        --card-border-radius: 14px;
        --primary-blue: #2563eb;
    }

    button, input, select, textarea {
        font-family: var(--font-kanit) !important;
    }

    <?php if ($is_in_modal): ?>
    /* อนุญาตให้เลื่อน Scrollbar แนวตั้งได้เมื่อเนื้อหาล้นกรอบ Modal */
    html, body {
        font-family: var(--font-kanit) !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        background-color: #ffffff;
        margin: 0;
        padding: 0;
    }

    .modal-embedded-content {
        padding: 10px 14px !important;
        background-color: #ffffff;
        border-radius: 14px;
        box-sizing: border-box;
    }

    .profile-split-grid {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 0.75rem !important;
    }

    @media (min-width: 576px) {
        .profile-split-grid {
            grid-template-columns: 1fr 1fr !important;
        }
    }
    <?php else: ?>
    /* เลย์เอาต์หน้าจอหลักบน คอมพิวเตอร์ (Desktop) */
    #mainContentArea,
    .main-content-layout {
        margin-left: 260px !important;
        width: calc(100% - 260px) !important;
        min-height: 100vh;
        padding: 24px !important;
        box-sizing: border-box !important;
        position: relative;
        z-index: 1;
    }

    .profile-split-grid {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 1rem !important;
        align-items: stretch !important;
    }

    @media (max-width: 991.98px) {
        #mainContentArea,
        .main-content-layout {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 16px !important;
        }
        
        .profile-split-grid {
            grid-template-columns: 1fr !important;
        }
    }
    <?php endif; ?>

    .main-card {
        border-radius: var(--card-border-radius);
        border: 1px solid #e2e8f0;
        background: #ffffff;
        padding: 1rem !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    }

    /* Input Group */
    .input-group {
        border-radius: 8px;
        overflow: hidden;
    }

    .input-group-text {
        border-radius: 8px 0 0 8px !important;
        border: 1px solid #cbd5e1;
        border-right: none;
        background-color: #f8fafc;
        color: #64748b;
        padding: 0.38rem 0.75rem;
    }

    .form-control {
        border-radius: 0 8px 8px 0 !important;
        border: 1px solid #cbd5e1;
        border-left: none;
        padding: 0.38rem 0.75rem;
        font-size: 0.85rem;
        color: #1e293b;
    }

    .form-control:focus {
        border-color: #cbd5e1;
        box-shadow: none;
    }

    .form-control[readonly] {
        background-color: #f8fafc !important;
        color: #475569;
    }

    .form-label {
        font-weight: 600;
        font-size: 0.8rem;
        color: #334155;
        margin-bottom: 0.2rem;
    }

    /* Avatar รูปโปรไฟล์ */
    .profile-avatar-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #e0edff;
        color: var(--primary-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        margin: 0 auto;
    }

    /* ปุ่มบันทึก */
    .btn-submit-custom {
        background-color: #2563eb;
        border: none;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 500;
        font-size: 0.85rem;
        color: #ffffff;
        transition: all 0.2s ease;
    }

    .btn-submit-custom:hover {
        background-color: #1d4ed8;
        color: #ffffff;
    }

    /* คำแนะนำความปลอดภัย */
    .security-notice-box {
        background-color: #f8fafc;
        border: 1px solid #f1f5f9;
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
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
            <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show rounded-3 py-2 px-3 mb-2" style="font-size: 0.82rem;">
                <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars(cleanEmoji($_SESSION['success'])) ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger border-0 shadow-sm alert-dismissible fade show rounded-3 py-2 px-3 mb-2" style="font-size: 0.82rem;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars(cleanEmoji($_SESSION['error'])) ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3 py-2 px-3 mb-2" style="font-size: 0.82rem;">
                <i class="bi bi-exclamation-octagon-fill me-1"></i> <?= htmlspecialchars(cleanEmoji($error_msg)) ?>
            </div>
        <?php else: ?>

        <!-- หัวข้อหลักหน้าเว็บ -->
        <div class="mb-2">
            <h5 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-person-gear text-primary fs-4"></i>
                <span>ตั้งค่าบัญชีและรหัสผ่าน</span>
            </h5>
            <p class="text-muted mb-0 small" style="font-size: 0.78rem;">จัดการชื่อผู้ใช้งาน สิทธิ์บัญชีผู้ใช้ และตั้งค่าเปลี่ยนรหัสผ่านระบบ</p>
        </div>

        <!-- โครงสร้างแบ่งคอลัมน์ -->
        <div class="profile-split-grid">
            
            <!-- การ์ดฝั่งซ้าย -->
            <div class="main-card d-flex flex-column justify-content-between">
                <div>
                    <div class="mb-2 text-center">
                        <div class="profile-avatar-circle">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>

                    <div class="text-center mb-2">
                        <h6 class="fw-bold text-dark mb-1" style="font-size: 0.95rem;"><?= htmlspecialchars($sales_info['name'] ?? 'พนักงานขาย') ?></h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-2.5 py-1 rounded-pill fw-normal" style="font-size: 0.7rem;">
                            <i class="bi bi-shield-fill me-1"></i> Sales Representative Account
                        </span>
                    </div>
                    
                    <div class="border-top pt-2 mt-2">
                        <div class="d-flex align-items-center gap-2 text-secondary mb-1.5" style="font-size: 0.78rem;">
                            <i class="bi bi-card-text text-primary"></i>
                            <span>รหัสอ้างอิง: <strong>#<?= sprintf('%04d', $sales_info['id']) ?></strong></span>
                        </div>
                        <div class="d-flex align-items-center gap-2 text-secondary mb-1.5" style="font-size: 0.78rem;">
                            <i class="bi bi-person text-primary"></i>
                            <span>บัญชีระบบสั่งจองรถมอเตอร์ไซค์</span>
                        </div>
                    </div>
                </div>

                <div class="security-notice-box mt-2">
                    <div class="d-flex align-items-center gap-1.5 mb-0.5 text-dark fw-bold" style="font-size: 0.75rem;">
                        <i class="bi bi-shield-exclamation text-warning fs-6"></i>
                        <span>คำแนะนำความปลอดภัย</span>
                    </div>
                    <p class="text-muted mb-0" style="font-size: 0.7rem; line-height: 1.3;">
                        ควรกำหนดรหัสผ่านที่มีความยาวอย่างน้อย 6 ตัวอักษร โดยผสมผสานตัวอักษรและตัวเลข
                    </p>
                </div>
            </div>

            <!-- การ์ดฝั่งขวา -->
            <div class="main-card">
                <form action="../actions/update_profile.php" method="POST">
                    <input type="hidden" name="user_id" value="<?= (int)$sales_info['id']; ?>">

                    <!-- ข้อมูลทั่วไป -->
                    <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                        <i class="bi bi-vcard text-primary fs-6"></i>
                        <h6 class="fw-bold m-0 text-dark" style="font-size: 0.82rem;">ข้อมูลทั่วไป</h6>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-vcard"></i></span>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($sales_info['username']); ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-2.5">
                        <label class="form-label">ชื่อ - นามสกุล <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($sales_info['name']); ?>" required placeholder="กรอกชื่อ-นามสกุล">
                        </div>
                    </div>

                    <!-- เปลี่ยนรหัสผ่านใหม่ -->
                    <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom mt-2.5">
                        <i class="bi bi-key text-primary fs-6"></i>
                        <h6 class="fw-bold m-0 text-dark" style="font-size: 0.82rem;">เปลี่ยนรหัสผ่านใหม่</h6>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">รหัสผ่านใหม่</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                            <input type="password" name="new_password" class="form-control" minlength="6" placeholder="รหัสผ่านใหม่ (อย่างน้อย 6 ตัว)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-shield-lock"></i></span>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
                        </div>
                    </div>

                    <!-- ปุ่มบันทึก -->
                    <button type="submit" class="btn btn-submit-custom w-100 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="bi bi-floppy"></i>
                        <span>บันทึกการเปลี่ยนแปลง</span>
                    </button>

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