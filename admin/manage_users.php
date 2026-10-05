<?php
/**
 * ==============================================================================
 * File: admin/manage_users.php - จัดการข้อมูลพนักงานขาย (Standardized Layout & UI)
 * Description: ปรับปรุงโครงสร้าง CSS, Form Controls, Table และ Modal ให้สวยงามตรงตามแบบ
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

// ตรวจสอบสิทธิ์ผู้ดูแลระบบ
checkAdmin();

// รายชื่อ 6 ภาคหลักสำหรับ Select Option
$region_options = [
    'ภาคกลาง',
    'ภาคเหนือ',
    'ภาคตะวันออกเฉียงเหนือ',
    'ภาคตะวันออก',
    'ภาคตะวันตก',
    'ภาคใต้'
];

// ==========================================
// 2. HELPER FUNCTIONS
// ==========================================
if (!function_exists('getRegionBadgeStyle')) {
    function getRegionBadgeStyle($region) {
        $cleanRegion = str_replace('ภาค', '', trim($region));
        
        switch ($cleanRegion) {
            case 'เหนือ': 
                return 'background-color: #f3e8ff; color: #6d28d9; border: 1px solid #ddd6fe;';
            case 'อีสาน': 
            case 'ตะวันออกเฉียงเหนือ': 
                return 'background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a;';
            case 'กลาง': 
                return 'background-color: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe;';
            case 'ใต้': 
                return 'background-color: #d1fae5; color: #047857; border: 1px solid #a7f3d0;';
            case 'ตะวันออก': 
                return 'background-color: #cffafe; color: #0e7490; border: 1px solid #a5f3fc;';
            case 'ตะวันตก': 
                return 'background-color: #fce7f3; color: #be185d; border: 1px solid #fbcfe8;';
            default: 
                return 'background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;';
        }
    }
}

if (!function_exists('cleanEmoji')) {
    function cleanEmoji($text) {
        if (empty($text)) return '';
        return trim(preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{2705}]/u', '', $text));
    }
}

// ==========================================
// 3. POST ACTION HANDLER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3.1 เพิ่มพนักงานใหม่
    if (isset($_POST['action']) && $_POST['action'] === 'add_sales') {
        $username = trim($_POST['username'] ?? '');
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';
        $name     = trim($_POST['name'] ?? '');
        $region   = trim($_POST['region'] ?? '');

        if (!empty($username) && !empty($password) && !empty($name) && !empty($region)) {
            try {
                $stmt = $conn->prepare("INSERT INTO users (username, password, name, region, role) VALUES (?, ?, ?, ?, 'sales')");
                $stmt->execute([$username, $password, $name, $region]);
                $_SESSION['success'] = "เพิ่มข้อมูลพนักงานขายเรียบร้อยแล้ว";
            } catch (PDOException $e) {
                $_SESSION['error'] = "ไม่สามารถเพิ่มข้อมูลได้ ชื่อผู้ใช้งานนี้อาจมีอยู่แล้วในระบบ";
            }
        } else {
            $_SESSION['error'] = "กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง";
        }
    }
    
    // 3.2 แก้ไขพนักงาน
    elseif (isset($_POST['action']) && $_POST['action'] === 'edit_sales') {
        $user_id  = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $region   = trim($_POST['region'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($user_id > 0 && !empty($username) && !empty($name) && !empty($region)) {
            try {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, name = ?, region = ? WHERE id = ? AND role = 'sales'");
                    $stmt->execute([$username, $hashed_password, $name, $region, $user_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, name = ?, region = ? WHERE id = ? AND role = 'sales'");
                    $stmt->execute([$username, $name, $region, $user_id]);
                }
                $_SESSION['success'] = "แก้ไขข้อมูลพนักงานขายเรียบร้อยแล้ว";
            } catch (PDOException $e) {
                $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล หรือชื่อผู้ใช้งานซ้ำ";
            }
        } else {
            $_SESSION['error'] = "ข้อมูลไม่ถูกต้องหรือกรอกข้อมูลไม่ครบถ้วน";
        }
    }

    // 3.3 ลบพนักงาน
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_sales') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($user_id > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'sales'");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "ลบข้อมูลพนักงานเรียบร้อยแล้ว";
            } catch (PDOException $e) {
                $_SESSION['error'] = "ไม่สามารถลบข้อมูลได้ เนื่องจากมีการเชื่อมโยงข้อมูลในระบบ";
            }
        }
    }
    
    header("Location: manage_users.php");
    exit();
}

// ==========================================
// 4. FETCH DATA & CALCULATE STATS
// ==========================================
try {
    $stmt = $conn->query("SELECT * FROM users WHERE role = 'sales' ORDER BY id ASC");
    $sales_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sales_staff = [];
}

$total_sales = count($sales_staff);
$regions_covered = array_unique(array_filter(array_column($sales_staff, 'region')));
$total_regions = count($regions_covered);

// ==========================================
// 5. INCLUDE LAYOUT HEADERS
// ==========================================
if (file_exists(__DIR__ . '/../includes/header.php')) {
    include_once __DIR__ . '/../includes/header.php';
}
?>

<!-- ==================================================================
     STYLES & FONTS
     ================================================================== -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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

    /* Standardized Layout */
    #mainContentArea,
    .main-content-layout,
    body:not(.sidebar-collapsed):not(.collapsed) #mainContentArea,
    body:not(.sidebar-collapsed):not(.collapsed) .main-content-layout {
        margin-left: 260px !important;
        width: calc(100% - 260px) !important;
        min-height: 100vh;
        padding: 24px 28px !important;
        box-sizing: border-box !important;
        transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                    width 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative;
        z-index: 1;
    }

    body.sidebar-collapsed #mainContentArea,
    body.sidebar-collapsed .main-content-layout,
    body.collapsed #mainContentArea,
    body.collapsed .main-content-layout {
        margin-left: 80px !important;
        width: calc(100% - 80px) !important;
    }

    @media (max-width: 991.98px) {
        body #mainContentArea,
        body .main-content-layout,
        body.sidebar-collapsed #mainContentArea,
        body.sidebar-collapsed .main-content-layout {
            margin-left: 80px !important;        
            width: calc(100% - 80px) !important; 
            padding: 16px !important;
        }
    }

    /* Modal Styling & Backdrop Effect */
    .modal-backdrop { 
        background-color: rgba(15, 23, 42, 0.45) !important; 
    }
    
    .modal-backdrop.show { 
        opacity: 1 !important; 
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
    }

    .modal-content {
        border-radius: 20px !important;
        border: none !important;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    }

    /* Custom Form Inputs */
    .form-control, .form-select {
        border-radius: 10px !important;
        border: 1px solid #cbd5e1;
        padding: 0.6rem 0.9rem;
        font-size: 0.92rem;
        color: #1e293b;
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .form-label {
        font-weight: 500;
        font-size: 0.88rem;
        color: #334155;
        margin-bottom: 0.35rem;
    }

    /* Keyframes Animation */
    .fade-in-up {
        animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Stat & Main Cards */
    .stat-card, .main-card {
        border-radius: var(--card-border-radius);
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    }

    .stat-card {
        transition: all 0.25s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }

    .icon-shape {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }

    /* Custom Badges & Table */
    .badge-region {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.78rem;
        font-weight: 500;
        padding: 2px 10px;
        border-radius: 20px;
        border-width: 1px;
        border-style: solid;
        white-space: nowrap;
    }

    .badge-user-role {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.2rem 0.6rem;
        border-radius: 50rem;
        white-space: nowrap;
        background-color: #f0f9ff;
        color: #0284c7;
        border: 1px solid #e0f2fe;
    }

    .table-custom {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .table-custom thead th {
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 12px 16px;
    }

    .table-custom tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 0.88rem;
    }

    .table-custom tbody tr:hover {
        background-color: #f8fafc;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .btn-action:hover {
        transform: translateY(-1px);
    }
</style>

<!-- ==================================================================
     MAIN HTML LAYOUT
     ================================================================== -->
<div class="position-relative dashboard-container">
    
    <!-- Sidebar -->
    <?php 
    if (file_exists(__DIR__ . '/../includes/sidebar.php')) {
        include_once __DIR__ . '/../includes/sidebar.php'; 
    }
    ?>
    
    <!-- ส่วนเนื้อหาหลัก -->
    <div class="main-content-layout fade-in-up" id="mainContentArea">
        
        <!-- Alert Flash Messages -->
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

        <!-- หัวข้อหน้า Manage Users -->
        <div class="mb-4">
            <h3 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-people-fill text-primary fs-3"></i>
                <span>จัดการข้อมูลพนักงานขาย</span>
            </h3>
            <p class="text-muted mb-0 small">บริหารจัดการบัญชีผู้ใช้งาน สิทธิ์การเข้าถึง และพื้นที่ดูแลของพนักงานขาย (Sales)</p>
        </div>

        <!-- Section 1: การ์ดสรุปสถิติ 4 ใบ -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">พนักงานขายทั้งหมด</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_sales); ?> <span class="fs-6 text-muted fw-normal">คน</span></h3>
                        </div>
                        <div class="icon-shape bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-person-check text-primary me-1"></i> บัญชีพนักงานที่ใช้งานอยู่
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">เขตพื้นที่ดูแล</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_regions); ?> / 6 <span class="fs-6 text-muted fw-normal">ภาค</span></h3>
                        </div>
                        <div class="icon-shape bg-info bg-opacity-10 text-info">
                            <i class="bi bi-geo-alt-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-map text-info me-1"></i> พื้นที่ครอบคลุมการขาย
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">สิทธิ์การใช้งาน</span>
                            <h3 class="fw-bold text-success my-1">Sales <span class="fs-6 text-muted fw-normal">Role</span></h3>
                        </div>
                        <div class="icon-shape bg-success bg-opacity-10 text-success">
                            <i class="bi bi-shield-lock-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i> เข้าถึงระบบจัดการจองรถ
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">สถานะระบบ</span>
                            <h3 class="fw-bold text-warning my-1">พร้อมใช้งาน</h3>
                        </div>
                        <div class="icon-shape bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-database-check text-warning me-1"></i> เชื่อมต่อฐานข้อมูลปกติ
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Split Area Layout 40/60 -->
        <div class="row g-4">
            
            <!-- ฝั่งซ้าย: ฟอร์มเพิ่มพนักงานใหม่ (40%) -->
            <div class="col-xl-5 col-12">
                <div class="main-card p-3 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-person-plus-fill text-primary"></i>
                        <h6 class="fw-bold m-0 text-dark">เพิ่มข้อมูลพนักงานขายใหม่</h6>
                    </div>
                    
                    <form action="manage_users.php" method="POST">
                        <input type="hidden" name="action" value="add_sales">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">ชื่อผู้ใช้งาน (Username)</label>
                                <input type="text" name="username" class="form-control" placeholder="เช่น sales_01" required>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label">รหัสผ่าน (Password)</label>
                                <input type="password" name="password" class="form-control" placeholder="กำหนดรหัสผ่าน" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">ชื่อ-นามสกุล</label>
                                <input type="text" name="name" class="form-control" placeholder="ระบุชื่อและนามสกุลจริง" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">เขตพื้นที่ดูแล (ภาค)</label>
                                <select name="region" class="form-select" required>
                                    <option value="" disabled selected>-- เลือกเขตภูมิภาค --</option>
                                    <?php foreach ($region_options as $reg): ?>
                                        <option value="<?= htmlspecialchars($reg) ?>"><?= htmlspecialchars($reg) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-medium d-inline-flex align-items-center gap-2 w-100 justify-content-center">
                                    <i class="bi bi-save"></i> บันทึกข้อมูลพนักงาน
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ฝั่งขวา: ตารางรายชื่อพนักงานขาย (60%) -->
            <div class="col-xl-7 col-12">
                <div class="main-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-people text-primary fs-5"></i>
                            <h6 class="fw-bold m-0 text-dark">รายชื่อพนักงานขายทั้งหมด</h6>
                        </div>
                        <span class="badge bg-light text-secondary border px-3 py-1 rounded-pill fw-medium">รวม <?= count($sales_staff) ?> คน</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 8%;">#</th>
                                    <th style="width: 37%;">ชื่อ-นามสกุล</th>
                                    <th style="width: 25%;">ชื่อผู้ใช้งาน</th>
                                    <th class="text-start" style="width: 20%;">เขตพื้นที่</th>
                                    <th class="text-center" style="width: 10%;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sales_staff)): $i=1; foreach ($sales_staff as $s): ?>
                                <tr>
                                    <td class="text-center text-muted font-monospace"><?= $i ?></td>
                                    <td>
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars($s['name'] ?? '-') ?>
                                        </div>
                                        <div class="mt-1">
                                            <span class="badge-user-role"><i class="bi bi-person-badge"></i> พนักงานขาย</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-secondary fw-medium"><?= htmlspecialchars($s['username'] ?? '-') ?></span>
                                    </td>
                                    <td class="text-start">
                                        <span class="badge-region" style="<?= getRegionBadgeStyle($s['region'] ?? '') ?>">
                                            <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($s['region'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <button type="button" class="btn btn-action btn-outline-primary btn-edit-sales" 
                                                title="แก้ไข"
                                                data-seq="<?= $i ?>"
                                                data-id="<?= htmlspecialchars($s['id']) ?>"
                                                data-name="<?= htmlspecialchars($s['name'] ?? '', ENT_QUOTES) ?>"
                                                data-username="<?= htmlspecialchars($s['username'] ?? '', ENT_QUOTES) ?>"
                                                data-region="<?= htmlspecialchars($s['region'] ?? '', ENT_QUOTES) ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <form method="POST" action="manage_users.php" class="d-inline" onsubmit="return confirm('คุณต้องการลบพนักงานขายท่านนี้ใช่หรือไม่?');">
                                                <input type="hidden" name="action" value="delete_sales">
                                                <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-action btn-outline-danger" title="ลบ">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php $i++; endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-person-x fs-1 d-block mb-2 opacity-25"></i>
                                            ยังไม่มีข้อมูลพนักงานขายในระบบ
                                        </td>
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

<!-- ==================================================================
     EDIT MODAL (จัดสไตล์และ Padding ตามภาพตัวอย่าง)
     ================================================================== -->
<div class="modal fade" id="editSalesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content overflow-hidden">
            
            <!-- Modal Header -->
            <div class="modal-header border-0 pt-4 px-4 pb-2 align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-pencil-square fs-5"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark m-0 fs-5">แก้ไขข้อมูลพนักงานขาย</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="manage_users.php" method="POST">
                <input type="hidden" name="action" value="edit_sales">
                <input type="hidden" name="user_id" id="m_id">
                
                <!-- Modal Body -->
                <div class="modal-body px-4 py-3">

                    <!-- ID / Sequence Display -->
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium mb-1">ลำดับ / ID ในระบบ</label>
                        <input type="text" class="form-control bg-light border-0 fw-bold text-dark" id="m_seq_display" readonly style="border-radius: 10px; padding: 0.65rem 1rem;">
                    </div>

                    <!-- Username & Password Row -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label text-dark small fw-semibold mb-1">ชื่อผู้ใช้งาน (Username)</label>
                            <input type="text" name="username" id="m_username" class="form-control" required style="border-radius: 10px; padding: 0.65rem 1rem;">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-dark small fw-semibold mb-1">รหัสผ่านใหม่ (หากไม่เปลี่ยนให้ว่างไว้)</label>
                            <input type="password" name="password" id="m_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่" style="border-radius: 10px; padding: 0.65rem 1rem;">
                        </div>
                    </div>

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-semibold mb-1">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" id="m_name" class="form-control" required style="border-radius: 10px; padding: 0.65rem 1rem;">
                    </div>

                    <!-- Region Select -->
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-semibold mb-1">เขตพื้นที่ดูแล (ภาค)</label>
                        <select name="region" id="m_region" class="form-select" required style="border-radius: 10px; padding: 0.65rem 1rem;">
                            <?php foreach ($region_options as $reg): ?>
                                <option value="<?= htmlspecialchars($reg) ?>"><?= htmlspecialchars($reg) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer border-0 pt-1 px-4 pb-4 gap-2 justify-content-end">
                    <button type="button" class="btn btn-light px-4 py-2 rounded-3 fw-medium text-dark border-0" data-bs-dismiss="modal" style="background-color: #f1f5f9; border-radius: 10px !important;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-medium d-inline-flex align-items-center gap-2 border-0" style="background-color: #0d6efd; border-radius: 10px !important;">
                        <i class="bi bi-box-arrow-down fs-6"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================================================================
     JAVASCRIPT LOGIC
     ================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('editSalesModal');
    const editSalesModal = modalElement ? new bootstrap.Modal(modalElement) : null;

    // Event Listener สำหรับปุ่มแก้ไขในตาราง
    document.querySelectorAll('.btn-edit-sales').forEach(btn => {
        btn.addEventListener('click', function() {
            const seq = this.dataset.seq;
            const id = this.dataset.id;
            const name = this.dataset.name;
            const username = this.dataset.username;
            const region = this.dataset.region;

            document.getElementById('m_seq_display').value = `#${seq} (ID: ${id})`;
            document.getElementById('m_id').value = id;
            document.getElementById('m_name').value = name;
            document.getElementById('m_username').value = username;
            document.getElementById('m_region').value = region;
            document.getElementById('m_password').value = ''; // เคลียร์ช่องรหัสผ่านใหม่

            if (editSalesModal) {
                editSalesModal.show();
            }
        });
    });
});
</script>

<?php 
if (file_exists(__DIR__ . '/../includes/footer.php')) {
    include_once __DIR__ . '/../includes/footer.php'; 
}
?>