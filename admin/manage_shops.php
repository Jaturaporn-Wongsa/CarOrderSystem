<?php
/**
 * ==============================================================================
 * File: admin/manage_shops.php - จัดการร้านค้า/สาขา (Standardized Complete Code)
 * Description: ปรับปรุงการจัดเรียงลำดับภูมิภาคในแท็บทั้งหมด และจัดสไตล์ Badge แท็บให้สวยงาม
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

// รายชื่อ 6 ภาคหลัก เรียงตามลำดับมาตรฐาน
$regions = [
    'ภาคกลาง',
    'ภาคเหนือ',
    'ภาคอีสาน',
    'ภาคตะวันออก',
    'ภาคตะวันตก',
    'ภาคใต้'
];

// Mapping ลำดับความสำคัญการเรียงภาคสำหรับแท็บ "ทั้งหมด"
$region_order_map = [
    'ภาคกลาง' => 1,
    'ภาคเหนือ' => 2,
    'ภาคอีสาน' => 3,
    'ภาคตะวันออกเฉียงเหนือ' => 3,
    'ภาคตะวันออก' => 4,
    'ภาคตะวันตก' => 5,
    'ภาคใต้' => 6
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
// 3. POST & GET ACTION HANDLERS
// ==========================================

// 3.1 เพิ่มสาขาใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $shop_name = trim($_POST['shop_name'] ?? '');
    $region    = trim($_POST['region'] ?? '');

    if (!empty($shop_name) && !empty($region)) {
        try {
            $stmt = $conn->prepare("INSERT INTO shops (shop_name, region) VALUES (?, ?)");
            $stmt->execute([$shop_name, $region]);
            $_SESSION['success'] = "เพิ่มข้อมูลสาขาเรียบร้อยแล้ว";
        } catch (PDOException $e) {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการเพิ่มสาขา: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "กรุณากรอกชื่อสาขาและเลือกภูมิภาคให้ครบถ้วน";
    }
    header("Location: manage_shops.php");
    exit();
}

// 3.2 แก้ไขสาขา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $shop_id   = (int)($_POST['shop_id'] ?? 0);
    $shop_name = trim($_POST['shop_name'] ?? '');
    $region    = trim($_POST['region'] ?? '');

    if ($shop_id > 0 && !empty($shop_name) && !empty($region)) {
        try {
            $stmt = $conn->prepare("UPDATE shops SET shop_name = ?, region = ? WHERE id = ?");
            $stmt->execute([$shop_name, $region, $shop_id]);
            $_SESSION['success'] = "แก้ไขข้อมูลสาขาเรียบร้อยแล้ว";
        } catch (PDOException $e) {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดต: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "ข้อมูลไม่ครบถ้วนสำหรับการแก้ไข";
    }
    header("Location: manage_shops.php");
    exit();
}

// 3.3 ลบสาขา
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM shops WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "ลบข้อมูลสาขาเรียบร้อยแล้ว";
    } catch (PDOException $e) {
        $_SESSION['error'] = "ไม่สามารถลบสาขานี้ได้เนื่องจากมีการเชื่อมโยงข้อมูลอยู่ในระบบ";
    }
    header("Location: manage_shops.php");
    exit();
}

// ==========================================
// 4. FETCH DATA & SORT BY CUSTOM REGION ORDER
// ==========================================
try {
    $stmt = $conn->query("SELECT * FROM shops");
    $all_shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_shops = [];
}

// ปรับแก้ชื่อภาคในรายการทั้งหมดให้เป็น "ภาคอีสาน"
foreach ($all_shops as &$shop) {
    if (($shop['region'] ?? '') === 'ภาคตะวันออกเฉียงเหนือ') {
        $shop['region'] = 'ภาคอีสาน';
    }
}
unset($shop);

// เรียงลำดับรายการในแท็บ "ทั้งหมด" ให้เรียงตาม ภาคกลาง -> เหนือ -> อีสาน -> ตะวันออก -> ตะวันตก -> ใต้
usort($all_shops, function($a, $b) use ($region_order_map) {
    $orderA = $region_order_map[$a['region'] ?? ''] ?? 99;
    $orderB = $region_order_map[$b['region'] ?? ''] ?? 99;
    if ($orderA === $orderB) {
        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    }
    return $orderA <=> $orderB;
});

$total_shops = count($all_shops);
$active_regions = array_unique(array_filter(array_column($all_shops, 'region')));
$total_active_regions = count($active_regions);

// จัดกลุ่มร้านค้าตามภาคสำหรับแท็บย่อย
$shops_by_region = [];
foreach ($regions as $reg) {
    $shops_by_region[$reg] = [];
}
foreach ($all_shops as $shop) {
    $reg = $shop['region'] ?? 'ภาคกลาง';
    if (!array_key_exists($reg, $shops_by_region)) {
        $shops_by_region[$reg] = [];
    }
    $shops_by_region[$reg][] = $shop;
}

// ==========================================
// 5. LAYOUT HEADERS
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

    /* Standardized Main Content Area */
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

    /* Modal Backdrop Blur Effect */
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

    /* Form Controls */
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

    /* Custom Badges & Tables */
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

    /* Nav Pills Region & Badge Style */
    .nav-pills-region .nav-link {
        color: #475569;
        border-radius: 8px;
        padding: 0.45rem 0.85rem;
        font-size: 0.88rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .nav-pills-region .nav-link.active {
        background-color: #2563eb;
        color: #ffffff;
    }

    /* วงกลมระบุจำนวนแบบเนียนตาตามภาพตัวอย่าง */
    .nav-pills-region .badge-count {
        background-color: #5c6b73 !important;
        color: #ffffff !important;
        font-size: 0.75rem;
        font-weight: 700;
        min-width: 22px;
        height: 22px;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 6px;
        line-height: 1;
    }

    .nav-pills-region .nav-link.active .badge-count {
        background-color: #ffffff !important;
        color: #2563eb !important;
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

        <!-- หัวข้อหน้า Manage Shops -->
        <div class="mb-4">
            <h3 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-shop text-primary fs-3"></i>
                <span>จัดการร้านค้า / สาขา</span>
            </h3>
            <p class="text-muted mb-0 small">บริหารจัดการรายชื่อโชว์รูมและสาขาร้านค้ารถมอเตอร์ไซค์ แยกตาม 6 ภูมิภาคหลัก</p>
        </div>

        <!-- Section 1: การ์ดสรุปสถิติ 4 ใบ -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">สาขาทั้งหมด</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_shops); ?> <span class="fs-6 text-muted fw-normal">สาขา</span></h3>
                        </div>
                        <div class="icon-shape bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-shop-window fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-check-circle text-primary me-1"></i> เปิดให้บริการสั่งจองรถ
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">ภาคที่มีสาขา</span>
                            <h3 class="fw-bold text-dark my-1"><?= number_format($total_active_regions); ?> / 6 <span class="fs-6 text-muted fw-normal">ภาค</span></h3>
                        </div>
                        <div class="icon-shape bg-info bg-opacity-10 text-info">
                            <i class="bi bi-geo-alt-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-map text-info me-1"></i> ครอบคลุมพื้นที่การขาย
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">การจัดสรรโควตา</span>
                            <h3 class="fw-bold text-success my-1">พร้อมใช้งาน</h3>
                        </div>
                        <div class="icon-shape bg-success bg-opacity-10 text-success">
                            <i class="bi bi-diagram-3-fill fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i> เชื่อมโยงระบบคำสั่งซื้อ
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-12">
                <div class="stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-muted small fw-medium">สิทธิ์จัดการ</span>
                            <h3 class="fw-bold text-warning my-1">Admin <span class="fs-6 text-muted fw-normal">Role</span></h3>
                        </div>
                        <div class="icon-shape bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-person-gear fs-4"></i>
                        </div>
                    </div>
                    <div class="border-top pt-2 small text-muted">
                        <i class="bi bi-database-check text-warning me-1"></i> จัดการฐานข้อมูลสาขา
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Split Area Layout 40/60 -->
        <div class="row g-4">
            
            <!-- ฝั่งซ้าย: ฟอร์มเพิ่มสาขาใหม่ (40%) -->
            <div class="col-xl-5 col-12">
                <div class="main-card p-3 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <i class="bi bi-plus-circle-fill text-primary"></i>
                        <h6 class="fw-bold m-0 text-dark">เพิ่มสาขาใหม่</h6>
                    </div>
                    
                    <form action="manage_shops.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">ชื่อสาขา / โชว์รูม</label>
                                <input type="text" name="shop_name" class="form-control" placeholder="เช่น สาขาเมืองเชียงใหม่, สาขาหาดใหญ่" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">เลือกภาค (ภูมิภาค)</label>
                                <select name="region" class="form-select" required>
                                    <option value="" disabled selected>-- เลือกภาค --</option>
                                    <?php foreach ($regions as $reg): ?>
                                        <option value="<?= htmlspecialchars($reg) ?>"><?= htmlspecialchars($reg) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary px-4 py-2 rounded-3 fw-medium d-inline-flex align-items-center gap-2 w-100 justify-content-center">
                                    <i class="bi bi-save"></i> บันทึกข้อมูลสาขา
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ฝั่งขวา: ตารางรายชื่อสาขาแบ่งตามภาค (60%) -->
            <div class="col-xl-7 col-12">
                <div class="main-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-building text-primary fs-5"></i>
                            <h6 class="fw-bold m-0 text-dark">รายชื่อสาขาแบ่งตามภูมิภาค</h6>
                        </div>
                        <span class="badge bg-light text-secondary border px-3 py-1 rounded-pill fw-medium">รวม <?= $total_shops ?> สาขา</span>
                    </div>

                    <!-- TAB FILTERS BY REGION (เรียงลำดับ: กลาง, เหนือ, อีสาน, ตะวันออก, ตะวันตก, ใต้) -->
                    <ul class="nav nav-pills nav-pills-region gap-1 mb-3 bg-light p-1 rounded-3" id="regionTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="all-tab" data-bs-toggle="pill" data-bs-target="#tab-all" type="button" role="tab" aria-controls="tab-all" aria-selected="true">
                                ทั้งหมด
                            </button>
                        </li>
                        <?php foreach ($regions as $idx => $reg): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reg-tab-<?= $idx ?>" data-bs-toggle="pill" data-bs-target="#tab-reg-<?= $idx ?>" type="button" role="tab" aria-controls="tab-reg-<?= $idx ?>" aria-selected="false">
                                    <span><?= htmlspecialchars(str_replace('ภาค', '', $reg)) ?></span>
                                    <span class="badge badge-count"><?= count($shops_by_region[$reg] ?? []) ?></span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- TAB CONTENTS -->
                    <div class="tab-content" id="regionTabContent">
                        
                        <!-- TAB ALL (ข้อมูลถูกจัดเรียงตามลำดับภาคเรียบร้อยแล้ว) -->
                        <div class="tab-pane show active" id="tab-all" role="tabpanel" aria-labelledby="all-tab" tabindex="0">
                            <div class="table-responsive">
                                <table class="table table-custom align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 10%;">#</th>
                                            <th style="width: 50%;">ชื่อสาขา</th>
                                            <th class="text-start" style="width: 25%;">ภูมิภาค</th>
                                            <th class="text-center" style="width: 15%;">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($all_shops)): $i=1; foreach ($all_shops as $shop): ?>
                                        <tr>
                                            <td class="text-center text-muted font-monospace"><?= $i ?></td>
                                            <td class="fw-bold text-dark">
                                                <?= htmlspecialchars($shop['shop_name']) ?>
                                            </td>
                                            <td class="text-start">
                                                <span class="badge-region" style="<?= getRegionBadgeStyle($shop['region'] ?? '') ?>">
                                                    <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($shop['region'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <button type="button" class="btn btn-action btn-outline-primary btn-edit-shop" 
                                                        title="แก้ไข"
                                                        data-seq="<?= $i ?>"
                                                        data-id="<?= $shop['id'] ?>"
                                                        data-name="<?= htmlspecialchars($shop['shop_name'], ENT_QUOTES) ?>"
                                                        data-region="<?= htmlspecialchars($shop['region'], ENT_QUOTES) ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <a href="manage_shops.php?delete_id=<?= $shop['id'] ?>" class="btn btn-action btn-outline-danger" title="ลบ" onclick="return confirm('คุณต้องการลบสาขานี้ใช่หรือไม่?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php $i++; endforeach; else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-5 text-muted">
                                                    <i class="bi bi-building-x fs-1 d-block mb-2 opacity-25"></i>
                                                    ยังไม่มีข้อมูลสาขาในระบบ
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB REGIONS 1..6 -->
                        <?php foreach ($regions as $idx => $reg): ?>
                            <div class="tab-pane" id="tab-reg-<?= $idx ?>" role="tabpanel" aria-labelledby="reg-tab-<?= $idx ?>" tabindex="0">
                                <div class="table-responsive">
                                    <table class="table table-custom align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 10%;">#</th>
                                                <th style="width: 50%;">ชื่อสาขา</th>
                                                <th class="text-start" style="width: 25%;">ภูมิภาค</th>
                                                <th class="text-center" style="width: 15%;">จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($shops_by_region[$reg])): $r_i=1; foreach ($shops_by_region[$reg] as $shop): ?>
                                            <tr>
                                                <td class="text-center text-muted font-monospace"><?= $r_i ?></td>
                                                <td class="fw-bold text-dark">
                                                    <?= htmlspecialchars($shop['shop_name']) ?>
                                                </td>
                                                <td class="text-start">
                                                    <span class="badge-region" style="<?= getRegionBadgeStyle($shop['region'] ?? '') ?>">
                                                        <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($shop['region'] ?? '-') ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                                        <button type="button" class="btn btn-action btn-outline-primary btn-edit-shop" 
                                                            title="แก้ไข"
                                                            data-seq="<?= $r_i ?>"
                                                            data-id="<?= $shop['id'] ?>"
                                                            data-name="<?= htmlspecialchars($shop['shop_name'], ENT_QUOTES) ?>"
                                                            data-region="<?= htmlspecialchars($shop['region'], ENT_QUOTES) ?>">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <a href="manage_shops.php?delete_id=<?= $shop['id'] ?>" class="btn btn-action btn-outline-danger" title="ลบ" onclick="return confirm('คุณต้องการลบสาขานี้ใช่หรือไม่?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $r_i++; endforeach; else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4 text-muted">
                                                        ยังไม่มีข้อมูลสาขาใน<?= htmlspecialchars($reg) ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ==================================================================
     EDIT MODAL
     ================================================================== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
        <div class="modal-content overflow-hidden">
            
            <!-- Modal Header -->
            <div class="modal-header border-0 pt-4 px-4 pb-2 align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-pencil-square fs-5"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark m-0 fs-5">แก้ไขข้อมูลสาขา / โชว์รูม</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="manage_shops.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="shop_id" id="edit_shop_id">
                
                <!-- Modal Body -->
                <div class="modal-body px-4 py-3">

                    <!-- ID / Sequence Display -->
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-medium mb-1">ลำดับ / ID ในระบบ</label>
                        <input type="text" class="form-control bg-light border-0 fw-bold text-dark" id="edit_seq_display" readonly style="border-radius: 10px; padding: 0.65rem 1rem;">
                    </div>

                    <!-- Shop Name -->
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-semibold mb-1">ชื่อสาขา / โชว์รูม</label>
                        <input type="text" name="shop_name" id="edit_shop_name" class="form-control" required style="border-radius: 10px; padding: 0.65rem 1rem;">
                    </div>

                    <!-- Region Select -->
                    <div class="mb-3">
                        <label class="form-label text-dark small fw-semibold mb-1">เขตพื้นที่ดูแล (ภาค)</label>
                        <select name="region" id="edit_region" class="form-select" required style="border-radius: 10px; padding: 0.65rem 1rem;">
                            <?php foreach ($regions as $reg): ?>
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
    const modalElement = document.getElementById('editModal');
    const editModal = modalElement ? new bootstrap.Modal(modalElement) : null;

    // Handler ปุ่มแก้ไขสาขา
    document.querySelectorAll('.btn-edit-shop').forEach(btn => {
        btn.addEventListener('click', function() {
            const seq = this.dataset.seq || '1';
            const id = this.dataset.id;
            const name = this.dataset.name;
            let region = this.dataset.region;

            if (region === 'ภาคตะวันออกเฉียงเหนือ') {
                region = 'ภาคอีสาน';
            }

            document.getElementById('edit_seq_display').value = `#${seq} (ID: ${id})`;
            document.getElementById('edit_shop_id').value = id;
            document.getElementById('edit_shop_name').value = name;
            document.getElementById('edit_region').value = region;

            if (editModal) {
                editModal.show();
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